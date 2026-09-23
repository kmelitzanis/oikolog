FROM php:8.3-fpm-trixie AS php-base
# Refresh security updates even when the upstream PHP image predates them.
# Keep only shared libraries needed by PHP after compiling the extensions.
RUN set -eux; \
    apt-get update; \
    apt-get upgrade -y; \
    apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql zip exif pcntl bcmath intl gd opcache; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual ca-certificates; \
    find /usr/local -type f -executable -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) next; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u \
        | xargs -r dpkg-query --search \
        | sed '/^diversion /d' \
        | cut -d: -f1 \
        | sort -u \
        | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/* /usr/src/php*
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM node:22-bookworm-slim AS assets
WORKDIR /app
# npm only bootstraps the package manager pinned by this project.
RUN npm install --global pnpm@12.4.2
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN CI=true pnpm install --frozen-lockfile
COPY resources ./resources
COPY public ./public
COPY --from=dependencies /var/www/html/vendor ./vendor
COPY vite.config.* ./
RUN pnpm run build

FROM php-base AS runtime
# Set by CI from the release tag; readable at runtime as env("APP_VERSION").
ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build
# Preserve assets outside the public volume for the entrypoint's boot-time sync.
RUN cp -a public /opt/public-dist \
    && sed -i 's|^listen = .*|listen = 0.0.0.0:9000|' /usr/local/etc/php-fpm.d/www.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh
EXPOSE 9000
VOLUME ["/var/www/html/public"]
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
