# Guide des Workers - Messenger et Scheduler

## Vue d'ensemble

Votre application utilise **Symfony Messenger** pour toutes les tâches en arrière-plan :

1. **Messages personnalisés** : Régénération PDF, etc.
2. **Tâches Scheduler** : Les tâches périodiques (expiration des devis) sont converties en messages Messenger

**Bonne nouvelle** : Le Scheduler utilise Messenger en interne, donc un seul worker peut traiter les deux types de tâches !

## Worker nécessaire

### Worker Messenger (traite tout)

Le worker Messenger traite à la fois :
- Les messages personnalisés (régénération PDF)
- Les tâches du Scheduler (expiration des devis)

**Commande :**
```bash
docker-compose exec app php bin/console messenger:consume async scheduler_default -vv
```

**Explication :**
- `async` : Transport pour les messages personnalisés (régénération PDF)
- `scheduler_default` : Transport pour les tâches du Scheduler (créé automatiquement)
- `-vv` : Mode verbeux pour voir toutes les tâches traitées

**Options utiles :**
- `-vv` : Mode verbeux
- `--time-limit=3600` : Limite à 1 heure
- `--memory-limit=128M` : Limite la mémoire

## Configuration en développement

### Lancer le worker

Un seul worker traite toutes les tâches :

```bash
docker-compose exec app php bin/console messenger:consume async scheduler_default -vv
```

Ce worker traitera :
- Les messages de régénération PDF (transport `async`)
- Les tâches périodiques du Scheduler (transport `scheduler_default`)

## Configuration en production

### Option 1 : Supervisor (Recommandé)

Créer deux configurations Supervisor pour maintenir les workers actifs.

**`/etc/supervisor/conf.d/messenger-worker.conf` :**
```ini
[program:messenger-worker]
command=php /var/www/html/bin/console messenger:consume async scheduler_default --time-limit=3600 --memory-limit=128M
directory=/var/www/html
user=www-data
numprocs=1
startsecs=0
autorestart=true
startretries=10
process_name=%(program_name)s_%(process_num)02d
stdout_logfile=/var/log/messenger-worker.log
stderr_logfile=/var/log/messenger-worker-error.log
```

**Relancer Supervisor :**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start messenger-worker:*
```

### Option 2 : Systemd

Créer deux services systemd.

**`/etc/systemd/system/messenger-worker.service` :**
```ini
[Unit]
Description=Symfony Messenger Worker (Messages + Scheduler)
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php bin/console messenger:consume async scheduler_default --time-limit=3600 --memory-limit=128M
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**Activer le service :**
```bash
sudo systemctl daemon-reload
sudo systemctl enable messenger-worker
sudo systemctl start messenger-worker
```

### Option 3 : Cron (Alternative)

Si vous préférez utiliser cron au lieu d'un worker continu, vous pouvez exécuter le worker périodiquement :

**Crontab (`crontab -e`) :**
```cron
# Exécuter le worker toutes les minutes (traite les messages en attente)
* * * * * cd /var/www/html && php bin/console messenger:consume async scheduler_default --time-limit=60 >> /var/log/messenger.log 2>&1
```

⚠️ **Note** : Cette approche est moins efficace qu'un worker continu car elle ne traite les messages que toutes les minutes.

## Tâches configurées

### Tâches Scheduler

- **`app:quotes:expire`** : Expire automatiquement les devis dont la date de validité est dépassée
  - Fréquence : Toutes les heures
  - Commande : `php bin/console app:quotes:expire`

### Messages Messenger

- **`RegeneratePdfMessage`** : Régénère les PDF lors de modifications client/entreprise
  - Transport : `async`
  - Handler : `RegeneratePdfHandler`

## Monitoring

### Vérifier les workers

**Messenger :**
```bash
# Voir les messages en queue
docker-compose exec app php bin/console messenger:stats

# Voir les messages échoués
docker-compose exec app php bin/console messenger:failed:show
```

**Scheduler :**
```bash
# Lister les tâches planifiées
docker-compose exec app php bin/console debug:scheduler

# Voir les logs
docker-compose exec app tail -f var/log/dev.log
```

### Vérifier les processus

```bash
# Voir les workers actifs
ps aux | grep -E "(messenger:consume|scheduler:run)"
```

## Dépannage

### Le worker Messenger ne traite pas les messages

1. Vérifier que le worker est lancé
2. Vérifier les messages en queue : `php bin/console messenger:stats`
3. Vérifier les logs : `tail -f var/log/dev.log`

### Le Scheduler n'exécute pas les tâches

1. Vérifier que le worker est lancé avec le transport `scheduler_default`
2. Vérifier les tâches : `php bin/console debug:scheduler`
3. Exécuter manuellement : `php bin/console app:quotes:expire`
4. Vérifier les logs : `tail -f var/log/dev.log`

### Les workers redémarrent en boucle

1. Vérifier les logs d'erreur
2. Vérifier les permissions des fichiers
3. Vérifier la mémoire disponible
4. Vérifier la connexion à la base de données

## Résumé

| Type de tâche | Transport | Commande Worker |
|---------------|-----------|----------------|
| **Messages personnalisés** (régénération PDF) | `async` | `messenger:consume async scheduler_default` |
| **Tâches Scheduler** (expiration devis) | `scheduler_default` | `messenger:consume async scheduler_default` |

**Important** : Un seul worker traite les deux types de tâches ! Utilisez :
```bash
php bin/console messenger:consume async scheduler_default -vv
```

