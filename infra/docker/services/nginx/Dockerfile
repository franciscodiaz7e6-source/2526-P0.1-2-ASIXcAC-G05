FROM nginx:alpine

# Instalar wget
RUN apk add --no-cache wget

# Crear directorios
RUN mkdir -p /app /uploads /static/css /static/js /static/images

# Crear directorios de caché con permisos
RUN mkdir -p /var/cache/nginx/client_temp \
             /var/cache/nginx/proxy_temp \
             /var/cache/nginx/fastcgi_temp \
             /var/cache/nginx/uwsgi_temp \
             /var/cache/nginx/scgi_temp && \
    chown -R nginx:nginx /var/cache/nginx /app /uploads /static && \
    chmod -R 755 /var/cache/nginx

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
