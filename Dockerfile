FROM php:8.4-apache

# Installer dépendances système et extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    git unzip zip curl \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev libpq-dev libgmp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_pgsql pgsql pdo_mysql mbstring bcmath gd zip opcache gmp \
    && rm -rf /var/lib/apt/lists/*

# Activer mod_rewrite pour Laravel
RUN a2enmod rewrite

# Définir le dossier public comme racine Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copier le projet Laravel
COPY . .

# Installer les dépendances Laravel
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# Créer les dossiers nécessaires
RUN mkdir -p storage/framework/cache \
    storage/framework/views \
    storage/framework/sessions \
    bootstrap/cache

# Donner les bons droits
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Script d'entrée pour lancer migrations + seeders
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
