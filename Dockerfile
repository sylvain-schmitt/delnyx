FROM php:8.3-fpm

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

RUN mkdir -p var/cache var/log public/bundles public/build public/uploads/projects \
    && chmod -R 777 var/ public/build public/bundles public/uploads/projects

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction \
    && composer dump-autoload --optimize

# Builds assets (désactivé si tu préfères faire localement)
RUN php bin/console importmap:install || true
RUN php bin/console tailwind:build --minify || true
RUN php bin/console asset-map:compile || true

# Permissions finales
RUN chmod -R 777 var/ public/

# OPCache config
RUN echo "opcache.enable=1\n\
opcache.memory_consumption=128\n\
opcache.max_accelerated_files=10000\n\
opcache.validate_timestamps=0" > /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM écoute sur 0.0.0.0:9000 par défaut, pas besoin de changer

EXPOSE 8001

CMD ["php-fpm"]
