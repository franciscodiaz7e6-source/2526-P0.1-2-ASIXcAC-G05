# PHP-FPM (FASTCGI PROCESS MANAGER)

## Tabla de contenidos

1. [Funcionamiento Interno y Arquitectura](#1-funcionamiento-interno-y-arquitectura)
2. [Integracion con Nginx](#2-integracion-con-nginx)
3. [Comparativa: PHP-FPM vs mod_php](#3-comparativa-php-fpm-vs-modphp)
4. [Rendimiento y Escalabilidad](#4-rendimiento-y-escalabilidad)

---

## 1. FUNCIONAMIENTO INTERNO Y ARQUITECTURA

### 1.1 Definicion: PHP-FPM y FastCGI

PHP-FPM (FastCGI Process Manager) es un proceso gestor alternativo de PHP que implementa el protocolo FastCGI. Es un daemon (servicio en segundo plano) que actua como intermediario entre el servidor web (Nginx) y los scripts PHP que necesitan ser procesados.

FastCGI es un protocolo de comunicacion estandar que define como un servidor web (cliente FastCGI) solicita a un proceso PHP (servidor FastCGI) que ejecute un script y devuelva el resultado. Fue creado como mejora sobre CGI clasico, resolviendo problemas de rendimiento mediante reutilizacion de procesos.

Diferencia clave respecto a CGI tradicional:

CGI Clasico:
- Nueva instancia de proceso PHP por cada solicitud.
- Overhead de fork/exec extremadamente alto.
- ~100-300 ms por solicitud (solo startup).
- Inviable en entornos con tráfico moderado-alto.

FastCGI/PHP-FPM:
- Procesos persistentes que atienden multiples solicitudes.
- Reutilizacion de procesos (no hay fork por solicitud).
- ~1-10 ms overhead de comunicacion.
- Viables en entornos de alto trafico.

### 1.2 Arquitectura: Master-Worker Process Model

PHP-FPM utiliza un modelo maestro-trabajador que es conceptualmente similar al de Nginx.   

Cada Worker:
   - Espera solicitudes FastCGI en su socket.
   - Procesa 1 solicitud a la vez.
   - Cuando termina, espera siguiente solicitud.
   - Ciclo de vida: Inicia, procesa N solicitudes, se termina.
   - No es multi-hilo (single-threaded).

Caracteristicas del Master Process:

- Aislado de procesamiento: No toca logica de negocio.
- Manejo de senales: Puede reinicar workers sin downtime.
- Escalabilidad automatica: Puede crear/destruir workers segun demanda (dynamic mode).
- Monitoreo: Detecta workers muertos, los reactiva automaticamente.

Caracteristicas de Worker Processes:

- Independientes: Fallo de 1 worker no afecta a otros.
- Persistent: Reutilizan memoria entre solicitudes (caches PHP, conexiones BD)
- Single-threaded: 1 solicitud a la vez, simplicidad, estabilidad
- Configurables: Cada pool puede tener settings distintos (uid/gid, memory_limit, etc.)

### 1.3 Flujo de una Solicitud: Desde Entrada hasta Respuesta

Flujo Completo: Cliente -> Nginx -> PHP-FPM -> Respuesta

Tiempo Total:
- Red Nginx -> PHP-FPM: 0.1-0.5 ms (socket local).
- Ejecucion PHP: 20-100 ms.
- Red Nginx -> Cliente: 10-50 ms.
- Total: 30-150 ms.

Puntos Criticos del flujo:

1. No es HTTP entre Nginx y PHP-FPM
   - FastCGI es protocolo binario mucho mas eficiente.
   - Headers HTTP se convierten a variables CGI.
   - Menos datos en la red, mas rapido.

2. Reutilizacion de Worker Process
   - Mismo worker procesa 100s de solicitudes en su vida.
   - No hay overhead de fork/exec (a diferencia de CGI).
   - Caches internas (opcodes PHP, conexiones BD) se reutilizan.

3. Independencia de Nginx
   - Si PHP-FPM cae, Nginx deja de servir .php (pero sigue sirviendo estaticos).
   - Si Nginx cae, PHP-FPM continua corriendo (sin proposito).
   - Esta independencia es arquitecturalmente limpia.

### 1.4 Pool Management (Concepto Fundamental)

PHP-FPM introduce concepto de "pools" que es revolucionario respecto a mod_php:

**¿Que es un Pool?**

Un pool es un grupo independiente de worker processes con configuracion propia.

Beneficios de Pools:

1. Aislamiento de recursos
   - Pool extagram.php: limites propios (10 workers max).
   - Pool upload.php: limites propios (5 workers, memory_limit mas alto).
   - Si upload se vuelve loco -> no afecta al resto.

2. Ejecucion con UID distinto
   - Pool www: corre como user www-data.
   - Pool upload: corre como user upload_user.
   - Reemplaza viejo concepto de safe_mode de PHP.
   - Aislamiento de seguridad: un pool comprometido no accede a otro.

3. Configuraciones distintas por tipo de trabajo
   - Scripts rapidos: memory_limit bajo, workers muchos.
   - Scripts pesados (video processing): memory_limit alto, workers pocos.
   - Optimizacion granular por caso de uso.

---

## 2. INTEGRACION CON NGINX

Nginx y PHP-FPM son complementarios (no redundantes):

NGINX:
- Lee archivos del disco
- Sirve contenido estatico (HTML, CSS, imagenes)
- Actua como reverse proxy
- NO ejecuta PHP

PHP-FPM:
- Ejecuta codigo PHP
- Genera HTML dinamico
- Accede a BD
- NO entiende HTTP nativo

Ventaja de esta separacion:

1. Responsabilidades claras
   - Nginx: Eficiente sirviendo estaticos.
   - PHP-FPM: Eficiente ejecutando PHP.
   - Cada uno hace lo que es bueno.

2. Escalabilidad independiente
   - Si crece trafico PHP -> agregar workers PHP-FPM.
   - Si crece trafico estatico -> mas Nginx (costo cero casi).

3. Seguridad en capas
   - PHP-FPM no expuesto a internet (socket local).
   - Nginx es unica "cara" publica.

## 3. Comparativa: PHP-FPM vs MOD_PHP

### 3.1 Arquitectura: Modelo Viejo vs Modelo Moderno

MOD_PHP (Apache Module, Modelo Viejo):

Caracteristicas:
- 1 proceso Apache por cliente (o multiplos con prefork)
- PHP incrustado en cada proceso Apache
- El proceso completo es 30-50 MB
- Incluso si solicitud es .html, lleva modulo PHP en memoria
- Restarting requiere matar todos los procesos

PHP-FPM (Modelo Moderno):

Caracteristicas:
- Nginx extremadamente ligero (solo HTTP, proxy, estaticos)
- PHP-FPM procesos mas pequenos (solo PHP)
- Solo cuando se NECESITA PHP se usa CPU/memoria PHP
- Nginx puede recargar sin afectar PHP-FPM

### 3.2 Aislamiento y Seguridad

MOD_PHP: Aislamiento Debil

- PHP corre con UID de Apache (www-data)
- Si PHP se compromete -> toda sesion Apache comprometida
- No hay isolacion entre scripts de usuarios diferentes
- Un script PHP malicioso puede:
  - Leer archivos de otros usuarios
  - Modificar configuracion de Apache
  - Afectar otros sitios en el mismo servidor

PHP-FPM: Aislamiento Fuerte

Pool 1: Sitio web A (extagram.itb)
- User: www-data
- UID: 33
- Acceso: Solo /var/www/extagram/

Pool 2: Sitio web B (otro.itb)
- User: otro_user
- UID: 1001
- Acceso: Solo /var/www/otro/

Beneficios:
- Arquitecto PHP puede definir que archivos accede
- Sitio comprometido no accede a otro sitio
- Mejor para entornos compartidos (hosting)

Para Extagram (single-site): Beneficio moderado pero IMPORTANTE por futura escalabilidad.

### 3.3 Escalabilidad Vertical y Horizontal

MOD_PHP: Escalabilidad Limitada

Escalar requiere:
1. Agregar mas Nginx (load balancer complejo)
2. O agregar mas memoria al servidor (limites fisicos)
3. O aumentar procesos Apache (mas memoria usada)
4. Siempre hay ceiling (max_clients = maxima concurrencia)

PHP-FPM: Escalabilidad Elastica

Escalar es simple:
1. Agregar mas workers PHP-FPM (dinamicamente)
2. O distribuir PHP-FPM en multiples servidores
3. Nginx balancea entre ellos (nativo)

Con Apache seria mas complejo (licencias, configuracion, etc.)

---

## 4. Rendimiento y Escalabilidad

### 4.1 Modos de Gestion de Procesos PHP-FPM

PHP-FPM ofrece 3 modos para spawning de workers:

**MODO 1: STATIC (Fijo)**

Comportamiento:
- Exactamente 16 workers levantados AL INICIAR
- Nunca varían de numero
- Si 1 worker muere -> respawned automaticamente
- Siempre 16 procesos en memoria

Ventajas:
- Predecible (siempre mismo consumo memory)
- Rapido (no creacion/destruccion dinamica)
- Simple monitorear

Desventajas:
- Desperdicia memoria en horas bajas de trafico
- Si tráfico sube mas de capacidad -> cola larga

Cuando usar:
- Servidores dedicados con trafico estable
- Máquinas con RAM suficiente
- Desarrollo/testing (predictibilidad)

**MODO 2: DYNAMIC (Dinamico)**

Comportamiento:
- Inicia con 3 workers
- Si hay solicitudes en cola:
  - Crear nuevos workers (hasta max 16)
- Si workers idle (sin solicitudes):
  - Destruye algunos (minimo 2 mantenidos)
- Autoscaling continuo

Ventajas:
- Adapta a trafico variable
- Ahorra memoria en horas bajas
- Mejor para VPS/Cloud (recurso limitado)

Desventajas:
- Overhead de creacion/destruccion de procesos
- Menos predecible (numero de procesos varia)
- Requiere tuning de parametros

**MODO 3: ON-DEMAND (A demanda)**

Comportamiento:
- NO crea workers al iniciar (0 procesos iniciales)
- Solo crea cuando hay solicitud
- Destruye inmediatamente si quedan idle 10 segundos
- Minima memoria consumida

Ventajas:
- Minimo consumo memoria
- Ideal para servidores muy limitados
- Escala desde cero

Desventajas:
- Latencia inicial alta (crear proceso toma tiempo)
- Mucho overhead de creacion/destruccion
- Inadecuado para trafico constante