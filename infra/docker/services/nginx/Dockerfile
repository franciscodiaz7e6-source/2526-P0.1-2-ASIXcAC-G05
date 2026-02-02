FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /app

EXPOSE 9000

CMD ["php-fpm"]
