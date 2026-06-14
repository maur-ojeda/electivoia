FROM dunglas/frankenphp:php8.3-alpine

# Install PHP extensions required by the project
RUN install-php-extensions pdo_pgsql intl gd zip opcache pcntl

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy dependency manifests first for layer caching
COPY composer.json composer.lock ./

# Install production dependencies (no dev, optimized autoloader)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

# Copy application source
COPY . .

# Create var directories BEFORE post-install scripts (cache:clear needs them)
RUN mkdir -p var/cache var/log \
    && chmod -R 775 var/

# Run composer post-install scripts (cache:clear, assets:install)
RUN composer run-script post-install-cmd --no-interaction

# Production environment
ENV APP_ENV=prod
ENV APP_DEBUG=0

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
