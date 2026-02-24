# Guia: Implementacion SSL/TLS con Let's Encrypt (Certbot)

**Objetivo:** Generar y activar certificados SSL firmados por Let's Encrypt para el dominio DuckDNS mediante validacion webroot a traves de Nginx en Docker.

**Requisitos previos:** Docker Compose operativo, Nginx escuchando en puerto 80, dominio DuckDNS apuntando a la IP publica del servidor.

---

## 1. Estructura de directorios

Crear los directorios donde Certbot almacenara los certificados y los archivos de validacion:

```bash
mkdir -p ./certbot/conf ./certbot/www
```

Resultado esperado: sin salida (directorios creados silenciosamente).

Verificacion:

```bash
ls -la ./certbot/
```

```
drwxrwxr-x 4 ubuntu ubuntu 4096 Feb 23 15:03 .
drwxrwxr-x 6 ubuntu docker 4096 Feb 23 15:09 ..
drwxrwxr-x 2 ubuntu ubuntu 4096 Feb 23 15:03 conf
drwxrwxr-x 2 ubuntu ubuntu 4096 Feb 23 15:03 www
```

---

## 2. Configuracion de Nginx (modo temporal HTTP)

Antes de generar el certificado, Nginx debe servir el challenge de validacion. Configurar `./nginx/default.conf` con el bloque `^~` para que tenga prioridad sobre cualquier regla de denegacion de archivos ocultos:

```nginx
location ^~ /.well-known/acme-challenge/ {
    root /var/www/certbot;
    allow all;
}
```

**Importante:** El modificador `^~` es necesario porque sin el, la regla `location ~ /\.` (que deniega rutas con punto) tiene prioridad sobre el bloque del challenge y devuelve 403.

Verificar que Nginx acepta la configuracion y recargar:

```bash
docker-compose exec s1-nginx nginx -t
docker-compose exec s1-nginx nginx -s reload
```

```
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```

---

## 3. Verificacion del challenge antes de solicitar el certificado

Crear un archivo de prueba y comprobar que Nginx lo sirve correctamente:

```bash
mkdir -p ./certbot/www/.well-known/acme-challenge/
echo "funciona" > ./certbot/www/.well-known/acme-challenge/test.txt
curl http://extagram-grup5.duckdns.org/.well-known/acme-challenge/test.txt
```

Resultado esperado:

```
funciona
```

Si el resultado es 403, el volumen `./certbot/www` no esta montado correctamente en Nginx o el bloque del challenge tiene conflicto con otra regla. Revisar los volumenes en `docker-compose.yml`:

```yaml
volumes:
  - ./certbot/www:/var/www/certbot:ro
```

---

## 4. Generacion del certificado

```bash
docker-compose run --rm certbot certonly \
  --webroot \
  --webroot-path /var/www/certbot \
  --email tu-email@dominio.com \
  --agree-tos \
  --no-eff-email \
  -d extagram-grup5.duckdns.org
```

Resultado esperado:

```
Account registered.
Requesting a certificate for extagram-grup5.duckdns.org
Successfully received certificate.
Certificate is saved at: /etc/letsencrypt/live/extagram-grup5.duckdns.org/fullchain.pem
Key is saved at:         /etc/letsencrypt/live/extagram-grup5.duckdns.org/privkey.pem
This certificate expires on 2026-05-24.
```

Verificacion de que los archivos existen en el host:

```bash
ls -la ./certbot/conf/live/extagram-grup5.duckdns.org/
```

```
-rw-r--r-- 1 root root  692 Feb 23 15:32 README
lrwxrwxrwx 1 root root   47 Feb 23 15:32 cert.pem -> ../../archive/extagram-grup5.duckdns.org/cert1.pem
lrwxrwxrwx 1 root root   52 Feb 23 15:32 chain.pem -> ../../archive/extagram-grup5.duckdns.org/chain1.pem
lrwxrwxrwx 1 root root   56 Feb 23 15:32 fullchain.pem -> ../../archive/extagram-grup5.duckdns.org/fullchain1.pem
lrwxrwxrwx 1 root root   54 Feb 23 15:32 privkey.pem -> ../../archive/extagram-grup5.duckdns.org/privkey1.pem
```

---

## 5. Configuracion de Nginx con HTTPS

Reemplazar `default.conf` con la configuracion definitiva que incluye redireccion HTTP a HTTPS y el bloque SSL:

```nginx
# Redireccion HTTP a HTTPS
server {
    listen 80;
    server_name extagram-grup5.duckdns.org;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/certbot;
        allow all;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

# Servidor HTTPS
server {
    listen 443 ssl;
    server_name extagram-grup5.duckdns.org;

    ssl_certificate     /etc/letsencrypt/live/extagram-grup5.duckdns.org/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/extagram-grup5.duckdns.org/privkey.pem;

    # resto de la configuracion de locations...
}
```

Recargar Nginx:

```bash
docker-compose exec s1-nginx nginx -t
docker-compose exec s1-nginx nginx -s reload
```

---

## 6. Comprobaciones finales

**Verificar que el certificado es valido y reconocido por Certbot:**

```bash
docker-compose run --rm certbot certificates
```

```
Found the following certs:
  Certificate Name: extagram-grup5.duckdns.org
    Domains: extagram-grup5.duckdns.org
    Expiry Date: 2026-05-24 (VALID: 89 days)
    Certificate Path: /etc/letsencrypt/live/extagram-grup5.duckdns.org/fullchain.pem
    Private Key Path: /etc/letsencrypt/live/extagram-grup5.duckdns.org/privkey.pem
```

**Verificar que HTTPS responde correctamente:**

```bash
curl -I https://extagram-grup5.duckdns.org
```

```
HTTP/2 200
server: nginx/1.29.4
```

**Verificar detalles del certificado (emisor y fechas):**

```bash
curl -v https://extagram-grup5.duckdns.org 2>&1 | grep -A5 "SSL certificate"
```

```
* SSL certificate verify ok.
* Server certificate:
*  subject: CN=extagram-grup5.duckdns.org
*  start date: Feb 23 15:32:55 2026 GMT
*  expire date: May 24 16:32:54 2026 GMT
*  issuer: C=US; O=Let's Encrypt; CN=E7
```

**Verificar renovacion automatica (dry-run):**

```bash
docker-compose run --rm certbot renew --dry-run
```

```
Simulating renewal of an existing certificate for extagram-grup5.duckdns.org
Congratulations, all simulated renewals succeeded
```

---

## Renovacion automatica

El servicio Certbot en `docker-compose.yml` esta configurado para intentar renovar cada 12 horas. Let's Encrypt solo renueva si el certificado caduca en menos de 30 dias:

```yaml
certbot:
  image: certbot/certbot
  volumes:
    - ./certbot/conf:/etc/letsencrypt
    - ./certbot/www:/var/www/certbot
  entrypoint: "/bin/sh -c 'trap exit TERM; while :; do certbot renew; sleep 12h & wait $${!}; done;'"
```