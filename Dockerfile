FROM php:8.2-fpm

WORKDIR /var/www/html

# -------------------------------------------------------
# System dependencies
# -------------------------------------------------------
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
    libc-client2007e-dev \
    libkrb5-dev \
    default-mysql-client \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# -------------------------------------------------------
# PHP extensions
# -------------------------------------------------------

# Configure GD
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg

# Configure IMAP with SSL + Kerberos support
RUN docker-php-ext-configure imap \
    --with-kerberos \
    --with-imap-ssl

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    imap

# -------------------------------------------------------
# PHP configuration
# -------------------------------------------------------

# Increase PHP memory limit for heavy reports/dashboards
RUN echo "memory_limit = 1024M" \
    > /usr/local/etc/php/conf.d/memory-limit.ini

# -------------------------------------------------------
# Composer
# -------------------------------------------------------

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -------------------------------------------------------
# Application
# -------------------------------------------------------

COPY . .

# Install production dependencies
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

# -------------------------------------------------------
# Laravel permissions
# -------------------------------------------------------

RUN chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
    && chmod -R 775 \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]