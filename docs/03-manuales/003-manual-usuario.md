# Manual de Usuario - Extagram
## Red Social Basada en Microservicios con Docker

---

## Índice

1. [Introducción](#1-introducción)
2. [Requisitos del Sistema](#2-requisitos-del-sistema)
3. [Acceso a la Aplicación](#3-acceso-a-la-aplicación)
4. [Guía de Uso](#4-guía-de-uso)
   - 4.1 [Página Principal](#41-página-principal)
   - 4.2 [Ver Publicaciones](#42-ver-publicaciones)
   - 4.3 [Crear Publicación](#43-crear-publicación)
   - 4.4 [Subir Imágenes](#44-subir-imágenes)
5. [Características de la Aplicación](#5-características-de-la-aplicación)
6. [Errores Comunes y Soluciones](#6-errores-comunes-y-soluciones)
7. [Preguntas Frecuentes (FAQ)](#7-preguntas-frecuentes-faq)
8. [Mejores Prácticas](#8-mejores-prácticas)
9. [Soporte Técnico](#9-soporte-técnico)

---

## 1. Introducción

### ¿Qué es Extagram?

**Extagram** es una red social ligera y moderna que permite a los usuarios compartir momentos mediante publicaciones de texto e imágenes. Está construida con una arquitectura de microservicios utilizando Docker, lo que garantiza alta disponibilidad y escalabilidad.

### Características Principales

-  **Publicaciones con texto e imágenes**
-  **Interfaz intuitiva tipo Instagram**
-  **Carga rápida de contenido**
-  **Diseño responsivo (móvil y escritorio)**
-  **Arquitectura distribuida con load balancing**
-  **Almacenamiento seguro de imágenes**

### Arquitectura Técnica

Extagram utiliza 6 contenedores Docker:
- **Nginx**: Balanceador de carga y reverse proxy
- **PHP-FPM (x3)**: 2 instancias para lectura, 1 para escritura
- **MySQL 8.0**: Base de datos
- **Storage**: Servidor de archivos estáticos

---

## 2. Requisitos del Sistema

### Para el Usuario Final

| Requisito | Especificación |
|-----------|----------------|
| **Navegador Web** | Chrome 90+, Firefox 88+, Safari 14+, Edge 90+ |
| **Conexión a Internet** | Mínimo 2 Mbps |
| **Resolución de Pantalla** | Mínimo 320x568px (móvil) |
| **JavaScript** | Habilitado |
| **Cookies** | Habilitadas (opcional) |

### Navegadores Recomendados

- 🟢 **Google Chrome** (Versión 90 o superior)
- 🟢 **Mozilla Firefox** (Versión 88 o superior)
- 🟢 **Safari** (Versión 14 o superior)
- 🟢 **Microsoft Edge** (Versión 90 o superior)

⚠️ **Nota**: Internet Explorer NO es compatible.

---

## 3. Acceso a la Aplicación

### 3.1 Acceso Local (Desarrollo)

Si la aplicación está instalada en tu equipo:

1. Abre tu navegador web
2. En la barra de direcciones, escribe:
   ```
   http://localhost
   ```
   o
   ```
   http://127.0.0.1
   ```
3. Presiona **Enter**

### 3.2 Acceso Remoto (Servidor)

Si la aplicación está en un servidor:

1. Abre tu navegador web
2. Escribe la **dirección IP pública** del servidor:
   ```
   http://TU_IP_PUBLICA
   ```
   Ejemplo: `http://34.205.10.15`
3. Presiona **Enter**

### 3.3 Verificar que la Aplicación Funciona

 **Página cargada correctamente**:
- Deberías ver el logo **"Extagram"** en la parte superior
- Aparece el texto "Comparte tus momentos"
- Se muestran publicaciones existentes o el mensaje "No hay posts aún"

 **Página no carga**:
- Ver sección [6. Errores Comunes](#6-errores-comunes-y-soluciones)

---

## 4. Guía de Uso

### 4.1 Página Principal

La interfaz de Extagram está dividida en dos secciones principales:

```
┌─────────────────────────────────────────┐
│            EXTAGRAM                     │
│      Comparte tus momentos              │
├─────────────────────────────────────────┤
│                                         │
│     FEED DE PUBLICACIONES               │
│  (Posts ordenados del más reciente      │
│   al más antiguo)                       │
│                                         │
├─────────────────────────────────────────┤
│                                         │
│      NUEVO POST                         │
│  Formulario para crear publicaciones    │
│                                         │
└─────────────────────────────────────────┘
```

### 4.2 Ver Publicaciones

Las publicaciones aparecen en un **feed cronológico inverso** (las más nuevas primero).

#### Estructura de una Publicación

Cada post muestra:

1. **Fecha y Hora**: Cuándo se publicó
   - Formato: `DD/MM/YYYY HH:MM`
   - Ejemplo: `09/02/2026 16:30`

2. **Imagen** (si la publicación tiene foto):
   - Se muestra en tamaño completo
   - Click para ver en detalle

3. **Texto del Post**:
   - Contenido de la publicación
   - Máximo recomendado: 500 caracteres

#### Navegación en el Feed

- **Desplazamiento**: Usa la rueda del ratón o scroll táctil para ver más publicaciones
- **Actualización**: Presiona `F5` o el botón de recargar para ver nuevas publicaciones

### 4.3 Crear Publicación

#### Paso 1: Ubicar el Formulario

Desplázate hacia abajo hasta encontrar la sección **"Nuevo Post"** con fondo blanco y borde redondeado.

#### Paso 2: Escribir el Texto

1. Haz click en el área de texto que dice:
   ```
   ¿Qué estás pensando?
   ```

2. Escribe tu mensaje:
   - **Mínimo**: 1 carácter
   - **Recomendado**: 20-300 caracteres
   - **Sin límite técnico**: Pero textos largos pueden ser incómodos de leer

3. **Formato del texto**:
   - Solo texto plano (sin negrita, cursiva, etc.)
   - Emojis: Compatibles
   - Saltos de línea: Permitidos (presiona Enter)

#### Paso 3: Publicar

Haz click en el botón **"Publicar"** (fondo degradado rosa-morado).

**Resultado esperado**:
- La página se recarga automáticamente
- Tu publicación aparece en la parte superior del feed
- El formulario se limpia para crear un nuevo post

### 4.4 Subir Imágenes

#### Paso 1: Seleccionar Imagen

1. En el formulario "Nuevo Post", haz click en:
   ```
   📎 Seleccionar archivo
   ```
   o
   ```
   Choose File
   ```

2. Se abrirá el explorador de archivos de tu sistema operativo

#### Paso 2: Elegir la Foto

**Formatos aceptados**:
-  JPG / JPEG (`.jpg`, `.jpeg`)
-  PNG (`.png`)
-  GIF (`.gif`)
-  WEBP (`.webp`)

**Formatos NO aceptados**:
- BMP, TIFF, SVG, PDF, etc.

**Tamaño máximo**: 100 MB por imagen

#### Paso 3: Vista Previa (Opcional)

Algunos navegadores mostrarán el nombre del archivo seleccionado:
```
 mi_foto.jpg
```

#### Paso 4: Publicar

1. Escribe un texto (obligatorio)
2. Click en **"Publicar"**

**Resultado esperado**:
- La imagen se sube al servidor
- Tu post aparece con la imagen en el feed
- La imagen se muestra a 400px de alto (escritorio) o ajustada (móvil)

---

## 5. Características de la Aplicación

### 5.1 Diseño Responsivo

La aplicación se adapta automáticamente a diferentes tamaños de pantalla:

| Dispositivo | Ancho de Contenedor | Altura de Imagen |
|-------------|---------------------|------------------|
| **Móvil** (< 600px) | 100% del ancho | 250px |
| **Tablet** (600-1024px) | 600px centrado | 400px |
| **Escritorio** (> 1024px) | 600px centrado | 400px |

### 5.2 Rendimiento

**Velocidad de carga**:
- Primera carga: ~2-3 segundos
- Carga de imagen: ~1-2 segundos (dependiendo del tamaño)
- Publicación de post: ~500ms-1s

**Límite de posts en feed**: 50 publicaciones más recientes

### 5.3 Seguridad

-  **Validación de archivos**: Solo imágenes permitidas
-  **Sanitización de inputs**: Prevención de ataques XSS
-  **Prepared Statements**: Protección contra SQL Injection
-  **Límite de tamaño**: Prevención de ataques DoS por archivos grandes

### 5.4 Accesibilidad

-  Etiquetas HTML semánticas
-  Contraste de colores conforme WCAG 2.1
-  Navegación por teclado
-  Alt text en imágenes

---

## 6. Errores Comunes y Soluciones

### 6.1 "No se puede acceder a la página"

**Síntomas**:
- El navegador muestra: "Este sitio no está disponible" o "ERR_CONNECTION_REFUSED"

**Causas posibles**:
1. La aplicación no está iniciada
2. Dirección IP incorrecta
3. Firewall bloqueando el puerto 80

**Soluciones**:

**Solución 1: Verificar que Docker está corriendo**
```bash
# En el servidor, ejecuta:
docker-compose ps

# Deberías ver 6 contenedores con estado "Up"
```

**Solución 2: Verificar IP correcta**
```bash
# En el servidor AWS:
curl ifconfig.me

# Usa esa IP en tu navegador
```

**Solución 3: Verificar grupo de seguridad (AWS)**
- Ve a EC2 Dashboard → Security Groups
- Verifica que hay una regla: **HTTP (80) → 0.0.0.0/0**

---

### 6.2 "El post no puede estar vacío"

**Síntomas**:
- Intentas publicar sin escribir texto
- Mensaje de error en pantalla

**Causa**:
- El campo de texto está vacío o solo tiene espacios

**Solución**:
```
 Escribe al menos 1 carácter válido antes de publicar
```

---

### 6.3 "Tipo de archivo no permitido"

**Síntomas**:
- Error al intentar subir una imagen
- Mensaje: "Tipo de archivo no permitido. Usa: JPG, PNG, GIF, WEBP"

**Causa**:
- Estás intentando subir un archivo que NO es imagen (PDF, DOCX, etc.)
- Formato de imagen no compatible (BMP, TIFF, SVG)

**Soluciones**:

**Solución 1: Convertir la imagen**
- Usa un conversor online: [Convertio](https://convertio.co/es/), [CloudConvert](https://cloudconvert.com/)
- Convierte a `.jpg` o `.png`

**Solución 2: Tomar captura de pantalla**
- Abre la imagen en tu ordenador
- Toma una captura de pantalla (JPG/PNG)
- Sube la captura

---

### 6.4 "Archivo demasiado grande. Máximo 100MB"

**Síntomas**:
- Error al intentar subir una imagen grande

**Causa**:
- La imagen pesa más de 100 MB

**Soluciones**:

**Solución 1: Comprimir la imagen online**
- [TinyPNG](https://tinypng.com/)
- [Compressor.io](https://compressor.io/)
- [iLoveIMG](https://www.iloveimg.com/es/comprimir-imagen)

**Solución 2: Reducir resolución**
- Usa una herramienta de edición (Paint, GIMP, Photoshop)
- Cambia el tamaño a 1920x1080 o menor
- Guarda con calidad 80-85%

---

### 6.5 "Error al guardar en BD"

**Síntomas**:
- Página muestra: "Error al guardar en BD: [mensaje técnico]"

**Causa**:
- Problemas de conexión con la base de datos MySQL

**Soluciones**:

**Para administradores**:
```bash
# Verificar que MySQL está corriendo
docker logs extagram-mysql

# Reiniciar contenedor MySQL
docker-compose restart mysql

# Verificar conectividad
docker exec extagram-php-1 php -r "new PDO('mysql:host=mysql', 'extagram_user', 'secure_password_123');"
```

**Para usuarios**:
- Contacta al administrador del sistema
- Espera unos minutos y recarga la página

---

### 6.6 La imagen no se muestra

**Síntomas**:
- El post aparece pero sin imagen
- Ícono de "imagen rota" 

**Causas posibles**:
1. El archivo no se subió correctamente
2. Permisos incorrectos en el servidor
3. Ruta de archivo incorrecta

**Soluciones**:

**Para usuarios**:
1. Recarga la página (`F5`)
2. Si persiste, intenta publicar de nuevo

**Para administradores**:
```bash
# Verificar permisos del directorio uploads
ls -la extagram/docker/volumes/uploads

# Debería mostrar: drwxrwxrwx

# Si no, corregir permisos:
chmod 777 extagram/docker/volumes/uploads

# Verificar que las imágenes están ahí:
ls -lh extagram/docker/volumes/uploads
```

---

### 6.7 La página se ve "rota" o sin estilos

**Síntomas**:
- Texto sin formato
- Colores incorrectos
- Layout desorganizado

**Causa**:
- El archivo CSS no está cargando

**Soluciones**:

**Solución 1: Limpiar caché del navegador**

**Chrome**:
1. Presiona `Ctrl + Shift + Delete` (Windows/Linux) o `Cmd + Shift + Delete` (Mac)
2. Selecciona "Imágenes y archivos en caché"
3. Click en "Borrar datos"
4. Recarga la página con `Ctrl + F5`

**Firefox**:
1. Presiona `Ctrl + Shift + Delete`
2. Selecciona "Caché"
3. Click en "Limpiar ahora"
4. Recarga con `Ctrl + F5`

**Solución 2: Verificar archivos estáticos (administradores)**
```bash
# Verificar que style.css existe
ls -la extagram/src/web/static/css/style.css

# Verificar logs de nginx
docker logs extagram-nginx | grep style.css
```

---

### 6.8 "Por favor, escribe algo antes de publicar"

**Síntomas**:
- Alert (ventana emergente) con este mensaje
- El formulario no se envía

**Causa**:
- Validación de JavaScript detectó campo vacío

**Solución**:
```
 Escribe texto en el campo antes de publicar
 No uses solo espacios en blanco
```

---

### 6.9 La página es muy lenta

**Síntomas**:
- Tarda más de 5 segundos en cargar
- Imágenes no se muestran rápidamente

**Causas posibles**:
1. Conexión a internet lenta
2. Servidor sobrecargado
3. Imágenes muy pesadas

**Soluciones**:

**Para usuarios**:
1. **Verifica tu conexión**:
   - Abre [Fast.com](https://fast.com/) para medir velocidad
   - Mínimo recomendado: 2 Mbps

2. **Cierra pestañas innecesarias**:
   - Mantén solo Extagram abierto

3. **Usa modo "lectura" del navegador**:
   - Mejora rendimiento en móviles

**Para administradores**:
```bash
# Verificar uso de recursos
docker stats

# Si hay contenedores con >80% CPU/RAM, escalar:
docker-compose up -d --scale php-app-1=3
```

---

### 6.10 Los posts no se actualizan

**Síntomas**:
- Publicas un post pero no aparece
- Los posts de otros usuarios no se ven

**Causa**:
- Caché del navegador mostrando versión antigua

**Solución**:
```
 Presiona F5 para recargar la página
 O presiona Ctrl + F5 (recarga forzada sin caché)
```

---

## 7. Preguntas Frecuentes (FAQ)

###  ¿Puedo editar o eliminar mis publicaciones?

**Respuesta**: En la versión actual, NO. Una vez publicado, el post es permanente. Esta funcionalidad está planeada para futuras versiones.

---

###  ¿Hay límite de publicaciones por día?

**Respuesta**: NO hay límite. Puedes publicar tantos posts como desees.

---

###  ¿Puedo subir videos?

**Respuesta**: En la versión actual, NO. Solo se aceptan imágenes estáticas (JPG, PNG, GIF, WEBP).

---

###  ¿Necesito crear una cuenta?

**Respuesta**: NO. Extagram no tiene sistema de usuarios en la versión actual. Todos los posts son anónimos.

---

###  ¿Los posts tienen likes o comentarios?

**Respuesta**: NO en la versión actual. Solo se pueden crear y ver posts.

---

###  ¿Puedo buscar posts por palabra clave?

**Respuesta**: NO hay buscador implementado actualmente.

---

###  ¿Cuánto tiempo permanecen los posts en la plataforma?

**Respuesta**: Permanentemente, mientras el servidor esté activo y no se elimine la base de datos.

---

###  ¿Funciona en móviles?

**Respuesta**: SÍ. La aplicación es 100% responsiva y funciona en smartphones y tablets.

---

###  ¿Qué pasa si subo una imagen con contenido inapropiado?

**Respuesta**: La aplicación NO tiene sistema de moderación automática. Contacta al administrador para reportar contenido.

---

###  ¿Puedo acceder sin conexión a internet?

**Respuesta**: NO. Extagram requiere conexión a internet constante.

---

## 8. Mejores Prácticas

### 8.1 Para Crear Publicaciones de Calidad

 **Texto Claro y Conciso**
- Usa entre 50-200 caracteres para mejor legibilidad
- Evita MAYÚSCULAS SOSTENIDAS (parece que gritas)
- Usa emojis con moderación 

 **Imágenes Optimizadas**
- Tamaño recomendado: 1080x1080px (formato cuadrado)
- Peso recomendado: < 5 MB
- Resolución: 72 DPI (para web)
- Formato preferido: JPG (mejor compresión)

 **Compatibilidad**
- Prueba tu post en móvil Y escritorio
- Asegúrate de que el texto es legible en pantallas pequeñas

---

### 8.2 Para Administradores

 **Monitoreo Regular**
```bash
# Revisar logs diariamente
docker-compose logs --tail 100

# Monitorear espacio en disco
df -h

# Verificar salud de contenedores
docker ps --format "table {{.Names}}\t{{.Status}}"
```

 **Backups de Base de Datos**
```bash
# Hacer backup semanal
docker exec extagram-mysql mysqldump -u root -p extagram_db > backup_$(date +%Y%m%d).sql
```

 **Limpieza de Uploads**
```bash
# Eliminar imágenes huérfanas (sin referencia en BD)
# Script a implementar según necesidad
```

---

## 9. Soporte Técnico

### Para Usuarios

Si experimentas problemas:

1. **Revisa esta guía**: Sección [6. Errores Comunes](#6-errores-comunes-y-soluciones)
2. **Contacta al administrador**: Proporciona:
   - Navegador y versión
   - Descripción del problema
   - Captura de pantalla (si es posible)
   - Mensaje de error exacto

### Para Administradores

Recursos técnicos:

- **Logs de contenedores**: `docker-compose logs -f [servicio]`
- **Documentación Docker**: [docs.docker.com](https://docs.docker.com/)
- **Documentación PHP**: [php.net](https://www.php.net/)
- **Documentación MySQL**: [dev.mysql.com](https://dev.mysql.com/doc/)

---

## Anexo: Atajos de Teclado

| Tecla | Acción |
|-------|--------|
| `F5` | Recargar página |
| `Ctrl + F5` | Recargar sin caché |
| `Ctrl + +` | Aumentar zoom |
| `Ctrl + -` | Disminuir zoom |
| `Ctrl + 0` | Resetear zoom |
| `Tab` | Navegar entre campos del formulario |
| `Enter` (en textarea) | Salto de línea |

---

## Historial de Versiones

| Versión | Fecha | Cambios |
|---------|-------|---------|
| 1.0 | 09/02/2026 | Versión inicial del manual |

---

## Créditos

**Proyecto**: Extagram - Red Social con Docker  
**Arquitectura**: Microservicios (Nginx + PHP-FPM + MySQL + Storage)  
**Tecnologías**: Docker, Docker Compose, PHP 8.2, MySQL 8.0, HTML5, CSS3, JavaScript  

---

**Fin del Manual de Usuario**

Para soporte técnico, contacta al administrador del sistema.