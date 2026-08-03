FROM php:8.4-fpm

# Installer dépendances système et extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libgmp-dev \
    zip unzip git curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd gmp

# Installer Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# Installer les dépendances Laravel
RUN composer install --no-dev --optimize-autoloader

# Droits sur storage et cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Optimiser Laravel pour la prod
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

EXPOSE 9000

# Commande de démarrage : migrations + seeders + PHP-FPM
CMD php artisan migrate --force && php artisan db:seed --force && php-fpm
