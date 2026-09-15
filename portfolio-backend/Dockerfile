FROM php:8.4-cli-alpine

RUN apk add --no-cache git unzip libpng-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8080
CMD php artisan migrate --force \
    && php artisan config:cache \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
