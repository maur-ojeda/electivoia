#!/bin/bash

# Script de deployment para Digital Ocean
# Uso: ./deploy.sh

set -e

echo "🚀 Iniciando deployment de Electivoia..."

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Pull del código más reciente
echo -e "${YELLOW}📥 Descargando código más reciente...${NC}"
git pull origin main

# 2. Instalar dependencias de Composer
echo -e "${YELLOW}📦 Instalando dependencias de Composer...${NC}"
composer install --no-dev --optimize-autoloader

# 3. Limpiar y calentar caché
echo -e "${YELLOW}🗑️  Limpiando caché...${NC}"
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod

# 4. Ejecutar migraciones de base de datos
echo -e "${YELLOW}🗄️  Ejecutando migraciones de base de datos...${NC}"
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Instalar assets
echo -e "${YELLOW}🎨 Instalando assets...${NC}"
php bin/console assets:install --env=prod

# 6. Ajustar permisos
echo -e "${YELLOW}🔐 Ajustando permisos...${NC}"
chmod -R 775 var/
chown -R www-data:www-data var/

echo -e "${GREEN}✅ Deployment completado exitosamente!${NC}"
