# MANUAL DE CREACION Y CONFIGURACION DE ENTORNO EXTAGRAM (DOCKER)

## Índice

1. [Dependencias](#1-dependencias)
3. [Instalación](#3-instalación)
4. [Configuración](#4-configuración)
5. [Procedimientos de Mantenimiento](#5-procedimientos-de-mantenimiento)
6. [Monitoreo y Logs](#6-monitoreo-y-logs)
7. [Recuperación ante Fallos](#7-recuperación-ante-fallos)
8. [Performance](#8-performance)
9. [Seguridad](#9-seguridad)
10. [Escalabilidad](#10-escalabilidad)

---

## 1. Dependencias

### 1.1 Requisitos del Sistema

| Componente | Versión Mínima | Recomendado |
|-----------|-----------------|-------------|
| Docker | 20.10 | 20.10+ |
| Docker Compose | 1.29 | 2.0+ |
| RAM | 2GB | 4GB |
| Espacio disco | 5GB | 10GB |
| CPU | 2 cores | 4 cores |

### 1.2 Imágenes Docker

```yaml
nginx-proxy:
  Base: nginx:alpine
  Tamaño: ~40MB
  Propósito: Reverse proxy, balanceo carga

php-app-1, php-app-2, upload-service:
  Base: php:8.2-fpm-alpine
  Extensiones: mysqli, pdo, pdo_mysql
  Tamaño: ~120MB
  Propósito: Ejecución código PHP

storage-service:
  Base: nginx:alpine
  Tamaño: ~40MB
  Propósito: Servir archivos estáticos

mysql:
  Base: mysql:8.0
  Tamaño: ~500MB (primer init)
  Propósito: Base de datos relacional
```

### 1.3 Dependencias de Aplicación (DOCKERFILES)

```
PHP 8.2
├── mysqli (conexión MySQL)
├── pdo (abstracción datos)
└── pdo_mysql (driver PDO MySQL)

MySQL 8.0
├── Charset: utf8mb4
├── Collation: utf8mb4_unicode_ci
└── InnoDB (motor transaccional)

Nginx 1.25
├── mod_http_upstream (balanceo)
├── mod_http_proxy (proxificación)
└── mod_http_fastcgi (PHP-FPM)
```

---

## 2. Instalación

### 2.1 Crear estructura inicial

```bash
# Crear directorios volúmenes
mkdir -p volumes/uploads
chmod 755 volumes/uploads

# Crear archivo .env
cat > .env << 'EOF'
MYSQL_ROOT_PASSWORD=rootpass123
MYSQL_DATABASE=extagram_db
MYSQL_USER=extagram_admin
MYSQL_PASSWORD=pass123

DB_HOST=mysql
DB_NAME=extagram_db
DB_USER=extagram_admin
DB_PASS=pass123

NGINX_CONTAINER=extagram-nginx
PHP_APP_1_CONTAINER=extagram-php-1
PHP_APP_2_CONTAINER=extagram-php-2
UPLOAD_CONTAINER=extagram-upload
STORAGE_CONTAINER=extagram-storage
MYSQL_CONTAINER=extagram-mysql

NETWORK_NAME=extagram-net
NGINX_PORT=80
EOF

# Verificar estructura
tree -L 3
```

### 2.2 Construir imágenes

```bash
# Construir todas las imágenes
docker-compose build

# Salida esperada:
# [+] Building 45.2s (12/12) FINISHED
# => => naming to docker.io/library/extagram-nginx:latest
# => => naming to docker.io/library/extagram-php-app-1:latest
# ...
```

### 2.3 Iniciar servicios

```bash
# Iniciar en background
docker-compose up -d

# Verificar estado
docker-compose ps

# Esperado:
# NAME               STATUS      PORTS
# extagram-nginx     Up 5s       0.0.0.0:80->80/tcp
# extagram-php-1     Up 5s       9000/tcp
# extagram-php-2     Up 5s       9000/tcp
# extagram-upload    Up 5s       9000/tcp
# extagram-storage   Up 5s       80/tcp
# extagram-mysql     Up 10s      3306/tcp, 33060/tcp
```

### 2.4 Validar instalación

```bash
# Test conectividad HTTP
curl -i http://localhost/

# Esperado: HTTP/1.1 200 OK
# Con contenido HTML de extagram.php

# Test conectividad MySQL
docker exec extagram-mysql mysql -u extagram_admin -p pass123 \
  extagram_db -e "SHOW TABLES;"

# Esperado: posts

# Test volumen uploads
ls -la volumes/uploads/
# Esperado: directorio existente, permissions 755
```

---

## 3. Configuración

### 3.1 Variables de Entorno

**Archivo**: `.env` en `infra/docker/`

```env
# CONFIGURACIÓN MYSQL
MYSQL_ROOT_PASSWORD=rootpass123
# Root password (CAMBIAR EN PRODUCCIÓN)

MYSQL_DATABASE=extagram_db
MYSQL_USER=extagram_admin
MYSQL_PASSWORD=pass123
# Usuario BD y password (CAMBIAR EN PRODUCCIÓN)

# CONFIGURACIÓN CONEXIÓN PHP
DB_HOST=mysql
# Hostname interno (NO CAMBIAR)

DB_NAME=extagram_db
DB_USER=extagram_admin
DB_PASS=pass123
# Debe coincidir con MYSQL_*

# NOMBRES CONTENEDORES
NGINX_CONTAINER=extagram-nginx
PHP_APP_1_CONTAINER=extagram-php-1
PHP_APP_2_CONTAINER=extagram-php-2
UPLOAD_CONTAINER=extagram-upload
STORAGE_CONTAINER=extagram-storage
MYSQL_CONTAINER=extagram-mysql

# NETWORK
NETWORK_NAME=extagram-net

# PUERTOS
NGINX_PORT=80
# Puerto expuesto en host (cambiar si 80 está ocupado)
```

### 3.2 Configuración Nginx

**Archivo**: `web/nginx/default.conf`

```nginx
# Upstream (balanceo de carga)
upstream php_backend {
    server php-app-1:9000;
    server php-app-2:9000;
    # Round-robin automático entre ambos
}

upstream upload_backend {
    server upload-service:9000;
}

server {
    listen 80;
    server_name _;
    root /app;
    index extagram.php;
    client_max_body_size 100M;
    # Límite de tamaño POST (fotos)

    # Ruta principal
    location / {
        try_files $uri $uri/ =404;
        
        location ~ \.php$ {
            fastcgi_pass php_backend;
            fastcgi_index extagram.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }

    # Ruta upload específica
    location /upload.php {
        fastcgi_pass upload_backend;
        fastcgi_index upload.php;
        fastcgi_param SCRIPT_FILENAME /app/upload.php;
        include fastcgi_params;
    }

    # Caché estáticos
    location ~* \.(css|js|png|svg|jpg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Servir errores 404
    error_page 404 =404 /404.html;
}
```

### 3.3 Configuración PHP-FPM

**Dockerfile**: `infra/docker/services/php-app/Dockerfile`

```dockerfile
FROM php:8.2-fpm-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    mysql-client \
    grep \
    bash

# Instalar extensiones PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /app

# Copiar código (opcional si no usa volumen)
COPY extagram.php /app/
COPY upload.php /app/

EXPOSE 9000

CMD ["php-fpm"]
```

### 3.4 Configuración MySQL

**Archivo**: `infra/database/init.sql`

```sql
CREATE DATABASE IF NOT EXISTS extagram_db;
USE extagram_db;

CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post TEXT NOT NULL,
    photourl VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos iniciales
INSERT INTO posts (post, photourl) VALUES 
('Bienvenido a Extagram!', ''),
('Primer post', '');
```

### 3.5 Volúmenes

```yaml
# docker-compose.yml
volumes:
  mysql-volume:
    # Volumen persistente para MySQL
    # Driver: local
    # Ubicación: /var/lib/docker/volumes/mysql-volume/_data/

# Montar en servicios:
mysql:
  volumes:
    - mysql-volume:/var/lib/mysql
    # Datos NO se pierden con docker-compose down
    # SÍ se pierden con docker volume prune
```

---

## 4. Procedimientos de Mantenimiento

### 4.1 Backup de Datos

```bash
# Backup MySQL a SQL
docker exec extagram-mysql mysqldump \
  -u extagram_admin -p pass123 \
  extagram_db > backup-$(date +%Y%m%d).sql

# Backup fotos
tar -czf uploads-backup-$(date +%Y%m%d).tar.gz \
  volumes/uploads/

# Backup volumen MySQL (snapshot)
docker run --rm \
  -v mysql-volume:/data \
  -v $(pwd):/backup \
  alpine tar -czf /backup/mysql-volume-backup.tar.gz -C /data .
```

### 4.2 Restaurar Datos

```bash
# Restaurar BD desde SQL
docker exec -i extagram-mysql mysql \
  -u extagram_admin -p pass123 \
  extagram_db < backup-20260126.sql

# Restaurar fotos
tar -xzf uploads-backup-20260126.tar.gz

# Restaurar volumen MySQL
docker volume create mysql-volume-restore
docker run --rm \
  -v mysql-volume-restore:/data \
  -v $(pwd):/backup \
  alpine tar -xzf /backup/mysql-volume-backup.tar.gz -C /data
```

### 4.3 Updates y Patches

```bash
# Actualizar imágenes base
docker-compose pull

# Reconstruir todas las imágenes
docker-compose build --no-cache

# Reiniciar servicios (con downtime)
docker-compose down
docker-compose up -d

# Actualizar sin downtime (PHP)
docker-compose restart php-app-1
sleep 30
docker-compose restart php-app-2

# Actualizar Nginx (sin downtime)
docker-compose restart nginx-proxy
# Conexiones existentes se mantienen
```

### 4.4 Limpieza de Recursos

```bash
# Ver uso de espacio
docker system df

# Limpiar imágenes dangling
docker image prune

# Limpiar volúmenes no usados
docker volume prune

# Limpiar redes no usadas
docker network prune

# Limpiar todo (CUIDADO)
docker system prune -a
```

### 4.5 Rotación de Logs

```bash
# Docker ya implementa rotación por defecto
# Verificar configuración
cat /etc/docker/daemon.json

# Configurar rotación de logs (Linux)
# /etc/docker/daemon.json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}

# Reiniciar Docker
systemctl restart docker
```

---

## 5. Monitoreo y Logs

### 5.1 Ver Logs en Tiempo Real

```bash
# Todos los servicios
docker-compose logs -f

# Servicio específico
docker-compose logs -f nginx-proxy
docker-compose logs -f mysql

# Últimas N líneas
docker-compose logs --tail 100 php-app-1

# Logs con timestamps
docker-compose logs -f --timestamps
```

### 5.2 Métricas de Recursos

```bash
# Monitor de recursos en vivo
docker stats

# De un contenedor específico
docker stats extagram-php-1

# CSV para análisis
docker stats --no-stream > stats.csv
```

### 5.3 Health Checks

```bash
# Verificar health de MySQL
docker exec extagram-mysql mysqladmin \
  -u extagram_admin -p pass123 ping

# Respuesta: mysqld is alive

# Verificar PHP-FPM
docker exec extagram-php-1 \
  php -r 'echo "PHP OK";'

# Verificar Nginx
curl -s -I http://localhost/ | head -5

# Status página Nginx (si está configurada)
curl -s http://localhost/nginx_status
```

### 5.4 Eventos y Alertas

```bash
# Ver eventos Docker
docker events --filter type=container

# Logs del daemon Docker
journalctl -u docker -n 50 -f

# Logs del sistema
tail -f /var/log/syslog | grep docker
```

---

## 6. Recuperación ante Fallos

### 6.1 Reinicio Automático

```yaml
# docker-compose.yml
services:
  nginx-proxy:
    restart: unless-stopped
    # Reinicia automáticamente si falla
    # EXCEPTO si fue parado manualmente
```

### 6.2 Recuperación de Fallos

#### MySQL no inicia

```bash
# Ver logs
docker-compose logs mysql

# Casos comunes:

# 1. Permisos en volumen
sudo chown 999:999 /var/lib/docker/volumes/mysql-volume/_data

# 2. Puerto en uso
lsof -i :3306
# Terminar proceso

# 3. Corrupción datos
# Eliminar volumen y reiniciar (CUIDADO: Pierdes datos)
docker volume rm mysql-volume
docker-compose down
docker-compose up -d
```

#### PHP-FPM no responde

```bash
# Reiniciar servicio
docker-compose restart php-app-1

# Si persiste
docker-compose down
docker-compose up -d

# Verificar memoria disponible
free -h
# Si <500MB libre, ajustar recursos en compose
```

#### Nginx no balancea correctamente

```bash
# Verificar upstream
docker exec extagram-nginx cat /etc/nginx/conf.d/default.conf | grep upstream

# Verificar conectividad upstream
docker exec extagram-nginx \
  wget -O- http://php-app-1:9000/

# Si falla, verificar que PHP está UP
docker-compose ps | grep php

# Reloadear Nginx (sin downtime)
docker exec extagram-nginx nginx -s reload
```

### 6.3 Rollback

```bash
# Si actualización causa problemas

# 1. Guardar imagen actual
docker commit extagram-mysql \
  mysql:8.0-backup-20260126

# 2. Revertir compose a versión anterior
git checkout HEAD~1 -- docker-compose.yml

# 3. Recrear servicios
docker-compose down
docker-compose up -d

# 4. Si necesario, restaurar desde backup
docker exec -i extagram-mysql mysql \
  -u extagram_admin -p pass123 \
  extagram_db < backup-previo.sql
```

---

## 7. Performance

### 7.1 Benchmarks

```bash
# AB (Apache Bench)
ab -n 1000 -c 10 http://localhost/

# Salida esperada:
# Requests per second: 500+ (en local)
# Failed requests: 0
# Time per request: 20ms

# Wrk (HTTP Load Testing)
wrk -t4 -c100 -d30s http://localhost/
```

### 7.2 Optimizaciones

#### PHP-FPM

```ini
# /usr/local/etc/php-fpm.conf (en container)
[global]
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 1000
```

#### MySQL

```sql
-- Índices en tabla posts
ANALYZE TABLE posts;
OPTIMIZE TABLE posts;

-- Ver queries lentas
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Ver plan de query
EXPLAIN SELECT * FROM posts ORDER BY created_at DESC LIMIT 50;
```

#### Nginx

```nginx
# /etc/nginx/nginx.conf (en container)
worker_processes auto;
worker_connections 2048;
keepalive_timeout 65;
gzip on;
gzip_min_length 1000;
gzip_types text/plain text/css application/json;
```

### 7.3 Monitoreo de Performance

```bash
# Latencia promedio
docker exec extagram-mysql mysql \
  -u extagram_admin -p pass123 \
  -e "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, NOW())) 
      FROM posts;"

# Conexiones activas
docker exec extagram-mysql mysql \
  -u extagram_admin -p pass123 \
  -e "SHOW PROCESSLIST;"

# Tamaño BD
docker exec extagram-mysql mysql \
  -u extagram_admin -p pass123 \
  -e "SELECT table_schema, 
           ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
      FROM information_schema.tables 
      GROUP BY table_schema;"
```

---

## 8. Seguridad

### 8.1 Hardening Contenedores

```yaml
# docker-compose.yml
services:
  mysql:
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE
    read_only: true
    # Filesystem solo lectura excepto volúmenes

  php-app-1:
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    read_only: true
```

### 8.2 Credenciales

**NUNCA en repositorio**:
```bash
# .gitignore
.env
.env.local
.env.*.local
```

**Usar variables**:
```bash
# Production .env
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
MYSQL_PASSWORD=$(openssl rand -base64 32)
DB_PASS=$MYSQL_PASSWORD
```

### 8.3 Validación de Entrada

```php
// web/php-app/upload.php
<?php
// Validar tipo archivo
$allowed = ['jpg', 'jpeg', 'png', 'gif'];
$ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
if (!in_array(strtolower($ext), $allowed)) {
    die('Formato no permitido');
}

// Validar tamaño
if ($_FILES['photo']['size'] > 10 * 1024 * 1024) {
    die('Archivo demasiado grande');
}

// Sanitizar input
$post = htmlspecialchars($_POST["post"], ENT_QUOTES);
?>
```

### 8.4 SQL Injection Prevention

```php
// Usar prepared statements
$stmt = $db->prepare("INSERT INTO posts (post, photourl) VALUES (?, ?)");
$stmt->bind_param("ss", $post, $photourl);
$stmt->execute();

// NO usar concatenación
// $query = "INSERT INTO posts VALUES ('" . $_POST['post'] . "')";
```

### 8.5 Control de Acceso

```nginx
# Restringir acceso a directorios
location ~ /\. {
    return 403;
}

location ~ \.php$ {
    # Solo ejecutar PHP en /app
    fastcgi_pass php_backend;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
}
```

---

## 9. Escalabilidad

### 9.1 Escalabilidad Horizontal (Más instancias)

```yaml
# Agregar más PHP-FPM
services:
  php-app-3:
    build:
      context: ./services/php-app
      dockerfile: Dockerfile
    container_name: extagram-php-3
    environment:
      - DB_HOST=mysql
      - DB_USER=extagram_admin
      - DB_PASS=pass123
      - DB_NAME=extagram_db
    volumes:
      - ../../../web/php-app:/app:ro
    depends_on:
      - mysql
    networks:
      - extagram-net
    restart: unless-stopped

# Actualizar upstream Nginx
upstream php_backend {
    server php-app-1:9000;
    server php-app-2:9000;
    server php-app-3:9000;
}
```

### 9.2 Escalabilidad Vertical (Más recursos)

```yaml
# docker-compose.yml
services:
  mysql:
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '1'
          memory: 1G

  php-app-1:
    deploy:
      resources:
        limits:
          cpus: '1'
          memory: 512M
```

### 9.3 Clustering MySQL (Alta Disponibilidad)

```yaml
# Replicación Master-Slave (requiere infra adicional)
services:
  mysql-master:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_REPLICATION_MODE: master
  
  mysql-slave:
    image: mysql:8.0
    environment:
      MYSQL_REPLICATION_MODE: slave
      MYSQL_REPLICATION_USER: replicator
      MYSQL_REPLICATION_PASSWORD: replpass
    depends_on:
      - mysql-master
```

### 9.4 Caché Distribuido

```bash
# Agregar Redis para caché
docker run -d -p 6379:6379 redis:7-alpine

# En PHP
$redis = new Redis();
$redis->connect('redis', 6379);
$posts = $redis->get('posts:list');
if (!$posts) {
    $posts = $db->query("SELECT * FROM posts");
    $redis->setex('posts:list', 3600, json_encode($posts));
}
```

### 9.5 Load Balancing Externo

```nginx
# Balanceador externo (otro Nginx)
upstream extagram-cluster {
    server 192.168.244.1:80 weight=1;
    server 192.168.244.2:80 weight=1;
    server 192.168.244.3:80 weight=1;
}

server {
    listen 8080;
    location / {
        proxy_pass http://extagram-cluster;
    }
}
```

---

## Troubleshooting

### A. Troubleshooting Rápido

| Problema | Comando | Solución |
|----------|---------|----------|
| Contenedor no inicia | `docker-compose logs SERVICE` | Ver error exacto |
| Conexión MySQL rechazada | `docker-compose ps mysql` | Verificar que MySQL está UP |
| Permiso denegado en uploads | `chmod 755 volumes/uploads` | Ajustar permisos |
| Puerto en uso | `lsof -i :80` | Cambiar NGINX_PORT en .env |
| Slow queries | `docker-compose logs mysql` | Analizar EXPLAIN |

### B. Archivos de Configuración

- `infra/docker/docker-compose.yml` - Orquestación
- `infra/docker/.env` - Variables entorno
- `infra/docker/services/nginx/Dockerfile` - Imagen Nginx
- `infra/docker/services/php-app/Dockerfile` - Imagen PHP
- `infra/database/init.sql` - Inicialización BD
- `web/nginx/default.conf` - Configuración Nginx
- `web/nginx/nginx.conf` - Configuración global Nginx
- `web/php-app/extagram.php` - Aplicación principal
- `web/php-app/upload.php` - Manejo de uploads

### C. Referencias Técnicas

- [Docker Compose Spec](https://compose-spec.io/)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [MySQL 8.0 Reference](https://dev.mysql.com/doc/refman/8.0/en/)
- [PHP-FPM Manual](https://www.php.net/manual/en/install.fpm.php)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)

---

**Documento técnico - Uso exclusivo de administradores**