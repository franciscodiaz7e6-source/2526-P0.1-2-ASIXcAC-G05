# Acta de Sprint Planning – Sprint 5

## Información General

**Código / Nombre del Sprint:** Sprint 5 – Fase de Monitorización y Automatización
**Fechas del Sprint:** 2 de marzo de 2026 – 10 de marzo de 2026

**Fecha:** 2 de marzo de 2026 15:00

**Asistentes:**
- Erick García Badaraco
- Francisco Díaz Encalada

---

## Objetivo del Sprint

**Objetivo Principal:** Implementar un sistema de monitorización centralizada de logs sobre la infraestructura AWS (nodos S1–S7), crear dashboards de rendimiento, ejecutar pruebas de estrés para validar la estabilidad del sistema bajo carga, y automatizar el proceso de despliegue a producción mediante Ansible y/o CI-CD, cumpliendo con los requisitos de la fase P0.2.

---

## Tareas Planificadas para el Sprint

| Tarea | Título | Descripción | Propietarios | Horas Estimadas |
|-------|--------|-------------|--------------|-----------------|
| 5.1 | Despliegue Stack Monitorización | Implementación de ELK Stack (Elasticsearch, Logstash, Kibana) o Grafana + Loki para centralizar y visualizar logs de todos los nodos S1–S7 | Equipo DevOps | 3:00 |
| 5.2 | Creación Dashboards Rendimiento | Configuración de dashboards de métricas clave: CPU, memoria, tráfico NGINX en S1, errores HTTP 4xx/5xx y latencia de respuesta | Equipo DevOps | 2:00 |
| 5.3 | Pruebas de Estrés | Simulación de carga con Apache Bench o JMeter sobre los endpoints de Extagram y análisis de rendimiento mediante los logs centralizados | DevOps / QA | 3:00 |
| 5.4 | Automatización Despliegues Ansible / CI-CD | Creación de playbooks Ansible para el despliegue automatizado e idempotente de los nodos S1–S7, con integración en GitHub Actions para puesta en producción | Equipo DevOps | 3:00 |
| 5.5 | Validación Final y Documentación | Revisión completa del sistema monitorizado, ejecución de pruebas de operativa web, validación WAF y preparación de la demo final para la defensa del 16-17/03 | Equipo Completo | 2:00 |

---

## Calendario de Sesiones

| Sesión | Día | Horas | Tareas |
|--------|-----|-------|--------|
| Sesión 1 | Lunes 02/03/2026 | 3h | Tarea 5.1 – Despliegue Stack Monitorización |
| Sesión 2 | Martes 03/03/2026 | 2h | Tarea 5.2 – Creación Dashboards Rendimiento |
| Sesión 3 | Lunes 09/03/2026 | 3h | Tarea 5.3 – Pruebas de Estrés + Tarea 5.4 – Automatización Ansible |
| Sesión 4 | Martes 10/03/2026 | 2h | Tarea 5.5 – Validación Final y Documentación |

---

## Métricas del Sprint 5

| Métrica | Valor |
|---------|-------|
| Total de Tareas Principales | 5 |
| Tareas Completadas | — |
| Horas Planificadas | 10:00 |
| Horas Registradas | — |
| Riesgos Identificados | Complejidad del stack ELK; curva de aprendizaje Ansible |
| Dependencias | Sprint 4 completado (Firewall, WAF y Hardening operativos) |

---

## Entregables del Sprint 5

- Stack de monitorización desplegado y operativo (ELK o Grafana).
- Dashboards de rendimiento configurados y accesibles.
- Informe de pruebas de estrés con métricas documentadas.
- Playbooks Ansible funcionales e idempotentes para los 7 nodos.
- Documentación técnica en Markdown subida al repositorio GitHub.
- Acta de Sprint Review commitada con capturas de ProofHub.

---

[Indice de Actas](../indice-acta.md)