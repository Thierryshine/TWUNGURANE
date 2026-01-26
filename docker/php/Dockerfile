# =============================================================================
# TWUNGURANE - PHP-FPM Dockerfile
# Laravel Backend API
# =============================================================================

FROM php:8.2-fpm

# Arguments
ARG user=twungurane
ARG uid=1000

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libcurl4-openssl-dev \
    libssl-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installation de Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Création d'un utilisateur système
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Configuration du répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers de configuration PHP
COPY local.ini /usr/local/etc/php/conf.d/local.ini

# Changer l'utilisateur
USER $user

# Exposer le port PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
