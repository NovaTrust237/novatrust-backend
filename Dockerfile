# 1. Utiliser l'image officielle PHP avec Apache
FROM php:8.2-apache

# 2. Installer les dépendances système et MySQL (Remplacement de PostgreSQL)
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_mysql

# 3. Activer la réécriture d'URL et forcer le bon module MPM pour PHP
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite

# 4. Changer la racine d'Apache vers le dossier "public" de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 5. Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Copier tout le code du projet dans le conteneur
WORKDIR /var/www/html
COPY . .

# 7. Installer les dépendances Laravel
RUN composer install --no-dev --optimize-autoloader

# 8. Assurer les droits d'écriture pour Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 9. Démarrage : Injection du port (compatible Render/Railway), Caches, migrations, seeders et lancement
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/g" /etc/apache2/ports.conf && \
    sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT:-80}>/g" /etc/apache2/sites-available/000-default.conf && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan migrate --force --seed && \
    apache2-foreground
