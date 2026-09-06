FROM php:8.3-cli

# Keep MySQL support for local XAMPP and add PostgreSQL for the free Render database.
RUN apt-get update && apt-get install -y git unzip libzip-dev libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize
RUN chmod +x docker/start.sh && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8000
CMD ["docker/start.sh"]
