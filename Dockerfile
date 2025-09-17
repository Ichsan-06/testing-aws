# Gunakan PHP 8.3 FPM
FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git curl unzip libonig-dev libzip-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    librdkafka-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader \
    && cp .env.example .env \
    && php artisan key:generate

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

CMD ["php-fpm"]
