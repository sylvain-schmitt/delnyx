# delnyx/Dockerfile
FROM php:8.3-fpm

# Installe les dépendances système
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    zip \
    libonig-dev \
    curl \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache

# Installe Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Définit le dossier de travail
WORKDIR /var/www/html

# Active l’opcache en prod (sera optimisé plus tard)
RUN echo "opcache.enable=1\nopcache.memory_consumption=128\nopcache.max_accelerated_files=10000\nopcache.validate_timestamps=0" \
    > /usr/local/etc/php/conf.d/opcache.ini
