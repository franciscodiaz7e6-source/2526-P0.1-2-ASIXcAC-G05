# DOCKER COMPOSE INICIAL - MVP SIMPLIFICADO

***

## ESTRUCTURA DE CARPETAS (INICIAL)

```
extagram-initial/
├── docker-compose.yml
├── .env
│
├── app/
│   ├── index.php
│   ├── health.php
│   ├── config.php
│   ├── api/
│   │   ├── posts.php
│   │   └── upload.php
│   ├── public/
│   │   ├── css/
│   │   │   └── style.css
│   │   ├── js/
│   │   │   └── app.js
│   │   └── images/
│   │       └── logo.png
│   └── storage/
│       └── uploads/
│
├── config/
│   ├── nginx/
│   │   └── nginx.conf
│   ├── php/
│   │   ├── php.ini
│   │   └── www.conf
│   └── mysql/
│       ├── my.cnf
│       └── init.sql
│
├── logs/
│   ├── nginx/
│   ├── php-fpm/
│   └── mysql/
│
└── README.md
```