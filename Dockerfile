# PHP FPM image for Laravel 12
FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        zlib1g-dev \
        libxml2-dev \
        procps \
        ca-certificates \
        gnupg \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql zip exif pcntl bcmath intl gd opcache

# Install composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create working directory
WORKDIR /var/www/html

# Copy entrypoint and make executable
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy application code (mounted in development via volume)
COPY . /var/www/html

# Ensure permissions for storage and cache directories
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

EXPOSE 9000

# Use entrypoint to ensure vendor install and permissions
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

