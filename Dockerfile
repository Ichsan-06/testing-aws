# Gunakan PHP 8.3 FPM
FROM php:8.3-fpm

# Install dependencies yang dibutuhkan
RUN apt-get update && apt-get install -y \
    git curl unzip libonig-dev libzip-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    librdkafka-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka

# Install composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy project files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permission untuk Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
