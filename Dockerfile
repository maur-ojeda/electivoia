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

# Production environment
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Dummy DATABASE_URL so Symfony kernel can boot during cache:clear (overridden at runtime)
ENV DATABASE_URL="postgresql://localhost:5432/dummy"

# Create var directories and set permissions
RUN mkdir -p var/cache var/log \
    && chmod -R 775 var/

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
