FROM php:8.2-fpm-alpine

# Install dependensi sistem + ekstensi PHP
RUN apk add --no-cache \
        libpq-dev \
        curl \
    && docker-php-ext-install pdo pdo_pgsql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer.json dan install dulu
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

EXPOSE 9000
CMD ["php-fpm"]
