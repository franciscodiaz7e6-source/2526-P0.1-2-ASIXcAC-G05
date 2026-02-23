# Acta de Sprint Planning – Sprint 4

## Información General

**Codigo / Nombre del Sprint:** Sprint 4 – Fase de Seguridad y Hardening
**Fechas del Sprint:** 17 de febrero de 2026 – 24 de febrero de 2026

**Fecha:** 17 de febrero de 2026 15:00

**Asistentes:**
- Erick García Badaraco
- Francisco Díaz Encalada
- Adrià Montero Sánchez

---

## Objetivo del Sprint

**Objetivo Principal:** Asegurar y endurecer la infraestructura ya desplegada en AWS (nodos S1–S7) mediante la implementación de firewall perimetral, Web Application Firewall (WAF), y hardening tanto de sistemas operativos como de la base de datos MySQL, cumpliendo con los requisitos establecidos en la fase P0.2.

---

**Tareas Planificadas para el Sprint**
| Tarea	| Título | Descripción |	Propietarios |	Horas Estimadas |
| 4.1 | Implementación Firewall S1 | Configurar Security Groups y reglas restrictivas delante del nodo S1 para tráfico HTTP, HTTPS y control de acceso seguro	| Equipo DevOps | 3:00 |
| 4.2 | Implementación WAF | Despliegue de WAF (AWS o ModSecurity) con reglas OWASP y mitigaciones de ataques web | Equipo DevOps |	3:00 |
| 4.3 | Hardening Sistema Operativo (S1–S7) | Actualización, deshabilitación servicios no esenciales, configuración de seguridad básica |	DevOps / Infraestructura | 2:00 |
| 4.4 | Hardening Base de Datos MySQL (S7) | Securizar MySQL, eliminar usuarios innecesarios, restringir accesos y activar auditoría | DBA / DevOps | 3:00 |
| 4.5 | Validación de Seguridad y Documentación |	Pruebas de funcionamiento, pruebas de ataques controlados, documentación de cambios | Equipo Completo | 2:00 |
📉 Definición de Hecho (DoD)

Todos los firewalls configurados y validados funcionalmente.

Reglas WAF en funcionamiento y probadas con casos de prueba OWASP.

Hardening documentado por nodo / sistema, con checklist de cambios y versiones.

Validación de pruebas de seguridad sin errores bloqueadores.

📊 Métricas del Sprint 4
Métrica	Valor
Total de Tareas Principales	5
Tareas Completadas	—
Horas Planificadas	13:00
Horas Registradas	—
Riesgos Identificados	Ninguno bloqueador previsto
Dependencias	Nodo S1/SG existente
🧾 Entregables Esperados

Security Group y WAF configurado.

Hardening OS y MySQL según checklist.

Documentación técnica en Markdown subida al repositorio.

Informe de pruebas de seguridad.

🤝 Compromisos y Notas

El equipo se compromete a completar las tareas según la capacidad definida (lunes bloques de 3h para trabajos técnicos, martes bloques de 2h para pruebas y ajustes) y documentarlas adecuadamente para la revisión.
