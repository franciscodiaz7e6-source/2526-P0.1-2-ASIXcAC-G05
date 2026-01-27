# Manual de Creacion de Extagram con Docker - Estructura Manual

Version 1.0 - Enero 2026

---

## Indice

1. [Marco Teorico](#marco-teorico)
2. [Estructura de Directorios](#estructura-de-directorios)
3. [Creacion de Archivos de Configuracion](#creacion-de-archivos-de-configuracion)
4. [Creacion de Aplicacion PHP](#creacion-de-aplicacion-php)
5. [Creacion de Archivos Estaticos](#creacion-de-archivos-estaticos)
6. [Configuracion de Base de Datos](#configuracion-de-base-de-datos)
7. [Iniciando los Servicios](#iniciando-los-servicios)
8. [Verificacion y Testing](#verificacion-y-testing)

---

# MARCO TEORICO

## 1.1 Que es Docker Compose

Docker Compose es una herramienta que permite definir y ejecutar aplicaciones multi-contenedor. En lugar de ejecutar cada contenedor manualmente, defines todos los servicios en un archivo YAML (docker-compose.yml).

### Conceptos Clave

**Servicio**: Contenedor que ejecuta una aplicacion o componente (Nginx, PHP, MySQL, etc.)

**Volumen**: Almacenamiento persistente que permite compartir datos entre contenedor y host

**Red**: Conexion interna entre contenedores para que se comuniquen entre si

**Imagen**: Plantilla base del contenedor (nginx:alpine, php:8.2-fpm-alpine, mysql:8.0)

**Dockerfile**: Archivo que define como construir una imagen personalizada

---

# ESTRUCTURA DE DIRECTORIOS

## 2.1 Directorio Base

Crea la siguiente estructura en tu equipo:

```
extagram/
├── docker/                        
│   ├── docker-compose.yml         
│   ├── .env                       
│   ├── .env.example               
│   ├── nginx/                     
│   │   ├── Dockerfile
│   │   └── default.conf
│   ├── php/                       
│   │   └── Dockerfile
│   └── volumes/
│       ├── uploads/
│       └── mysql-data/
│
├── src/                            
│   ├── web/
│   │   ├── php/                   
│   │   │   ├── extagram.php       
│   │   │   └── upload.php         
│   │   ├── static/                
│   │   │   ├── css/
│   │   │   │   └── style.css
│   │   │   ├── js/
│   │   │   │   └── app.js
│   │   │   └── images/
│   │   │       └── logo.svg
│   │
│   └── database/
│       └── init.sql               
│
└── README.md
```

## 2.2 Crear Directorios

Ejecuta estos comandos en terminal:

```bash
# Crear estructura completa
mkdir -p extagram/docker/nginx
mkdir -p extagram/docker/php
mkdir -p extagram/docker/volumes/uploads
mkdir -p extagram/docker/volumes/mysql-data
mkdir -p extagram/src/web/php
mkdir -p extagram/src/web/static/css
mkdir -p extagram/src/web/static/js
mkdir -p extagram/src/web/static/images
mkdir -p extagram/src/database

# Navegar al directorio
cd extagram
```

---

# CREACION DE ARCHIVOS DE CONFIGURACION

## 3.1 Archivo: docker-compose.yml

**Ubicacion**: extagram/docker/docker-compose.yml

**Descripcion**: Orquesta todos los servicios (Nginx, PHP, MySQL, Storage)

Crea el archivo y copia el contenido:

```yaml
version: '3.9'

services:
  # ============================================
  # NGINX - Reverse Proxy & Load Balancer
  # ============================================
  nginx:
    build:
      context: ./nginx
      dockerfile: Dockerfile
    container_name: extagram-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      # Configuracion
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      # Codigo PHP
      - ../src/web/php:/app:ro
      # Archivos estaticos
      - ../src/web/static:/static:ro
      # Uploads (para servir fotos)
      - ./volumes/uploads:/uploads:ro
    depends_on:
      - php-app-1
      - php-app-2
      - php-upload
      - storage
    networks:
      - extagram-net
    healthcheck:
      test: ["CMD", "wget", "--quiet", "--tries=1", "--spider", "http://localhost/"]
      interval: 10s
      timeout: 5s
      retries: 3

  # ============================================
  # PHP-FPM - Instancia 1 (Lectura)
  # ============================================
  php-app-1:
    build:
      context: ./php
      dockerfile: Dockerfile
    container_name: extagram-php-1
    restart: unless-stopped
    environment:
      DB_HOST: mysql
      DB_NAME: extagram_db
      DB_USER: extagram_user
      DB_PASS: secure_password_123
    volumes:
      - ../src/web/php:/app:ro
    depends_on:
      - mysql
    networks:
      - extagram-net

  # ============================================
  # PHP-FPM - Instancia 2 (Lectura)
  # ============================================
  php-app-2:
    build:
      context: ./php
      dockerfile: Dockerfile
    container_name: extagram-php-2
    restart: unless-stopped
    environment:
      DB_HOST: mysql
      DB_NAME: extagram_db
      DB_USER: extagram_user
      DB_PASS: secure_password_123
    volumes:
      - ../src/web/php:/app:ro
    depends_on:
      - mysql
    networks:
      - extagram-net

  # ============================================
  # PHP-FPM - Upload (Escritura en /uploads)
  # ============================================
  php-upload:
    build:
      context: ./php
      dockerfile: Dockerfile
    container_name: extagram-php-upload
    restart: unless-stopped
    environment:
      DB_HOST: mysql
      DB_NAME: extagram_db
      DB_USER: extagram_user
      DB_PASS: secure_password_123
    volumes:
      - ../src/web/php:/app:ro
      - ./volumes/uploads:/uploads:rw
    depends_on:
      - mysql
    networks:
      - extagram-net

  # ============================================
  # NGINX - Storage (Sirve archivos estaticos)
  # ============================================
  storage:
    image: nginx:alpine
    container_name: extagram-storage
    restart: unless-stopped
    volumes:
      - ../src/web/static:/usr/share/nginx/html:ro
      - ./volumes/uploads:/usr/share/nginx/html/uploads:ro
    networks:
      - extagram-net

  # ============================================
  # MySQL 8.0 - Base de Datos
  # ============================================
  mysql:
    image: mysql:8.0
    container_name: extagram-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: root_secure_123
      MYSQL_DATABASE: extagram_db
      MYSQL_USER: extagram_user
      MYSQL_PASSWORD: secure_password_123
    volumes:
      - mysql-data:/var/lib/mysql
      - ../src/database/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    networks:
      - extagram-net
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 3

# ============================================
# VOLUMENES - Persistencia
# ============================================
volumes:
  mysql-data:
    driver: local

# ============================================
# RED INTERNA
# ============================================
networks:
  extagram-net:
    driver: bridge
```

## 3.2 Archivo: .env.example

**Ubicacion**: extagram/docker/.env.example

**Descripcion**: Variables de entorno (plantilla para copiar a .env)

Crea el archivo y copia el contenido:

```bash
# Copiar este archivo a .env y cambiar valores
# cp .env.example .env
# NO COMMITEAR .env (solo .env.example)

# ============================================
# MYSQL - Base de Datos
# ============================================
MYSQL_ROOT_PASSWORD=root_secure_123
MYSQL_DATABASE=extagram_db
MYSQL_USER=extagram_user
MYSQL_PASSWORD=secure_password_123

# ============================================
# NGINX - Servidor Web
# ============================================
NGINX_PORT=80

# ============================================
# PHP - Configuracion
# ============================================
PHP_MEMORY_LIMIT=256M
PHP_MAX_UPLOAD_SIZE=100M
PHP_MAX_EXECUTION_TIME=30

# ============================================
# Conexion a Base de Datos
# ============================================
DB_HOST=mysql
DB_NAME=extagram_db
DB_USER=extagram_user
DB_PASS=secure_password_123
DB_PORT=3306
```

## 3.3 Archivo: nginx/Dockerfile

**Ubicacion**: extagram/docker/nginx/Dockerfile

**Descripcion**: Define la imagen de Nginx personalizada

Crea el archivo y copia el contenido:

```dockerfile
FROM nginx:1.25-alpine

# Copiar configuracion personalizada
COPY default.conf /etc/nginx/conf.d/default.conf

# Verificar sintaxis de configuracion
RUN nginx -t

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
```

## 3.4 Archivo: nginx/default.conf

**Ubicacion**: extagram/docker/nginx/default.conf

**Descripcion**: Configuracion de Nginx (routing, load balancing, cache)

Crea el archivo y copia el contenido:

```nginx
# Upstream - Load Balancing entre PHP-FPM
upstream php_backend {
    server php-app-1:9000;
    server php-app-2:9000;
}

upstream php_upload {
    server php-upload:9000;
}

server {
    listen 80;
    server_name _;
    
    root /app;
    index extagram.php;
    
    # Limite de tamaño POST (fotos)
    client_max_body_size 100M;
    
    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log warn;

    # ===== RUTA PRINCIPAL (lectura) =====
    location / {
        try_files $uri $uri/ =404;
        
        location ~ \.php$ {
            fastcgi_pass php_backend;
            fastcgi_index extagram.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_param HTTP_PROXY "";
        }
    }

    # ===== RUTA UPLOAD (escritura) =====
    location ~ ^/upload\.php$ {
        fastcgi_pass php_upload;
        fastcgi_index upload.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param HTTP_PROXY "";
    }

    # ===== ARCHIVOS ESTATICOS (cache 30 dias) =====
    location ~* \.(css|js|png|jpg|jpeg|gif|svg|ico)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # ===== DENEGAR ACCESO A ARCHIVOS OCULTOS =====
    location ~ /\. {
        return 403;
    }

    # ===== ERROR 404 =====
    error_page 404 =404 /404.html;
}
```

## 3.5 Archivo: php/Dockerfile

**Ubicacion**: extagram/docker/php/Dockerfile

**Descripcion**: Define la imagen de PHP-FPM con extensiones

Crea el archivo y copia el contenido:

```dockerfile
FROM php:8.2-fpm-alpine

# ===== INSTALAR DEPENDENCIAS DEL SISTEMA =====
RUN apk add --no-cache \
    mysql-client \
    git \
    curl \
    bash

# ===== INSTALAR EXTENSIONES PHP =====
RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql

# ===== CONFIGURACION PHP =====
RUN echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/docker.ini && \
    echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/docker.ini && \
    echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/docker.ini && \
    echo "max_execution_time = 30" >> /usr/local/etc/php/conf.d/docker.ini

WORKDIR /app

EXPOSE 9000

CMD ["php-fpm"]
```

---

# CREACION DE APLICACION PHP

## 4.1 Archivo: src/web/php/extagram.php

**Ubicacion**: extagram/src/web/php/extagram.php

**Descripcion**: Aplicacion principal. Muestra todos los posts publicados

Crea el archivo y copia el contenido:

```php
<?php
/**
 * Extagram - Aplicacion Principal
 * Muestra los posts de la base de datos
 * Formulario para crear nuevos posts
 */

// ===== VARIABLES DE CONEXION (desde Docker env) =====
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');

// ===== CONEXION A MYSQL =====
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Error de conexion: " . $e->getMessage());
}

// ===== OBTENER POSTS =====
try {
    $stmt = $pdo->query("
        SELECT id, post, photourl, created_at 
        FROM posts 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    $posts = [];
    $error = "Error al obtener posts: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extagram - Red Social</title>
    <link rel="stylesheet" href="/static/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Extagram</h1>
            <p>Comparte tus momentos</p>
        </header>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="posts">
            <?php if (empty($posts)): ?>
                <p class="no-posts">No hay posts aun. Se el primero en compartir!</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post">
                        <div class="post-header">
                            <small><?php echo date('d/m/Y H:i', strtotime($post['created_at'])); ?></small>
                        </div>
                        
                        <?php if (!empty($post['photourl'])): ?>
                            <img src="/uploads/<?php echo htmlspecialchars($post['photourl']); ?>" 
                                 alt="Foto del post"
                                 class="post-image">
                        <?php endif; ?>
                        
                        <p class="post-content"><?php echo htmlspecialchars($post['post']); ?></p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <section class="upload-section">
            <h2>Nuevo Post</h2>
            <form action="/upload.php" method="POST" enctype="multipart/form-data">
                <textarea name="post" placeholder="Que estas pensando?" required></textarea>
                <input type="file" name="photo" accept="image/*">
                <button type="submit">Publicar</button>
            </form>
        </section>
    </div>

    <script src="/static/js/app.js"></script>
</body>
</html>
```

## 4.2 Archivo: src/web/php/upload.php

**Ubicacion**: extagram/src/web/php/upload.php

**Descripcion**: Maneja la carga de fotos y creacion de nuevos posts

Crea el archivo y copia el contenido:

```php
<?php
/**
 * Extagram - Manejo de Uploads
 * Recibe fotos, las valida y las guarda en /uploads
 * Luego inserta el post en la base de datos
 */

// ===== VARIABLES DE CONEXION =====
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'extagram_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

// ===== VALIDACION DEL METODO HTTP =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Metodo no permitido');
}

// ===== OBTENER DATOS DEL FORMULARIO =====
$post_text = $_POST['post'] ?? '';
$photo_file = $_FILES['photo'] ?? null;

// ===== VALIDACION DEL CONTENIDO =====
if (empty($post_text)) {
    die('El post no puede estar vacio');
}

// ===== PROCESAR ARCHIVO (opcional) =====
$photourl = null;
if ($photo_file && $photo_file['error'] === UPLOAD_ERR_OK) {
    // Tipos permitidos
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 100 * 1024 * 1024;
    
    // Validar tipo de archivo
    if (!in_array($photo_file['type'], $allowed_types)) {
        die('Tipo de archivo no permitido. Usa: JPG, PNG, GIF, WEBP');
    }
    
    // Validar tamaño
    if ($photo_file['size'] > $max_size) {
        die('Archivo demasiado grande. Maximo 100MB');
    }
    
    // Generar nombre unico
    $ext = pathinfo($photo_file['name'], PATHINFO_EXTENSION);
    $photourl = uniqid('photo_') . '.' . strtolower($ext);
    
    // Crear directorio si no existe
    $upload_dir = '/uploads';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Guardar archivo
    if (!move_uploaded_file($photo_file['tmp_name'], "$upload_dir/$photourl")) {
        die('Error al guardar la foto');
    }
}

// ===== INSERTAR EN BASE DE DATOS =====
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Preparar consulta
    $stmt = $pdo->prepare("
        INSERT INTO posts (post, photourl, created_at) 
        VALUES (?, ?, NOW())
    ");
    
    // Ejecutar con sanitizacion
    $stmt->execute([$post_text, $photourl]);
    
    // Redirigir a pagina principal
    header('Location: /');
    exit;
    
} catch (PDOException $e) {
    die('Error al guardar en BD: ' . $e->getMessage());
}
?>
```

---

# CREACION DE ARCHIVOS ESTATICOS

## 5.1 Archivo: src/web/static/css/style.css

**Ubicacion**: extagram/src/web/static/css/style.css

**Descripcion**: Estilos CSS. Diseno responsivo e intuitivo

Crea el archivo y copia el contenido:

```css
/* ===== VARIABLES DE COLOR ===== */
:root {
    --primary: #e1306c;
    --secondary: #405de6;
    --light: #f8f9fa;
    --dark: #1a1a1a;
    --border: #dbdbdb;
    --text: #262626;
}

/* ===== RESET CSS ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--light);
    color: var(--text);
    line-height: 1.6;
}

/* ===== HEADER ===== */
header {
    background: white;
    border-bottom: 1px solid var(--border);
    padding: 1.5rem 0;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

header h1 {
    font-size: 2rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
}

header p {
    color: #999;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

/* ===== CONTENEDOR PRINCIPAL ===== */
.container {
    max-width: 600px;
    margin: 2rem auto;
    padding: 0 1rem;
}

/* ===== SECCION DE POSTS ===== */
.posts {
    margin-bottom: 2rem;
}

.post {
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.3s ease;
}

.post:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.post-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border);
}

.post-header small {
    color: #999;
    font-size: 0.85rem;
}

.post-image {
    width: 100%;
    height: 400px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.post-content {
    font-size: 1rem;
    line-height: 1.5;
    word-wrap: break-word;
}

.no-posts {
    text-align: center;
    color: #999;
    padding: 2rem;
    font-size: 1.1rem;
}

/* ===== SECCION UPLOAD ===== */
.upload-section {
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.upload-section h2 {
    margin-bottom: 1rem;
    font-size: 1.3rem;
}

.upload-section form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ===== TEXTAREA ===== */
textarea {
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-family: inherit;
    font-size: 1rem;
    resize: vertical;
    min-height: 100px;
    transition: border-color 0.3s ease;
}

textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(225, 48, 108, 0.1);
}

/* ===== FILE INPUT ===== */
input[type="file"] {
    padding: 0.5rem;
    border: 2px dashed var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: border-color 0.3s ease;
}

input[type="file"]:hover {
    border-color: var(--primary);
}

/* ===== BOTON ENVIAR ===== */
button {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(225, 48, 108, 0.3);
}

button:active {
    transform: translateY(0);
}

/* ===== ALERTAS ===== */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    text-align: center;
}

.alert-error {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
}

/* ===== RESPONSIVO ===== */
@media (max-width: 600px) {
    .container {
        padding: 0 0.5rem;
    }

    header h1 {
        font-size: 1.5rem;
    }

    .post-image {
        height: 250px;
    }
}
```

## 5.2 Archivo: src/web/static/js/app.js

**Ubicacion**: extagram/src/web/static/js/app.js

**Descripcion**: JavaScript. Validacion de formularios y eventos

Crea el archivo y copia el contenido:

```javascript
/**
 * Extagram - JavaScript Frontend
 * Validacion de formularios y eventos de usuario
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('Extagram cargado');

    // ===== VALIDACION DE FORMULARIO =====
    const form = document.querySelector('.upload-section form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const textarea = form.querySelector('textarea');
            if (!textarea.value.trim()) {
                e.preventDefault();
                alert('Por favor, escribe algo antes de publicar');
                textarea.focus();
            }
        });
    }

    // ===== PREVIEWSUALIZACION DE IMAGEN =====
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                // Validar tipo
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Solo se permiten imagenes');
                    e.target.value = '';
                    return;
                }
                
                // Validar tamaño
                const maxSize = 100 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('Archivo demasiado grande (maximo 100MB)');
                    e.target.value = '';
                    return;
                }

                console.log('Imagen: ' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + 'MB)');
            }
        });
    }
});
```

---

# CONFIGURACION DE BASE DE DATOS

## 6.1 Archivo: src/database/init.sql

**Ubicacion**: extagram/src/database/init.sql

**Descripcion**: Script SQL. Se ejecuta automaticamente cuando MySQL inicia por primera vez

Crea el archivo y copia el contenido:

```sql
-- ===== Crear Base de Datos =====
CREATE DATABASE IF NOT EXISTS extagram_db 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE extagram_db;

-- ===== Tabla de Posts =====
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post TEXT NOT NULL,
    photourl VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci;

-- ===== Insertar Datos Iniciales =====
INSERT INTO posts (post, photourl) VALUES 
('Bienvenido a Extagram! Esta es una red social de prueba.', NULL),
('Mi primer post en esta plataforma. Emocionado!', NULL),
('Compartiendo momentos especiales con ustedes.', NULL);

-- ===== Crear Usuario de Aplicacion =====
CREATE USER IF NOT EXISTS 'extagram_user'@'%' 
    IDENTIFIED BY 'secure_password_123';

GRANT ALL PRIVILEGES ON extagram_db.* 
    TO 'extagram_user'@'%';

FLUSH PRIVILEGES;
```

---

# INICIANDO LOS SERVICIOS

## 7.1 Preparacion Previa

Antes de iniciar los servicios, ejecuta estos pasos:

### Paso 1: Verificar Estructura

```bash
# Desde extagram/docker/
cd extagram/docker

# Listar archivos (debe ver todos los archivos creados)
ls -la
ls nginx/
ls php/
ls ../../src/web/php/
ls ../../src/database/
```

### Paso 2: Crear Archivo .env

```bash
# Copiar plantilla a .env real
cp .env.example .env

# Opcional: editar valores (por defecto funcionan)
# nano .env
```

### Paso 3: Crear Directorio de Uploads

```bash
# Crear directorio si no existe
mkdir -p volumes/uploads

# Asignar permisos
chmod 755 volumes/uploads
```

## 7.2 Construir Imagenes Docker

En terminal, dentro de extagram/docker/:

```bash
# Construir todas las imagenes
docker-compose build

# Esperado: Ver mensaje de construccion exitosa
# [+] Building 45.2s (12/12) FINISHED
```

## 7.3 Iniciar Servicios

```bash
# Iniciar en background
docker-compose up -d

# Esperado: Ver lista de servicios iniciados
# Creating extagram-mysql ... done
# Creating extagram-php-1 ... done
# ...
```

---

# VERIFICACION Y TESTING

## 8.1 Verificar Estado de Servicios

```bash
# Ver estado de todos los contenedores
docker-compose ps

# Esperado:
# NAME               STATUS      PORTS
# extagram-nginx     Up 5s       0.0.0.0:80->80/tcp
# extagram-php-1     Up 5s       9000/tcp
# extagram-php-2     Up 5s       9000/tcp
# extagram-php-upload Up 5s      9000/tcp
# extagram-storage   Up 5s       80/tcp
# extagram-mysql     Up 10s      3306/tcp
```

## 8.2 Verificar Conectividad

### Test HTTP

```bash
# Acceder a la aplicacion
curl http://localhost/

# Esperado: HTML de extagram.php con posts iniciales
```

### Test MySQL

```bash
# Conectar a MySQL
docker exec extagram-mysql mysql \
  -u extagram_user -p secure_password_123 \
  extagram_db -e "SELECT * FROM posts;"

# Esperado: Ver 3 posts iniciales
```

### Test Archivos PHP

```bash
# Verificar que archivos PHP estan montados
docker exec extagram-php-1 ls -la /app/

# Esperado:
# extagram.php
# upload.php
```

## 8.3 Test de Publicacion

### A traves del Navegador

1. Abrir http://localhost/ en navegador
2. Ver titulo "Extagram" y posts iniciales
3. En seccion "Nuevo Post":
   - Escribir texto en textarea
   - (Opcional) Seleccionar una imagen
   - Click en "Publicar"
4. Esperar redireccion a pagina principal
5. Ver nuevo post en la lista

### A traves de CURL

```bash
# Publicar post sin imagen
curl -X POST http://localhost/upload.php \
  -d "post=Hola desde cURL!"

# Esperado: Redireccion (HTTP 302)
# Nuevo post debe aparecer en http://localhost/
```

## 8.4 Ver Logs

Para ver que pasa en los servicios:

```bash
# Logs de todos los servicios
docker-compose logs -f

# Logs solo de Nginx
docker-compose logs -f nginx

# Logs solo de PHP
docker-compose logs -f php-app-1

# Logs solo de MySQL
docker-compose logs -f mysql

# Ver ultimas 50 lineas
docker-compose logs --tail 50
```

## 8.5 Comando: Detener Servicios

```bash
# Detener todos los servicios (sin perder datos)
docker-compose down

# Detener y eliminar volumenes (CUIDADO: pierde datos)
docker-compose down -v

# Detener solo un servicio
docker-compose stop nginx
```

## 8.6 Comando: Acceder a Contenedores

```bash
# Terminal interactiva en PHP
docker exec -it extagram-php-1 sh

# Terminal interactiva en Nginx
docker exec -it extagram-nginx sh

# Terminal interactiva en MySQL
docker exec -it extagram-mysql bash
```