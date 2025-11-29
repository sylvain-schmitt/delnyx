# Régénération PDF en arrière-plan avec Symfony Messenger

## Vue d'ensemble

La régénération des PDF lors de modifications des informations client ou entreprise est maintenant effectuée **en arrière-plan** via Symfony Messenger. Cela permet de ne pas bloquer la mise à jour des informations et d'améliorer les performances.

## Architecture

### Composants

1. **Message** : `App\Message\RegeneratePdfMessage`
   - Contient le type de régénération (`company` ou `client`)
   - Contient l'identifiant (companyId ou clientId)

2. **Handler** : `App\MessageHandler\RegeneratePdfHandler`
   - Traite les messages de régénération PDF
   - Appelle le service `PdfRegenerationService`

3. **Subscribers** :
   - `CompanySettingsUpdateSubscriber` : Dispatch un message lors de modification CompanySettings
   - `ClientUpdateSubscriber` : Dispatch un message lors de modification Client

### Transport

- **Transport asynchrone** : `doctrine://default?queue_name=async`
  - Utilise la base de données pour stocker les messages
  - Permet le traitement en arrière-plan

- **Transport d'échec** : `doctrine://default?queue_name=failed`
  - Stocke les messages qui ont échoué pour analyse ultérieure

## Installation

### 1. Installer le package Doctrine Messenger

Le transport Doctrine nécessite le package `symfony/doctrine-messenger` :

```bash
docker-compose exec app composer require symfony/doctrine-messenger
```

### 2. Créer les tables de la queue

Exécuter la commande pour créer les tables nécessaires :

```bash
docker-compose exec app php bin/console messenger:setup-transports
```

Cette commande crée automatiquement les tables `messenger_messages` pour les transports `async` et `failed`.

### 2. Lancer le worker Messenger

Pour traiter les messages en arrière-plan, vous devez lancer un worker :

```bash
docker-compose exec app php bin/console messenger:consume async -vv
```

**Options utiles :**
- `-vv` : Mode verbeux pour voir les messages traités
- `--time-limit=3600` : Limite le temps d'exécution (1 heure)
- `--memory-limit=128M` : Limite la mémoire utilisée

### 3. Worker en production

En production, utilisez un gestionnaire de processus comme **Supervisor** ou **systemd** pour maintenir le worker actif.

**Exemple avec Supervisor** (`/etc/supervisor/conf.d/messenger-worker.conf`) :

```ini
[program:messenger-worker]
command=php /var/www/html/bin/console messenger:consume async --time-limit=3600
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

## Fonctionnement

### Flux de régénération

1. **Modification d'informations** (Client ou CompanySettings)
   - L'utilisateur modifie les informations via l'interface
   - Le subscriber détecte les changements pertinents

2. **Dispatch du message**
   - Un message `RegeneratePdfMessage` est créé et dispatché
   - Le message est stocké dans la queue `async`
   - **La réponse HTTP est immédiate** (pas d'attente)

3. **Traitement en arrière-plan**
   - Le worker Messenger consomme le message
   - Le handler appelle `PdfRegenerationService`
   - Les PDF sont régénérés et les anciens supprimés
   - Les logs sont enregistrés

### Avantages

✅ **Performance** : Pas de blocage lors de la mise à jour  
✅ **Scalabilité** : Plusieurs workers peuvent traiter les messages  
✅ **Fiabilité** : Les messages échoués sont stockés pour analyse  
✅ **Monitoring** : Logs détaillés pour suivre les régénérations  

## Monitoring

### Voir les messages en attente

```bash
docker-compose exec app php bin/console messenger:stats
```

### Voir les messages échoués

```bash
docker-compose exec app php bin/console messenger:failed:show
```

### Réessayer un message échoué

```bash
docker-compose exec app php bin/console messenger:failed:retry <message-id>
```

## Dépannage

### Le worker ne traite pas les messages

1. Vérifier que le worker est lancé :
   ```bash
   ps aux | grep messenger:consume
   ```

2. Vérifier les logs :
   ```bash
   docker-compose exec app tail -f var/log/dev.log
   ```

3. Vérifier les messages en queue :
   ```bash
   docker-compose exec app php bin/console messenger:stats
   ```

### Messages qui échouent

1. Voir les messages échoués :
   ```bash
   docker-compose exec app php bin/console messenger:failed:show
   ```

2. Analyser les erreurs dans les logs

3. Réessayer après correction :
   ```bash
   docker-compose exec app php bin/console messenger:failed:retry <id>
   ```

## Configuration

Le transport est configuré dans `config/packages/messenger.yaml` :

```yaml
framework:
    messenger:
        transports:
            async:
                dsn: 'doctrine://default?queue_name=async'
            failed:
                dsn: 'doctrine://default?queue_name=failed'
        
        routing:
            'App\Message\RegeneratePdfMessage': async
```

## Notes importantes

- ⚠️ **Le worker doit être lancé** pour que les messages soient traités
- ⚠️ En développement, vous pouvez utiliser le transport `sync` pour un traitement immédiat (mais cela bloque la requête)
- ⚠️ En production, utilisez toujours un transport asynchrone avec un worker actif

