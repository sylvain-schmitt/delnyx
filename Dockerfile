FROM php:8.3-fpm

# Installation des dépendances système + Supervisor
RUN apt-get update && apt-get install -y \
    git unzip curl zip \
    libpq-dev libicu-dev libzip-dev libonig-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Installer Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash && \
    mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www/html

COPY . .

# Création des dossiers nécessaires
RUN mkdir -p var/cache var/log public/bundles public/build public/uploads/projects public/uploads/projects/thumbnails \
    && mkdir -p /var/log/supervisor \
    && chmod -R 777 var/ public/build public/bundles public/uploads

# Installation des dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction \
    && composer dump-autoload --optimize

# Compiler le cache de production AVANT les assets (requis pour les templates Twig)
RUN php bin/console cache:warmup --env=prod || true

# Build des assets (après le cache pour que les icônes soient détectées)
RUN php bin/console importmap:install || true
RUN php bin/console tailwind:build --minify || true

# Permissions finales sur var et public
RUN chmod -R 777 var/ public/
RUN chown -R www-data:www-data public/uploads

# Configuration OPcache
RUN echo "opcache.enable=1\n\
    opcache.memory_consumption=128\n\
    opcache.max_accelerated_files=10000\n\
    opcache.validate_timestamps=0" > /usr/local/etc/php/conf.d/opcache.ini

# Configuration de la timezone PHP
RUN echo "date.timezone = Europe/Paris" > /usr/local/etc/php/conf.d/timezone.ini

# Copier la configuration Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copier et rendre exécutable l'entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Variables d'environnement (peuvent être surchargées par docker-compose)
ARG APP_ENV=prod
ENV APP_ENV=${APP_ENV}

EXPOSE 8001

# Utiliser l'entrypoint puis lancer Supervisor
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
