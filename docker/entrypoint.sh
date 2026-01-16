#!/bin/bash
set -e

# Créer les dossiers uploads s'ils n'existent pas
mkdir -p /var/www/html/public/uploads/projects/thumbnails
mkdir -p /var/www/html/var/log
mkdir -p /var/log/supervisor

# Configurer les permissions
chown -R www-data:www-data /var/www/html/public/uploads || true
chmod -R 775 /var/www/html/public/uploads || true
chmod -R 777 /var/www/html/var || true

# Attendre que la base de données soit prête (max 30 secondes)
echo "🔄 Attente de la base de données..."
for i in {1..30}; do
    if php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; then
        echo "✅ Base de données prête"
        break
    fi
    echo "⏳ Tentative $i/30..."
    sleep 1
done

# Exécuter les migrations
echo "🔄 Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || echo "⚠️ Migrations failed, continuing..."

# Compiler le cache (après que la DB soit prête)
echo "🔄 Compilation du cache (APP_ENV=${APP_ENV:-dev})..."
php bin/console cache:warmup --env="${APP_ENV:-dev}" --no-debug || echo "⚠️ Cache warmup failed, continuing..."

echo "✅ Démarrage de l'application..."

# Exécuter la commande originale (Supervisor)
exec "$@"
