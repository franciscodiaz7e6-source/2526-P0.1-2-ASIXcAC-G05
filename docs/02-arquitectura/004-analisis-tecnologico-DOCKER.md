# DOCKER - ARQUITECTURA, COMPARATIVA Y VIABILIDAD PARA EXTAGRAM

---

## TABLA DE CONTENIDOS

1. [Anatomía y Arquitectura de Docker](#1-anatomía-y-arquitectura-de-docker)
2. [Análisis Comparativo: Docker vs Máquinas Virtuales vs Instalación Nativa](#2-análisis-comparativo-docker-vs-máquinas-virtuales-vs-instalación-nativa)
3. [Funcionalidades Críticas para Extagram](#3-funcionalidades-críticas-para-extagram)
4. [Conclusión Técnica y Viabilidad en AWS](#4-conclusión-técnica-y-viabilidad-en-aws)

---

## 1. ANATOMÍA Y ARQUITECTURA DE DOCKER

### 1.1 Descripción General

**Docker** es una plataforma de containerización que permite empaquetar aplicaciones, dependencias y configuración en unidades independientes llamadas contenedores. Publicada en 2013 por Solomon Hykes, ha revolucionado la forma de desplegar aplicaciones en producción.

A diferencia de máquinas virtuales tradicionales, Docker utiliza containerización a nivel del sistema operativo, proporcionando la mayoría de los beneficios de aislamiento con una fracción del overhead.

---

### 1.2 Concepto Fundamental: Contenedores vs Máquinas Virtuales

#### Arquitectura Tradicional: Máquinas Virtuales

```
HOST FÍSICO (AWS EC2 t3.micro - 1 GB RAM)
├─────────────────────────────────────────────────────┐
│ HYPERVISOR (KVM, Xen, etc.)                        │
├─────────────────────────────────────────────────────┤
│                                                      │
│ ┌────────────────┐  ┌────────────────┐             │
│ │ VM 1           │  │ VM 2           │             │
│ ├────────────────┤  ├────────────────┤             │
│ │ Kernel Linux   │  │ Kernel Linux   │             │
│ │ (300-500 MB)   │  │ (300-500 MB)   │             │
│ │                │  │                │             │
│ │ Libc, Utils    │  │ Libc, Utils    │             │
│ │ (100-200 MB)   │  │ (100-200 MB)   │             │
│ │                │  │                │             │
│ │ Nginx          │  │ PHP-FPM        │             │
│ │ MySQL          │  │ MySQL          │             │
│ │ (300-400 MB)   │  │ (300-400 MB)   │             │
│ └────────────────┘  └────────────────┘             │
│  ~700 MB cada       ~700 MB cada                   │
│                                                      │
│  PROBLEMA: 1400 MB de 1000 MB disponibles          │
│  RESULTADO: OOM (Out of Memory) - FALLA             │
│                                                      │
└─────────────────────────────────────────────────────┘
```

---

#### Arquitectura Docker: Contenedores

```
HOST FÍSICO (AWS EC2 t3.micro - 1 GB RAM)
├─────────────────────────────────────────────────────┐
│ KERNEL LINUX (compartido)                          │
├─────────────────────────────────────────────────────┤
│                                                      │
│ DOCKER RUNTIME (daemon dockerd)                    │
│                                                      │
│ ┌──────────────────┐  ┌──────────────────┐        │
│ │ CONTENEDOR 1     │  │ CONTENEDOR 2     │        │
│ │ (Nginx)          │  │ (PHP-FPM)        │        │
│ ├──────────────────┤  ├──────────────────┤        │
│ │ /bin, /lib       │  │ /bin, /lib       │        │
│ │ (solo lo que     │  │ (solo lo que     │        │
│ │  necesita)       │  │  necesita)       │        │
│ │                  │  │                  │        │
│ │ Nginx binary     │  │ PHP binary       │        │
│ │ (5-15 MB)        │  │ (30-50 MB)       │        │
│ │                  │  │                  │        │
│ │ Procesos aislados│  │ Procesos aislados│        │
│ │ (PID 1, 2, 3)    │  │ (PID 1, 2, 3)    │        │
│ └──────────────────┘  └──────────────────┘        │
│  ~50 MB total        ~100 MB total                │
│                                                      │
│ ┌──────────────────┐                              │
│ │ CONTENEDOR 3     │                              │
│ │ (MySQL)          │                              │
│ ├──────────────────┤                              │
│ │ MySQL binary     │                              │
│ │ InnoDB           │                              │
│ │ (80-150 MB)      │                              │
│ └──────────────────┘                              │
│  ~150 MB total                                    │
│                                                      │
│  TOTAL: 50 + 100 + 150 = 300 MB                   │
│  MARGEN: 1000 - 300 = 700 MB LIBRE                │
│  RESULTADO: FUNCIONA PERFECTAMENTE                │
│                                                      │
└─────────────────────────────────────────────────────┘
```

---

#### Tabla Comparativa: Overhead de Recursos

| Aspecto | Máquina Virtual | Contenedor Docker | Diferencia |
|---|---|---|---|
| **Kernel duplicado** | Sí (300-500 MB cada) | No (kernel compartido) | 50% menos RAM |
| **Libc/Utilities** | Completo (100-200 MB) | Mínimo (5-20 MB) | 80% menos RAM |
| **Boot time** | 30-60 segundos | 1-5 segundos | 60x más rápido |
| **Densidad (en 1GB)** | 1-2 VMs | 8-15 contenedores | 8-10x más |
| **Aislamiento** | Completo (Hypervisor) | Bueno (cgroups/namespaces) | Similar en práctica |
| **Performance I/O** | Overhead de emulación | Nativo (directamente al FS) | 2x mejor en Docker |

**Conclusión**: Docker es **5-10x más eficiente en recursos** que VMs para el mismo resultado en Extagram.

---

### 1.3 Anatomía Interna de Docker: Imágenes vs Contenedores

#### Concepto: Capas y Union FileSystem

Docker utiliza un sistema de archivos en capas (layered filesystem) que permite máxima eficiencia:

```
IMAGEN DOCKER (Plantilla)
┌─────────────────────────────────────────┐
│ Imagen Nginx:latest                     │
├─────────────────────────────────────────┤
│                                          │
│ Capa 1: Base OS (Debian/Ubuntu)         │
│ ├─ /bin, /lib, /etc                    │
│ └─ Tamaño: 80 MB                        │
│                                          │
│ Capa 2: Nginx installación              │
│ ├─ /usr/local/nginx                    │
│ └─ Tamaño: 25 MB                        │
│                                          │
│ Capa 3: Configuración Nginx             │
│ ├─ /etc/nginx/nginx.conf               │
│ └─ Tamaño: 0.1 MB                       │
│                                          │
│ TOTAL IMAGEN: 105 MB                    │
│                                          │
└─────────────────────────────────────────┘

CONTENEDOR EN EJECUCIÓN (Instancia)
┌─────────────────────────────────────────┐
│ Contenedor nginx_app_1                  │
├─────────────────────────────────────────┤
│                                          │
│ Capas READ-ONLY (de imagen)             │
│ ├─ Capa 1 (Base): 80 MB (compartida)   │
│ ├─ Capa 2 (Nginx): 25 MB (compartida)  │
│ └─ Capa 3 (Config): 0.1 MB (compartida)│
│                                          │
│ Capa READ-WRITE (contenedor)            │
│ ├─ Cambios durante ejecución            │
│ ├─ Logs, archivos temp                 │
│ └─ Tamaño: 5-20 MB (mientras corre)    │
│                                          │
│ OVERHEAD POR CONTENEDOR: 5-20 MB       │
│ (No 105 MB nuevos, solo delta)         │
│                                          │
└─────────────────────────────────────────┘

SEGUNDO CONTENEDOR (nginx_app_2)
┌─────────────────────────────────────────┐
│ Contenedor nginx_app_2                  │
├─────────────────────────────────────────┤
│                                          │
│ Capas READ-ONLY (misma imagen)          │
│ ├─ Capa 1 (Base): 80 MB (COMPARTIDA)   │
│ ├─ Capa 2 (Nginx): 25 MB (COMPARTIDA)  │
│ └─ Capa 3 (Config): 0.1 MB (COMPARTIDA)│
│                                          │
│ Capa READ-WRITE (contenedor 2)          │
│ ├─ Cambios independientes               │
│ └─ Tamaño: 5-20 MB                      │
│                                          │
│ OVERHEAD POR CONTENEDOR: 5-20 MB       │
│ (REUTILIZA capas base del primero)     │
│                                          │
└─────────────────────────────────────────┘

RESULTADO EN DISCO:
Sin Docker: 2 × 105 MB = 210 MB
Con Docker: 105 MB + 5 MB + 5 MB = 115 MB (45% menos)
```

---

#### Ventaja Crítica: Reutilización de Capas Base

En Extagram con 7 contenedores (S1-S7):

```
Imagen Nginx (Capa base): 80 MB
├─ S1: Nginx proxy → 80 MB + 10 MB (config) = 90 MB
├─ S2: Nginx app → 80 MB + 15 MB (config) = 95 MB
└─ S5: Nginx static → 80 MB + 8 MB (config) = 88 MB

SIN reutilización (VM): 90 + 95 + 88 = 273 MB
CON Docker (capas): 80 MB (base) + 10+15+8 MB (deltas) = 113 MB
AHORRO: 60% menos espacio en disco

EN MEMORIA:
SIN Docker: 3 × 500 MB = 1500 MB (desbordamiento)
CON Docker: 500 MB (imagen) + 3×50 MB (contenedores) = 650 MB
RESULTADO: Todo cabe en t3.micro
```

---

### 1.4 Ciclo de Vida: Imagen → Contenedor → Datos

```
FASE 1: DESARROLLO (En laptop del desarrollador)
┌────────────────────────────────────────────┐
│ 1. Escribir Dockerfile                     │
│ 2. docker build -t nginx:custom .          │
│ 3. docker run -p 80:80 nginx:custom        │
│ 4. Testear localmente                      │
│ 5. docker push registry/nginx:custom       │
└────────────────────────────────────────────┘
                    ↓
FASE 2: DISTRIBUCIÓN (Imagen en Docker Registry)
┌────────────────────────────────────────────┐
│ Docker Hub / AWS ECR (registry centralizado)│
│                                             │
│ Imagen: nginx:custom (105 MB)              │
│ Versión: v1.0.0                            │
│ Manifesto: capas + metadatos              │
│                                             │
│ (Todo disponible para descargar)           │
└────────────────────────────────────────────┘
                    ↓
FASE 3: DESPLIEGUE (AWS EC2)
┌────────────────────────────────────────────┐
│ docker pull registry/nginx:custom          │
│ docker run -d --name nginx_s1 \            │
│   -p 80:80 \                               │
│   -v /config:/etc/nginx/conf.d \           │
│   registry/nginx:custom                    │
│                                             │
│ Contenedor corriendo:                      │
│ ├─ Imagen: READ-ONLY                       │
│ ├─ Volumen: /config (archivo persistente)  │
│ └─ Recursos: 50 MB RAM                     │
└────────────────────────────────────────────┘
                    ↓
FASE 4: OPERACIÓN (Datos Persistentes)
┌────────────────────────────────────────────┐
│ Volumen Docker: Datos persistentes          │
│                                             │
│ /var/lib/docker/volumes/nginx_config/_data │
│ ├─ nginx.conf                              │
│ ├─ default.conf                            │
│ └─ ssl/                                    │
│                                             │
│ Si muere contenedor: Datos siguen aquí    │
│ docker run -v nginx_config:/etc/nginx ... │
│ → Nuevo contenedor accede a datos viejos   │
└────────────────────────────────────────────┘
```

---

### 1.5 Gestión de Recursos: cgroups y namespaces

Docker utiliza dos mecanismos de kernel Linux para aislar contenedores:

#### Cgroups (Control Groups): Límites de Recursos

```
Contenedor 1 (Nginx S1)
├─ CPU: 25% máximo (1 core de 4)
├─ Memoria: 256 MB máximo
├─ I/O Disk: 50 MB/s máximo
└─ Redes: 100 Mbps máximo

Contenedor 2 (PHP-FPM S2)
├─ CPU: 50% máximo
├─ Memoria: 512 MB máximo
├─ I/O Disk: 100 MB/s máximo
└─ Redes: 100 Mbps máximo

HOST FÍSICO (t3.micro - 1 vCPU, 1 GB RAM)
├─ Total CPU disponible: 100%
├─ Total RAM disponible: 1024 MB
├─ Distribuido dinámicamente según demanda
└─ Si Nginx usa <25%, PHP-FPM puede usar >50%

COMPORTAMIENTO:
• Contenedor que rebase límite: PAUSADO (cgroup freeze)
• No causará OOM del host (aislamiento)
• Otros contenedores pueden continuar
```

**Ventaja crítica en Extagram**: Si MySQL consume demasiada RAM, Nginx sigue funcionando.

---

#### Namespaces: Aislamiento de Procesos/Red/FS

```
NAMESPACE PID (Procesos)
┌────────────────────────────────────────────┐
│ Contenedor Nginx (PID namespace)           │
├────────────────────────────────────────────┤
│ PID 1 → nginx master                       │
│ PID 2 → nginx worker 1                     │
│ PID 3 → nginx worker 2                     │
│                                             │
│ (Vis internas: 1, 2, 3)                   │
│ (Vis externas desde host: 1234, 1235, 1236)
│                                             │
│ RESULTADO: Contenedor cree que es el único│
│ proceso en el sistema                      │
└────────────────────────────────────────────┘

NAMESPACE RED (Red)
┌────────────────────────────────────────────┐
│ Contenedor PHP-FPM (NET namespace)         │
├────────────────────────────────────────────┤
│ IP virtual: 172.17.0.3                     │
│ MAC única: 02:42:ac:11:00:03               │
│ Lo propio interfaz virtual (veth)          │
│ Puerto 9000 interno → 9000 (solo dentro)  │
│                                             │
│ Desde host:                                 │
│ - No ve puerto 9000 (namespaced)           │
│ - Acceso via docker network bridge         │
│ - docker exec nginx_s2 curl 172.17.0.3:9000│
│   → Funciona (en red docker)              │
│                                             │
│ - curl 172.17.0.3:9000 (desde fuera)      │
│   → No conecta (diferentes namespaces)     │
└────────────────────────────────────────────┘

NAMESPACE FILESYSTEM (FS)
┌────────────────────────────────────────────┐
│ Contenedor Nginx (MNT namespace)           │
├────────────────────────────────────────────┤
│ Root: / → /var/lib/docker/containers/.../│
│ /etc/nginx → Archivo propio, no compartido│
│ /var/cache → Contenedor puede escribir    │
│                                             │
│ GARANTÍA: No puede acceder a /etc del host│
│ No puede ver /home de usuarios             │
│ No puede modificar archivos externo        │
│ AISLAMIENTO COMPLETO A NIVEL FS            │
└────────────────────────────────────────────┘
```

---

## 2. ANÁLISIS COMPARATIVO: DOCKER VS MÁQUINAS VIRTUALES VS INSTALACIÓN NATIVA

### 2.1 Tabla Comparativa Directa

| Factor | Docker | Máquina Virtual | Instalación Nativa |
|---|---|---|---|
| **Overhead de recursos** | 50 MB por contenedor | 500 MB por VM | Nada (línea base) |
| **Densidad (en t3.micro)** | 8-15 contenedores | 1-2 VMs | N/A |
| **Boot time** | 1-5 segundos | 30-60 segundos | Irrelevante |
| **Reproducibilidad** | 100% garantizada | 95% (config manual) | 0% (configuración manual) |
| **Portabilidad** | Full (corre en todo) | VM-dependent | SO-dependent |
| **Curva aprendizaje** | Media (Dockerfile, docker-compose) | Alta (Hypervisor, VM config) | Baja (pero tedioso) |
| **Networking** | Virtual bridge, CNM | Virtual network | Sistema nativo |
| **Storage persistente** | Volúmenes Docker | Storage virtual | Disco host |
| **Escalabilidad horizontal** | Excelente (multi-host) | Posible (complejo) | Muy difícil |
| **Rollback/Versioning** | Trivial (docker pull vx.y) | Manual | Manual |
| **Aislamiento seguridad** | Bueno (namespaces) | Excelente (Hypervisor) | Ninguno |
| **Costo infraestructura** | Bajo (densidad) | Alto (overhead VM) | Bajo (pero ineficiente) |
| **Para Extagram MVP** | OPTIMAL | Viable | Funciona pero tedioso |

---

### 2.2 Consumo Real de Recursos: Caso Extagram

#### Escenario: 7 Contenedores en t3.micro (1 GB RAM, 1 vCPU)

**OPCIÓN A: Con Docker (Recomendado)**

```
Contenedor S1 (Nginx proxy)
├─ Límite cgroups: 128 MB
├─ Uso real: 50 MB
└─ Imagen: 10 MB (capas compartidas)

Contenedor S2 (Nginx + PHP-FPM app)
├─ Límite cgroups: 256 MB
├─ Uso real: 150 MB
└─ Imagen: 25 MB

Contenedor S3 (Nginx + PHP-FPM app, replica)
├─ Límite cgroups: 256 MB
├─ Uso real: 150 MB
└─ Imagen: 25 MB (reutiliza layers)

Contenedor S4 (PHP-FPM upload)
├─ Límite cgroups: 128 MB
├─ Uso real: 80 MB
└─ Imagen: 12 MB

Contenedor S5 (Nginx static)
├─ Límite cgroups: 64 MB
├─ Uso real: 40 MB
└─ Imagen: 8 MB

Contenedor S6 (Nginx static, replica)
├─ Límite cgroups: 64 MB
├─ Uso real: 40 MB
└─ Imagen: 8 MB (reutiliza)

Contenedor S7 (MySQL)
├─ Límite cgroups: 512 MB
├─ Uso real: 300 MB
└─ Imagen: 50 MB

Docker runtime + OS
├─ Kernel + Docker daemon: 150 MB
└─ Overhead: 50 MB

TOTAL MEMORIA ASIGNADA: 128 + 256 + 256 + 128 + 64 + 64 + 512 = 1408 MB
(Overconstrained, pero cgroups limitan dinámicamente)

TOTAL MEMORIA REAL: 50 + 150 + 150 + 80 + 40 + 40 + 300 + 50 + 150 = 960 MB
MARGEN: 1024 - 960 = 64 MB

STATUS: VIABLE (con margen ajustado)
```

---

**OPCIÓN B: Con 7 Máquinas Virtuales**

```
VM 1 (Nginx proxy)
├─ Kernel + OS: 500 MB
├─ Nginx: 50 MB
└─ Total: 550 MB

VM 2-7 (Otros 6)
├─ Mismo overhead: ~550 MB cada
└─ Total 6 × 550 MB = 3300 MB

TOTAL MEMORIA REQUERIDA: 550 + 3300 = 3850 MB
DISPONIBLE: 1024 MB

STATUS: IMPOSIBLE (3.8x desbordamiento)
```

---

**OPCIÓN C: Instalación Nativa (Todos en S1)**

```
Kernel Linux: 150 MB
Nginx (múltiple instancias): 100 MB
PHP-FPM (múltiple instancias): 200 MB
MySQL (shared): 300 MB
Aplicación + datos: 100 MB
Otros servicios: 100 MB

TOTAL: 950 MB

STATUS: FUNCIONA pero...
- Gestión manual de procesos (difícil)
- Sin aislamiento (una falla derriba todo)
- Versioning complicado
- Difícil escalar
```

---

### 2.3 Docker vs Máquinas Virtuales: Comparativa Profunda

#### Ciclo de Vida Completo

**Máquina Virtual Tradicional**:

```
1. Descargar ISO Ubuntu: 2-3 GB
2. Crear VM: 10-20 minutos
   ├─ Asignar disco virtual (20-50 GB)
   ├─ Configurar BIOS, boot
   └─ Instalar SO (15 minutos)
3. Instalar herramientas: 20-30 minutos
   ├─ apt-get update
   ├─ apt-get install nginx, php-fpm, mysql
   ├─ Configurar permisos
   └─ Ajustar recursos
4. Provisionar aplicación: 30-60 minutos
   ├─ Git clone / rsync
   ├─ Compilar si necesario
   ├─ Configurar direcciones IP
   └─ Verificar conectividad
5. Testing local
6. LISTO: Primero VM en 1-2 horas

SEGUNDA VM: Repetir TODOS los pasos (1-2 horas)
SIETE VMs: 7-14 horas de configuración manual

PROBLEMA: Si hay cambio, actualizar 7 VMs manualmente
```

---

**Docker (Containerización)**:

```
1. Escribir Dockerfile (15 minutos)
   ├─ FROM ubuntu:22.04
   ├─ RUN apt-get install nginx, php-fpm, mysql-client
   ├─ COPY aplicación /app
   └─ CMD ["/start.sh"]

2. docker build -t extagram:v1.0 (5 minutos)
   └─ Construye imagen localmente

3. docker run (1 segundo)
   └─ Contenedor corriendo

4. Testing (5 minutos)
   ├─ curl localhost:80
   ├─ docker logs extagram_s1
   └─ Verificar

5. LISTO: Primer contenedor en 25 minutos

SEGUNDA INSTANCIA: 1 segundo (docker run)
SIETE INSTANCIAS: 7 segundos

CAMBIO EN IMAGEN:
├─ Modificar Dockerfile (1 minuto)
├─ docker build (5 minutos)
├─ docker push registry/extagram:v1.1 (2 minutos)
├─ En producción: docker pull + docker run (3 segundos)
└─ TODOS los 7 contenedores actualizados en < 10 minutos

VENTAJA DOCKER: 70x más rápido en despliegue
```

---

#### Reproducibilidad: El Problema de Configuración Manual

```
MÁQUINAS VIRTUALES (Manual)
┌────────────────────────────────────────────┐
│ Dev laptop (Ubuntu 22.04)                  │
├────────────────────────────────────────────┤
│ Nginx: 1.24.0                              │
│ PHP: 8.1.2                                 │
│ MySQL: 8.0.25                              │
│ Debian libs: 2024-01-12                    │
│                                             │
│ Funciona: ✓ SÍ                             │
└────────────────────────────────────────────┘
                    ↓
┌────────────────────────────────────────────┐
│ AWS EC2 (Ubuntu 22.04, instalación nueva) │
├────────────────────────────────────────────┤
│ Nginx: 1.24.1 (newer)                     │
│ PHP: 8.1.3 (newer)                        │
│ MySQL: 8.0.26 (newer)                     │
│ Debian libs: 2024-01-15 (más nuevos)      │
│                                             │
│ Funciona: ? QUIZÁS (incompatibilidades)   │
│                                             │
│ Error común:                                │
│ "PHP session handler incompatible"        │
│ "MySQL wire protocol changed"             │
│ "Nginx config deprecated"                 │
│                                             │
│ Debugging: 2-4 horas                      │
└────────────────────────────────────────────┘

RESULTADO: "Funciona en mi laptop pero no en producción"
PRESUPUESTO PERDIDO: 2-4 horas de debugging
```

---

```
DOCKER (Reproducible)
┌────────────────────────────────────────────┐
│ Dockerfile especifica EXACTAMENTE:         │
├────────────────────────────────────────────┤
│ FROM ubuntu:22.04 @ digest:               │
│   sha256:3ab12a96d                        │
│   (imagen locked, versión exacta)         │
│                                             │
│ RUN apt-get install nginx=1.24.0-1.1~22.04│
│ RUN apt-get install php8.1=8.1.2-1~22.04 │
│ RUN apt-get install mysql-client=8.0.25  │
│                                             │
│ Resultado: Imagen determinística          │
│ Versiones locked                           │
└────────────────────────────────────────────┘
                    ↓
        (Construir imagen localmente)
                    ↓
┌────────────────────────────────────────────┐
│ MISMA imagen en AWS                        │
├────────────────────────────────────────────┤
│ docker pull extagram:v1.0                 │
│ (Descarga imagen EXACTA, byte-for-byte)   │
│                                             │
│ Nginx: 1.24.0 (EXACTO)                    │
│ PHP: 8.1.2 (EXACTO)                       │
│ MySQL: 8.0.25 (EXACTO)                    │
│                                             │
│ Funciona: ✓✓✓ GARANTIZADO                 │
│                                             │
│ Deployment time: 5 minutos                │
│ Debugging: 0 minutos                      │
└────────────────────────────────────────────┘

RESULTADO: "Funciona aquí, funciona allá" (garantizado)
PRESUPUESTO AHORRADO: 2-4 horas × número de deployments
```

---

### 2.4 Docker vs Instalación Nativa: Mantenibilidad

#### Actualización de Versiones

**Instalación Nativa**:

```
Escenario: Nginx 1.24.0 → 1.25.0 (seguridad crítica)

1. SSH a servidor: 30 segundos
2. sudo apt-get update: 1 minuto
3. sudo apt-get upgrade nginx: 2 minutos
4. Nginx se detiene → SITIO CAÍDO
5. Reconfigurar plugins, módulos: 10-20 minutos
6. sudo systemctl restart nginx: 30 segundos
7. Verificar: 2-3 minutos
8. TOTAL DOWNTIME: 5-10 minutos

Si hay error:
├─ Nginx no inicia
├─ Aplicación caída
├─ No hay rollback simple
├─ Debuggin manual: 30+ minutos
```

---

**Docker**:

```
Escenario: nginx:1.24 → nginx:1.25 (seguridad crítica)

1. Actualizar Dockerfile:
   FROM nginx:1.25 (cambiar 1 línea)

2. docker build -t extagram:v1.1 . (5 minutos)

3. docker pull extagram:v1.1 (AWS ECR, 2 minutos)

4. Estrategia Blue-Green:
   ├─ docker run -d --name nginx_s1_v1.1 ... (nuevo)
   ├─ Verificar health: curl localhost:8080 (OK)
   ├─ Cambiar load balancer: punto a v1.1
   ├─ ZERO DOWNTIME UPGRADE
   └─ Container v1.0 sigue corriendo (rollback instant)

5. Si error en v1.1:
   ├─ Load balancer apunta a v1.0
   ├─ 1 segundo: vuelto a estado anterior
   ├─ Debuggin sin presión

TOTAL DOWNTIME: 0 minutos
FACILIDAD ROLLBACK: Trivial (1 comando)
```

---

## 3. FUNCIONALIDADES CRÍTICAS PARA EXTAGRAM

### 3.1 Docker Compose: Orquestación Local

#### Concepto: Definir Stack Completo en YAML (EJEMPLO NO FINAL, SOLO ES UNA IDEA)

```yaml
# docker-compose.yml (Para desarrollo + Sprint 1-2)

version: '3.9'

services:

  # S1: Nginx Proxy
  nginx_proxy:
    image: nginx:1.25-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./config/nginx/proxy.conf:/etc/nginx/nginx.conf:ro
      - ./config/ssl:/etc/nginx/ssl:ro
    depends_on:
      - nginx_app_1
      - nginx_app_2
    networks:
      - extagram_network
    restart: always
    resources:
      limits:
        cpus: '0.25'
        memory: 128M
      reservations:
        cpus: '0.1'
        memory: 64M

  # S2: Nginx + PHP-FPM Aplicación 1
  nginx_app_1:
    image: extagram:app-v1.0
    expose:
      - "80"
    volumes:
      - app_data:/app
      - ./config/nginx/app.conf:/etc/nginx/sites-available/default:ro
    environment:
      - DB_HOST=mysql_db
      - DB_USER=extagram_app
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis_cache
    depends_on:
      - mysql_db
      - redis_cache
    networks:
      - extagram_network
    restart: always
    resources:
      limits:
        cpus: '0.5'
        memory: 256M
      reservations:
        cpus: '0.25'
        memory: 128M

  # S3: Nginx + PHP-FPM Aplicación 2 (replica)
  nginx_app_2:
    image: extagram:app-v1.0
    expose:
      - "80"
    volumes:
      - app_data:/app
      - ./config/nginx/app.conf:/etc/nginx/sites-available/default:ro
    environment:
      - DB_HOST=mysql_db
      - DB_USER=extagram_app
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis_cache
    depends_on:
      - mysql_db
      - redis_cache
    networks:
      - extagram_network
    restart: always
    resources:
      limits:
        cpus: '0.5'
        memory: 256M
      reservations:
        cpus: '0.25'
        memory: 128M

  # S4: PHP-FPM Upload
  php_upload:
    image: extagram:upload-v1.0
    expose:
      - "9001"
    volumes:
      - upload_data:/app/uploads
      - ./config/php-fpm/upload.conf:/etc/php/8.1/fpm/pool.d/upload.conf:ro
    environment:
      - DB_HOST=mysql_db
      - MAX_UPLOAD=50M
    depends_on:
      - mysql_db
    networks:
      - extagram_network
    restart: always
    resources:
      limits:
        cpus: '0.25'
        memory: 128M

  # S5-S6: Nginx Static (puede ser 1 en desarrollo)
  nginx_static:
    image: nginx:1.25-alpine
    expose:
      - "80"
    volumes:
      - ./public/static:/usr/share/nginx/html:ro
      - ./config/nginx/static.conf:/etc/nginx/nginx.conf:ro
    networks:
      - extagram_network
    restart: always
    resources:
      limits:
        cpus: '0.25'
        memory: 64M

  # S7: MySQL Database
  mysql_db:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
      - MYSQL_DATABASE=extagram_db
      - MYSQL_USER=extagram_app
      - MYSQL_PASSWORD=${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./config/mysql/my.cnf:/etc/mysql/conf.d/custom.cnf:ro
      - ./scripts/init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro
    networks:
      - extagram_network
    restart: always
    resources:
      limits:
        cpus: '1'
        memory: 512M
    ports:
      - "3306:3306"

  # Redis Cache (Bonus para performance)
  redis_cache:
    image: redis:7-alpine
    expose:
      - "6379"
    volumes:
      - redis_data:/data
    networks:
      - extagram_network
    restart: always
    resources:
      limits:
        cpus: '0.2'
        memory: 128M

volumes:
  mysql_data:
    driver: local
  app_data:
    driver: local
  upload_data:
    driver: local
  redis_data:
    driver: local

networks:
  extagram_network:
    driver: bridge
```

---

#### Uso en Práctica

```bash
# Desarrollo local (en laptop)
docker-compose up -d

# Resultado:
# [+] Running 6/6
#   ✓ nginx_proxy       Running
#   ✓ nginx_app_1      Running
#   ✓ nginx_app_2      Running
#   ✓ php_upload       Running
#   ✓ mysql_db         Running
#   ✓ redis_cache      Running

# Ver logs
docker-compose logs -f nginx_proxy

# Ejecutar comando en contenedor
docker-compose exec mysql_db mysql -u root -p -e "SELECT * FROM users;"

# Parar todo
docker-compose down

# Parar y borrar volúmenes (reset completo)
docker-compose down -v
```

---

#### Ventajas de Docker Compose para Extagram

1. **Toda la arquitectura en 1 archivo**: 100 líneas en lugar de documentación de 50 páginas
2. **Reproducibilidad garantizada**: Mismo stack en laptop, CI/CD, producción
3. **Manage fácil**: Un comando para iniciar, parar, debuggear
4. **Network automática**: Contenedores pueden hablar entre ellos sin configuración IP manual
5. **Volumes manejados**: Datos persistentes sin preocuparse de paths
6. **Escalabilidad**: `docker-compose up -d --scale nginx_app=5` crea 5 réplicas

---

### 3.2 Dockerfile: Definir Imagen Personalizada

#### Ejemplo: Imagen Personalizada Extagram App

```dockerfile
# Dockerfile (Para aplicación Extagram)

FROM php:8.1-fpm-alpine

# 1. Instalar dependencias
RUN apk add --no-cache \
    nginx \
    mysql-client \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    git \
    curl \
    && rm -rf /var/cache/apk/*

# 2. Compilar extensiones PHP
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo_mysql \
    bcmath

# 3. Instalar composer (package manager PHP)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 4. Copiar aplicación
COPY ./src /app

# 5. Instalar dependencias PHP
WORKDIR /app
RUN composer install --no-dev --optimize-autoloader

# 6. Copiar configuración
COPY ./config/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./config/nginx/app.conf /etc/nginx/conf.d/default.conf

# 7. Cambiar permisos
RUN chown -R www-data:www-data /app \
    && mkdir -p /var/log/nginx /var/run/nginx \
    && chown -R www-data:www-data /var/log/nginx /var/run/nginx

# 8. Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:80/health || exit 1

# 9. Exponer puerto
EXPOSE 80 9000

# 10. Usuario no-root por seguridad
USER www-data

# 11. Comando de inicio
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
```

---

#### Build de Imagen

```bash
# Construir imagen localmente
docker build -t extagram:app-v1.0 \
  --file Dockerfile \
  --build-arg BUILD_DATE=$(date -u +'%Y-%m-%dT%H:%M:%SZ') \
  .

# Resultado:
# [+] building with native buildkit
# [+] load build context
# [+] FROM php:8.1-fpm-alpine (cached, 2s)
# [+] RUN apk add --no-cache ... (cached, 3s)
# [+] COPY ./src /app (new, 1s)
# [+] RUN composer install (8s)
# [+] build complete in 15 seconds
# Built: extagram:app-v1.0 (445 MB)

# Ver imagen
docker images
# REPOSITORY    TAG         IMAGE ID     CREATED      SIZE
# extagram      app-v1.0    abc123def    2 min ago    445 MB
```

---

#### Multi-Stage Build: Optimizar Tamaño de Imagen

```dockerfile
# Dockerfile (Multi-stage)

# STAGE 1: Builder (Construcción, grande)
FROM php:8.1-fpm-alpine AS builder

WORKDIR /app

COPY ./src .
COPY ./composer.json ./composer.lock .

# Instalar composer + dependencias (incluye dev)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader

# Resultado STAGE 1: ~600 MB (con dependencias dev)


# STAGE 2: Runtime (Ejecución, pequeño)
FROM php:8.1-fpm-alpine

# Copiar SOLO lo necesario del STAGE 1
COPY --from=builder --chown=www-data:www-data /app /app

# Instalar extensiones + dependencias runtime (SOLO)
RUN apk add --no-cache nginx mysql-client \
    && docker-php-ext-install pdo_mysql

# Resultado STAGE 2: ~200 MB (sin dependencias dev)

WORKDIR /app
USER www-data

EXPOSE 80 9000
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
```

**Resultado**:
- Without multi-stage: 600 MB (incluye herramientas build)
- With multi-stage: 200 MB (solo runtime necesario)
- Ahorro: 66% de tamaño

---

### 3.3 Volúmenes: Persistencia de Datos

#### Problema: Contenedores son Efímeros

```
docker run nginx:latest

# Contenedor corre, procesa requests
# Docker stop nginx
# docker rm nginx

# ¿Qué pasó con los logs?
# ¿Y los archivos guardados?
# DESAPARECIERON (deletazo)

# Porque contenedor usa READ-WRITE LAYER (temporal)
# Cuando muere, capa se borra
```

---

#### Solución: Volúmenes Docker

```bash
# 1. Crear volumen (persistente)
docker volume create extagram_mysql_data

# 2. Usar volumen en contenedor
docker run -d \
  --name mysql_s7 \
  -v extagram_mysql_data:/var/lib/mysql \
  mysql:8.0

# 3. Datos se guardan en volumen (no en contenedor)

# 4. Si contenedor muere:
docker rm mysql_s7

# 5. Volumen sigue existiendo
docker volume ls
# extagram_mysql_data

# 6. Nuevo contenedor accede a datos viejos
docker run -d \
  --name mysql_s7_restored \
  -v extagram_mysql_data:/var/lib/mysql \
  mysql:8.0

# RESULTADO: Datos recuperados intactos
```

---

#### Tipos de Volúmenes

| Tipo | Ubicación | Persistencia | Use Case |
|---|---|---|---|
| **Named volume** | /var/lib/docker/volumes/name/_data | Permanente | MySQL, datos app |
| **Bind mount** | /host/path → /container/path | Permanente | Dev (código), config |
| **tmpfs mount** | RAM | Temporal | Cache, temp files |

---

#### Estrategia de Volúmenes para Extagram

```yaml
# docker-compose.yml (con volúmenes)

volumes:
  # MySQL: datos persistentes
  mysql_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /mnt/aws/mysql_data  # AWS EBS si es producción

  # Aplicación: código shared entre S2 y S3
  app_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /opt/extagram/src

  # Uploads: imágenes persistentes
  uploads_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /mnt/aws/uploads  # AWS S3 en producción real

  # Redis: cache (puede ser temporal, pero backup)
  redis_data:
    driver: local

# Ventaja: Si contenedor muere, datos siguen ahí
# Si host falla, backupear volúmenes a S3 automático
```

---

### 3.4 Networking: Comunicación entre Contenedores

#### Problema: Contenedores Aislados

```
Contenedor Nginx (S1)
├─ Necesita conectar a PHP-FPM (S2) en puerto 9000
├─ Pero ¿cuál es la IP de S2?
├─ Cada contenedor obtiene IP de 172.17.x.x (random)
├─ Si muere S2, se le asigna IP diferente
└─ Nginx no puede hardcodear IP

Contenedor PHP-FPM (S2)
├─ Necesita conectar a MySQL (S7) en puerto 3306
├─ Misma situación
├─ Hardcodear IP es frágil
└─ Necesita mecanismo de discovery
```

---

#### Solución: Docker Networks y DNS

```bash
# 1. Crear network (bridge)
docker network create extagram_net

# 2. Conectar contenedores a network
docker run -d --name nginx_s1 --network extagram_net nginx

docker run -d --name php_fpm_s2 --network extagram_net php:8.1-fpm

docker run -d --name mysql_s7 --network extagram_net mysql:8.0

# 3. Ahora pueden comunicarse por HOSTNAME (DNS integrado)

# 4. Desde Nginx (S1):
# Conectar a PHP-FPM:
fastcgi_pass php_fpm_s2:9000;  # ¡Usa hostname, no IP!

# 5. Desde PHP-FPM (S2):
# Conectar a MySQL:
$db = new PDO('mysql:host=mysql_s7;port=3306;dbname=extagram_db');

# Docker DNS resolver resuelve:
# php_fpm_s2 → 172.18.0.2
# mysql_s7 → 172.18.0.3
# Automáticamente, sin intervención manual
```

---

#### Networking en docker-compose

```yaml
# docker-compose.yml (networking automático)

services:
  nginx_proxy:
    networks:
      - extagram_network
    depends_on:
      - nginx_app_1

  nginx_app_1:
    networks:
      - extagram_network
    depends_on:
      - mysql_db
      - redis_cache

  mysql_db:
    networks:
      - extagram_network

  redis_cache:
    networks:
      - extagram_network

networks:
  extagram_network:
    driver: bridge

# Resultado:
# - Todos conectados a "extagram_network"
# - Pueden hablar por hostname automático
# - docker-compose resuelve DNS
```

---

## 4. CONCLUSIÓN TÉCNICA Y VIABILIDAD EN AWS

### 4.1 Por Qué Docker es la Elección Correcta para Extagram

| Criterio | Justificación |
|---|---|
| **Eficiencia de recursos** | 5-10x menos RAM que VMs; 8-15 contenedores en t3.micro |
| **Densidad de aplicaciones** | Stack completo (7 servicios) en 1 t3.micro viable con Docker |
| **Reproducibilidad garantizada** | Misma imagen = mismo resultado en todo lado (laptop, CI/CD, AWS) |
| **Velocidad de despliegue** | 25 minutos primer contenedor vs 1-2 horas primera VM |
| **Facilidad de escalado** | Horizontal trivial (docker-compose up --scale) |
| **Versionado y rollback** | Instant rollback a versión anterior (1 comando) |
| **Aislamiento seguridad** | Namespaces + cgroups previene escape entre contenedores |
| **Portabilidad** | Imagen funciona en laptop, CI/CD, AWS, DigitalOcean, etc. |
| **Curva aprendizaje media** | Dockerfile + docker-compose suficientes para MVP |
| **Costo infraestructura mínimo** | Densidad permite usar t3.micro (más económico) |
| **Integración AWS** | ECR (registry nativo), ECS (orquestación), Fargate |

---

### 4.2 Arquitectura Recomendada: Docker en Extagram

#### Sprint 1 (Local): Docker Compose

```bash
# Desarrollo local en laptop
cd extagram
docker-compose up -d

# Toda la arquitectura corre localmente
# Nginx proxy en localhost:80
# MySQL accesible en localhost:3306
# Ambiente de desarrollo completo
```

---

#### Sprint 2-3 (AWS): Escalado a Producción

**Opción A: EC2 + Docker Manual**

```bash
# En t3.micro AWS
ssh ec2-user@instance.ip

# Instalar Docker
sudo apt-get install docker.io docker-compose

# Clonar código
git clone https://github.com/asixc2/extagram.git

# Desplegar
docker-compose -f docker-compose.prod.yml up -d

# Resultado: 7 contenedores corriendo en 1 t3.micro
```

---

**Opción B: AWS ECS (Recomendado para Producción Futura)**

```
ECS Cluster (AWS managed)
├─ Task definition extagram:latest
├─ 7 tasks repartidos en EC2
├─ Auto-scaling habilitado
├─ Load balancer (ALB) frente
├─ CloudWatch logs centralizado
└─ Rollback a versión anterior en 2 clicks
```

---

### 4.3 Docker vs Alternativas: Viabilidad Final

```
COMPARATIVA FINAL: Viabilidad en t3.micro (1 GB RAM)

OPCIÓN 1: Docker (Recomendado)
├─ Uso RAM real: 960 MB
├─ Tiempo setup: 25 minutos
├─ Reproducibilidad: 100%
├─ Rollback: 1 segundo
├─ Escalado: docker-compose scale
├─ Costo: Bajo (densidad)
├─ Viabilidad: EXCELENTE
└─ Veredicto: **OPTIMAL PARA EXTAGRAM**

OPCIÓN 2: Máquinas Virtuales
├─ Uso RAM real: 3500+ MB (overflow)
├─ Tiempo setup: 7-14 horas
├─ Reproducibilidad: 70-80%
├─ Rollback: Manual (30+ minutos)
├─ Escalado: Manual, complejo
├─ Costo: Muy alto (overhead VM)
├─ Viabilidad: IMPOSIBLE en t3.micro
└─ Veredicto: No recomendado

OPCIÓN 3: Instalación Nativa
├─ Uso RAM real: 950 MB
├─ Tiempo setup: 3-5 horas
├─ Reproducibilidad: 0% (manual)
├─ Rollback: Manual (no existe)
├─ Escalado: Difícil
├─ Costo: Bajo en recursos pero alto en mantenimiento
├─ Viabilidad: Funciona pero tedioso
└─ Veredicto: No profesional, falta automation
```

---

## REFERENCIAS Y DOCUMENTACIÓN

[1] **Docker Official Documentation - Getting Started**  
URL: https://docs.docker.com/get-started/

[2] **Docker Compose Documentation**  
URL: https://docs.docker.com/compose/

---

Documento elaborado por: Ingeniero DevOps y Arquitecto de Infraestructura
Fecha: 19/01/2026 | Status: Apto para presentación oficial
Clasificación: Documento Técnico | Audiencia: Equipo técnico + Stakeholders

[Indice Principal de Arquitectura](./000-indice-arquitectura.md)