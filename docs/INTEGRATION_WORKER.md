# Intégration du Worker Messenger dans docker-compose.yml

## Étape 1 : Ajouter le service dans docker-compose.yml

Ajoutez cette section dans la partie `services:` de votre `docker-compose.yml` :

```yaml
services:
  # ... vos autres services (app, db, nginx, etc.) ...

  messenger-worker:
    build:
      context: .
      dockerfile: Dockerfile
    image: delnyx-app-prod
    container_name: delnyx_messenger_worker
    working_dir: /var/www/html
    volumes:
      - app_public:/var/www/html/public
      - uploads_data:/var/www/html/public/uploads
    depends_on:
      - app
      - db
    command: php bin/console messenger:consume async scheduler_default --time-limit=3600 --memory-limit=128M -vv
    restart: unless-stopped
    environment:
      - APP_ENV=prod
      - APP_DEBUG=0
```

## Étape 2 : Vérifier la configuration

Le service utilise :
- ✅ La même image que le service `app` (`delnyx-app-prod`)
- ✅ Les mêmes volumes (`app_public`, `uploads_data`)
- ✅ `restart: unless-stopped` pour redémarrer automatiquement
- ✅ `depends_on: app, db` pour s'assurer que les dépendances sont prêtes

## Étape 3 : Script de déploiement

Le script `scripts/deploy.sh` a été mis à jour avec la section suivante :

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

## Commandes utiles

### Vérifier le statut du worker
```bash
docker compose ps messenger-worker
```

### Voir les logs du worker
```bash
docker compose logs -f messenger-worker
```

### Redémarrer le worker
```bash
docker compose restart messenger-worker
```

### Arrêter le worker
```bash
docker compose stop messenger-worker
```

### Vérifier les messages en queue
```bash
docker compose exec app php bin/console messenger:stats
```

### Vérifier les tâches Scheduler
```bash
docker compose exec app php bin/console debug:scheduler
```

## Avantages de cette approche

✅ **Redémarrage automatique** : Si le conteneur crash, Docker le redémarre automatiquement  
✅ **Logs séparés** : Les logs du worker sont isolés et faciles à consulter  
✅ **Monitoring facile** : `docker compose ps` montre le statut du worker  
✅ **Gestion standard** : Utilise les commandes Docker Compose standard  
✅ **Isolation** : Le worker ne bloque pas le service `app` principal  

## Dépannage

### Le worker ne démarre pas

1. Vérifier les logs :
   ```bash
   docker compose logs messenger-worker
   ```

2. Vérifier que les transports sont configurés :
   ```bash
   docker compose exec app php bin/console messenger:setup-transports
   ```

3. Vérifier que la base de données est accessible :
   ```bash
   docker compose exec app php bin/console doctrine:query:sql "SELECT 1"
   ```

### Le worker s'arrête en boucle

1. Vérifier les logs pour voir l'erreur
2. Vérifier la mémoire disponible : `docker stats delnyx_messenger_worker`
3. Vérifier les permissions sur `var/` : `docker compose exec app ls -la var/`

## Production

En production, le worker sera :
- ✅ Lancé automatiquement au démarrage (`restart: unless-stopped`)
- ✅ Relancé automatiquement lors du déploiement (via le script)
- ✅ Monitorable via les logs Docker
- ✅ Isolé du service principal pour éviter les impacts

