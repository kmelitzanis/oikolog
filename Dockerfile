FROM php:8.3-fpm

# System dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
        libfreetype6-dev zlib1g-dev libxml2-dev procps ca-certificates gnupg curl \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql zip exif pcntl bcmath intl gd opcache

# Node.js (for Vite/Mix)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files and install dependencies first for better caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy rest of the application
COPY . .

# Build assets (if using Vite/Mix)
RUN npm ci && npm run build

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
