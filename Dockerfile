FROM php:8.3-cli
RUN apt-get update && apt-get install -y git unzip libzip-dev && docker-php-ext-install zip pdo pdo_mysql
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev
CMD php artisan serve --host=0.0.0.0 --port=$PORT
