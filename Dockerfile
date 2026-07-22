# ⚠️ CONFIRM: check your composer.json -> "require": { "php": "^X.X" }
# and change the tag below to match (e.g. 8.1-fpm, 8.2-fpm, 8.3-fpm)
FROM php:8.0-fpm

WORKDIR /var/www/html

# System dependencies
RUN apt-get update && apt-get install -y \
  git \
  unzip \
  libpng-dev \
  libonig-dev \
  libxml2-dev \
  libzip-dev \
  libjpeg-dev \
  libfreetype6-dev \
  libpq-dev \
  postgresql-client \
  && rm -rf /var/lib/apt/lists/*

# PHP extensions commonly needed by Laravel CRMs
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . .

# Install PHP dependencies (production, no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions for Laravel writable dirs
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
  && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]