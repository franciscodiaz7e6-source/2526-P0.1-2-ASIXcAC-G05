# Acta de Sprint Planning – Sprint 3

## Información General

**Codigo / Nombre del Sprint:** Sprint 3 – Fase de Pruebas y Optimización  
**Fechas del Sprint:** 2 de febrero de 2026 – 10 de febrero de 2026

**Fecha:** 2 de febrero de 2026 15:00

**Asistentes:**
- Erick García Badaraco
- Francisco Díaz Encalada
- Adrià Montero Sánchez

---

## Objetivo del Sprint

**Objetivo Principal:** Desplegar y configurar todos los nodos de la infraestructura Extagram (S1–S7) mediante Docker y docker-compose en AWS EC2, validar la operativa completa de la aplicación de extremo a extremo, probar la redundancia ante caídas de nodos, y generar documentación técnica exhaustiva que cubra arquitectura, instalación, configuración y troubleshooting.

---

## Tareas Planificadas para el Sprint

| Tarea | Título | Descripción | Propietarios | Horas Estimadas |
|-------|--------|-------------|--------------|-----------------|
| 3.1 | Sprint Planning + Instalación Docker | Kickoff Sprint 3. Instalar Docker y docker-compose en las 7 instancias EC2 y preparar imágenes base para el despliegue de servicios. | Equipo Completo | 3:00 |
| 3.2 | Configuración S1 (NGINX Proxy Inverso con Balanceig) | Configurar S1 como reverse proxy NGINX que reparte tráfico hacia S2 y S3, enruta a S4 para uploads y a S5/S6 para estáticos, cumpliendo el rol de balanceador y puerta de entrada. | Erick García | 3:00 |
| 3.3 | Configuración S2 y S3 (PHP-FPM – extagram.php) | Configurar las instancias S2 y S3 para ejecutar PHP-FPM con extagram.php, proporcionando redundancia y capacidad de balanceo de carga en la capa de aplicación. | Erick García / Adrià Montero | 2:00 |
| 3.4 | Configuración S4 (PHP-FPM – upload.php) | Configurar S4 para ejecutar upload.php con PHP-FPM y gestionar las subidas de imágenes hacia un volumen persistente /uploads accesible por otros servicios. | Francisco Díaz / Adrià Montero | 2:00 |
| 3.5 | Configuración S5 y S6 (NGINX Estáticos) | Configurar NGINX en S5 para servir imágenes desde /uploads y en S6 para servir ficheros estáticos como CSS y SVG requeridos por Extagram. | Francisco Díaz / Erick García | 3:00 |
| 3.6 | Configuración S7 (MySQL) | Configurar S7 con MySQL usando BD extagram_db y usuario extagram_admin, asegurando volumen persistente para datos y acceso controlado desde los servidores de aplicación. | Francisco Díaz / Adrià Montero | 3:00 |
| 3.7 | Pruebas de Operativa Completa (End-to-End) | Realizar pruebas end-to-end para validar todo el flujo de Extagram desde el navegador hasta la base de datos y el almacenamiento de imágenes. | Equipo Completo | 2:00 |
| 3.8 | Pruebas de Caída de Nodos (Redundancia) | Probar el comportamiento del sistema ante caídas de nodos clave (S2, S5, S7) y validar el failover y persistencia de datos. | Francisco Díaz / Adrià Montero | 2:00 |
| 3.9 | Documentación Técnica Exhaustiva | Crear documentación técnica completa para administrador cubriendo arquitectura, instalación, configuración, troubleshooting, dependencias y procedimientos, cumpliendo criterios RA3. | Equipo Completo | 3:00 |
| 3.10 | Sprint Review Final + Lecciones Aprendidas | Realizar la Sprint Review final del Sprint 3, validar cumplimiento de requisitos P0.1 y documentar lecciones aprendidas y roadmap para P0.2. | Equipo Completo | 2:00 |

---

## Métricas del Sprint 3

| Métrica                     | Valor                        |
|-----------------------------|------------------------------|
| Total de Tareas Principales | 10                           |
| Tareas Completadas          | —                            |
| Horas Planificadas          | 25:00                        |
| Horas Registradas           | —                            |
| Riesgos Identificados       | Ninguno bloqueador previsto  |
| Dependencias                | Instancias EC2 aprovisionadas (Sprint 2) |

---

## Entregables del Sprint 3

- Los 7 nodos S1–S7 configurados y operativos con Docker y docker-compose.
- Aplicación Extagram funcional de extremo a extremo (formulario, upload, galería y BD).
- Pruebas de redundancia validadas con failover documentado.
- Documentación técnica completa en Markdown subida al repositorio.

