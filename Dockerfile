# Stage 1: Composer dependencies
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Stage 2: Node build (needs vendor/symfony/stimulus-bundle for webpack alias)
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --prefer-offline
COPY . .
COPY --from=composer /app/vendor ./vendor
RUN npm run build

# Stage 3: PHP application
FROM dunglas/frankenphp:php8.3-alpine

# Install PHP extensions required by the project
RUN install-php-extensions pdo_pgsql intl gd zip opcache pcntl

# Set working directory
WORKDIR /app

# Copy Composer vendor from stage 1
COPY --from=composer /app/vendor ./vendor

# Copy application source
COPY . .

# Copy compiled frontend assets from Node stage
COPY --from=assets /app/public/build ./public/build

# Copy Caddyfile for FrankenPHP
COPY Caddyfile /etc/frankenphp/Caddyfile

# Production environment
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Dummy DATABASE_URL so Symfony kernel can boot during cache:clear (overridden at runtime)
ENV DATABASE_URL="postgresql://localhost:5432/dummy"

# Create var directories and set permissions
RUN mkdir -p var/cache var/log \
    && chmod -R 775 var/

# Install importmap vendor assets (required by EasyAdmin AssetMapper)
RUN php bin/console importmap:install 2>/dev/null || true

# Warm up the Symfony cache (production)
RUN php bin/console cache:warmup --env=prod --no-debug 2>/dev/null || true

# FrankenPHP / Caddy configuration
# Trust Coolify's internal reverse proxy (Docker networks)
ENV TRUSTED_PROXIES="127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16"
ENV TRUSTED_HOSTS=".*"

# Expose HTTP
EXPOSE 80
EXPOSE 443

# Healthcheck against the built-in Caddy server
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD wget -qO- http://localhost/ || exit 1
