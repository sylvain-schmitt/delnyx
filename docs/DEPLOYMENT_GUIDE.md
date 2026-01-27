# Guide de Déploiement - Delnyx (Janvier 2026)

Ce guide détaille les étapes nécessaires pour déployer les nouvelles fonctionnalités (Recherche, Stripe Multi-tenant, Acomptes, Abonnements) en production sur Coolify.

## 1. Mise à jour du Code
Le code a été poussé sur la branche `main`. Coolify devrait déclencher le build automatiquement si l'auto-déploiement est activé. Sinon, lancez un déploiement manuel.

## 2. Base de Données
Une fois le conteneur `app` démarré, exécutez les migrations pour mettre à jour le schéma (clés Stripe dans settings, entités Deposit et Subscription) :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

## 3. Configuration Stripe (CRITIQUE)
Les clés Stripe ne sont plus lues depuis le fichier `.env` par défaut sous peine d'écraser la configuration multi-tenant.

1. Connectez-vous à l'administration Delnyx en production.
2. Allez dans **Paramètres Entreprise**.
3. Activez Stripe et renseignez vos clés de production :
   - Clé Publique (`pk_live_...`)
   - Clé Secrète (`sk_live_...`)
   - Secret Webhook (`whsec_...`)
4. **Webhook** : Dans votre dashboard Stripe, configurez l'URL suivante pour recevoir les événements :
   `https://votre-domaine.fr/webhook/stripe`
   (Événements recommandés : `invoice.payment_succeeded`, `charge.refunded`, `payment_intent.payment_failed`, `invoice.payment_failed`)

## 4. Tâche CRON (Coolify)
Pour gérer le renouvellement automatique des abonnements manuels (génération de facture 15 jours avant échéance), ajoutez une tâche cron dans votre ressource Coolify :

- **Command** : `php bin/console app:subscription:renew-manual`
- **Schedule** : `0 2 * * *` (Tous les jours à 2h du matin)

## 5. Assets
Si les styles ou scripts ne s'affichent pas correctement (notamment la recherche), assurez-vous que le build des assets a bien été effectué :
```bash
php bin/console importmap:install
php bin/console tailwind:build --minify
```

---
> [!IMPORTANT]
> Après le déploiement, vérifiez dans les logs (`var/log/prod.log`) qu'aucune erreur de connexion à la base de données ou Stripe ne survient.
