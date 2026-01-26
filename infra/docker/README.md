# DOCKER COMPOSE INICIAL - MVP SIMPLIFICADO

***

## ESTRUCTURA DE CARPETAS (INICIAL)

```
docker/
├── docker-compose.yml
├── nginx/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── default.conf
├── php-app/
│   ├── Dockerfile
│   ├── extagram.php
│   └── upload.php
├── storage/
│   ├── Dockerfile
│   └── (volumen para /images)
├── static/
│   ├── style.css
│   └── preview.svg
├── uploads/
│   └── (volumen para fotos)
└── mysql/
    └── init.sql
```