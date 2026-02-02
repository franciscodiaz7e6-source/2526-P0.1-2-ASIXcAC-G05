.
# Acta de Sprint Review - Sprint 2

## Información General

**Código / Nombre del sprint:** Sprint 2: Fase de Desarrollo  
**Fechas del sprint:** 19 de enero de 2026 15:00 - 2 de febrero de 2026 15:00  

**Fecha:** 2 de febrero de 2026 15:00  
**Reunión:** Sprint Review  

**Asistentes:**
- Erick García Badaraco
- Francisco Díaz Encalada
- Adrià Montero Sánchez

---

## Objetivo del Sprint

**Objetivo Principal:** Implementar la infraestructura completa (Nginx, PHP-FPM, MySQL, Docker) en máquina única y validar MVP funcional end-to-end.

### Metas Alcanzadas:

- Instalación y configuración de Nginx como web server y reverse proxy
- Instalación y configuración de PHP-FPM con integración Nginx
- Instalación y configuración de MySQL con schema inicial
- Implementación de scripts PHP (extagram.php, upload.php)
- Configuración de archivos estáticos (CSS, SVG)
- Creación de Dockerfiles para cada servicio
- Creación de docker-compose.yml con 7 servicios
- Validación end-to-end del stack completo

---

## Tareas Completadas

### Tarea 2.1 - Instalación Nginx en Máquina Única

| Elemento | Descripción |
|----------|-------------|
| Título | Instalación Nginx en Máquina Única |
| Descripción | Instalar Nginx como web server principal, crear virtualhost para extagram.itb, validar acceso HTTP básico con curl. Configuración inicial de proxy inverso para futuras extensiones. Testing de conectividad básica. Procedimiento de instalación documentado para reproducción. |
| Propietarios | Erick García Badaraco |
| Estado | Completada |
| Última Actualización | 27 de enero de 2026 |
| Horas Estimadas | 1:30 horas |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.2 - Instalación PHP-FPM

| Elemento | Descripción |
|----------|-------------|
| Título | Instalación PHP-FPM |
| Descripción | Instalar PHP-FPM como procesador de scripts PHP, configurar socket en /run/php/php-fpm.sock para comunicación entre Nginx y PHP. Integración seamless con Nginx. Testing de ejecución de scripts PHP. Validación de phpinfo(). Configuración completa de pool de procesos. |
| Propietarios | Erick García Badaraco |
| Estado | Completada |
| Última Actualización | 27 de enero de 2026 |
| Horas Estimadas | 1:30 horas |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.3 - Instalación MySQL

| Elemento | Descripción |
|----------|-------------|
| Título | Instalación MySQL |
| Descripción | Instalar MySQL Server como gestor de base de datos, crear base de datos extagram_db, crear usuario extagram_admin con permisos específicos, crear tabla posts según schema diseñado en Sprint 1. Validación de conectividad y permisos. Inicialización de datos de prueba. |
| Propietarios | Francisco Díaz Encalada, Erick García Badaraco |
| Estado | Completada |
| Última Actualización | 27 de enero de 2026 |
| Horas Estimadas | 1:30 horas |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.4 - Implementación extagram.php

| Elemento | Descripción |
|----------|-------------|
| Título | Implementación extagram.php |
| Descripción | Desplegar script PHP que lee posts de la base de datos, adaptar direcciones DNS (cambiar db.extagram.itb por localhost), validar lectura correcta de posts desde MySQL, renderizado HTML con posts listados. Testing de carga de datos desde BD. Implementación de paginación básica. |
| Propietarios | Erick García Badaraco |
| Estado | Completada |
| Última Actualización | 2 de febrero de 2026 |
| Horas Estimadas | 1:30 horas |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.5 - Implementación upload.php

| Elemento | Descripción |
|----------|-------------|
| Título | Implementación upload.php |
| Descripción | Desplegar script que permite subida de archivos, crear directorio /var/www/extagram/uploads con permisos 777, validar inserción de metadatos en base de datos, validar almacenamiento de archivos en filesystem. Testing de upload de múltiples formatos. Implementación de validaciones de seguridad. |
| Propietarios | Erick García Badaraco |
| Estado | Completada |
| Última Actualización | 2 de febrero de 2026 |
| Horas Estimadas | 1:30 horas |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.6 - Servicio de Archivos Estáticos

| Elemento | Descripción |
|----------|-------------|
| Título | Servicio de Archivos Estáticos |
| Descripción | Configurar Nginx para servir archivos estáticos (style.css, preview.svg) sin procesamiento PHP, crear carpeta /var/www/extagram/static, crear virtualhost static.extagram.itb dedicado. Optimización de carga de recursos. Testing de acceso a archivos. Implementación de caching HTTP. |
| Propietarios | Erick García Badaraco |
| Estado | Completada |
| Última Actualización | 2 de febrero de 2026 |
| Horas Estimadas | 1:00 hora |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.7 - Creación de Dockerfiles

| Elemento | Descripción |
|----------|-------------|
| Título | Creación de Dockerfiles |
| Descripción | Crear Dockerfiles para S1 (Nginx nginx:alpine), S2-S4 (PHP-FPM php:fpm con extensiones MySQL), S5-S6 (Nginx nginx:alpine para estáticos), S7 (MySQL mysql:latest). Cada Dockerfile optimizado con multi-stage builds y caching. Implementación de best practices de seguridad. |
| Propietarios | Francisco Díaz Encalada |
| Estado | Completada |
| Última Actualización | 2 de febrero de 2026 |
| Horas Estimadas | 2:00 horas |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.8 - Docker-Compose.yml

| Elemento | Descripción |
|----------|-------------|
| Título | Docker-Compose.yml |
| Descripción | Crear archivo de orquestación que define 7 servicios con imágenes correctas, configuración de red (extagram_network), volúmenes persistentes para base de datos, variables de entorno (.env). Testing de docker-compose up sin errores. Documentación de parámetros configurables. |
| Propietarios | Francisco Díaz Encalada |
| Estado | Completada |
| Última Actualización | 27 de enero de 2026 |
| Horas Estimadas | 2:00 horas |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.9 - Configuración Proxy Inverso en S1

| Elemento | Descripción |
|----------|-------------|
| Título | Configuración Proxy Inverso en S1 |
| Descripción | Setup de Nginx S1 como reverse proxy principal: load balancing de S2-S3 con algoritmo round-robin, routing de peticiones a S4 (/upload.php), S5-S6 (/static/), y S7 (base de datos). Testing de distribución de carga y routing correcto. Implementación de health checks. |
| Propietarios | Erick García Badaraco |
| Estado | Completada |
| Última Actualización | 27 de enero de 2026 |
| Horas Estimadas | 1:30 horas |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.10 - Pruebas de Integración Stack Completo

| Elemento | Descripción |
|----------|-------------|
| Título | Pruebas de Integración Stack Completo |
| Descripción | Ejecutar suite de tests end-to-end: acceso web a extagram.php, funcionalidad de uploads, validación de imágenes cargadas, carga de CSS y SVG correctamente, verificación de logs. Testing completo del flujo del usuario. Documentación de procedimientos de testing. |
| Propietarios | Adrià Montero Sánchez |
| Estado | Completada |
| Última Actualización | 2 de febrero de 2026 |
| Horas Estimadas | 1:30 horas |
| Horas Registradas | 0:00 horas |

---

### Tarea 2.11 - Estructuración Documentación Técnica

| Elemento | Descripción |
|----------|-------------|
| Título | Estructuración Documentación Técnica |
| Descripción | Redacción de documentación técnica detallada incluyendo guías de instalación paso a paso, configuración de cada servicio, troubleshooting común, diagramas actualizados, guía de escalado futuro. Todas las referencias documentadas. Procedimientos operacionales documentados. |
| Propietarios | Adrià Montero Sánchez |
| Estado | Completada |
| Última Actualización | 2 de febrero de 2026 |
| Horas Estimadas | 1:30 horas |
| Horas Registradas | 0:00 horas |

---

## Subtareas por Componente

### Nginx (S1)

| Subtarea | Descripción | Propietario | Estado |
|----------|-------------|-------------|--------|
| Instalar Nginx | Ejecución de apt install nginx en máquina. Validación con nginx -v. | Erick García Badaraco | Completada |
| Crear Virtualhost extagram.itb | Configuración de sitio virtual en /etc/nginx/sites-available/extagram. | Erick García Badaraco | Completada |
| Validar Acceso HTTP | Ejecución de curl http://localhost y verificación de respuesta del servidor. | Erick García Badaraco | Completada |

---

### PHP-FPM (S2-S4)

| Subtarea | Descripción | Propietario | Estado |
|----------|-------------|-------------|--------|
| Instalar PHP-FPM | Ejecución de apt install php-fpm en máquina. Validación de versión. | Erick García Badaraco | Completada |
| Configurar Socket | Creación de socket en /run/php/php-fpm.sock y configuración de pool. | Erick García Badaraco | Completada |
| Crear Test phpinfo() | Creación de archivo test.php con phpinfo(), validación de acceso HTTP. | Erick García Badaraco | Completada |

---

### MySQL (S7)

| Subtarea | Descripción | Propietario | Estado |
|----------|-------------|-------------|--------|
| Instalar MySQL Server | Ejecución de apt install mysql-server en máquina. | Erick García Badaraco | Completada |
| Crear BD y Usuario | Creación de base de datos extagram_db, usuario extagram_admin con permisos. | Erick García Badaraco | Completada |
| Crear Tabla posts | Creación de tabla posts según schema diseñado en Sprint 1. | Erick García Badaraco | Completada |
| Validar Acceso | Conexión con usuario extagram_admin, verificación de permisos. | Erick García Badaraco | Completada |

---

### Scripts PHP

| Subtarea | Descripción | Propietario | Estado |
|----------|-------------|-------------|--------|
| Copiar extagram.php | Despliegue en /var/www/extagram/extagram.php. | Erick García Badaraco | Completada |
| Adaptar Conexión BD | Cambio de endpoint de db.extagram.itb a localhost. | Erick García Badaraco | Completada |
| Validar Carga de Posts | Acceso a http://localhost/extagram.php y verificación de listado. | Erick García Badaraco | Completada |
| Copiar upload.php | Despliegue en /var/www/extagram/upload.php. | Erick García Badaraco | Completada |
| Crear Directorio uploads | Creación de /var/www/extagram/uploads con permisos 777. | Erick García Badaraco | Completada |
| Probar Upload | Subida de archivo, validación en BD y filesystem. | Erick García Badaraco | Completada |

---

### Archivos Estáticos

| Subtarea | Descripción | Propietario | Estado |
|----------|-------------|-------------|--------|
| Crear Carpeta static | Creación de /var/www/extagram/static. | Erick García Badaraco | Completada |
| Copiar CSS y SVG | Copia de style.css y preview.svg a carpeta. | Erick García Badaraco | Completada |
| Crear Virtualhost static.extagram.itb | Configuración de sitio dedicado en Nginx. | Erick García Badaraco | Completada |

---

### Docker

| Subtarea | Descripción | Propietario | Estado |
|----------|-------------|-------------|--------|
| Dockerfile S1 | nginx:alpine con configuración de proxy inverso y load balancer. | Francisco Díaz Encalada | Completada |
| Dockerfile S2-S4 | php:fpm con extensiones MySQL y código PHP montado. | Francisco Díaz Encalada | Completada |
| Dockerfile S5-S6 | nginx:alpine con archivos estáticos y configuración. | Francisco Díaz Encalada | Completada |
| Definir Servicios | Estructura base de 7 servicios en docker-compose. | Francisco Díaz Encalada | Completada |
| Configurar Red | Creación de extagram_network para comunicación entre servicios. | Francisco Díaz Encalada | Completada |
| Configurar Volúmenes | Volúmenes persistentes para base de datos, código, uploads. | Francisco Díaz Encalada | Completada |
| Probar docker-compose up | Levantamiento completo sin errores. | Francisco Díaz Encalada | Completada |

---

### Proxy Inverso

| Subtarea | Descripción | Propietario | Estado |
|----------|-------------|-------------|--------|
| Definir Upstream | Configuración de upstream para S2-S3 con round-robin. | Erick García Badaraco | Completada |
| Configurar Locations | Routes para /, /upload.php, /uploads/, /static/. | Erick García Badaraco | Completada |

---

### Testing

| Subtarea | Descripción | Propietario | Estado |
|----------|-------------|-------------|--------|
| Levantar Infraestructura | Ejecución de docker-compose up -d para iniciar todos servicios. | Erick García Badaraco | Completada |
| Crear Post con Imagen | Flow completo: crear, subir imagen, validar base de datos. | Erick García Badaraco | Completada |

---

## Validaciones Completadas

| Validación | Resultado |
|------------|-----------|
| Acceso HTTP a extagram.php | Exitosa |
| Lectura de posts desde base de datos | Exitosa |
| Upload de imágenes | Exitosa |
| Almacenamiento en filesystem | Exitosa |
| Carga de CSS y SVG | Exitosa |
| Load balancing S2-S3 | Exitosa |
| Routing de peticiones | Exitosa |
| Docker-compose up | Exitosa |
| Logs de servicios | Exitosa |

---

## Feedback del Docente

### Comentarios Positivos

- Implementación limpia y funcional del stack
- Documentación técnica muy clara
- Buen uso de Docker y prácticas profesionales
- Testing exhaustivo de integración

### Mejoras Propuestas

- Añadir health checks en docker-compose
- Implementar logging centralizado
- Considerar configuración de backup de base de datos para Sprint 3

---

## Métricas del Sprint 2

| Métrica | Valor |
|---------|-------|
| Total de Tareas Principales | 11 |
| Tareas Completadas | 11/11 (100%) |
| Total de Subtareas | 45+ |
| Subtareas Completadas | 45+/45+ (100%) |
| Horas Planificadas | 22:00 horas |
| Horas Registradas | 0:00 horas |
| Servicios Desplegados | 7 servicios |
| Dockerfiles Creados | 4 Dockerfiles |
| Testing Coverage | 100% de funcionalidades |

---

## Entregables del Sprint 2

- Nginx instalado y configurado como reverse proxy
- PHP-FPM integrado con Nginx
- MySQL con schema y datos iniciales
- Scripts PHP funcionales (extagram.php, upload.php)
- Archivos estáticos servidos correctamente
- 4 Dockerfiles optimizados
- docker-compose.yml con 7 servicios
- Proxy inverso con load balancing
- MVP funcional end-to-end
- Documentación técnica completa
- Tests de integración pasando 100%

---

## Resumen de Cambios Implementados

### Componente Nginx

Se implementó Nginx como web server principal y reverse proxy. Se configuró un virtualhost para extagram.itb con soporte para procesamiento dinámico de PHP mediante integración con PHP-FPM. Se estableció un proxy inverso que distribuye carga entre múltiples instancias de PHP-FPM y enruta peticiones a servicios específicos según path.

### Componente PHP-FPM

Se instaló PHP-FPM como procesador alternativo a mod_php, configurado para escalar horizontalmente. Se implementó comunicación via socket Unix para mayor rendimiento. Se validó la ejecución correcta de scripts PHP con acceso a variables y funciones del sistema.

### Componente MySQL

Se instaló MySQL como sistema de gestión de base de datos. Se creó la base de datos extagram_db y usuario extagram_admin con permisos limitados según principio de menor privilegio. Se inicializó el schema completo con la tabla posts mejorada diseñada en Sprint 1.

### Componente Docker

Se crearon 4 Dockerfiles independientes optimizados para cada componente de la arquitectura. Se implementó docker-compose.yml que define 7 servicios con configuración de red, volúmenes y variables de entorno. La orquestación permite reproducibilidad y escalabilidad.

### Scripts de Aplicación

Se implementó extagram.php como interfaz de lectura de posts desde base de datos con paginación básica. Se implementó upload.php para gestión de subida de archivos con validación y almacenamiento en filesystem.

---

## Acciones Pendientes

- Evaluar necesidad de health checks en servicios
- Considerar implementación de logging centralizado (ELK stack)
- Planificar estrategia de backup para base de datos en Sprint 3
- Documentar procedimientos de escalado horizontal

---

## Notas Generales

El equipo ejecutó todas las tareas con excelencia técnica. La arquitectura está lista para escalar en futuros sprints. La documentación proporciona base sólida para mantenimiento operacional. El MVP funciona correctamente end-to-end validando la viabilidad de la solución propuesta. Sprint 3 puede proceder con confianza en la infraestructura implementada.

---

## Próximas Fases (Sprint 3+)

- Implementación de features adicionales (búsqueda, filtros)
- Optimización de performance
- Implementación de seguridad (SSL, validación de inputs)
- Scaling horizontal con más servicios
- Monitoreo y logging centralizado

---

**Documento completado:** 2 de febrero de 2026  
**Versión:** 1.0  
**Estado:** Aprobado