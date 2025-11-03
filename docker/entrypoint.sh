#!/bin/bash
set -e

# Créer les dossiers uploads s'ils n'existent pas
mkdir -p /var/www/html/public/uploads/projects/thumbnails

# Configurer les permissions
chown -R www-data:www-data /var/www/html/public/uploads || true
chmod -R 775 /var/www/html/public/uploads || true

# Exécuter la commande originale (PHP-FPM)
exec "$@"

