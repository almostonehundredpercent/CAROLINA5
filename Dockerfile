FROM php:8.3-cli

RUN apt-get update && apt-get install -y git unzip libzip-dev && docker-php-ext-install pdo_mysql zip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8000
CMD ["sh", "-c", "php artisan config:cache && php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=8000"]
