# # delnyx/Dockerfile
# FROM php:8.3-fpm

# # Installe les dépendances système
# RUN apt-get update && apt-get install -y \
#     git \
#     unzip \
#     libpq-dev \
#     libicu-dev \
#     libzip-dev \
#     zip \
#     libonig-dev \
#     curl \
#     libpng-dev \
#     libjpeg-dev \
#     libfreetype6-dev \
#     && docker-php-ext-configure gd --with-freetype --with-jpeg \
#     && docker-php-ext-install pdo pdo_pgsql intl zip opcache gd

# # Installe Composer
# COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# # Définit le dossier de travail
# WORKDIR /var/www/html

# # Active l’opcache en prod (sera optimisé plus tard)
# RUN echo "opcache.enable=1\nopcache.memory_consumption=128\nopcache.max_accelerated_files=10000\nopcache.validate_timestamps=0" \
#     > /usr/local/etc/php/conf.d/opcache.ini
# Dockerfile optimisé pour Coolify
FROM php:8.3-fpm

# Dépendances système
RUN apt-get update && apt-get install -y \
    git unzip curl zip libpq-dev libicu-dev libzip-dev libonig-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash && \
    mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www/html

# Copie du code complet
COPY . .

# Installation des dépendances
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Génération de l'autoloader
RUN composer dump-autoload --optimize

# Créer les dossiers nécessaires avec permissions
RUN mkdir -p var/cache var/log public/uploads/projects/thumbnails \
    && chmod -R 777 var/ \
    && chown -R www-data:www-data var/ public/uploads || true

# Config OPCache
RUN echo "opcache.enable=1\n\
opcache.memory_consumption=128\n\
opcache.max_accelerated_files=10000\n\
opcache.validate_timestamps=0" > /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM écoute sur toutes les interfaces
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000

CMD ["php-fpm"]