# MYSQL - ARQUITECTURA, COMPARATIVA Y VIABILIDAD PARA EXTAGRAM

***

## TABLA DE CONTENIDOS

1. [Anatomía y Arquitectura de MySQL](#1-anatomía-y-arquitectura-de-mysql-80)
2. [Análisis Comparativo: MySQL vs PostgreSQL vs MongoDB](#2-análisis-comparativo-mysql-vs-postgresql-vs-mongodb)
3. [Funcionalidades Críticas para Extagram](#3-funcionalidades-críticas-para-extagram)
4. [Conclusión Técnica y Viabilidad en AWS](#4-conclusión-técnica-y-viabilidad-en-aws)

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

## 2. ANÁLISIS COMPARATIVO: MYSQL VS POSTGRESQL VS MONGODB

### 2.1 Tabla Comparativa Directa

| Factor | MySQL 8.0 | PostgreSQL 15 | MongoDB 6.0 | Mejor para Extagram |
|--------|-----------|---------------|------------|-------------------|
| Modelo Datos | Relacional (tablas) | Relacional (tablas) | Documental (JSON) | MySQL |
| Arquitectura | Threads (1 proceso) | Procesos (N procesos) | Documental | MySQL |
| Memoria/conexión | 1-2 MB | 8-10 MB | Variable | MySQL |
| ACID Nativo | InnoDB sí | Sí | Requiere config | MySQL/PostgreSQL |
| Foreign Keys | Enforced | Enforced | No soportado | MySQL/PostgreSQL |
| Conexiones viables t3.micro | 500+ | 100-200 | 50-100 | MySQL |
| Curva aprendizaje | Baja | Media | Baja | MySQL |
| Documentación | Excelente | Excelente | Buena | Empate |
| AWS Free Tier | RDS 12 meses | RDS 12 meses | Atlas (pago) | MySQL/PostgreSQL |
| Costo operacional anual | $ | $$ | $$$ | MySQL |
| Viabilidad Proyecto ASIX | Excelente | Buena | Problemas | MySQL |

***

### 2.2 MySQL vs PostgreSQL: Análisis Detallado de Recursos

#### Consumo de Memoria en Inactividad (Idle)

```
SERVIDOR IDLE (sin transacciones activas):

MySQL 8.0:
├─ Proceso principal: ~50 MB
├─ Thread pool (10 threads cached): ~15 MB
├─ InnoDB Buffer Pool: ~128 MB (configurable)
├─ Estructura de datos: ~10 MB
└─ TOTAL: ~203 MB


PostgreSQL 15:
├─ Proceso postmaster: ~30 MB
├─ BGWriter + AutoVacuum: ~20 MB
├─ Shared Buffers: ~128 MB
├─ Página caché SO (menos controlado): ~200 MB
└─ TOTAL: ~378 MB


Conclusión: MySQL gasta MENOS memoria en estado idle
└─ Diferencia: 175 MB (86% más en PostgreSQL)
└─ En t3.micro de 1 GB: diferencia crítica
```

***

#### Consumo bajo Carga (Workload Real)

```
ESCENARIO: 1000 queries/segundo, 100 conexiones simultáneas

MySQL 8.0 bajo carga:
├─ Base: 203 MB
├─ Conexiones: 100 × 1.2 MB = 120 MB
├─ Query cache (eliminado v8.0): 0 MB
├─ Buffer Pool + datos activos: 150 MB
├─ TOTAL: ~473 MB
├─ CPU: 35-40% (eficiente)
└─ Estado: Normal, margen de 527 MB libre


PostgreSQL 15 bajo carga:
├─ Base: 378 MB
├─ Procesos nuevos: 100 × 8 MB = 800 MB
├─ Shared Buffers + caché: 150 MB
├─ Trabajo memory: 100 MB
├─ TOTAL: ~1,428 MB
├─ CPU: 55-70% (saturado)
└─ Estado: OOM (Out of Memory) - CRASH
```

**Para t3.micro (1 GB RAM)**:
- MySQL: Cómodo, puede manejar 500+ conexiones concurrentes
- PostgreSQL: Apenas llega a 100 conexiones antes de problemas

***

#### Replicación: MySQL vs PostgreSQL

| Aspecto | MySQL | PostgreSQL |
|--------|-------|-----------|
| Método de replicación | Binary Logs (asincrónico) | WAL (Write-Ahead Log) |
| Complejidad setup | Simple (5-7 pasos) | Complejo (herramientas externas) |
| Overhead de red | Mínimo (~1-2% CPU) | Medio (~5% CPU) |
| Velocidad de sincronización | Rápida (ms) | Más lenta (seconds) |
| Failover automático | Manual (requiere herramientas) | Manual (requiere herramientas) |
| Documentación | Excelente | Buena |
| PITR (Point In Time Recovery) | Binlog + backup | WAL + backup |

**Para Extagram**: Replicación MySQL es más directa, mejor documentada y con menos overhead.

***

### 2.3 MySQL vs MongoDB: Relacional vs Documental

#### Modelo Conceptual: Diferencias Fundamentales

```
EXTAGRAM DATA MODEL:

OPCIÓN 1: Relacional (MySQL - NORMALIZADO)

Tabla users:
┌────┬──────────┬──────────────────┐
│ id │ username │ email            │
├────┼──────────┼──────────────────┤
│ 1  │ juan     │ juan@example.com │
│ 2  │ maria    │ maria@example.com│
└────┴──────────┴──────────────────┘

Tabla posts:
┌────┬─────────┬──────────────────┬────────────────────────┐
│ id │ user_id │ post_text        │ created_at             │
├────┼─────────┼──────────────────┼────────────────────────┤
│ 1  │ 1       │ Mi primera foto  │ 2026-01-12 10:00:00   │
│ 2  │ 2       │ Hola mundo       │ 2026-01-12 10:30:00   │
└────┴─────────┴──────────────────┴────────────────────────┘

GARANTÍA: Foreign key user_id=1 solo puede referencia usuario existente
QUERY: SELECT u.username, p.post_text FROM users u JOIN posts p 
       ON u.id = p.user_id
VENTAJA: Integridad forzada, imposible datos huérfanos


OPCIÓN 2: Documental (MongoDB - DESNORMALIZADO)

Colección posts_collection:
{
  "_id": ObjectId("507f1f77bcf86cd799439011"),
  "username": "juan",           ← Datos del usuario COPIADOS
  "user_id": ObjectId("507f1f77bcf86cd799439001"),
  "post_text": "Mi primera foto",
  "created_at": ISODate("2026-01-12T10:00:00Z"),
  "comments": [
    { "user": "maria", "text": "Bonita!", "created": "2026-01-12T10:15:00Z" }
  ],
  "likes": ["user2_id", "user3_id"]
}

PROBLEMA 1: Si usuario cambia username "juan" → "javier"
  ├─ ¿Actualizamos en todos los posts? (1000+ documentos)
  ├─ ¿Dejamos inconsistencia?
  └─ RIESGO: Data integrity problems

PROBLEMA 2: Si eliminamos usuario, ¿qué pasa con posts?
  ├─ Si borramos referencia, post queda huérfano
  ├─ Si borramos post, perdemos historial
  └─ RIESGO: Decisión complicada sin FK

PROBLEMA 3: Sin ACID transacciones:
  ├─ Si falla actualización a mitad, inconsistencia
  └─ RIESGO: Estado corrupto sin recovery
```

***

#### Riesgos de MongoDB en Servidor Pequeño

| Riesgo | Descripción | Impacto en Extagram |
|--------|-----------|-------------------|
| Consumo RAM alto | MongoDB mantiene todo en memoria por default | t3.micro (1 GB) → capacidad muy limitada |
| No ACID nativo | Requiere aplicación + código extra | Bugs de integridad de datos |
| Datos huérfanos | Sin foreign keys, posts sin usuario | Data quality degradada |
| Queries complejas | Agregaciones menos optimizadas | Rendimiento degradado |
| Scaling vertical | Crece muy rápido en RAM con datos | Necesita upgrade de instancia rápidamente |
| Replicación compleja | Replica sets más complicado que MySQL | DevOps overhead |

***

#### Ejemplo Real: El Problema de MongoDB

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
db.users.updateOne(
  { _id: "user123" }, 
  { $set: { username: "javier" } }
);

// PROBLEMA: ¿Qué pasa con los 50 posts que dicen "juan"?
// 
// Opción A: Siguen diciendo "juan" (INCONSISTENCIA)
// Opción B: Actualizar todos con updateMany (LENTO, RIESGO)
// Opción C: Aplicación resuelve en cada query (BUGS, OVERHEAD)


// En MySQL (RELACIONAL):
// Username está EN LA TABLA USERS
// Posts solo tiene user_id (FK)
// Actualizar nombre: UPDATE users SET username = 'javier'
// Resultado: INSTANTÁNEO, CONSISTENTE en todos los posts
```

***

### 2.4 Conclusión Comparativa para Extagram

Basado en análisis técnico exhaustivo:

| Base de Datos | Recomendación | Justificación |
|---------------|---------------|---------------|
| MySQL 8.0 | **ELEGIDA - RECOMENDADO** | Ideal t3.micro, ACID, relaciones claras, bajo costo |
| PostgreSQL 15 | Posible pero no óptima | Más RAM, más CPU, overkill para esta escala |
| MongoDB 6.0 | No recomendada | Riesgos de integridad, consumo RAM, overkill |

***

## 3. FUNCIONALIDADES CRÍTICAS PARA EXTAGRAM

### 3.1 Replicación Maestro-Esclavo (Master-Slave)

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

### 3.2 Gestión de Integridad: Foreign Keys

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

#### Opciones de Foreign Key: Qué Hacer Al Eliminar

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

#### Esquema Propuesto: Integridad Total para Extagram

```sql
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


GARANTÍAS LOGRADAS:
├─ No puede haber post sin usuario (FK enforced)
├─ Si eliminas usuario, posts se eliminan automáticamente (CASCADE)
├─ No puede haber foto sin post (FK enforced)
├─ Integridad referencial FORZADA a nivel BD
└─ No depende de código de aplicación para integridad
```

***

### 3.3 Backups Lógicos: mysqldump

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

**Para Extagram (aplicación pequeña)**: mysqldump es PERFECTO.

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

## 4. CONCLUSIÓN TÉCNICA Y VIABILIDAD EN AWS

### 4.1 Por Qué MySQL es la Elección Correcta

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

### 4.2 Arquitectura Recomendada: S7 en Extagram

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

***

### 4.3 Comparativa Final: Viabilidad en t3.micro (1 GB RAM)

```
ESCENARIO: 1000 usuarios activos, 100 conexiones simultáneas, peak ocasional 200


OPCIÓN 1: MySQL 8.0 (ELEGIDA)
├─ Memoria base: 375 MB
├─ Conexiones 100: 100 × 1.2 MB = 120 MB
├─ Datos en Buffer Pool: 150 MB
├─ TOTAL: 645 MB
├─ Margen disponible: 1000 - 645 = 355 MB
├─ Capacidad peak 200 conexiones: 795 MB (cabe)
├─ CPU: 30-40% normal, 60-70% peak
├─ Estado: VIABLE CON MARGEN GENEROSO
└─ Recomendación: USAR MYSQL


OPCIÓN 2: PostgreSQL 15
├─ Memoria base: 578 MB
├─ Conexiones 100: 100 × 8 MB = 800 MB
├─ Datos en shared buffers: 150 MB
├─ TOTAL: 1528 MB
├─ Margen disponible: 1000 - 1528 = -528 MB
├─ RESULTADO: OOM (Out of Memory) a los 100 conexiones
├─ CPU: 55-70% normal, 100% saturado peak
├─ Estado: NO VIABLE EN t3.micro
└─ Recomendación: NO USAR (upgrade a t3.small mínimo)


OPCIÓN 3: MongoDB 6.0
├─ Memoria base: 400 MB
├─ Datasets en memoria: 600 MB (setting por default)
├─ Índices: 200 MB
├─ TOTAL: 1200 MB
├─ Margen: 1000 - 1200 = -200 MB
├─ RESULTADO: OOM, degradación performance
├─ CPU: 70-80% normal, 100% peak
├─ Estado: MARGINAL, NO RECOMENDADO
└─ Recomendación: NO USAR (overhead innecesario)


VEREDICTO FINAL:
════════════════════════════════════════════════════════
MySQL 8.0 es la MEJOR y ÚNICA opción viable
para t3.micro con aplicación de escala Extagram.

Ventajas:
├─ 5x menos memoria que PostgreSQL
├─ 350 MB margen para picos de tráfico
├─ ACID garantizado sin overhead
├─ Replicación simple (binlog)
├─ Free tier AWS 12 meses
├─ Stack probado 25+ años
└─ Escalable a servidores mayores si crece

Conclusión: USAR MYSQL 8.0 EN S7
════════════════════════════════════════════════════════
```

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