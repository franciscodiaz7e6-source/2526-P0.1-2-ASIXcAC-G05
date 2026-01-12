# NGINX - Arquitectura, comparativa y analisis de roles
---

## TABLA DE CONTENIDOS

1. [Anatomía de Nginx](#1-anatomía-de-nginx)
2. [Análisis Comparativo: Nginx vs Apache](#2-análisis-comparativo-nginx-vs-apache)
3. [Nginx en Roles Específicos](#3-nginx-en-roles-específicos)
4. [Conclusión Técnica para Extagram](#4-conclusión-técnica-para-extagram)

---

## 1. Anatomía de NGINX

### 1.1 Descripción General

**Nginx** es un servidor web de código abierto de alta rendimiento, distribuido bajo licencia BSD 2-Clause. Desarrollado por Igor Sysoev en 2002 y lanzado públicamente en 2004, fue concebido específicamente para resolver el problema de **alta concurrencia** que enfrentaban otros servidores web tradicionales.

### 1.2 Modelo Arquitectónico: Event-Driven & Asynchronous

#### Componentes Arquitectónicos de Nginx

**Modelo Maestro-Trabajador (Master-Worker)**
1. **Master** nunca procesa solicitudes → confiable, no afectado por picos
2. **Workers** son ligeros, independientes, reemplazables
3. **Hot reload**: Cambios en config se aplican sin reiniciar (CERO downtime)
4. **Escalabilidad**: Número de workers ≈ cores de CPU (configuración óptima)

---

### 1.3 Características de Alto Rendimiento

#### 1.3.1 Bajo Consumo de Memoria

| Servidor | Memoria por Conexión | 1000 Conexiones | 10,000 Conexiones |
|---|---|---|---|
| **Apache (Prefork)** | 10-20 MB | 10-20 GB | No viable |
| **Apache (Event MPM)** | 1-2 MB | 1-2 GB | 10-20 GB |
| **Nginx** | 100-500 KB | 100-500 MB | 1-5 GB |

**Razón**: 
- Nginx: 1 worker de 5-10 MB + conexiones (~100 KB cada una)
- Apache: Nuevo proceso/thread por conexión (~5 MB cada uno)

---

### 1.4 Arquitectura Modular

Nginx utiliza un sistema de **módulos** tanto compilados como de terceros:

**Módulos Principales (Compilados)**:
- **HTTP Module**: Servidor web, reverse proxy, caching
- **Stream Module**: Balanceo de carga L3/L4 (TCP/UDP)
- **Mail Module**: Proxy para SMTP, POP3, IMAP
- **Core Module**: Configuración y gestión de procesos

**Módulos de Terceros Populares**:
- Lua: Scripting dinámico dentro de Nginx
- RTMP: Streaming de video en vivo
- WebDAV: Protocolo de escritura remota
- GeoIP: Geolocalización basada en IP

**Ventaja**: Compilación modular permite kernel ligero + características bajo demanda

---

## 2. ANÁLISIS COMPARATIVO: NGINX VS APACHE

### 2.1 Tabla Comparativa

| Aspecto | NGINX | Apache |
|---|---|---|
| **Arquitectura Core** | Event-driven, async, non-blocking | MPM-based (prefork, worker, event) |
| **Procesos** | N workers (generalmente = CPU cores) | 1 proceso/thread por conexión (configurable) |
| **Memoria por conexión** | 100-500 KB | 1-20 MB (depende de MPM) |
| **Escalabilidad Concurrencia** | Excelente (10K-40K+ conexiones) | Buena con Event MPM (2K-5K típico) |
| **Contenido Estático** | Extremadamente rápido | Rápido pero consume más recursos |
| **Contenido Dinámico** | PHP-FPM, Node.js, Python (externo) | Módulos nativos (mod_php) o externo |
| **Configuración .htaccess** | No soportado | Soportado (por directorio) |
| **Recarga Configuración** | Hot reload (zero downtime) | Requiere restart o graceful restart |
| **SSL/TLS Termination** | Nativo, excelente | Nativo, similar a Nginx |
| **Reverse Proxy** | Excelente, altamente optimizado | Posible con mod_proxy, menos eficiente |
| **Load Balancing** | Nativo, múltiples estrategias | Necesita módulos adicionales |
| **Rate Limiting** | Integrado | Disponible via módulos |
| **Caché** | Integrado y eficiente | Disponible via módulos |
| **VirtualHosts / Server Blocks** | Server blocks (más eficientes) | VirtualHosts (parseados por solicitud) |
| **Seguridad** | Configuración centralizada (menos superficies) | Más superficies de ataque (múltiples módulos) |
| **Comunidad Activa** | Muy activa, moderna | Muy activa, más conservadora |
| **Documentación** | Excelente (oficial y comunitaria) | Excelente (muy extensa) |

---

### 2.2 Ventajas de NGINX

- **Rendimiento en Contenido Estático:** Nginx no crea nuevos procesos, reutiliza workers eficientemente.

- **Reverse Proxy y Load Balancing Integrados**

```nginx
# Nginx: Nativo, 5 líneas
upstream backend {
    server app1:3000;
    server app2:3000;
}
server {
    proxy_pass http://backend;
}

# Apache: Necesita mod_proxy + mod_proxy_balancer
<Proxy balancer://backend>
    BalancerMember http://app1:3000
    BalancerMember http://app2:3000
</Proxy>
```

**Ventaja**: Nginx reverse proxy es más rápido y consume menos CPU.

- **Terminación SSL/TLS Eficiente:** El handshake SSL es operación CPU-intensiva. Nginx lo maneja mejor:
    - **Nginx**: Múltiples conexiones SSL en paralelo, con bajo overhead
    - **Apache**: Cada proceso tiene su propio overhead SSL

Benchmark típico:
- Nginx: ~3000 handshakes SSL/s en 1 core
- Apache: ~1500 handshakes SSL/s en 1 core

### 2.3 Desventajas/Limitaciones de NGINX

- **Sin soporte para .htaccess:** `.htaccess` permite configuración por directorio en Apache:

```apache
# .htaccess en /var/www/uploads/ → solo aplica a ese directorio
<IfModule mod_php5.c>
    php_flag upload_tmp_dir /tmp
    php_value max_upload_size 100M
</IfModule>
```

**Equivalente en Nginx** (centralizado en nginx.conf):
```nginx
location /uploads/ {
    client_max_body_size 100M;
}
```

**Implicación**: 
- Apache: Más flexible pero menos performante (parsea .htaccess por solicitud)
- Nginx: Centralizado (más eficiente, pero menos granular)

- **Módulos Dinámicos Limitados:** 
    Apache permite cargar/descargar módulos en runtime:
        ```apache
        LoadModule auth_module modules/mod_auth.so
        ```

    Nginx requiere recompilación:
        ```bash
        ./configure --with-http_gzip_static_module
        make
        make install
        ```
    **Sin embargo**: La mayoría de módulos Nginx están compilados por defecto.

- **Menos Módulos de Terceros:**

    Apache ecosistema es más antiguo y extenso:
    - ~100+ módulos maduros documentados
    - Nginx tiene ~50+ módulos conocidos (pero creciendo)

    **Mitigación**: Los módulos más comunes en Nginx están compilados en el core.

- **Contenido Dinámico Requiere Procesador Externo:**

    Apache puede servir PHP nativamente:
    ```apache
    LoadModule php_module modules/libphp.so
    ```

    Nginx requiere:
    ```nginx
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;  # PHP-FPM separado
    }
    ```

---

### 2.4 Comparativa de Uso Real: Proyecto Extagram

Para nuestro proyecto Extagram (7 servidores, 2 de app balanceados):

| Métrica | NGINX | Apache | Ganador |
|---|---|---|---|
| Proxy S1 → S2/S3 | Excelente (6-10 ms latencia) | Bueno pero +overhead | **NGINX** |
| Servir imágenes S5 | Muy eficiente (50-100 MB/s) | Eficiente (30-50 MB/s) | **NGINX** |
| Consumo RAM total (7 servidores) | ~70-100 MB | ~200-300 MB | **NGINX** |
| Configuración centralizada | Limpio, único punto | Distribuida en .htaccess | **NGINX** |
| Cambios sin downtime | Soportado | Graceful pero no true hot-reload | **NGINX** |
| Facilidad aprendizaje | Moderada | Más accesible | **Apache** |

**Conclusión para Extagram**: **NGINX es la opción clara** por rendimiento y flexibilidad en arquitectura moderna.

---

## 3. NGINX en roles específicos

### 3.1 NGINX como Web Server (Servidor Web)

#### 3.1.1 Función Principal

Un servidor web recibe solicitudes HTTP y responde con contenido. Nginx destaca en:

**A. Servicio de Contenido Estático**

```nginx
server {
    listen 80;
    server_name extagram.itb;
    
    # Raíz del sitio
    root /var/www/html/extagram;
    
    # Índices por defecto
    index index.html index.htm;
    
    # Ubicación para archivos estáticos
    location / {
        try_files $uri $uri/ =404;
    }
}
```

**Características que Nginx maneja eficientemente**:

1. **Sendfile Kernel Bypass** (Zero-Copy):
```nginx
server {
    sendfile on;  # Usa kernel sendfile() en lugar de copiar en userspace
    tcp_nopush on;  # Agrupa múltiples respuestas en 1 TCP packet
}
```

**Ventaja**: Reducir copia de datos de kernel → aplicación → red
- Mejora throughput de ~30-50% en archivos grandes
- Reduce CPU dramáticamente

2. **Compresión On-the-fly**:
```nginx
gzip on;
gzip_types text/plain text/css text/xml application/json;
gzip_min_length 1024;  # Solo comprimir si > 1 KB
```

3. **Caché de Archivos Abiertos**:
```nginx
open_file_cache max=1000 inactive=20s;
open_file_cache_valid 30s;
```

---

**B. Manejo de Virtual Hosts / Server Blocks**

```nginx
# Servidor 1: extagram.itb
server {
    listen 80;
    server_name extagram.itb;
    root /var/www/extagram;
    # ... configuración
}

# Servidor 2: api.extagram.itb
server {
    listen 80;
    server_name api.extagram.itb;
    root /var/www/api;
    # ... configuración
}

# Servidor 3: admin.extagram.itb
server {
    listen 80;
    server_name admin.extagram.itb;
    root /var/www/admin;
    # ... configuración
}
```

**Ventaja sobre Apache**:
- Nginx interpola cada server block solo **UNA VEZ** en startup
- Apache interpola VirtualHosts en **CADA** solicitud (incluso si no aplica)

**Performance**: En 1000s/sec, Nginx ahorra CPU significativo.

---

**C. Manejo de Índices y Negociación de Contenido**

```nginx
location / {
    # Índices: si solicitan /carpeta/, busca índice
    try_files $uri $uri/ /index.php?$query_string;
    
    # Ejemplo: /extagram/ → busca /extagram/index.html
    #                   → si no existe, llama /index.php
}

location ~ \.(jpg|jpeg|png|gif|ico)$ {
    # Imágenes: servir directamente, sin PHP
    expires 1d;  # Cacheable 1 día en navegador
    add_header Cache-Control "public, immutable";
}
```

---

#### 3.1.2 Configuración Práctica para Extagram

```nginx
server {
    listen 80 default_server;
    server_name extagram.itb;
    
    root /var/www/html;
    index index.php index.html;
    
    # Servir archivos estáticos directamente
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg)$ {
        expires 30d;  # Cacheable en navegador
        add_header Cache-Control "public, immutable";
        access_log off;  # No registrar en logs
    }
    
    # Carpeta uploads: servir imágenes
    location /uploads/ {
        alias /var/www/uploads/;  # Montaje virtual
        expires 7d;
    }
    
    # Solicitudes a .php → PHP-FPM
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;  # PHP-FPM en puerto 9000
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Bloquear acceso a archivos sensibles
    location ~ /\. {
        deny all;  # Bloquear .htaccess, .git, .env, etc.
    }
}
```

**Resultado**:
- Solicitudes a `.jpg`/`.png` → servidas por Nginx (ultra-rápido)
- Solicitudes a `.php` → pasadas a PHP-FPM (procesadas)
- Seguridad: archivos ocultos bloqueados

---

## 4. Conclusión

### 4.1 ¿Por Qué Nginx?

Basado en el análisis anterior, **Nginx es la elección óptima para Extagram** por las siguientes razones técnicas:

| Criterio | Impacto | Justificación |
|---|---|---|
| **Proxy Inverso Eficiente** | CRÍTICO | S1 debe balancear S2 ↔ S3 con baja latencia. Nginx lo hace de forma nativa. |
| **Bajo Consumo de Recursos** | ALTO | AWS t3.micro: Nginx cabe cómodamente, Apache sería lento. |
| **Hot Reload** | MEDIO | Cambios en balanceo/SSL sin downtime (importante en producción). |
| **Escalabilidad Futura** | ALTO | Si crece tráfico: fácil agregar más backends sin reconfiguración compleja. |
| **Caching Integrado** | MEDIO | Reducir cargas a PHP-FPM: imágenes, respuestas comunes cacheadas. |
| **SSL Termination** | ALTO | Centralizar criptografía en Nginx → backends más rápidos. |
| **Seguridad** | MEDIO-ALTO | Ocultación de backends, rate limiting integrado. |

---