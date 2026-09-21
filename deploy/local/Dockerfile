FROM node:22-bookworm-slim AS frontend
WORKDIR /build
COPY frontend/package*.json ./
RUN npm ci --legacy-peer-deps
COPY frontend/ ./
ENV VITE_BASE_URL=/api VITE_ImageURL=/storage/
RUN npm run build

FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libmagickwand-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql bcmath intl zip gd mbstring opcache \
    && pecl install imagick-3.8.0 && docker-php-ext-enable imagick \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/backend
COPY backend/ ./
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /build/dist/ /var/www/html/
COPY deploy/local/index.php /var/www/html/index.php
COPY deploy/local/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/local/entrypoint.sh /usr/local/bin/local-lab-entrypoint
RUN chmod +x /usr/local/bin/local-lab-entrypoint \
    && ln -s /var/www/backend/storage/app/public /var/www/html/storage \
    && printf 'expose_php=Off\nupload_max_filesize=20M\npost_max_size=25M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/lab.ini
ENTRYPOINT ["local-lab-entrypoint"]
CMD ["apache2-foreground"]
