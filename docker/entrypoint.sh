#!/bin/bash
set -e

# Créer les dossiers uploads s'ils n'existent pas
mkdir -p /var/www/html/public/uploads/projects/thumbnails
mkdir -p /var/www/html/var/log
mkdir -p /var/log/supervisor

# Favicon à la RACINE du site.
#
# ⚠️ Posé ici et non seulement dans le dépôt : /favicon.ico renvoyait 404 en production
# alors que public/favicon.ico est bien versionné et que le Dockerfile fait `COPY . .`.
# Cause probable, un volume persistant monté sur public/ qui masque le contenu de
# l'image — ce qui n'empêche pas /assets/ de fonctionner, puisque asset-map:compile les
# régénère à chaque démarrage, juste en dessous.
#
# Le copier au démarrage rend le résultat indépendant de la cause : que ce soit un
# volume, une détection de build différente ou autre chose, le fichier sera là.
#
# Pourquoi ce fichier compte : Edge demande /favicon.ico EN DIRECT pour les favoris,
# l'historique et les vignettes du nouvel onglet, sans lire les balises de la page.
# ⚠️ Chemins ABSOLUS et sans empreinte, à la racine du site.
#
# AssetMapper renomme les fichiers (favicon-NvfwGBX.ico) : un manifeste ne peut donc
# pas y référencer ses icônes, il ne connaît pas l'empreinte. Et /favicon.ico est de
# toute façon demandé en dur par les navigateurs. Ces quatre-là vivent donc à la
# racine, comme le veut l'usage.
for f in favicon.ico site.webmanifest web-app-manifest-192x192.png web-app-manifest-512x512.png; do
    if [ -f "/var/www/html/assets/images/favicon/$f" ]; then
        cp -f "/var/www/html/assets/images/favicon/$f" "/var/www/html/public/$f" || true
    fi
done

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

echo "🔄 Compilation des assets (pour s'assurer que tous les contrôleurs Stimulus sont détectés)..."
php bin/console asset-map:compile || echo "⚠️ Asset compilation failed, continuing..."

echo "✅ Démarrage de l'application..."

# Exécuter la commande originale (Supervisor)
exec "$@"
