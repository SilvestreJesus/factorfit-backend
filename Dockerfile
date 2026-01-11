FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    zip unzip curl libpq-dev git \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

# Crear carpeta storage real y symlink dentro del contenedor
RUN mkdir -p storage/app/public \
    && php artisan storage:link || true

CMD php artisan serve --host=0.0.0.0 --port=8000
