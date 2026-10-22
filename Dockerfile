# ── Stage 1: JS/CSS assets ────────────────────────────────────────────────
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Stage 2: PHP app ──────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS app

# System deps
RUN apk add --no-cache \
    nginx supervisor curl unzip git \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    sqlite-dev icu-dev oniguruma-dev

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    pdo pdo_sqlite opcache gd intl mbstring pcntl bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app source first (needed for post-autoload-dump scripts)
COPY . .

# Create directories that are gitignored but required at build time
RUN mkdir -p bootstrap/cache \
             storage/app/public \
             storage/logs \
             storage/framework/sessions \
             storage/framework/views \
             storage/framework/cache/data

# Install PHP deps (production, no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy built assets from stage 1
COPY --from=assets /app/public/build ./public/build

# ── Config files ──────────────────────────────────────────────────────────
COPY docker/nginx.conf      /etc/nginx/nginx.conf
COPY docker/php-fpm.conf    /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh   /entrypoint.sh
RUN chmod +x /entrypoint.sh

# SQLite database lives on a persistent volume at /data
RUN mkdir -p /data && \
    chown -R www-data:www-data /data /var/www/html/storage /var/www/html/bootstrap/cache

# PHP config for production
RUN printf "opcache.enable=1\nopcache.memory_consumption=128\npost_max_size=20M\nupload_max_filesize=20M\n" \
    > /usr/local/etc/php/conf.d/custom.ini

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
