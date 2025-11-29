# Déploiement du Worker Messenger

## Option 1 : Via le script de déploiement (Simple)

Le script `scripts/deploy.sh` a été mis à jour pour :
1. Arrêter l'ancien worker (s'il existe)
2. Lancer le nouveau worker en arrière-plan
3. Vérifier que le worker est bien lancé

**Avantages :**
- Simple à mettre en place
- Pas besoin de modifier docker-compose.yml

**Inconvénients :**
- Le worker peut s'arrêter si le conteneur redémarre
- Moins facile à monitorer

## Option 2 : Service Docker séparé (Recommandé)

Créer un service dédié dans `docker-compose.yml` pour le worker.

### Étape 1 : Ajouter le service dans docker-compose.yml

```yaml
services:
  # ... vos autres services ...
  
  messenger-worker:
    build:
      context: .
      dockerfile: ./Dockerfile
    container_name: delnyx_messenger_worker
    volumes:
      - .:/var/www/html
      - delnyx_uploads:/var/www/html/public/uploads
    depends_on:
      - app
      - db
    networks:
      - delnyx_net
    command: php bin/console messenger:consume async scheduler_default --time-limit=3600 --memory-limit=128M -vv
    restart: unless-stopped
    environment:
      - APP_ENV=prod
      - APP_DEBUG=0
```

### Étape 2 : Modifier le script de déploiement

Remplacer la section "Configuration du worker Messenger" par :

```bash
# 15. Relance du worker Messenger (via service Docker)
echo "🔄 Relance du worker Messenger..."
docker compose up -d messenger-worker

# Vérifier que le worker est bien lancé
sleep 3
if docker ps | grep -q delnyx_messenger_worker; then
    echo "✅ Worker Messenger lancé avec succès"
else
    echo "❌ Le worker Messenger n'est pas lancé"
    docker compose logs messenger-worker
    exit 1
fi
```

**Avantages :**
- Redémarrage automatique si le conteneur crash
- Logs séparés (`docker compose logs messenger-worker`)
- Plus facile à monitorer
- Gestion via docker-compose standard

**Commandes utiles :**
```bash
# Voir les logs du worker
docker compose logs -f messenger-worker

# Redémarrer le worker
docker compose restart messenger-worker

# Arrêter le worker
docker compose stop messenger-worker

# Vérifier le statut
docker compose ps messenger-worker
```

## Option 3 : Supervisor dans le conteneur (Avancé)

Si vous préférez gérer le worker depuis l'intérieur du conteneur avec Supervisor.

### Étape 1 : Créer la configuration Supervisor

**`docker/supervisor/messenger-worker.conf` :**
```ini
[program:messenger-worker]
command=php /var/www/html/bin/console messenger:consume async scheduler_default --time-limit=3600 --memory-limit=128M
directory=/var/www/html
user=www-data
numprocs=1
startsecs=0
autorestart=true
startretries=10
stdout_logfile=/var/log/messenger-worker.log
stderr_logfile=/var/log/messenger-worker-error.log
```

### Étape 2 : Modifier le Dockerfile

Ajouter Supervisor et la configuration :

```dockerfile
# Installer Supervisor
RUN apt-get update && apt-get install -y supervisor && rm -rf /var/lib/apt/lists/*

# Copier la configuration Supervisor
COPY docker/supervisor/ /etc/supervisor/conf.d/

# Lancer Supervisor au démarrage
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
```

## Recommandation

**Pour la production, utilisez l'Option 2 (Service Docker séparé)** car :
- ✅ Redémarrage automatique
- ✅ Logs séparés
- ✅ Monitoring facile
- ✅ Gestion standard Docker

## Vérification après déploiement

```bash
# Vérifier que le worker est actif
docker compose ps messenger-worker

# Voir les messages en queue
docker compose exec app php bin/console messenger:stats

# Voir les logs en temps réel
docker compose logs -f messenger-worker

# Vérifier les tâches Scheduler
docker compose exec app php bin/console debug:scheduler
```

