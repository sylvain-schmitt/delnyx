# Documentation de Déploiement : Renouvellement des Abonnements Manuels

## Configuration Cron Task (Coolify / Serveur)

Pour assurer le renouvellement automatique des abonnements manuels (paiements par virement, chèque, etc.), vous devez configurer une tâche planifiée (Cron Job).

### Commande à exécuter
Cette commande doit être exécutée **une fois par jour** (par exemple à 04:00 du matin).

```bash
php bin/console app:subscription:renew-manual
```

### Configuration recommandée (Crontab)
```cron
# Tous les jours à 04:00
0 4 * * * cd /path/to/project && php bin/console app:subscription:renew-manual >> /var/log/cron.log 2>&1
```

### Options disponibles
- `--dry-run` : Simule le processus sans créer de factures ni envoyer de messages. Utile pour tester.
- `--days-before=X` : (Par défaut: 0) Nombre de jours avant l'échéance pour générer le renouvellement. Exemple : `--days-before=7` pour générer la facture 7 jours avant la fin de l'abonnement.

## Fonctionnement
1. La commande détecte les abonnements manuels actifs dont la date de fin est atteinte (ou sera atteinte dans X jours).
2. Elle envoie un message asynchrone (`RenewManualSubscriptionMessage`) dans la file d'attente.
3. Le worker (`messenger:consume async`) traite le message et génère une nouvelle facture "DRAFT" pour la période suivante.
4. L'abonnement est mis à jour avec la nouvelle période.

> **Note :** Le worker Messenger doit être actif (avec Supervisor ou Docker) pour que les factures soient réellement créées.
