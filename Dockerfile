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

FROM php:8.3-fpm

#RUN curl -fsSL https://deb.nodesource.com/setup_16.x | bash - \
    #&& apt-get install -y nodejs

RUN apt-get update && apt-get install -y \
    git unzip curl zip \
    libpq-dev libicu-dev libzip-dev libonig-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

RUN curl -sS https://get.symfony.com/cli/installer | bash && \
    mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www/html

COPY . .

RUN mkdir -p var/cache var/log public/bundles public/build \
    && chmod -R 777 var/ public/build public/bundles

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction \
    && composer dump-autoload --optimize

# NPM + Build
#RUN npm install && npm run build

# ===== TOUS LES BUILDS D'ASSETS ICI =====
RUN php bin/console importmap:install || true
RUN php bin/console tailwind:build --minify || true
RUN php bin/console asset-map:compile || true

# Permissions finales
RUN chmod -R 777 var/ public/

RUN echo "opcache.enable=1\n\
opcache.memory_consumption=128\n\
opcache.max_accelerated_files=10000\n\
opcache.validate_timestamps=0" > /usr/local/etc/php/conf.d/opcache.ini

ENV APP_ENV=prod

EXPOSE 8001

CMD ["php", "-S", "0.0.0.0:8001", "-t", "public"]
