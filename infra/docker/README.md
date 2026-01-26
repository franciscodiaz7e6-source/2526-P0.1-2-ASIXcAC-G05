# Extagram Docker - Guía de Uso

## Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Requisitos Previos](#requisitos-previos)
4. [Estructura de Directorios](#estructura-de-directorios)
5. [Configuración Inicial](#configuración-inicial)
6. [Cómo Funciona](#cómo-funciona)
7. [Cómo Utilizar](#cómo-utilizar)
8. [Servicios Disponibles](#servicios-disponibles)
9. [Variables de Entorno](#variables-de-entorno)
10. [Comandos Útiles](#comandos-útiles)
11. [Solución de Problemas](#solución-de-problemas)

---

## Descripción General

Extagram es una aplicación web de red social minimalista desplegada en Docker. Permite a los usuarios publicar mensajes y fotos que se almacenan en una base de datos MySQL, con servicio de caché y balanceo de carga mediante Nginx.

La arquitectura está diseñada para producción con:
- Redundancia de aplicación (2 instancias PHP-FPM)
- Balanceo de carga automático
- Almacenamiento persistente de datos
- Separación de código e infraestructura

---

## Arquitectura del Sistema

```
Internet (Puerto 80)
    ↓
┌───────────────────┐
│ NGINX PROXY       │ (S1)
│ - Reverse proxy   │
│ - Load balancer   │
└────────┬──────────┘
         │
    ┌────┴────────────────────────┐
    ↓                             ↓
┌─────────────┐           ┌─────────────┐
│ PHP-FPM 1   │           │ PHP-FPM 2   │ (S2, S3)
│ :9000       │           │ :9000       │ Redundancia
└─────┬───────┘           └──────┬──────┘
      │                          │
      └──────────────┬───────────┘
                     ↓
          ┌──────────────────┐
          │ UPLOAD SERVICE   │ (S4)
          │ Recibe fotos     │
          │ :9000            │
          └────────┬─────────┘
                   │
      ┌────────────┼────────────┐
      ↓            ↓            ↓
   MySQL      Uploads     Storage Service
   (S7)       (Volumen)    (S5)
   :3306      Persistente  :80
```

### Servicios

| Servicio | Propósito | Container | Puertos |
|----------|-----------|-----------|---------|
| NGINX Proxy | Entrada pública, balanceo | extagram-nginx | 80 |
| PHP-FPM 1 | Aplicación (instancia 1) | extagram-php-1 | 9000 |
| PHP-FPM 2 | Aplicación (instancia 2) | extagram-php-2 | 9000 |
| Upload Service | Recibe y guarda fotos | extagram-upload | 9000 |
| Storage Service | Sirve archivos estáticos | extagram-storage | 80 |
| MySQL | Base de datos | extagram-mysql | 3306 |

---

## Requisitos Previos

- Docker (versión 20.10+)
- Docker Compose (versión 1.29+)
- 2GB RAM mínimo
- 5GB espacio en disco

### Verificar instalación

```bash
docker --version
docker-compose --version
```

---

## Estructura de Directorios

```
2526-P0.1-2-ASIXcAC-G05/
│
├── infra/
│   ├── docker/
│   │   ├── services/
│   │   │   ├── nginx/
│   │   │   │   └── Dockerfile
│   │   │   └── php-app/
│   │   │       └── Dockerfile
│   │   ├── volumes/
│   │   │   └── uploads/          (Fotos subidas)
│   │   ├── docker-compose.yml
│   │   └── .env
│   │
│   └── database/
│       └── init.sql              (Inicialización BD)
│
└── web/
    ├── nginx/
    │   ├── default.conf
    │   └── nginx.conf
    ├── php-app/
    │   ├── extagram.php
    │   └── upload.php
    └── static/
        ├── style.css
        └── preview.svg
```

---

## Configuración Inicial

### 1. Clonar/Descargar el proyecto

```bash
cd 2526-P0.1-2-ASIXcAC-G05/infra/docker
```

### 2. Crear archivo .env

```bash
# Crear desde plantilla
cp .env.example .env

# O crear manualmente
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
```

### 3. Crear directorio de volúmenes

```bash
mkdir -p volumes/uploads
chmod 775 volumes/uploads
```

### 4. Verificar archivos en ../web/

```bash
ls -la ../../../web/php-app/
ls -la ../../../web/nginx/
ls -la ../../../web/static/
ls -la ../../../database/
```

---

## Cómo Funciona

### Flujo de Peticiones

1. **Usuario accede a http://192.168.244.1/**
   - Nginx Proxy recibe en puerto 80
   - Resuelve a `../web/php-app/extagram.php`

2. **Nginx ejecuta PHP**
   - Nginx envía petición a upstream (php-app-1:9000 o php-app-2:9000)
   - PHP-FPM ejecuta `extagram.php`
   - Se conecta a MySQL (hostname: `mysql`)

3. **PHP consulta MySQL**
   - Obtiene posts de tabla `posts`
   - Renderiza HTML con posts

4. **Usuario publica un post**
   - Formulario POST a `/upload.php`
   - Nginx envía a upload-service:9000
   - Upload Service:
     - Guarda foto en `./volumes/uploads/`
     - Inserta registro en MySQL

5. **Archivos estáticos**
   - CSS, imágenes: storage-service sirve desde `/static` y `/uploads`

### Persistencia

- **BD MySQL**: Volumen named `mysql-volume` (no se pierde con `down`)
- **Fotos**: `./volumes/uploads/` (accesible desde host)

### Redundancia

- 2 instancias PHP-FPM balanceadas por Nginx
- Si php-app-1 falla, Nginx automáticamente usa php-app-2
- MySQL es punto único (para HA adicional, replicación externa)

---

## Cómo Utilizar

### Iniciar los servicios

```bash
cd infra/docker

# Construir imágenes y iniciar contenedores
docker-compose up -d --build

# Ver estado
docker-compose ps

# Verificar logs
docker-compose logs -f
```

### Acceder a la aplicación

```
http://192.168.244.1/
```

### Detener servicios

```bash
# Parar sin eliminar datos
docker-compose stop

# Parar y eliminar contenedores (datos persisten)
docker-compose down

# Parar y eliminar todo incluyendo volúmenes
docker-compose down -v
```

### Reiniciar un servicio específico

```bash
# Reiniciar PHP-FPM 1
docker-compose restart php-app-1

# Reiniciar MySQL
docker-compose restart mysql
```

---

## Servicios Disponibles

### NGINX Proxy (S1)

**Rol**: Entrada pública, balanceo de carga

**Características**:
- Escucha en puerto 80
- Balancea peticiones entre php-app-1 y php-app-2
- Sirve archivos estáticos desde `/static`

**Ubicación código**: `../../../web/nginx/`

**Acceso a logs**:
```bash
docker-compose logs nginx-proxy
```

---

### PHP-FPM App 1 & 2 (S2, S3)

**Rol**: Ejecutar aplicación PHP

**Características**:
- 2 instancias para redundancia
- Mismo código (../../../web/php-app/)
- Ambas conectan a MySQL

**Variables de entorno**:
```
DB_HOST=mysql
DB_USER=extagram_admin
DB_PASS=pass123
DB_NAME=extagram_db
```

**Acceso a logs**:
```bash
docker-compose logs php-app-1
docker-compose logs php-app-2
```

---

### Upload Service (S4)

**Rol**: Recibir y guardar fotos

**Características**:
- Ejecuta `upload.php`
- Permisos WRITE en `/uploads`
- Único servicio que guarda fotos

**Ubicación fotos**: `./volumes/uploads/`

**Acceso a logs**:
```bash
docker-compose logs upload-service
```

---

### Storage Service (S5)

**Rol**: Servir archivos estáticos

**Características**:
- Nginx optimizado para archivos
- Sirve CSS, SVG, fotos
- Sin lógica PHP

**Acceso a logs**:
```bash
docker-compose logs storage-service
```

---

### MySQL (S7)

**Rol**: Persistencia de datos

**Base de datos**: `extagram_db`

**Usuario**: `extagram_admin`

**Acceso desde host**:
```bash
mysql -h 127.0.0.1 -u extagram_admin -p pass123 extagram_db
```

**Dentro del contenedor**:
```bash
docker exec -it extagram-mysql mysql -u extagram_admin -p pass123 extagram_db
```

**Ver posts**:
```bash
docker exec -it extagram-mysql mysql -u extagram_admin -p pass123 extagram_db \
  -e "SELECT id, post, photourl, created_at FROM posts ORDER BY id DESC LIMIT 5;"
```

---

## Variables de Entorno

### Archivo .env

```env
# MYSQL CONFIGURATION
MYSQL_ROOT_PASSWORD=rootpass123
MYSQL_DATABASE=extagram_db
MYSQL_USER=extagram_admin
MYSQL_PASSWORD=pass123

# DATABASE CONNECTION (Para PHP)
DB_HOST=mysql
DB_NAME=extagram_db
DB_USER=extagram_admin
DB_PASS=pass123

# CONTAINER NAMES
NGINX_CONTAINER=extagram-nginx
PHP_APP_1_CONTAINER=extagram-php-1
PHP_APP_2_CONTAINER=extagram-php-2
UPLOAD_CONTAINER=extagram-upload
STORAGE_CONTAINER=extagram-storage
MYSQL_CONTAINER=extagram-mysql

# NETWORK
NETWORK_NAME=extagram-net

# PORTS
NGINX_PORT=80
```

### Cambiar variables

```bash
# Editar .env
nano .env

# Reconstruir con nuevas variables
docker-compose down
docker-compose up -d --build
```

---

## Comandos Útiles

### Ver estado general

```bash
# Estado de contenedores
docker-compose ps

# Información detallada
docker-compose ps --format "table {{.Service}}\t{{.Status}}\t{{.Ports}}"
```

### Logs y debug

```bash
# Ver todos los logs
docker-compose logs

# Logs de un servicio específico
docker-compose logs nginx-proxy

# Seguir logs en tiempo real
docker-compose logs -f php-app-1

# Últimas 50 líneas
docker-compose logs --tail 50 mysql
```

### Acceso a contenedores

```bash
# Terminal interactiva en PHP
docker exec -it extagram-php-1 bash

# Ejecutar comandos en PHP
docker exec extagram-php-1 php -v

# Terminal MySQL
docker exec -it extagram-mysql bash
```

### Volúmenes y datos

```bash
# Ver volúmenes
docker volume ls

# Ver contenido de uploads
ls -la volumes/uploads/

# Limpiar volúmenes (CUIDADO: Elimina datos)
docker volume prune
```

### Reconstruir

```bash
# Reconstruir sin caché
docker-compose build --no-cache

# Reconstruir un servicio
docker-compose build php-app-1

# Reconstruir y reiniciar
docker-compose up -d --build
```

---

## Solución de Problemas

### Error: "Port 80 already in use"

```bash
# Ver qué usa puerto 80
sudo lsof -i :80

# Cambiar puerto en .env
NGINX_PORT=8080

# Reconstruir
docker-compose down
docker-compose up -d --build
```

### Error: "Cannot connect to MySQL"

```bash
# Verificar MySQL está UP
docker-compose ps mysql

# Ver logs MySQL
docker-compose logs mysql

# Esperar a que MySQL inicie (toma 10-15 segundos)
sleep 15
docker-compose restart php-app-1
```

### Error: "Permission denied" en uploads

```bash
# Dar permisos
chmod 755 volumes/uploads

# Dentro del contenedor
docker exec extagram-upload chmod 755 /uploads
```

### Archivos PHP no se actualizan

```bash
# Verificar volumen está montado
docker exec extagram-php-1 ls -la /app

# Reiniciar PHP
docker-compose restart php-app-1 php-app-2 upload-service
```

### MySQL no inicializa (sin tabla posts)

```bash
# Verificar script init.sql existe
cat ../database/init.sql

# Ejecutar manualmente
docker exec -it extagram-mysql mysql -u extagram_admin -p pass123 extagram_db < ../database/init.sql
```

### Ver estadísticas de recursos

```bash
# CPU, memoria, red
docker stats

# De un contenedor
docker stats extagram-nginx
```

### Limpiar todo y empezar de cero

```bash
# Parar y eliminar contenedores
docker-compose down

# Eliminar volúmenes (CUIDADO: Pierdes datos)
docker volume rm extagram_mysql-volume

# Recrear
docker-compose up -d --build
```

---

## Checklist de Configuración

- [ ] Docker y Docker Compose instalados
- [ ] .env creado en `infra/docker/`
- [ ] Directorio `volumes/uploads/` existe y tiene permisos 755
- [ ] Archivos en `web/nginx/`, `web/php-app/`, `web/static/` existen
- [ ] `infra/database/init.sql` existe
- [ ] `docker-compose.yml` con variables .env correctas
- [ ] Puerto 80 disponible (o cambiar en .env)
- [ ] 2GB RAM disponible
- [ ] 5GB espacio en disco

---

## Referencias

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [PHP-FPM Documentation](https://www.php.net/manual/en/install.fpm.php)

---

**Última actualización**: 26 de Enero de 2026
**Versión**: 1.0
**Estado**: Producción