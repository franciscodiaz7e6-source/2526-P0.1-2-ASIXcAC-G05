# Implementación Seguridad - Extagram (S1 Docker Nginx)

**Objetivo:** Proteger S1 (Nginx Reverse Proxy) contra OWASP Top 10 + DoS, manteniendo arquitectura Docker actual.

**Prerrequisitos**
Docker Compose actual con s1-nginx (nginx:alpine).

**Archivos:** ./nginx/Dockerfile, default.conf, volúmenes ./nginx/logs, ./nginx/modsec-logs.

**Dominio:** extagram-grup5.duckdns.org (Certbot activo).

**Backends:** s2/s3-php, s4-upload, s5-images, s6-static.

## 1. Dockerfile Actualizado (./nginx/Dockerfile)
```bash
FROM nginx:alpine

# Dependencias ModSecurity v3
RUN apk add --no-cache wget git gcc musl-dev pcre2-dev openssl-dev libxml2-dev curl-dev geoip-dev lmdb-dev maxminddb-dev libtool autoconf automake make pkgconf

# Compilar ModSecurity lib
RUN git clone --depth 1 -b v3/master https://github.com/SpiderLabs/ModSecurity.git /usr/src/ModSecurity && \
    cd /usr/src/ModSecurity && git submodule init && git submodule update && \
    ./build.sh && ./configure --enable-standalone-module && make dist && make install-libs && make clean

# Nginx connector
RUN git clone --depth 1 https://github.com/owasp-modsecurity/modsecurity-nginx.git /usr/src/ModSecurity-nginx && \
    cd /usr/src/ModSecurity-nginx && ./configure --with-compatibility && make modules && \
    mkdir -p /etc/nginx/modules && cp objs/ngx_http_modsecurity_module.so /etc/nginx/modules/

# OWASP CRS
RUN mkdir -p /etc/nginx/owasp-crs && git clone --depth 1 https://github.com/coreruleset/coreruleset.git /etc/nginx/owasp-crs/coreruleset && \
    cp /etc/nginx/owasp-crs/coreruleset/crs-setup.conf.example /etc/nginx/owasp-crs/crs-setup.conf

# Directorios app + logs
RUN mkdir -p /app /uploads /static/css /static/js /static/images \
             /var/cache/nginx/{client_temp,proxy_temp,fastcgi_temp,uwsgi_temp,scgi_temp} \
             /var/log/modsec && \
    chown -R nginx:nginx /var/cache/nginx /app /uploads /static /var/log/modsec /etc/nginx/owasp-crs && \
    chmod -R 755 /var/cache/nginx /var/log/modsec

# Cargar módulo
RUN echo "load_module /etc/nginx/modules/ngx_http_modsecurity_module.so;" > /docker-entrypoint.d/10-modsec.conf

# Logrotate
RUN apk add --no-cache logrotate && cat > /etc/logrotate.d/nginx << 'EOF'
/var/log/nginx/*.log /var/log/modsec/*.log {
    daily rotate 14 compress delaycompress missingok notifempty copytruncate create 644 nginx nginx dateext dateformat -%Y%m%d
    postrotate /usr/sbin/nginx -s reopen >/dev/null 2>&1 || true; endscript
}
EOF

EXPOSE 80 443
CMD ["nginx", "-g", "daemon off;"]
```

## 2. default.conf WAF-Ready (./nginx/default.conf)

```bash
upstream php_read { least_conn; server s2-php-app-1:9000; server s3-php-app-2:9000; }
upstream php_upload { server s4-php-upload:9000; }
upstream s5-images { server s5-images:80; }
upstream s6-static { server s6-static:80; }

server {
    listen 80; server_name extagram-grup5.duckdns.org; root /app; index extagram.php;

    # WAF + Rate Limiting
    modsecurity on; modsecurity_rules_file /etc/nginx/modsecurity.conf;
    limit_req_zone $binary_remote_addr zone=global:10m rate=10r/m;

    # Certbot bypass
    location /.well-known/acme-challenge/ { root /var/www/certbot; modsecurity off; }

    # Logs pipe-delimited
    log_format pipe_log '$time_iso8601|$remote_addr|$request_method|$request_uri|$status|$bytes_sent|$request_time|$http_referer|$http_user_agent';
    access_log /var/log/nginx/access.log pipe_log buffer=64k flush=10m;

    # Rutas protegidas (tu config original + limits)
    location /static/ { limit_req zone=global burst=50 nodelay; proxy_pass http://s6-static/; ... }
    location /uploads/ { limit_req zone=global burst=100 nodelay; proxy_pass http://s5-images/uploads/; ... }
    location = /upload.php { limit_req zone=global burst=5; fastcgi_pass php_upload; client_max_body_size 20M; ... }
    location ~ \.php$ { limit_req zone=global burst=20; fastcgi_pass php_read; ... }
    location / { limit_req zone=global burst=30; try_files $uri $uri/ /extagram.php?$query_string; }

    # Denegar sensibles
    location ~ /\. { deny all; }
    location ~ \.(git|env|bak|conf|log)$ { deny all; }
}
```

## 3. docker-compose.yml Volumes

```bash
s1-nginx:
  # ... tu config actual ...
  volumes:
    - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    - ./nginx/logs:/var/log/nginx
    - ./nginx/modsec-logs:/var/log/modsec
```

## 4. modsecurity.conf Base (./nginx/modsecurity.conf - Opcional)

```bash
SecRuleEngine On
SecRequestBodyAccess On
SecAuditEngine RelevantOnly
SecAuditLog /var/log/modsec/modsec.log
SecAuditLogParts ABIJDEFHZ
Include /etc/nginx/owasp-crs/crs-setup.conf
Include /etc/nginx/owasp-crs/coreruleset/rules/*.conf
```

## Despliegue
```bash
cd extagram-docker/  # Usa la ruta hacia tu proyecto
docker-compose build --no-cache s1-nginx
docker-compose up -d
docker exec s1-nginx nginx -t && docker logs s1-nginx | grep modsecurity
```

## Pruebas y Validación
| Prueba | Comando | Resultado Esperado |
|--------|---------|--------------------|
| Config OK | docker exec s1-nginx nginx -t | syntax is ok|
| XSS Block (A7) | curl "http://localhost/?q=<script>" | 403 Forbidden |
| SQLi Block (A3) | curl "http://localhost/?id=1' OR 1=1--" |	403 |
| Rate Limit | for i in {1..12}; do curl http://localhost/; sleep 3; done | Últimos 429/503 |
| Certbot OK | Renovación Let's Encrypt | Pasa sin bloqueo |
| Logs Access | docker exec s1-nginx tail /var/log/nginx/access.log | 2026-02-23T16:20:00+00:00|IP|GET|/xss|403|...
| Logs WAF | docker exec s1-nginx tail /var/log/modsec/modsec.log | --[timestamp] msg:"XSS Attack" |

## Comandos Monitoring:

```bash
# Top bloqueos
docker exec s1-nginx awk -F'|' '$5=="403" {print $2,$4}' /var/log/nginx/access.log | sort | uniq -c | sort -nr

# Stats min
docker exec s1-nginx tail -10000 /var/log/nginx/access.log | awk -F'|' '{print $7}' | awk '{sum+=$1} END {print sum/NR "s avg RT"}'
```

## Mantenimiento
- Falsos Positivos: Edita /etc/nginx/owasp-crs/crs-setup.conf (Paranoia Level 1 → 2).
- Logs Rotación: Automática diaria (14 días gzipped).
- Update CRS: docker exec s1-nginx git -C /etc/nginx/owasp-crs/coreruleset pull.
- Rollback: docker-compose down && git checkout HEAD~1 ./nginx/.

que es esto caumsa, porque hardening que da muy generalista