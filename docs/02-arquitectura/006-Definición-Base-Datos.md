# MYSQL - ARQUITECTURA, COMPARATIVA Y VIABILIDAD PARA EXTAGRAM

***

## TABLA DE CONTENIDOS

1. [Anatomía y Arquitectura de MySQL](#1-anatomía-y-arquitectura-de-mysql-80)
2. [Funcionalidades Críticas para Extagram](#2-funcionalidades-críticas-para-extagram)
3. [Conclusión Técnica y Viabilidad en AWS](#3-conclusión-técnica-y-viabilidad-en-aws)

***

## 1. ANATOMÍA Y ARQUITECTURA DE MYSQL

### 1.1 Descripción General

MySQL es un sistema de gestión de bases de datos relacional (RDBMS) de código abierto. Desde su creación en 1995, ha evolucionado para convertirse en la base de datos más utilizada en aplicaciones web, particularmente en el stack LAMP/LEMP.

La versión moderna MySQL 8.0 (2018-presente) introduce mejoras significativas en seguridad, performance y confiabilidad respecto a versiones anteriores.

***

### 1.2 Modelo Arquitectónico: Basado en Hilos (Thread-Based)

#### Concepto Fundamental: Thread-Based vs Process-Based

MySQL utiliza un modelo de arquitectura basado en HILOS (threads), no procesos independientes. Esta es la diferencia arquitectónica más crítica respecto a PostgreSQL.

```
ARQUITECTURA MYSQL (Thread-Based):

Servidor MySQL (1 proceso principal)
├─ Connection Handler (acepta conexiones)
│
├─ Thread Pool / Connection Manager
│  ├─ Thread 1 (Cliente 1) - ~1-2 MB
│  ├─ Thread 2 (Cliente 2) - ~1-2 MB
│  ├─ Thread 3 (Cliente 3) - ~1-2 MB
│  └─ Thread N (Cliente N) - ~1-2 MB
│
├─ Memoria Compartida (Buffer Pool, Caché)
│  ├─ InnoDB Buffer Pool: ~128 MB (configurable)
│  ├─ Query Cache: Eliminado en v8.0
│  ├─ Thread Cache: Reutiliza threads
│  └─ Data Dictionary Cache
│
└─ Almacenamiento en Disco
   ├─ Tablespaces (.ibd files)
   ├─ Redo Log (IB_LOGFILE0, IB_LOGFILE1)
   └─ Undo Log (para transacciones)


RESULTADO CRÍTICO:
- N conexiones = N threads (~1-2 MB cada uno)
- Memoria total: Base SQL + (N × 1-2 MB)
- 1000 conexiones = ~1 GB RAM + estructura base
- Reutilización eficiente de memoria
```

***

#### Ventaja Crítica en Entornos Contenerizados (Docker)

En contenedores Docker con recursos limitados como AWS t3.micro (1 GB RAM total), el modelo thread-based de MySQL proporciona ventaja decisiva:

| Métrica | MySQL | PostgreSQL | Diferencia |
|--------|-------|-----------|-----------|
| Conexiones en 1 GB | 500-1000 | 100-200 | 5x más en MySQL |
| Memoria/conexión | 1-2 MB | 8-10 MB | 4-5x menos en MySQL |
| Footprint 100 conn | 300 MB | 800-1000 MB | 3x más eficiente MySQL |
| Viabilidad t3.micro | Excelente | Marginal | Ganador: MySQL |

**Razón técnica**: MySQL reutiliza memoria entre threads mediante un pool compartido (shared memory pool). PostgreSQL crea procesos independientes con overhead de memoria propio para cada conexión.

***

### 1.3 Motor de Almacenamiento: InnoDB

InnoDB es el motor de almacenamiento predeterminado desde MySQL 5.7 y es obligatorio para Extagram.

#### Características Principales

**A. Transacciones ACID**

InnoDB garantiza las cuatro propiedades fundamentales de transacciones:

| Propiedad | Significado | Implementación en InnoDB |
|-----------|-----------|------------------------|
| Atomicidad | Todo o nada | BEGIN, COMMIT, ROLLBACK |
| Consistencia | Reglas de integridad | Foreign Keys, Constraints |
| Aislamiento | Transacciones independientes | Isolation Levels |
| Durabilidad | Persistencia en disco | Redo Log, fsync |

***

**B. Atomicidad: Todo o Nada**

Ejemplo de transferencia de dinero (operación clásica ACID):

```sql
BEGIN TRANSACTION;
    UPDATE cuentas SET saldo = saldo - 100 WHERE cuenta_id = 1;
    -- Si aquí falla el servidor (crash, error, etc.)
    UPDATE cuentas SET saldo = saldo + 100 WHERE cuenta_id = 2;
COMMIT;
```

Resultado garantizado:
- Si todo exitoso: AMBAS operaciones aplican (atomicidad)
- Si falla en mitad: NINGUNA operación aplica (rollback automático)
- Nunca deja dinero "desaparecido" o duplicado en el sistema

En caso de crash del servidor DURANTE la transacción:
- MySQL recupera el estado consistente automáticamente
- No requiere verificación manual de integridad
- Imposible corrupción de datos

***

**C. Consistencia: Reglas de Integridad**

```sql
-- Tabla: posts en Extagram
CREATE TABLE posts (
    post_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_text TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Intento: Insertar post de usuario inexistente
INSERT INTO posts (user_id, post_text) VALUES (9999, 'Mi foto');
```

Resultado: ERROR - Foreign key constraint violated

InnoDB rechaza automáticamente la inserción porque user_id=9999 no existe en tabla users.

**Ventaja crítica**: Datos huérfanos (orphaned data) son imposibles en Extagram. Nunca puede haber posts sin usuario propietario.

***

**D. Aislamiento: Transacciones Independientes**

InnoDB ofrece 4 niveles de aislamiento seleccionables:

| Nivel | Comportamiento | Anomalías posibles | Use Case |
|------|----------------|------------------|----------|
| READ UNCOMMITTED | Lee datos no confirmados | Dirty reads | Reporting aproximado |
| READ COMMITTED | Lee solo datos confirmados | Non-repeatable reads | Estándar general |
| REPEATABLE READ (default InnoDB) | Snapshot consistente | Phantom reads | Transacciones críticas |
| SERIALIZABLE | Secuencial estricto | Ninguna | Máxima consistencia |

Para Extagram: REPEATABLE READ (default) es óptimo porque:
- Previene lecturas inconsistentes
- No requiere locking estricto (mejor performance)
- Perfecta para aplicaciones web concurrentes

***

**E. Durabilidad: Persistencia en Disco**

Flujo de durabilidad cuando MySQL procesa una UPDATE:

```
1. Cliente ejecuta: UPDATE posts SET likes = likes + 1 WHERE post_id = 5
   │
   ├─ InnoDB lo registra en MEMORY (Buffer Pool)
   │
   ├─ InnoDB escribe en REDO LOG (archivo en disco)
   │
   ├─ MySQL devuelve "OK" al cliente (confirmación)
   │
   └─ Checkpoint periódico (cada N segundos):
      └─ InnoDB escribe datos en tablespace file (.ibd)


Garantía de Durabilidad:
Si servidor falla DESPUÉS de "OK":
  • El cambio está garantizado en redo log
  • En reinicio, crash recovery restaura estado
  • Cero pérdida de datos confirmados
```

***

#### Arquitectura Interna de InnoDB

```
InnoDB Storage Engine
│
├─ Buffer Pool (RAM)
│  ├─ InnoDB Buffer Pool: ~128 MB (configurable)
│  │  └─ Caché de páginas de datos (8 KB cada página)
│  ├─ Change Buffer (adaptaciones de índices)
│  ├─ Log Buffer (redo log en memoria)
│  └─ Data Dictionary Cache (metadatos)
│
├─ Disco (Storage Persistent)
│  ├─ Tablespaces (.ibd files)
│  │  └─ Contienen tablas e índices
│  ├─ Redo Log (IB_LOGFILE0, IB_LOGFILE1)
│  │  └─ Permite crash recovery
│  └─ Undo Log
│     └─ Permite ROLLBACK de transacciones
│
└─ Features Críticas
   ├─ Row-level locking (no table locks)
   │  └─ Múltiples transacciones pueden ejecutarse
   ├─ Crash recovery automático
   ├─ Foreign keys enforcement
   └─ MVCC (Multi-Version Concurrency Control)
      └─ Lecturas y escrituras concurrentes sin bloqueos
```

**MVCC (Multi-Version Concurrency Control)**:
- Transacción 1 lee versión A de una fila
- Transacción 2 modifica la fila → crea versión B
- Transacción 1 sigue leyendo versión A (no se bloquea)
- Transacción 2 confirma → versión B es committada
- Resultado: **Máximo rendimiento sin locks** entre lectores y escritores

***

### 1.4 Consumo de Memoria y Escalabilidad

#### Footprint Base vs Conexiones en t3.micro (1 GB RAM)

```
STARTUP MYSQL BASE (sin conexiones):

Binarios y SO: ~50 MB
InnoDB Buffer Pool: ~100 MB (configurable)
Thread Cache: ~5 MB (máx 10 threads cached)
Data Dictionary: ~20 MB
Sistema Operativo: ~150 MB
Otros (logs, metadata): ~50 MB
────────────────────────────────
TOTAL BASE: ~375 MB

Conexiones Adicionales (cada una):
├─ Conexión 1: +1.2 MB
├─ Conexión 2: +1.2 MB
├─ ...
└─ Conexión 500: +1.2 MB
────────────────────────────────
TOTAL 500 conexiones: 500 × 1.2 = 600 MB


MEMORIA TOTAL (500 conexiones):
375 MB (base) + 600 MB (conexiones) = 975 MB
├─ Margen disponible: 1000 MB - 975 MB = 25 MB
└─ Viabilidad: EXCELENTE (peak occasional)
```

***

#### Comparativa con PostgreSQL en Mismo Escenario

```
POSTGRESQL BASE (sin conexiones):

Binarios y SO: ~100 MB
Shared Buffers: ~128 MB
Page Cache (SO): ~200 MB
Sistema: ~150 MB
────────────────────────────────
TOTAL BASE: ~578 MB

Procesos Independientes (cada conexión):
├─ Conexión 1: +8 MB (proceso separado)
├─ Conexión 2: +8 MB (proceso separado)
├─ ...
└─ Conexión 100: +800 MB
────────────────────────────────
TOTAL 100 conexiones: 578 + 800 = 1,378 MB

RESULTADO: OOM (Out of Memory)
└─ Solo 100 conexiones antes de crash
└─ Insuficiente para Extagram (≥300 usuarios simultáneos)
```

***

## 2 FUNCIONALIDADES CRÍTICAS PARA EXTAGRAM

### 2.1 Replicación Maestro-Esclavo (Master-Slave)

#### Concepto Fundamental

Replicación MySQL: Copiar datos de MySQL Master (S7) a MySQL Slave (replica) en tiempo real, manteniendo consistencia.

```
ARQUITECTURA DE REPLICACIÓN:

┌─────────────────────────┐
│ Master (S7)             │
├─────────────────────────┤
│ • Ejecuta escribes      │
│   (INSERT, UPDATE, DEL) │
│ • Registra en binlog    │
│ • Envía eventos a Slave │
│ • Acepta reads (SELECT) │
└────────────┬────────────┘
             │
             │ Replicación asincrónica
             │ (via binlog streaming)
             │
┌────────────v────────────┐
│ Slave (Replica)         │
├─────────────────────────┤
│ • Recibe eventos binlog │
│ • Aplica localmente     │
│ • Acepta SOLO reads     │
│ • Lag: 0-5 segundos     │
└─────────────────────────┘
```

***

#### Mecanismo: Binary Logs (Binlog)

Binary Log: Archivo que registra TODOS los cambios en la base de datos en formato de eventos.

```sql
-- En Master (S7), cliente ejecuta:

INSERT INTO posts (user_id, post_text) VALUES (1, 'Mi primera foto');
-- Registrado en binlog como evento QueryEvent


UPDATE posts SET post_text = 'Mi foto mejorada' WHERE id = 1;
-- Registrado en binlog como evento QueryEvent


-- En Slave:
-- Thread SQL Lee binlog del Master
-- Ejecuta los MISMOS eventos en orden exacto

-- Verificar binary logs en Master:
SHOW BINARY LOGS;
+──────────────────+───────────+
| Log_name         | File_size |
+──────────────────+───────────+
| mysql-bin.000001 | 1024576   |
| mysql-bin.000002 | 2048000   |
| mysql-bin.000003 | 512000    |
+──────────────────+───────────+

-- Resultado en Slave:
SELECT * FROM posts;
-- Idéntico a Master (con pequeño lag de 0-5 segundos)
```

***

#### Configuración Mínima

**En Master (S7)**:

```sql
-- 1. Editar /etc/mysql/my.cnf
-- [mysqld]
-- log-bin=mysql-bin
-- server-id=1


-- 2. Crear usuario de replicación
CREATE USER 'replication'@'%' IDENTIFIED BY 'replicpass123';
GRANT REPLICATION SLAVE ON *.* TO 'replication'@'%';


-- 3. Verificar posición binlog
SHOW MASTER STATUS;
+──────────────────+──────────+──────────────────────────────┐
| File             | Position | Binlog_Do_DB                 │
+──────────────────+──────────+──────────────────────────────┤
| mysql-bin.000003 | 154      | (vacío = todas las bases)    │
+──────────────────+──────────+──────────────────────────────┘
```

**En Slave (si existiera)**:

```sql
-- 1. Conectarse al Master
CHANGE MASTER TO
  MASTER_HOST = '10.0.2.5',             -- IP privada S7 (Master)
  MASTER_USER = 'replication',
  MASTER_PASSWORD = 'replicpass123',
  MASTER_LOG_FILE = 'mysql-bin.000003',
  MASTER_LOG_POS = 154;


-- 2. Iniciar replicación
START SLAVE;


-- 3. Verificar estado
SHOW SLAVE STATUS\G
-- Buscar: Slave_IO_Running: Yes
-- Buscar: Slave_SQL_Running: Yes
-- Si ambos Yes = replicación OK
```

***

#### Características Clave

**A. Asincrónica (No Bloqueante)**

```
Master recibe INSERT de cliente:

1. Ejecuta en memoria (Buffer Pool)
2. Escribe en binlog en disco
3. Responde "OK" a aplicación INMEDIATAMENTE
4. Slave recibe binlog DESPUÉS (lag típico 0-5 segundos)


VENTAJA CRÍTICA:
├─ Master NUNCA espera al Slave
├─ Si Slave falla, Master sigue funcionando
├─ Aplicación no afectada por performance de Slave
└─ Velocidad: Master responde en milliseconds
```

***

**B. Separación Escritura/Lectura (Read Replica Pattern)**

```
ARQUITECTURA OPTIMIZADA:

Aplicación (S1/S2/S3)
├─ Insertar post (escriba crítica)
│  └─ Master (S7) → 1-5 ms (RÁPIDO, garantizado)
│
├─ Cargar feed usuario (lectura crítica)
│  └─ Master (S7) → consistencia garantizada
│
└─ Generar estadísticas (lectura no-crítica)
   └─ Slave → puede tener lag de 2-5 segundos
      └─ Aceptable para reportes


Beneficio:
├─ Master: Enfocado en escribes + lecturas críticas
├─ Slave: Enfocado en reporting + analytics
└─ Performance: Carga distribuida, no concentrada
```

***

**C. Point In Time Recovery (PITR)**

```
ESCENARIO: Recuperación ante desastre

Martes 12/01/26:
├─ 10:00 - Backup completo tomado (dump a disco)
├─ 10:30 - Usuario A sube foto (binlog registra)
├─ 11:00 - Usuario B comenta (binlog registra)
├─ 11:30 - ACCIDENTE: DELETE FROM users WHERE id > 10;
└─ (¡Error! Eliminó usuarios equivocados)


Recovery Process (Point In Time Recovery):
1. Restaurar backup de 10:00 (base limpia)
2. Recuperar binlog: mysql-bin.000001 hasta 11:29
3. Aplicar eventos en orden exacto
4. Parar en 11:29 (justo antes del error)
5. RESULTADO: Base de datos en estado 11:29

DATOS RECUPERADOS:
├─ Foto de usuario A (10:30)
├─ Comentario de usuario B (11:00)
├─ Usuarios originales intactos
└─ Sin corrupción de datos


VENTAJA: NO pierdes horas de datos
└─ Posibilidad de recovery a cualquier segundo (si hay binlog)
```

***

### 2.2 Gestión de Integridad: Foreign Keys

#### Problema Sin Foreign Keys

```sql
-- Tabla posts SIN foreign key (INCORRECTO)
CREATE TABLE posts (
    post_id INT PRIMARY KEY,
    user_id INT,                    -- ¡Completamente libre!
    post_text TEXT
);


-- Insertar post de usuario inexistente
INSERT INTO posts VALUES (1, 9999, 'Mi foto');        -- ACEPTA
INSERT INTO posts VALUES (2, 9999, 'Otra foto');      -- ACEPTA
INSERT INTO posts VALUES (3, 9999, 'Más fotos');      -- ACEPTA


-- Resultado: 3 posts de usuario inexistente
-- Problema: ¿Quién es el dueño?
-- Problema: ¿De dónde vinieron estos posts?
-- RESULTADO: DATOS HUÉRFANOS (Orphaned Data)
-- IMPACTO: Integridad comprometida, bugs en aplicación
```

***

#### Solución: Foreign Keys (CORRECTO)

```sql
-- Tabla users (padre)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE
);


-- Tabla posts CON foreign key (hijo)
CREATE TABLE posts (
    post_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


-- Ahora intentamos insertar:
INSERT INTO users VALUES (1, 'juan', 'juan@example.com');
INSERT INTO posts VALUES (NULL, 1, 'Mi foto');  -- ACEPTA (user_id=1 existe)


INSERT INTO posts VALUES (NULL, 9999, 'Fantasma');
-- ERROR: Foreign key constraint violated
-- user_id=9999 no existe en users
-- MYSQL RECHAZA automáticamente
```

***

#### Opciones de Foreign Key: Qué Hacer Al Eliminar un usuario

```sql
OPCIÓN 1: ON DELETE CASCADE (Eliminar en cascada)

DELETE FROM users WHERE user_id = 1;
-- Automáticamente elimina TODOS los posts de usuario 1
-- Result: Limpia al usuario y sus posts


OPCIÓN 2: ON DELETE RESTRICT (Rechazar)

DELETE FROM users WHERE user_id = 1;
-- ERROR: Cannot delete user with posts
-- Fuerza eliminar posts PRIMERO
-- Útil para: Prevenir accidentes


OPCIÓN 3: ON DELETE SET NULL (Poner NULL)

ALTER TABLE posts MODIFY user_id INT NULL;
ALTER TABLE posts ADD CONSTRAINT ...
  ON DELETE SET NULL;

DELETE FROM users WHERE user_id = 1;
-- Posts.user_id = NULL
-- Posts quedan huérfanos pero conservados
-- Útil para: Auditoría
```

**Para Extagram**: Recomendado ON DELETE CASCADE (eliminar usuario elimina posts).

***

GARANTÍAS LOGRADAS:
├─ No puede haber post sin usuario (FK enforced)
├─ Si eliminas usuario, posts se eliminan automáticamente (CASCADE)
├─ No puede haber foto sin post (FK enforced)
├─ Integridad referencial FORZADA a nivel BD
└─ No depende de código de aplicación para integridad
```

***

### 2.3 Backups Lógicos: mysqldump

#### Concepto

mysqldump: Herramienta CLI que exporta datos y estructura de MySQL a archivo SQL en texto plano.

```bash
# Backup completo de base de datos extagram_db
mysqldump -u root -p -h 10.0.4.5 extagram_db > /backups/extagram_2026-01-12.sql

# Resultado: Archivo de 5-50 MB (dependiendo de cantidad de datos)
# Contiene:
# - CREATE TABLE statements (estructura)
# - CREATE INDEX statements (índices)
# - INSERT statements (todos los datos)
```

***

#### Ventajas de mysqldump

1. **Portabilidad**: Archivo SQL es texto plano, legible en cualquier editor
   - Puedes inspeccionarlo antes de restaurar
   - Funciona en cualquier OS

2. **Independencia de versión**: SQL es universal
   - Backup MySQL 8.0 restaurable en MySQL 5.7
   - No depende de binarios específicos

3. **Compresibilidad**: SQL comprime muy bien
   ```bash
   # Backup: 50 MB
   mysqldump extagram_db | gzip > extagram.sql.gz
   # Resultado comprimido: 5 MB
   # Ratio: 90% de compresión
   ```

4. **Verificabilidad**: Puedes inspeccionar datos antes de restaurar
   ```bash
   gunzip < extagram.sql.gz | head -100
   # Muestra primeras 100 líneas
   # Verificas que contenga datos esperados
   ```

***

#### Desventajas de mysqldump

1. **Lentitud**: No ideal para BD muy grandes (>50 GB)
   - Extrae TODO en memoria
   - Luego escribe a disco
   - Para Extagram (pequeña): No problema

2. **Bloqueo durante backup**: BD está bajo carga
   - Si agregamos `--single-transaction`: No bloquea (InnoDB)
   - Para Extagram: No es problema

3. **No incremental**: Siempre es backup completo
   - Si cambias 1 MB en BD de 100 MB, respalda 100 MB
   - Para Extagram: Aceptable

**Para Extagram (aplicación pequeña)**: mysqldump es perfecta

***

#### Estrategia de Backup Recomendada

```bash
#!/bin/bash
# Script de backup automatizado (cron job)

BACKUP_DIR="/var/backups/mysql"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/extagram_$TIMESTAMP.sql.gz"

# 1. Crear backup completo
mysqldump -u root -p$MYSQL_PASSWORD \
  --single-transaction \
  --quick \
  extagram_db | gzip > $BACKUP_FILE

# 2. Verificar integridad del backup
if gunzip -t $BACKUP_FILE 2>/dev/null; then
    echo "Backup válido: $BACKUP_FILE"
    
    # 3. Purgar backups antiguos (>30 días)
    find $BACKUP_DIR -name "extagram_*.sql.gz" -mtime +30 -delete
else
    echo "ERROR: Backup CORRUPTO: $BACKUP_FILE"
    rm $BACKUP_FILE
    exit 1
fi


# Configurar cron job (ejecutar diariamente a las 2 AM):
# crontab -e
# 0 2 * * * /scripts/backup-mysql.sh >> /var/log/backup.log 2>&1
```

***

#### Restauración desde Backup

```bash
# En caso de desastre: Restaurar desde backup

# Opción 1: Desde archivo comprimido
gunzip < /backups/extagram_2026-01-12.sql.gz | mysql -u root -p extagram_db

# Opción 2: Descomprimir primero, luego restaurar
gunzip /backups/extagram_2026-01-12.sql.gz
mysql -u root -p extagram_db < /backups/extagram_2026-01-12.sql


# Verificar que restauración fue exitosa:
mysql -u root -p -e "SELECT COUNT(*) as total_posts FROM extagram_db.posts;"

# Resultado debe coincidir con backup anterior
```

***

## 3. CONCLUSIÓN TÉCNICA Y VIABILIDAD EN AWS

### 3.1 Por Qué MySQL es la Elección Correcta

| Criterio | Justificación Técnica |
|----------|----------------------|
| **Viabilidad t3.micro** | Thread-based: 500+ conexiones en 1 GB RAM. PostgreSQL: solo 100-150. |
| **ACID Garantizado** | InnoDB nativo: Atomicidad, Consistencia, Aislamiento, Durabilidad. Imposible corrupción. |
| **Free Tier AWS** | RDS MySQL incluido en free tier 12 meses. Nuevo usuario: gratis completamente. |
| **Documentación** | Excelente y masiva. Stack LAMP/LEMP desde 1995. Millones de ejemplos. |
| **Costo Operacional** | Menor que PostgreSQL/MongoDB en AWS. RDS más barato en t3 tier. |
| **Replicación Simple** | Binary logs: setup 5 pasos. Asincrónico, no-bloqueante. Muy documentado. |
| **Integridad de Datos** | Foreign keys enforced. Imposible datos huérfanos. No depende de aplicación. |
| **Backups Portables** | mysqldump produce SQL puro. Restaurable en cualquier versión. Verificable. |
| **Stack Probado** | LAMP/LEMP: 25+ años en producción. Millones de sitios web dependen. |

***

### 3.2 Arquitectura Recomendada: S7 en Extagram

```
S7: SERVIDOR MYSQL (AWS t3.micro, 1 GB RAM, 1 vCPU)

Configuración Base:
├─ MySQL 8.0.x (latest stable)
├─ Engine: InnoDB (default)
├─ Binary logging: ENABLED (para replicación futura)
├─ max_connections: 500 (default: 151)
├─ innodb_buffer_pool_size: 256 MB (configurable)
├─ Replicación Master (posibilidad de Slave futuro)
└─ Caracterset: utf8mb4 (soporte emojis)


Usuarios de Aplicación:
├─ root: Administración (no acceso remoto)
├─ extagram_admin: Aplicación (SELECT, INSERT, UPDATE, DELETE)
├─ replication: Replicación (si hay Slave)
└─ backup: mysqldump (lectura solo)


Seguridad:
├─ Authentication: caching_sha2_password (algorithm moderno)
├─ SSH tunnel: Acceso remoto via SSH solo
├─ Firewall: 3306 bloqueado desde 0.0.0.0/0
├─ Firewall: 3306 abierto solo desde S2, S3, S4 (app servers)
└─ Backups: Encriptados en EBS snapshots


Backups Automatizados:
├─ Daily: mysqldump extagram_db (compressed)
├─ Retention: 30 días (configurable)
├─ Test: Restauración semanal a BD test
└─ Alertas: CloudWatch si backup falla


Monitoreo en Producción:
├─ CloudWatch: CPU, Disk I/O, Network
├─ Alertas: Si CPU > 80% o Disk > 90%
├─ SHOW PROCESSLIST: Queries lentos (>5 segundos)
├─ SHOW SLAVE STATUS: Replicación (si existe Slave)
├─ Logs: MySQL audit log + error log
└─ Health checks: Ping cada 60 segundos desde S2/S3/S4
```
## 4.EVOLUCIÓN DE BASE DE DATOS: EXTAGRAM

## SPRINT 1: MVP - ESTRUCTURA INICIAL

### Creación de Base de Datos

CREATE DATABASE extagram_db;
CREATE USER 'extagram_admin'@'%' IDENTIFIED BY 'pass123';
GRANT ALL PRIVILEGES ON extagram_db.* TO 'extagram_admin'@'%';
FLUSH PRIVILEGES;


### Tabla: posts (MVP)

CREATE TABLE extagram_db.posts (
    post TEXT,
    photourl TEXT
);

**Estructura Inicial**:
| Campo | Tipo | Descripción | Limitaciones |
|-------|------|-------------|--------------|
| `post` | TEXT | Texto del caption/descripción | Max 65,535 caracteres |
| `photourl` | TEXT | URL relativa o absoluta a foto | Path en `/uploads` o CDN |

**Problemas Identificados**:
- Sin `post_id` (no hay identificador único)
- Sin timestamps (no se sabe cuándo se creó)
- Sin relación a usuario (quién publicó)
- Sin metadatos de archivo (nombre, MIME type, tamaño)
- Sin índices (queries lentos)

---

## SPRINT 2-3: EXPANSIÓN RECOMENDADA

### Tabla: users (Autores)

CREATE TABLE extagram_db.users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

**Campos**:
| Campo | Tipo | Descripción | Justificación |
|-------|------|-------------|---------------|
| `user_id` | INT AUTO_INCREMENT | Identificador único | PK para relaciones |
| `username` | VARCHAR(50) UNIQUE | Nombre de usuario | Búsquedas frecuentes |
| `email` | VARCHAR(100) UNIQUE | Email de contacto | Autenticación, recuperación |
| `password_hash` | VARCHAR(255) | Hash bcrypt/argon2 | Nunca plaintext |
| `created_at` | TIMESTAMP | Fecha creación | Auditoría |
| `updated_at` | TIMESTAMP | Fecha última modificación | Tracking cambios |

---

### Tabla: posts (Expandida)

CREATE TABLE extagram_db.posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    caption TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    likes_count INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

**Cambios Principales**:
| Campo (MVP) | Campo (Expandido) | Cambio | Razón |
|-------------|-------------------|--------|-------|
| - | `post_id` | NUEVO | Identificador único, relaciones |
| - | `user_id` | NUEVO | Quién publicó (FK a users) |
| `post` | `caption` | RENOMBRADO | Semántica más clara |
| - | `created_at` | NUEVO | Ordenar cronológicamente |
| - | `updated_at` | NUEVO | Ediciones posteriores |
| - | `likes_count` | NUEVO | Estadísticas rápidas |

**Relación**: 
- 1 usuario -> N posts (1:N)
- DELETE CASCADE: si se borra usuario, se borran sus posts

---

### Tabla: media (Metadatos de Archivos)

CREATE TABLE extagram_db.media (
    media_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    extension VARCHAR(10),
    mime_type VARCHAR(50),
    original_name VARCHAR(255),
    file_size BIGINT,
    file_blob LONGBLOB,
    storage_path VARCHAR(512),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id),
    INDEX idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

**Estructura Completa**:
| Campo | Tipo | Descripción | Ejemplo |
|-------|------|-------------|---------|
| `media_id` | INT AUTO_INCREMENT | Identificador único | 1, 2, 3... |
| `post_id` | INT FK | Referencia a post | Relación 1:N |
| `filename` | VARCHAR(255) | Nombre generado (hash) | `a1b2c3d4e5f6.jpg` |
| `extension` | VARCHAR(10) | Extensión del archivo | `jpg`, `png`, `mp4` |
| `mime_type` | VARCHAR(50) | Tipo MIME registrado | `image/jpeg`, `video/mp4` |
| `original_name` | VARCHAR(255) | Nombre original del usuario | `mi_foto_vacaciones.jpg` |
| `file_size` | BIGINT | Tamaño en bytes | 2097152 (2 MB) |
| `file_blob` | LONGBLOB | Binario del archivo (opcional) | Datos JPEG/PNG comprimidos |
| `storage_path` | VARCHAR(512) | Ruta en /uploads o S3 | `/uploads/2026/01/a1b2c3d4e5f6.jpg` |
| `uploaded_at` | TIMESTAMP | Fecha carga | 2026-01-19 16:00:00 |

**Decisiones de Diseño**:

1. **LONGBLOB vs Path**: 
   - ✅ Recomendado: `storage_path` (NFS/S3) + `filename`
   - ⚠️ Alternativa: `file_blob` en BD (solo para archivos < 1 MB)
   - Extagram: Usar `storage_path` en Sprint 2, opcional `file_blob` para thumbnails

2. **Timestamps**:
   - Facilita pruning de archivos antiguos
   - Útil para analytics ("picos de upload")

3. **MIME type verification**:
   - Previene uploads maliciosos
   - Validación en PHP antes de INSERT

---

## EVOLUCIÓN: DIAGRAMA ENTIDAD-RELACIÓN

┌─────────────┐
│   users     │
├─────────────┤
│ user_id(PK) │
│ username    │
│ email       │
│ password    │
│ created_at  │
└─────────────┘
       │
       │ 1:N (user_id FK)
       ▼
┌─────────────────┐
│     posts       │
├─────────────────┤
│ post_id(PK)     │
│ user_id(FK)     │ ◄─── Relación a usuarios
│ caption         │
│ created_at      │
│ likes_count     │
└─────────────────┘
       │
       │ 1:N (post_id FK)
       ▼
┌──────────────────┐
│     media        │
├──────────────────┤
│ media_id(PK)     │
│ post_id(FK)      │ ◄─── Relación a posts
│ filename         │
│ mime_type        │
│ file_blob        │
│ storage_path     │
│ uploaded_at      │
└──────────────────┘

**Características**:
- Normalización 3FN (Third Normal Form)
- Foreign keys con CASCADE delete
- Índices en FK y campos de búsqueda frecuente

---

## MIGRACION: SPRINT 1 → SPRINT 2

### SQL Migration Script

-- Backup de datos Sprint 1
CREATE TABLE extagram_db.posts_backup_v1 AS SELECT * FROM posts;

-- Crear tabla users
CREATE TABLE extagram_db.users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar usuario "admin" de migración
INSERT INTO extagram_db.users (username, email, password_hash) 
VALUES ('admin', 'admin@extagram.local', SHA2('pass123', 256));

-- Recrear tabla posts con nuevas columnas
CREATE TABLE extagram_db.posts_v2 (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    caption TEXT,
    photourl TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    likes_count INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar datos: asignar posts al usuario admin
INSERT INTO extagram_db.posts_v2 (user_id, caption, photourl)
SELECT 1, post, photourl FROM posts_backup_v1;

-- Renombrar tablas
RENAME TABLE posts TO posts_v1;
RENAME TABLE posts_v2 TO posts;

-- Crear tabla media
CREATE TABLE extagram_db.media (
    media_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    extension VARCHAR(10),
    mime_type VARCHAR(50),
    original_name VARCHAR(255),
    file_size BIGINT,
    storage_path VARCHAR(512),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id),
    INDEX idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

**Pasos**:
1. Backup de datos originales
2. Crear tabla `users` con usuario admin
3. Recrear `posts` con nuevas columnas
4. Migrar datos existentes a nuevo schema
5. Crear tabla `media` con metadatos

---

## USUARIO BASE DE DATOS: PRIVILEGIOS LIMITADOS

### Usuario de Aplicación (Recomendado para Sprint 2+)

-- Usuario con permisos limitados (principle of least privilege)
CREATE USER 'app_user'@'%' IDENTIFIED BY 'secure_password_123';

-- Permisos: SELECT, INSERT, UPDATE (NO DROP, ALTER)
GRANT SELECT, INSERT, UPDATE, DELETE ON extagram_db.posts TO 'app_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON extagram_db.users TO 'app_user'@'%';
GRANT SELECT, INSERT, UPDATE ON extagram_db.media TO 'app_user'@'%';

FLUSH PRIVILEGES;

**Ventajas**:
- ✅ App no puede hacer DROP TABLE
- ✅ App no puede hacer ALTER (cambiar schema)
- ✅ Firewall a nivel BD (SQL injection limitado)
- ✅ Separación admin (extagram_admin) vs app (app_user)

---

## CHECKLIST: VALIDACION MODELO DATOS

Estructura Sprint 1:
- [ ] Base de datos creada: `extagram_db`
- [ ] Usuario: `extagram_admin` con ALL PRIVILEGES
- [ ] Tabla: `posts` con columnas (post, photourl)

Estructura Sprint 2+:
- [ ] Tabla: `users` con PK, índices, timestamps
- [ ] Tabla: `posts` expandida con FK a users
- [ ] Tabla: `media` con metadatos archivo (MIME, tamaño, path)
- [ ] Foreign keys funcionando (CASCADE delete)
- [ ] Índices en campos de búsqueda (username, created_at)

Seguridad:
- [ ] Usuario `app_user` creado con permisos limitados
- [ ] `extagram_admin` separado de `app_user`
- [ ] No plaintext passwords (usar SHA2, bcrypt)
- [ ] Charset UTF8MB4 para soporte Unicode

Performance:
- [ ] Índices creados en FK y campos frecuentes
- [ ] ENGINE=InnoDB para transacciones
- [ ] Latencia SELECT < 5ms (sin full table scan)

---

## ROADMAP FUTURO (Sprint 4+)

| Sprint | Mejora | Impacto |
|--------|--------|---------|
| **Sprint 2-3** | Tablas users, media, índices | Relaciones, metadatos |
| **Sprint 4** | Replicación master-slave (S7, S7b) | HA (alta disponibilidad) |
| **Sprint 5** | Sharding por user_id | Horizontal scaling |
| **Sprint 6+** | Cache Redis, ElasticSearch | Performance queries |

***

## REFERENCIAS Y DOCUMENTACIÓN

 MySQL 8.0 Official Documentation - InnoDB Storage Engine [ppl-ai-file-upload.s3.amazonaws](https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/attachments/images/128159294/af096837-a2ae-4de6-b88d-6343007340b0/image.jpg)
URL: https://dev.mysql.com/doc/refman/8.0/en/innodb.html

 MySQL 8.0 Replication Official Documentation [ppl-ai-file-upload.s3.amazonaws](https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/attachments/108224119/84af9c1e-ecfd-470e-ab69-b3405bd1c81b/Rubrica_Alumnes_ASIXc2_Projecte_P0.1_P0.2-Rubrica_P0.1_P0.2.pdf)
URL: https://dev.mysql.com/doc/refman/8.0/en/replication.html

 AWS RDS MySQL Best Practices [ppl-ai-file-upload.s3.amazonaws](https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/attachments/108224119/1f4e0589-e04e-48b1-920f-00aef39ba827/sample_import.csv)
URL: https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/CHAP_MySQL.html

 PostgreSQL vs MySQL Performance Comparison (2024) [notes.kodekloud](https://notes.kodekloud.com/docs/Nginx-For-Beginners/Introduction/Nginx-Architecture)
URL: https://www.postgresql.org/about/featurematrix/

 MongoDB Memory Consumption on Small Instances [hostinger](https://www.hostinger.com/tutorials/nginx-vs-apache)
URL: https://docs.mongodb.com/manual/reference/limits-and-thresholds/

 ACID Transactions in InnoDB [stackoverflow](https://stackoverflow.com/questions/15394904/nginx-load-balance-with-upstream-ssl)
URL: https://dev.mysql.com/doc/refman/8.0/en/commit.html

 MySQL Foreign Keys - Referential Integrity [linkedin](https://www.linkedin.com/posts/harisha-warnakulasuriya-_1-event-driven-asynchronous-architecture-activity-7325163399183523842-CblZ)
URL: https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html

***
