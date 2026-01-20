# 2526-P0.1-2-ASIXcAC-G05

## **Descripción del Proyecto**

**Despliegue de una red en la nube para Extagram**, aplicación PHP de publicación de imágenes. 

La arquitectura **garantiza alta disponibilidad, escalabilidad y seguridad** mediante integración de servicios web y bases de datos en entorno interconectado. 

**Curso:** ASIXcAC-G05 (2025-2026) - Institut Tecnològic de Barcelona  
**Equipo:** Adrià Montero Sánchez, Erick García Badaraco, Francisco Díaz Encalada

## **Objetivos Principales**

1. **Desplegar Extagram en nube** con alta disponibilidad 
2. **Escalabilidad horizontal** mediante microservicios/CDN 
3. **Seguridad integral** (autenticación, ModSecurity, VLANs) 
4. **Gestión ágil** vía ProofHub Sprints 

## **Tabla de Contenidos del Repositorio**

| Carpeta/Archivo | Descripción | Enlace Directo |
|-----------------|-------------|----------------|
| **📋 `/actas/`** | Actas Sprint Review | [Ver actas →](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/tree/main/actas) |
| `/actas/Acta-Sprint1-16dic2025.md` | Acta inicial Sprint 1 | [Abrir](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/blob/main/actas/Acta-Sprint1-16dic2025.md) |
| `/actas/Acta-Sprint1-19ene2026.md` | Acta cierre Sprint 1 | [Abrir](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/blob/main/actas/Acta-Sprint1-19ene2026.md) |
| **📚 `/docs/`** | Documentación técnica | [Ver docs →](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/tree/main/docs) |
| `/docs/Estudio-del-mercado.md` | **Análisis competitivo completo** (Instagram/Flickr/etc.) | [Abrir](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/blob/main/docs/Estudio-del-mercado.md)  |
| `/docs/Arbol-Documentacion.md` | Estructura documentación | [Abrir](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/blob/main/docs/Arbol-Documentacion.md) |
| **📊 `/diagrams/`** | Gráficos de mercado | [Ver diagramas →](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/tree/main/diagrams) |
| `/diagrams/diagramadepastel.png` | **Diagrama pastel distribución mercado** | [Ver imagen](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/blob/main/diagrams/diagramadepastel.png) |
| `/diagrams/Comparativa...2026.png` | **Comparativa usuarios/engagement 2026** | [Ver imagen](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/blob/main/diagrams/Comparativa_de_usuarios_activos_mensuales_y_nivel_de_engagement_por_plataforma_(2026).png) |
| **📁 `/diagrams/media/adria/`** | Archivos adicionales Adria | [Ver carpeta →](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/tree/main/diagrams/media/adria) |
| **💻 `/src/`** | Código fuente Extagram | [Ver src →](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/tree/main/src) |
| **⚙️ `/config/`** | Configuraciones | [Ver config →](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/tree/main/config) |


## **Arquitectura Técnica**

```
Extagram (NGINX + PHP-FPM + MySQL)
├── CDN Global (imágenes)
├── Microservicios (posts/timeline)
├── Redis (caché)
├── Alta disponibilidad (multi-región)
└── ProofHub (gestión ágil)
```

**Competencia analizada:** Instagram (3B usuarios), Flickr, 500px, Imgur, Google Photos 
## **Próximos Objetivos**

- ✅ Setup inicial completado
- 🔄 Diseño arquitectura/red
- ⏳ Desarrollo Extagram escalable
- 📈 Integración nube/CDN

## **Recursos Externos**

- [ProofHub Backlog](https://itecbcn.proofhub.com/bapplite/#app/todos/project-9429692256/list-270270720757) 
- [Issues GitHub](https://github.com/franciscodiaz7e6-source/2526-P0.1-2-ASIXcAC-G05/issues)
- [Estudio Mercado Completo](/docs/Estudio-del-mercado.md) 

***

**Proyecto académico ASIXcAC-G05 -  Enero 2026** 
