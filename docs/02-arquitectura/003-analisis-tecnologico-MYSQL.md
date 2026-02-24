# MYSQL - ARQUITECTURA, COMPARATIVA Y VIABILIDAD PARA EXTAGRAM
---

## TABLA DE CONTENIDOS

1. [Anatomía y Arquitectura de MySQL](#1-anatomía-y-arquitectura-de-mysql-80)
2. [Análisis Comparativo: MySQL vs PostgreSQL vs MongoDB](#2-análisis-comparativo-mysql-vs-postgresql-vs-mongodb)
3. [Funcionalidades Críticas para Extagram](#3-funcionalidades-críticas-para-extagram)
4. [Conclusión Técnica y Viabilidad en AWS](#4-conclusión-técnica-y-viabilidad-en-aws)

---

## 1. ANATOMÍA Y ARQUITECTURA DE MYSQL

### 1.1 Descripción General

**MySQL** es un sistema de gestión de bases de datos relacional (RDBMS) de código abierto, publicado originalmente en 2019 por Oracle. Es la versión más moderna y segura del stack LAMP/LEMP que ha dominado la web durante más de 25 años.

---

### 1.2 Modelo Arquitectónico: Basado en Hilos (Thread-Based)

#### Concepto Fundamental: Thread-Based vs Process-Based

**MySQL utiliza un modelo de arquitectura basado en HILOS (threads), no procesos independientes.**

```
ARQUITECTURA MYSQL (Thread-Based):
┌─────────────────────────────────────────────────┐
│ SERVIDOR MYSQL (1 PROCESO)                      │
│                                                  │
│ ┌──────────────────────────────────────────────┐│
│ │ THREAD POOL / CONNECTION HANDLER              ││
│ │ (Reutilizable, ~1-2 MB por conexión)         ││
│ └──────────────────────────────────────────────┘│
│                                                  │
│ ┌──────────────────────────────────────────────┐│
│ │ MEMORIA COMPARTIDA (Buffer Pool, Caché)       ││
│ │ • InnoDB Buffer Pool: ~128 MB (configurable)  ││
│ │ • Query Cache: Eliminado en v8.0              ││
│ │ • Thread Cache: Reutiliza threads             ││
│ └──────────────────────────────────────────────┘│
│                                                  │
│ Cliente 1 → Thread 1 ──┐                         │
│ Cliente 2 → Thread 2   │                         │
│ Cliente 3 → Thread 3   ├─→ Memoria compartida   │
│ ...                    │   (Eficiente)          │
│ Cliente N → Thread N ──┘                         │
│                                                  │
└─────────────────────────────────────────────────┘

RESULTADO:
• N conexiones = N threads (~1-2 MB c/u)
• Memoria total: Base SQL + (N × 1-2 MB)
• 1000 conexiones = ~1 GB RAM + base
```

#### Ventaja Crítica en Entornos Contenerizados (Docker)

En **contenedores Docker** con recursos limitados (t3.micro AWS = 1 GB RAM):

| Escenario | MySQL | PostgreSQL |
|---|---|---|
| Conexiones simultáneas | 500-1000 | 100-200 |
| Memoria por conexión | ~1-2 MB | ~8-10 MB |
| Footprint total (100 conn) | 200-300 MB | 800 MB - 1 GB |
| **Viabilidad en t3.micro** |  **Perfecto** | **Marginal** |

**Razón**: MySQL reutiliza memoria entre threads (shared memory pool). PostgreSQL crea procesos independientes con overhead de memoria propio.

---

### 1.3 Motor de Almacenamiento: InnoDB

**InnoDB** es el motor de almacenamiento predeterminado de MySQL(desde MySQL 5.7).

#### Características Principales

**A. Transacciones ACID**

InnoDB garantiza las propiedades ACID:

| Propiedad | Significado | Implementación en InnoDB |
|---|---|---|
| **Atomicidad** | Todo o nada | Transactions: BEGIN, COMMIT, ROLLBACK |
| **Consistencia** | Reglas de integridad | Foreign Keys, Check Constraints |
| **Aislamiento** | Transacciones independientes | Isolation Levels: READ_COMMITTED, REPEATABLE_READ, etc. |
| **Durabilidad** | Datos permanentes | Redo Log, Flush to disk |

---

**B. Atomicidad: Todo o Nada**

```sql
-- Ejemplo: Transferencia de dinero entre cuentas

BEGIN TRANSACTION;
    UPDATE cuentas SET saldo = saldo - 100 WHERE id = 1;
    -- Si aquí falla (error, crash, etc.)
    UPDATE cuentas SET saldo = saldo + 100 WHERE id = 2;
COMMIT;

-- Resultado:
-- • Si todo exitoso: AMBAS operaciones aplican
-- • Si falla en mitad: NINGUNA operación aplica (ROLLBACK automático)
-- • Nunca deja dinero "desaparecido" o duplicado
```

**Ventaja**: En caso de crash de servidor durante transacción:
- MySQL recupera estado consistente (sin actualización parcial)
- No requiere verificación manual de integridad

---

**C. Consistencia: Reglas de Integridad**

```sql
-- Tabla: posts (en Extagram)
CREATE TABLE posts (
    post_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_text TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Intento: Insertar post de usuario inexistente
INSERT INTO posts (user_id, post_text) VALUES (9999, 'Mi foto');

-- Resultado: ERROR
-- Foreign key constraint violated: user_id=9999 no existe en users
-- InnoDB rechaza automáticamente, manteniendo consistencia
```

**Ventaja**: **Datos huérfanos (orphaned data) son imposibles**. Extagram no puede tener posts sin usuario.

---

**D. Aislamiento: Transacciones Independientes**

InnoDB ofrece 4 niveles de aislamiento:

| Nivel | Comportamiento | Use Case |
|---|---|---|
| **READ UNCOMMITTED** | Lee datos no confirmados (dirty reads) | Reporting: acepta datos aproximados |
| **READ COMMITTED** (predeterminado en algunos) | Lee solo datos confirmados | Aplicaciones estándar |
| **REPEATABLE READ** (default InnoDB) | Snapshot consistente por transacción | Transacciones críticas |
| **SERIALIZABLE** | Transacciones son estrictamente secuenciales | Máxima consistencia (más lento) |

**Para Extagram**: REPEATABLE READ (default) es perfecto.

---

**E. Durabilidad: Persistencia en Disco**

```
Flujo de Durabilidad InnoDB:

1. Usuario ejecuta: UPDATE posts SET ... WHERE ...
   ↓
2. InnoDB lo registra en MEMORY (Buffer Pool)
   ↓
3. InnoDB escribe en REDO LOG (disco)
   ↓
4. MySQL devuelve "OK" al cliente
   ↓
5. Checkpoint periódico: InnoDB escribe datos en tablespace (archivo .ibd)

Resultado: Incluso si servidor falla después de PASO 4:
   • Datos recuperables desde redo log
   • Crash recovery automático en reinicio
```

---

#### Arquitectura Interna de InnoDB

```
┌─────────────────────────────────────────────────────┐
│ InnoDB STORAGE ENGINE                               │
├─────────────────────────────────────────────────────┤
│                                                      │
│ ┌────────────────────────────────────────────────┐ │
│ │ MEMORIA (RAM)                                   │ │
│ │ • Buffer Pool (default 128 MB)                 │ │
│ │ • Change Buffer (adaptaciones de índices)     │ │
│ │ • Log Buffer (redo log)                        │ │
│ │ • Data Dictionary Cache                        │ │
│ └────────────────────────────────────────────────┘ │
│                                                      │
│ ┌────────────────────────────────────────────────┐ │
│ │ DISCO (Storage)                                │ │
│ │ • Tablespaces (.ibd files)                    │ │
│ │ • Redo Log (IB_LOGFILE0, IB_LOGFILE1)        │ │
│ │ • Undo Log (para ROLLBACK)                    │ │
│ └────────────────────────────────────────────────┘ │
│                                                      │
│ ┌────────────────────────────────────────────────┐ │
│ │ FEATURES CRÍTICAS                              │ │
│ │ • Row-level locking (no table locks)          │ │
│ │ • Crash recovery automatic                     │ │
│ │ • Foreign keys enforcement                     │ │
│ │ • MVCC (Multi-Version Concurrency Control)    │ │
│ └────────────────────────────────────────────────┘ │
│                                                      │
└─────────────────────────────────────────────────────┘
```

**MVCC (Multi-Version Concurrency Control)**:
- Transacción 1 lee versión A de row
- Transacción 2 modifica row → crea versión B
- Transacción 1 sigue leyendo versión A (no se bloquea)
- Transacción 2 confirma → versión B es commitada
- Resultado: **Máximo rendimiento sin locks** (readers y writers concurrentes)

---

### 1.4 Consumo de Memoria y Escalabilidad

#### Footprint Base vs Conexiones

En AWS t3.micro (1 GB RAM):

```
MySQL 8.0 Minimal:
├─ Binarios + Sistema: ~50 MB
├─ InnoDB Buffer Pool: ~100 MB (configurable)
├─ Thread Cache: ~5 MB (10 threads cached)
├─ Data Dictionary: ~20 MB
└─ Sistema Operativo: ~150 MB
   ────────────────────
   TOTAL BASE: ~325 MB

Conexiones Adicionales:
├─ Conexión 1: +1.2 MB
├─ Conexión 2: +1.2 MB
├─ ...
└─ Conexión 500: +1.2 MB
   ────────────────────
   TOTAL 500 conexiones: 500 × 1.2 = 600 MB

MEMORIA TOTAL (500 conexiones): 325 + 600 = 925 MB
(Cabe en t3.micro con margen)
```

**Si fuera PostgreSQL**:
```
PostgreSQL Base: ~100 MB
Conexión 1: +8 MB (proceso independiente)
Conexión 2: +8 MB
...
Conexión 100: +800 MB
TOTAL: 100 + 800 = 900 MB (¡casi todo!)

Conexión 101: OOM (Out of Memory)
```

---

## 2. ANÁLISIS COMPARATIVO: MYSQL VS POSTGRESQL VS MONGODB

### 2.1 Tabla Comparativa Directa

| Factor | MySQL 8.0 | PostgreSQL 15 | MongoDB 6.0 | Mejor para Extagram |
|---|---|---|---|---|
| **Modelo Datos** | Relacional (tablas) | Relacional (tablas) | Documental (JSON) | MySQL |
| **Arquitectura** | Threads (1 proceso) | Procesos (N procesos) | Documental | MySQL  |
| **Memoria/conexión** | 1-2 MB | 8-10 MB | Variable | MySQL |
| **ACID Nativo** | InnoDB | Sí | Requiere config |  MySQL |
| **Foreign Keys** | Enforced | Enforced | No soportado | MySQL/PostgreSQL |
| **Viabilidad t3.micro** | 500+ conn | 100-200 conn | Problemas | MySQL |
| **Curva aprendizaje** | Baja | Media | Baja | MySQL |
| **Documentación** | Excelente | Excelente | Buena | Empate |
| **AWS Free Tier** | RDS gratis | RDS gratis | Atlas pago | MySQL/PostgreSQL |
| **Costo operacional** | $ | $$ | $$$ | MySQL |

---

### 2.2 MySQL vs PostgreSQL: Análisis Detallado

#### Consumo de Recursos en Inactividad

```
ESTADO IDLE (sin transacciones):

MySQL:
├─ Proceso principal: ~50 MB
├─ Thread pool (10 threads): ~15 MB
├─ InnoDB Buffer Pool: ~128 MB (configurable)
└─ TOTAL: ~193 MB

PostgreSQL:
├─ Proceso principal: ~30 MB
├─ BGWriter + AutoVacuum: ~20 MB
├─ Shared Buffers: ~128 MB
├─ Página caché OS: ~200 MB (mucho menos controlado)
└─ TOTAL: ~378 MB (pero disperso en OS)
```

**Veredicto**: MySQL gasta MENOS memoria en estado idle.

---

#### Consumo bajo Carga (100 conexiones simultáneas)

```
WORKLOAD: 1000 queries/segundo, 100 conexiones simultáneas

MySQL 8.0:
├─ Base: 193 MB
├─ Conexiones: 100 × 1.2 MB = 120 MB
├─ Buffer Pool + datos: 150 MB
└─ TOTAL: ~463 MB
└─ CPU: 35-40%

PostgreSQL 15:
├─ Base: 378 MB
├─ Procesos nuevos: 100 × 8 MB = 800 MB
├─ Shared Buffers + caché: 150 MB
└─ TOTAL: ~1328 MB
└─ CPU: 50-60%
```

**Para t3.micro (1 GB)**:
- MySQL: Cómodo, puede manejar 500+ conexiones
- PostgreSQL: Apenas llega a 100 conexiones antes de OOM

---

#### Replicación: MySQL vs PostgreSQL

| Aspecto | MySQL | PostgreSQL |
|---|---|---|
| Método | Binary Logs (asincrónico) | WAL (Write-Ahead Log) |
| Simplicidad setup | Simple (5 pasos) | Más complejo |
| Overhead de red | Mínimo | Medio |
| Velocidad sync | Rápida | Más lenta que MySQL |
| Failover automático | Manual | Manual (requiere herramientas) |

**Para Extagram**: MySQL replicación es más directa y mejor documentada.

---

### 2.3 MySQL vs MongoDB: Relacional vs Documental

#### Modelo Conceptual

```
EXTAGRAM DATA MODEL:

OPCIÓN 1: Relacional (MySQL)
┌──────────────────────────────────────────┐
│ users                                     │
├──────┬──────────┬───────────────────────┤
│ id   │ username │ email                 │
├──────┼──────────┼───────────────────────┤
│ 1    │ juan     │ juan@example.com      │
│ 2    │ maria    │ maria@example.com     │
└──────┴──────────┴───────────────────────┘

┌────────────────────────────────────────────────────────┐
│ posts                                                   │
├────┬─────────┬──────────────┬──────────────────────────┤
│ id │ user_id │ post_text    │ created_at              │
├────┼─────────┼──────────────┼──────────────────────────┤
│ 1  │ 1       │ Mi primera   │ 2026-01-12 10:00:00    │
│ 2  │ 2       │ Hola mundo   │ 2026-01-12 10:30:00    │
│ 3  │ 1       │ Segunda foto │ 2026-01-12 11:00:00    │
└────┴─────────┴──────────────┴──────────────────────────┘

VENTAJA: Integridad forzada (no puedes eliminar usuario si tiene posts)
QUERY: SELECT u.username, p.post_text FROM users u JOIN posts p ON u.id = p.user_id


OPCIÓN 2: Documental (MongoDB)
┌────────────────────────────────────────────────────────────────────┐
│ posts_collection                                                    │
├─────────────────────────────────────────────────────────────────────┤
│ {                                                                   │
│   "_id": ObjectId("507f1f77bcf86cd799439011"),                    │
│   "username": "juan",                                              │
│   "user_id": ObjectId("507f1f77bcf86cd799439001"),                │
│   "post_text": "Mi primera foto",                                 │
│   "created_at": ISODate("2026-01-12T10:00:00Z"),                  │
│   "comments": [                                                    │
│     { "user": "maria", "text": "Bonita!" }                        │
│   ],                                                               │
│   "likes": ["user2", "user3"]                                     │
│ }                                                                   │
│ ...                                                                 │
│ }                                                                   │
└─────────────────────────────────────────────────────────────────────┘

PROBLEMA: Si cambias username en MongoDB, ¿actualizas en 1000 posts?
         Si eliminas usuario, ¿qué pasa con posts huérfanos?
RIESGO: Inconsistencia de datos
```

---

#### Riesgos de MongoDB en Servidor Pequeño

| Riesgo | Descripción | Impacto |
|---|---|---|
| **Consumo RAM** | MongoDB mantiene todo en memoria por default | t3.micro (1 GB) → capacidad limitada |
| **No ACID nativo** | Requiere aplicación + código extra | Bugs de integridad |
| **Datos huérfanos** | Sin foreign keys, usuarios pueden tener posts sin vincular | Data quality |
| **Complejidad queries** | Queries complejas menos optimizadas | Rendimiento degradado |
| **Scaling vertical** | Crece muy rápido en RAM con datos | Necesita upgrade rápido |

---

#### Ejemplo Real: El Problema de MongoDB en Extagram

```javascript
// MongoDB: Usuario sube foto
db.posts.insertOne({
  _id: ObjectId(),
  user_id: "user123",
  username: "juan",
  photo_url: "/uploads/photo1.jpg",
  created_at: new Date()
});

// Semanas después: Usuario decide cambiar username "juan" → "javier"
db.users.updateOne({ _id: "user123" }, { $set: { username: "javier" } });

// Resultado: ¿Qué pasa con los 50 posts anteriores?
// Opción A: Siguen diciendo "juan" (inconsistencia)
// Opción B: Actualizar todos (query lenta, riesgo)
// Opción C: Dejar al código de aplicación resolverlo (bugs)

// En MySQL/Relacional: Username está en tabla users
// Posts solo tiene user_id (FK)
// Actualizar nombre es instantáneo y consistente
```

---

### 2.4 Conclusión Comparativa

**Para Extagram (servidor pequeño, recursos limitados, ambiente ASIX)**:

| BD | Recomendación |
|---|---|
| **MySQL** | **ELEGIDA** - Ideal para t3.micro, ACID, relaciones claras |
| **PostgreSQL** | Posible pero consume más RAM, requiere optimización |
| **MongoDB** | No recomendada - Riesgos de integridad en entorno pequeño |

---

## 3. FUNCIONALIDADES CRÍTICAS PARA EXTAGRAM

### 3.1 Replicación Maestro-Esclavo (Master-Slave)

#### Concepto Fundamental

**Replicación**: Copiar datos de MySQL Master (S7) a MySQL Slave (si existiera) en tiempo real.

```
ARQUITECTURA DE REPLICACIÓN:

Master (S7)
├─ Ejecuta escribes (INSERT, UPDATE, DELETE)
├─ Registra cambios en Binary Log (binlog)
├─ Envía eventos a Slave
└─ Acepta reads (SELECT)

        ↕ (replicación asincrónica)

Slave (Esclavo, si hubiera)
├─ Recibe eventos del binlog
├─ Aplica cambios localmente
├─ Acepta SOLO reads (SELECT)
└─ Consistencia eventual (pequeño lag)
```

---

#### Mecanismo: Binary Logs (Binlog)

**Binary Log**: Archivo que registra TODOS los cambios en base de datos.

```sql
-- En Master (S7):

-- Evento 1: INSERT
INSERT INTO posts (user_id, post_text) VALUES (1, 'Mi primera foto');
-- → Registrado en binlog como evento

-- Evento 2: UPDATE
UPDATE posts SET post_text = 'Mi foto mejorada' WHERE id = 1;
-- → Registrado en binlog

-- En Slave:
-- Thread SQL lee binlog del Master
-- Ejecuta los MISMOS eventos en orden
SELECT * FROM posts;
-- Resultado: Idéntico al Master (con pequeño lag)

-- SHOW BINARY LOGS; en Master
| Log_name        | File_size |
|─────────────────|──────────│
| mysql-bin.000001| 1024576   |
| mysql-bin.000002| 2048000   |
| mysql-bin.000003| 512000    |
```

---

#### Configuración Mínima

**En Master (S7)**:
```sql
-- 1. Habilitar binary logging
-- (agregar a /etc/mysql/my.cnf)
-- [mysqld]
-- log-bin=mysql-bin
-- server-id=1

-- 2. Crear usuario de replicación
CREATE USER 'replication'@'%' IDENTIFIED BY 'replicpass123';
GRANT REPLICATION SLAVE ON *.* TO 'replication'@'%';

-- 3. Ver posición binlog
SHOW MASTER STATUS;
+──────────────────+───────+──────────────┬──────────────────+
| File             | Position | Binlog_Do_DB | Binlog_Ignore_DB |
|──────────────────|─────────│──────────────┼──────────────────|
| mysql-bin.000003 | 154      |              |                  |
+──────────────────+───────+──────────────┬──────────────────+
```

**En Slave (si existiera)**:
```sql
-- 1. Conectarse al Master
CHANGE MASTER TO
  MASTER_HOST = '10.0.2.5',      -- IP privada S7
  MASTER_USER = 'replication',
  MASTER_PASSWORD = 'replicpass123',
  MASTER_LOG_FILE = 'mysql-bin.000003',
  MASTER_LOG_POS = 154;

-- 2. Iniciar replicación
START SLAVE;

-- 3. Verificar estado
SHOW SLAVE STATUS\G
```

---

#### Características Clave

**A. Asincrónica (No Bloqueante)**

```
Master recibe INSERT:
1. Ejecuta en memoria
2. Escribe en binlog
3. Responde "OK" a aplicación inmediatamente
4. Slave recibe binlog DESPUÉS (pequeño lag)

VENTAJA: Master NUNCA espera al Slave
VELOCIDAD: Aplicación no afectada por Slave lento
```

---

**B. Separación Escritura/Lectura**

```
ARQUITECTURA DE EXTAGRAM (Optimizado):

Aplicación S1/S2/S3
├─ INSERT/UPDATE/DELETE → Master (S7) → RÁPIDO (1-5 ms)
├─ SELECT lectura-crítica → Master (S7) → CONSISTENCIA
└─ SELECT lectura-secundaria → Slave → DESCARGAR CARGA

Ejemplo:
// Cargar feed principal (reads críticos) → Master
$user_feed = SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 10;

// Generar estadísticas (reads no-críticos) → Slave
$top_posts = SELECT COUNT(*) as likes FROM posts GROUP BY user_id;
// Si hay lag de 2 segundos, no importa
```

---

**C. Point in Time Recovery (PITR)**

```
Si ocurre desastre:

Martes 12/01/26:
10:00 - Backup completo tomado (dump)
10:30 - Usuario A sube foto
11:00 - Usuario B comenta
11:30 - Accidente: DELETE FROM users WHERE id > 10; (¡ERROR!)

Recovery:
1. Restaurar backup de 10:00
2. Recuperar binlog desde 10:00 hasta 11:29
3. Aplicar eventos en orden → base de datos en estado 11:29
4. RESULTADO: Todos los posts, comentarios de A y B recuperados

Ventaja: NO pierdes horas de datos
```

---

### 3.2 Gestión de Integridad: Foreign Keys

#### Problema Sin Foreign Keys

```sql
-- Tabla posts SIN foreign key
CREATE TABLE posts (
    post_id INT PRIMARY KEY,
    user_id INT,                    -- ¡Libre!
    post_text TEXT
);

-- Insertar post de usuario inexistente
INSERT INTO posts VALUES (1, 9999, 'Mi foto');  -- ACEPTA
INSERT INTO posts VALUES (2, 9999, 'Otra foto'); -- ACEPTA
INSERT INTO posts VALUES (3, 9999, 'Más fotos'); -- ACEPTA

-- Resultado: 3 posts de usuario inexistente
-- ¿Quién es el dueño? ¿De dónde vinieron?
-- DATOS HUÉRFANOS (Orphaned Data)
```

---

#### Solución: Foreign Keys

```sql
-- Tabla users
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE
);

-- Tabla posts CON foreign key
CREATE TABLE posts (
    post_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Ahora:
INSERT INTO users VALUES (1, 'juan', 'juan@example.com');
INSERT INTO posts VALUES (NULL, 1, 'Mi foto');  -- ACEPTA

INSERT INTO posts VALUES (NULL, 9999, 'Foto fantasma');  -- RECHAZA
-- Error: Foreign key constraint violated

-- Si eliminamos usuario:
DELETE FROM users WHERE user_id = 1;
-- Opción: CASCADE → Elimina automáticamente posts de ese usuario
-- Opción: RESTRICT → Rechaza si hay posts
-- Opción: SET NULL → Pone posts.user_id = NULL
```

---

#### Reglas de Integridad para Extagram

```sql
-- Propuesta de esquema EXTAGRAM

CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
);

CREATE TABLE posts (
    post_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_text TEXT NOT NULL,
    photo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

CREATE TABLE photos (
    photo_id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50),
    file_size INT,
    storage_path VARCHAR(255),
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    INDEX idx_post_id (post_id)
);

-- GARANTÍAS:
-- No puede haber post sin usuario
-- Si eliminas usuario, sus posts se eliminan automáticamente
-- No puede haber foto sin post
-- Integridad referencial FORZADA a nivel BD (no confiar en app)
```

---

### 3.3 Backups Lógicos: mysqldump

#### Concepto

**mysqldump**: Herramienta que exporta datos y estructura a archivo SQL.

```bash
# Backup completo de base de datos extagram_db
mysqldump -u root -p -h 10.0.2.5 extagram_db > /backups/extagram_2026-01-12.sql

# Resultado: Archivo de ~5-50 MB (dependiendo de datos)
# Contiene:
# - CREATE TABLE statements
# - CREATE INDEX statements
# - INSERT statements con todos los datos
```

---

#### Ventajas

1. **Portabilidad**: Archivo SQL es texto plano, legible en cualquier editor
2. **Independencia**: No depende de versión MySQL exacta
3. **Compresibilidad**: SQL se comprime muy bien (gzip)
4. **Verificabilidad**: Puedes inspeccionar datos antes de restaurar

```bash
# Backup comprimido
mysqldump -u root -p extagram_db | gzip > extagram.sql.gz
# Tamaño: 50 MB → 5 MB (90% compresión)

# Restaurar
gunzip < extagram.sql.gz | mysql -u root -p extagram_db
```

---

#### Desventajas

1. **Lentitud**: No es ideal para BD muy grandes (>10 GB)
2. **Bloqueo**: Durante backup, BD está bajo carga
3. **No incremental**: Siempre es backup completo

**Para Extagram** (pequeña): Perfecta.

---

#### Estrategia de Backup Recomendada

```bash
# Script en S7 (cron job diario)
#!/bin/bash

BACKUP_DIR="/var/backups/mysql"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/extagram_$TIMESTAMP.sql.gz"

# 1. Backup completo
mysqldump -u root -p$MYSQL_PASSWORD extagram_db | gzip > $BACKUP_FILE

# 2. Verificar integridad
if gunzip -t $BACKUP_FILE; then
    echo "Backup válido: $BACKUP_FILE"
    
    # 3. Purgar backups antiguos (>30 días)
    find $BACKUP_DIR -name "extagram_*.sql.gz" -mtime +30 -delete
else
    echo "Backup CORRUPTO: $BACKUP_FILE"
    rm $BACKUP_FILE
fi

# Ejecutar diariamente:
# crontab -e
# 0 2 * * * /scripts/backup-mysql.sh
# (todos los días a las 2 AM)
```

---

## 4. CONCLUSIÓN TÉCNICA Y VIABILIDAD EN AWS

### 4.1 Por Qué MySQL es la Elección Correcta

| Criterio | Justificación |
|---|---|
| **Viabilidad t3.micro** | Thread-based: 500+ conexiones en 1 GB RAM |
| **ACID Garantizado** | InnoDB nativo: integridad de datos segura |
| **Free Tier AWS** | RDS MySQL incluido en free tier 1 año |
| **Documentación** | Excelente, comunidad masiva, fácil troubleshoot |
| **Costo Operacional** | Menor que PostgreSQL/MongoDB en AWS |
| **Replicación Simple** | Binlog setup en 5 pasos, asincrónico, no-bloqueante |
| **Integridad de Datos** | Foreign keys enforced, imposible datos huérfanos |
| **Backups Portables** | mysqldump produce SQL puro, fácil de restaurar |
| **Stack LAMP/LEMP** | 25+ años probado en producción |

---

### 4.2 Arquitectura Recomendada: S7 en Extagram

```
S7: MYSQL SERVER (AWS t3.micro)
┌─────────────────────────────────────────────────┐
│ Configuración:                                   │
├─────────────────────────────────────────────────┤
│ • MySQL 8.0.x (latest)                          │
│ • InnoDB (default storage engine)               │
│ • Binary logging ENABLED (para replicación)     │
│ • max_connections = 500 (default 151)           │
│ • innodb_buffer_pool_size = 256 MB (auto)      │
│ • Replicación Master (si hay Slave futuro)     │
│                                                  │
│ Seguridad:                                       │
│ • Authentication: caching_sha2_password         │
│ • User: root (sin password root remoto)         │
│ • User: extagram_admin (aplicación)             │
│ • User: replication (replicación)               │
│ • User: backup (mysqldump)                      │
│                                                  │
│ Backups:                                         │
│ • Daily: mysqldump extagram_db (compressed)    │
│ • Retention: 30 días                            │
│ • Test: Restauración semanal a BD test         │
│                                                  │
│ Monitoreo:                                       │
│ • CloudWatch: CPU, Disk, Network               │
│ • SHOW PROCESSLIST: queries lentos             │
│ • SHOW SLAVE STATUS: replicación (si aplica)   │
│                                                  │
└─────────────────────────────────────────────────┘
```

---

### 4.3 Comparativa: Viabilidad en t3.micro

```
ESCENARIO: 1000 usuarios activos, 100 conexiones simultáneas

MySQL 8.0:
├─ Memoria: 325 MB (base) + 100 MB (conexiones) = 425 MB
├─ Margen: 1000 MB - 425 MB = 575 MB libre
├─ CPU: 30-40% en carga normal
├─ Resultado: VIABLE CON MARGEN

PostgreSQL 15:
├─ Memoria: 378 MB (base) + 800 MB (100 procesos × 8 MB) = 1178 MB
├─ Margen: 1000 MB - 1178 MB = -178 MB
├─ Resultado: OOM (Out of Memory) a los 100 conexiones

MongoDB 6.0:
├─ Memoria: 400 MB (base) + 600 MB (datasets) = 1000 MB
├─ Margen: 0 MB
├─ Performance: Degradado sin margen
├─ Resultado: Marginal, no recomendado
```

**VEREDICTO**: **MySQL es la mejor opción** para t3.micro con proyección de crecimiento.

---

## REFERENCIAS Y DOCUMENTACIÓN

[1] **MySQL 8.0 Official Documentation - InnoDB Storage Engine**  
URL: https://dev.mysql.com/doc/refman/8.0/en/innodb.html  
---

[Indice Principal de Arquitectura](./000-indice-arquitectura.md)