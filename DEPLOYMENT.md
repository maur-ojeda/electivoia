# 🚀 Guía de Deployment - Electivoia en Digital Ocean

## 📋 Pre-requisitos

- Droplet de Digital Ocean (1 GB RAM / 1 CPU)
- Ubuntu 22.04 LTS
- Acceso SSH al servidor
- Dominio configurado (opcional)

---

## 🔧 PARTE 1: Configuración Inicial del Servidor

### 1.1. Conectarse al Droplet

```bash
ssh root@TU_IP_DEL_DROPLET
```

### 1.2. Actualizar el sistema

```bash
apt update && apt upgrade -y
```

### 1.3. Instalar dependencias necesarias

```bash
# PHP 8.3 y extensiones
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-mysql \
    php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml \
    php8.3-bcmath php8.3-pgsql php8.3-intl

# PostgreSQL
apt install -y postgresql postgresql-contrib

# Nginx
apt install -y nginx

# Git
apt install -y git

# Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Node.js y npm (si es necesario)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

---

## 🗄️ PARTE 2: Configurar PostgreSQL

### 2.1. Crear usuario y base de datos

```bash
sudo -u postgres psql
```

Dentro de PostgreSQL:

```sql
CREATE USER electivoia_user WITH PASSWORD 'TU_PASSWORD_SEGURO';
CREATE DATABASE electivoia_db OWNER electivoia_user;
GRANT ALL PRIVILEGES ON DATABASE electivoia_db TO electivoia_user;
\q
```

### 2.2. Configurar PostgreSQL para conexiones locales

```bash
nano /etc/postgresql/16/main/pg_hba.conf
```

Asegurarse de que tenga esta línea:
```
local   all             all                                     md5
```

Reiniciar PostgreSQL:
```bash
systemctl restart postgresql
```

---

## 📦 PARTE 3: Clonar y Configurar la Aplicación

### 3.1. Crear directorio para la aplicación

```bash
mkdir -p /var/www
cd /var/www
```

### 3.2. Clonar el repositorio

```bash
git clone https://github.com/maur-ojeda/electivoia.git
cd electivoia
```

### 3.3. Configurar variables de entorno

```bash
cp .env.production .env.local
nano .env.local
```

Editar con tus valores:
- `APP_SECRET`: Generar con `php -r "echo bin2hex(random_bytes(16));"`
- `DATABASE_URL`: Usar el password de PostgreSQL que creaste
- Guardar y salir (Ctrl+X, Y, Enter)

### 3.4. Instalar dependencias

```bash
composer install --no-dev --optimize-autoloader
```

### 3.5. Ejecutar migraciones y cargar fixtures

```bash
# Crear el schema de la base de datos
php bin/console doctrine:schema:create

# Cargar datos iniciales
php bin/console doctrine:fixtures:load --no-interaction

# O si prefieres migraciones:
# php bin/console doctrine:migrations:migrate --no-interaction
```

### 3.6. Configurar permisos

```bash
chown -R www-data:www-data /var/www/electivoia
chmod -R 775 /var/www/electivoia/var
```

---

## 🌐 PARTE 4: Configurar Nginx

### 4.1. Crear configuración de Nginx

```bash
nano /etc/nginx/sites-available/electivoia
```

Pegar esta configuración:

```nginx
server {
    listen 80;
    server_name TU_DOMINIO_O_IP;
    root /var/www/electivoia/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/electivoia_error.log;
    access_log /var/log/nginx/electivoia_access.log;
}
```

### 4.2. Activar el sitio

```bash
ln -s /etc/nginx/sites-available/electivoia /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default  # Eliminar sitio por defecto
nginx -t  # Verificar configuración
systemctl restart nginx
```

---

## ⚙️ PARTE 5: Optimizar PHP-FPM para 1 GB RAM

### 5.1. Editar configuración de PHP-FPM

```bash
nano /etc/php/8.3/fpm/pool.d/www.conf
```

Buscar y modificar estas líneas:

```ini
pm = dynamic
pm.max_children = 3
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 2
pm.max_requests = 500
```

### 5.2. Reiniciar PHP-FPM

```bash
systemctl restart php8.3-fpm
```

---

## 🔐 PARTE 6: Configurar Firewall (UFW)

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
ufw status
```

---

## 🎯 PARTE 7: Deployment y Actualizaciones Futuras

### 7.1. Para futuras actualizaciones, usar el script de deployment:

```bash
cd /var/www/electivoia
./deploy.sh
```

### 7.2. O manualmente:

```bash
cd /var/www/electivoia
git pull origin main
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction
chown -R www-data:www-data var/
```

---

## ✅ PARTE 8: Verificación

### 8.1. Verificar que todo funciona:

1. Abrir navegador: `http://TU_IP_O_DOMINIO`
2. Deberías ver la página de inicio
3. Ir a `/admin` y login con:
   - Usuario: `13473632-1`
   - Password: `134736`

### 8.2. Verificar logs si hay problemas:

```bash
# Logs de Nginx
tail -f /var/log/nginx/electivoia_error.log

# Logs de Symfony
tail -f /var/www/electivoia/var/log/prod.log

# Logs de PHP-FPM
tail -f /var/log/php8.3-fpm.log
```

---

## 🔒 PARTE 9: Seguridad Adicional (Recomendado)

### 9.1. Configurar SSL con Let's Encrypt (HTTPS)

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d tu-dominio.com
```

### 9.2. Crear usuario no-root para SSH

```bash
adduser deploy
usermod -aG sudo deploy
# Luego usar este usuario en lugar de root
```

---

## 📊 PARTE 10: Monitoreo

### 10.1. Instalar herramientas de monitoreo

```bash
apt install -y htop
```

### 10.2. Monitorear uso de recursos

```bash
htop  # Ver uso de CPU y RAM en tiempo real
df -h  # Ver uso de disco
free -h  # Ver uso de memoria
```

---

## 🆘 Troubleshooting Común

### Problema: Error 500

```bash
# Ver logs
tail -f /var/www/electivoia/var/log/prod.log

# Limpiar caché
php bin/console cache:clear --env=prod
```

### Problema: Permisos

```bash
chown -R www-data:www-data /var/www/electivoia
chmod -R 775 /var/www/electivoia/var
```

### Problema: Base de datos no conecta

```bash
# Verificar que PostgreSQL esté corriendo
systemctl status postgresql

# Verificar conexión
psql -U electivoia_user -d electivoia_db -h localhost
```

---

## 📝 Notas Importantes

1. **Backups**: Configurar backups automáticos en Digital Ocean (cuesta ~20% extra)
2. **Monitoreo**: Revisar uso de memoria semanalmente con `htop`
3. **Actualizaciones**: Ejecutar `apt update && apt upgrade` mensualmente
4. **Logs**: Rotar logs para no llenar el disco

---

## 🎉 ¡Listo!

Tu aplicación debería estar corriendo en producción. Si tienes problemas, revisa los logs y la sección de troubleshooting.
