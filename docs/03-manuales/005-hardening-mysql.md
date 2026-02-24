# Guia: Hardening de Base de Datos MySQL (S7)

**Objetivo:** Reducir la superficie de ataque de la instancia MySQL eliminando accesos remotos innecesarios, limitando los privilegios del usuario de aplicacion al minimo necesario y restringiendo las interfaces de red en las que escucha el servicio.

**Requisitos previos:** Contenedor `s7-mysql` operativo, archivo `.env` con las credenciales, `docker-compose.yml` accesible.

---

## Estado inicial (antes del hardening)

```bash
docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "SELECT user, host FROM mysql.user; SHOW VARIABLES LIKE 'bind_address';"
```

```
user              host
extagram_user     %           <- acceso desde cualquier IP
root              %           <- acceso root remoto abierto
mysql.infoschema  localhost
mysql.session     localhost
mysql.sys         localhost
root              localhost

Variable_name   Value
bind_address    *             <- escucha en todas las interfaces de red
```

**Problemas identificados:**

- `root@%`: el usuario administrador acepta conexiones desde cualquier IP del mundo.
- `extagram_user@%`: el usuario de la aplicacion acepta conexiones desde cualquier IP, sin restriccion de red.
- `bind_address = *`: MySQL expone el puerto 3306 en todas las interfaces del servidor.
- Los privilegios de `extagram_user` no estaban definidos al minimo necesario para la aplicacion.

---

## 1. Restringir bind_address

Editar `docker-compose.yml` y anadir el parametro `command` al servicio `s7-mysql`:

```yaml
s7-mysql:
    image: mysql:8.0
    container_name: s7-mysql
    restart: unless-stopped
    command: --bind-address=127.0.0.1 --local-infile=0
    environment:
      ...
```

Reiniciar el contenedor para aplicar el cambio:

```bash
docker-compose up -d s7-mysql
```

Verificacion:

```bash
docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "SHOW VARIABLES LIKE 'bind_address';"
```

```
Variable_name   Value
bind_address    127.0.0.1
```

`127.0.0.1` confirma que MySQL ya no escucha en interfaces externas. El puerto 3306 no es accesible desde fuera del servidor, unicamente desde dentro del propio host y desde la red interna Docker.

---

## 2. Eliminar usuario root remoto

```bash
docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "DROP USER IF EXISTS 'root'@'%';"
```

Sin salida indica ejecucion correcta.

---

## 3. Restringir extagram_user a la red Docker

La red interna Docker utiliza el rango `172.x.x.x`. Renombrando el usuario de `%` a `172.%` se limita el acceso unicamente a contenedores de esa red:

```bash
docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "RENAME USER 'extagram_user'@'%' TO 'extagram_user'@'172.%';"
```

---

## 4. Aplicar principio de minimo privilegio

La aplicacion (tipo board/Tumblr) unicamente necesita leer, insertar, actualizar y borrar registros en su propia base de datos. Se revocan todos los permisos existentes y se otorgan solo los necesarios:

```bash
docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "REVOKE ALL PRIVILEGES ON *.* FROM 'extagram_user'@'172.%';"

docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "GRANT SELECT, INSERT, UPDATE, DELETE ON extagram_db.* TO 'extagram_user'@'172.%';"

docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "FLUSH PRIVILEGES;"
```

Permisos eliminados y su justificacion:

| Permiso eliminado | Riesgo que suponia |
|---|---|
| CREATE / DROP / ALTER | Modificar o destruir la estructura de tablas via SQL injection |
| FILE | Leer o escribir archivos del sistema operativo |
| SUPER / SHUTDOWN | Administrar o detener el servidor MySQL |
| GRANT OPTION | Crear nuevos usuarios con privilegios |

---

## 5. Comprobaciones finales

**Verificar usuarios y hosts resultantes:**

```bash
docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "SELECT user, host FROM mysql.user;"
```

```
user              host
extagram_user     172.%       <- solo red Docker interna
mysql.infoschema  localhost
mysql.session     localhost
mysql.sys         localhost
root              localhost   <- root solo en local, eliminado el remoto
```

**Verificar permisos de extagram_user:**

```bash
docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "SHOW GRANTS FOR 'extagram_user'@'172.%';"
```

```
GRANT SELECT, INSERT, UPDATE, DELETE ON `extagram_db`.* TO `extagram_user`@`172.%`
```

Solo los cuatro permisos necesarios sobre la base de datos de la aplicacion, sin acceso al resto de bases de datos del servidor.

**Verificar que la aplicacion sigue funcionando:**

```bash
curl -I https://extagram-grup5.duckdns.org
```

```
HTTP/2 200
server: nginx/1.29.4
```

Respuesta 200 confirma que los contenedores PHP siguen conectandose correctamente a MySQL a traves de la red Docker `172.%`.

**Verificar que local_infile esta desactivado:**

```bash
docker exec s7-mysql mysql -uroot -p"$(grep MYSQL_ROOT_PASSWORD .env | cut -d= -f2)" \
  -e "SHOW VARIABLES LIKE 'local_infile';"
```

```
Variable_name   Value
local_infile    OFF
```

`local_infile = OFF` impide que un atacante use `LOAD DATA LOCAL INFILE` para leer archivos del sistema a traves de una inyeccion SQL.

---

## Resumen del estado final

| Configuracion | Antes | Despues |
|---|---|---|
| `root@%` | Existia (acceso remoto total) | Eliminado |
| `extagram_user` host | `%` (cualquier IP) | `172.%` (solo red Docker) |
| Privilegios `extagram_user` | Sin definir / excesivos | SELECT, INSERT, UPDATE, DELETE |
| `bind_address` | `*` (todas las interfaces) | `127.0.0.1` (solo local) |
| `local_infile` | OFF | OFF (confirmado) |