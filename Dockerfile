# Étape 1 : Image PHP avec FPM
FROM php:8.4-fpm

# Installer dépendances système et extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip unzip git curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Installer Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Définir le dossier de travail
WORKDIR /var/www

# Copier le projet Laravel
COPY . .

# Installer les dépendances Laravel
RUN composer install --no-dev --optimize-autoloader

# Donner les bons droits aux dossiers nécessaires
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Optimiser Laravel pour la prod
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# Exposer le port PHP-FPM
EXPOSE 9000

# Commande de démarrage : lancer PHP-FPM puis migrations + seeders
CMD php artisan migrate --force && php artisan db:seed --force && php-fpm
