# Déploiement du Worker Messenger

## Architecture de Production (Coolify)

Le déploiement utilise **Supervisor** pour gérer PHP-FPM et le worker Messenger dans le même conteneur.

### Fichiers de configuration

- `Dockerfile` : Installe Supervisor et copie la configuration
- `docker/supervisor/supervisord.conf` : Configure PHP-FPM + Messenger worker

### Comment ça fonctionne

1. Coolify build l'image Docker depuis le `Dockerfile`
2. Supervisor démarre automatiquement :
   - **PHP-FPM** (port 9000) pour servir l'application
   - **Messenger Worker** pour traiter les tâches en arrière-plan

### Logs

Les logs du worker sont disponibles dans :
```bash
# Dans le conteneur
cat /var/log/supervisor/messenger-worker.log
cat /var/log/supervisor/messenger-worker-error.log

# Via Docker (si accès au conteneur)
docker logs <container_id>
```

### Vérifier le statut

```bash
# Dans le conteneur Coolify
supervisorctl status

# Résultat attendu :
# messenger-worker   RUNNING   pid 123, uptime 0:10:00
# php-fpm            RUNNING   pid 456, uptime 0:10:00
```

### Redémarrer le worker

```bash
# Dans le conteneur
supervisorctl restart messenger-worker
```

### Configuration Supervisor

Le fichier `docker/supervisor/supervisord.conf` configure :

| Programme | Description |
|-----------|-------------|
| `php-fpm` | Serveur PHP-FPM (priorité 5) |
| `messenger-worker` | Worker Messenger (priorité 10, redémarre toutes les heures) |

### Paramètres du worker

- `--time-limit=3600` : Redémarre toutes les heures (libère la mémoire)
- `--memory-limit=128M` : Arrête si dépasse 128 Mo
- `async` : Transport pour les messages async (régénération PDF, etc.)
- `scheduler_default` : Transport pour les tâches planifiées

---

## Développement Local

En local, le worker est géré par un service Docker séparé (`docker-compose.yml`) :

```bash
# Démarrer les containers (inclut le worker)
docker compose up -d

# Voir les logs du worker
docker compose logs -f messenger

# Traiter les messages manuellement
docker compose exec app php bin/console messenger:consume async --limit=10 -vv
```

---

## Anciennes configurations (obsolètes)

> [!WARNING]
> Les configurations suivantes ne sont plus utilisées :
> - ~~GitHub Actions (`deploy.yml`)~~ - Supprimé, Coolify gère le déploiement
> - ~~Script `scripts/deploy.sh`~~ - N'existe plus
