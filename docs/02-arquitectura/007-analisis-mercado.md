# **_Estudio del Mercado_**

## **_Tabla de Plataformas_**

| **Plataforma** | **Tipo de servicio principal** | **Público objetivo** | **Modelo de negocio** |
|----------------|--------------------------------|---------------------|----------------------|
| Instagram | Red social de fotos/vídeos con feed y stories. | Masivo, usuarios móviles generales. | Gratuito con anuncios y features pro. |
| Flickr | Hosting + comunidad fotográfica profesional. | Aficionados y pros de fotografía. | Freemium / suscripción Pro. |
| 500px | Comunidad de fotografía profesional. | Fotógrafos avanzados/profesionales. | Freemium, planes de pago y concursos. |
| Imgur | Hosting y compartición rápida de imágenes. | Usuarios de foros, redes, anónimo. | Anuncios y servicios empresariales. |
| Google Photos | Backup, organización y compartición en la nube. | Usuarios de Android/Google en general. | Freemium ligado a almacenamiento Google. |

## **_Instagram_**

**Instagram:**  
**Funcionalidad:** permite subir fotos y vídeos, aplicar filtros, publicar en un feed, stories, reels, enviar mensajes, seguir usuarios y comentar/likear contenido.

**Arquitectura y escalabilidad:** usa apps móviles/web que hablan con un API gateway y múltiples microservicios (servicio de posts, timeline, búsqueda, notificaciones, etc.), con bases de datos particionadas, cachés (por ejemplo Redis) y almacenamiento distribuido para imágenes/vídeos servido mediante CDN a escala global.​ 

**Alta disponibilitat:** replicación multi‑región, balanceig de càrrega L7, particionado por usuario, colas de mensajes para procesar tareas pesadas y mecanismos de degradación controlada cuando hay picos.

**Comparación con extagram:** extagram mantiene la lógica en unos pocos contenedores NGINX+PHP+MySQL con un balanceig simple y una sola BBDD; Instagram separa muchos dominios de negocio en microservicios, replica datos entre datacenters y usa CDN, lo que permite manejar miles de millones de posts con alta disponibilidad real. 

## **_Flickr_**

**Flickr:**  
**Funcionalidad:** subida de fotos, creación de álbumes y colecciones, grupos, comentarios, etiquetas, licencias (Creative Commons, etc.) y API para integraciones externas.​

**Arquitectura:** tradicionalmente usa servidores web y de aplicación con caché (memcached) y un almacenamiento masivo de objetos para las fotos; los metadatos se guardan en clusters MySQL "shared‑nothing" con sharding y replicación maestro‑maestro para escalar usuarios y consultas.​ 

**Alta disponibilitat:** separación clara entre metadatos (BD) y ficheros (storage), replicación de datos, balanceig de càrrega entre servidors d'aplicació i ús d'estratègies de "graceful degradation" quan hi ha fallades parcials.​

**Comparación con extagram:** extagram ya separa parte estática (NGINX S5/S6) y lógica dinámica (PHP‑FPM S2/S3/S4) más MySQL S7, pero todo sigue en una sola BBDD y almacenamiento local/BLOB; Flickr demuestra cómo escalar este patrón con sharding, storage especializado y caché distribuid 

## **_500px_**

**500px**  
**Funcionalidad:** red social enfocada a fotografía de alta calidad, con subida de fotos, etiquetas, colecciones, ratings, rankings "Popular/Fresh/Upcoming" y concursos que priorizan contenido de calidad.​ 

**Arquitectura y negocio:** orientada a fotógrafos profesionales, por lo que combina almacenamiento de alta resolución con servicios de descubrimiento, curación algorítmica y herramientas para vender/licenciar fotos en algunos planes.​ 

**Alta disponibilitat i escalabilitat:** necesita servir imágenes de mucha resolución de forma rápida a un público global, por lo que se apoya en almacenamiento en la nube y en sistemas de ranking y caché del contenido más visto.​ 

**Comparación con extagram:** funcionalmente se parece (timeline de fotos), pero añade capas de ranking, descubrimiento y monetización; la arquitectura inicial de varios NGINX + PHP‑FPM + MySQL podría verse como el "núcleo" de 500px sin toda la lógica de negocio adicional ni la infraestructura global.

## **_Imgur_**

**Imgur**  
**Funcionalidad:** ​subida rápida de imágenes, GIFs y vídeos cortos de forma anónima o con cuenta, álbumes, votos (upvote/downvote), comentarios, galerías populares y edición ligera. 

**Arquitectura y negocio:** usa un frontend web simple conectado a servidores backend, guarda archivos en almacenamiento en la nube como S3, bases de datos para metadatos de usuarios y posts, CDN para cargar rápido globalmente, y colas para procesar imágenes en segundo plano (thumbnails, compresión). 

**Alta disponibilitat i escalabilitat:** entrega imágenes vía CDN mundiales, réplicas en bases de datos, limpieza automática de storage viejo y monitoreo constante, aunque a veces hay caídas en uploads masivos.

**Comparación con extagram:** extagram ya divide estática (NGINX S5/S6) y dinámica (PHP S2-4 + MySQL S7), pero Imgur lleva esto a la nube con storage distribuido y tareas asíncronas, escalando el modelo local para tráfico anónimo masivo sin servidores dedicados everywhere.

## **_Google Photos_**

**Google Photos**  
**Funcionalidad:** backup automático desde móviles, organización con IA, Memories y álbumes inteligentes, edición mágica (borrar objetos, mejorar fotos), compartición fácil y creaciones automáticas como collages. 

**Arquitectura y negocio:** servicios globales para subir fotos, storage en capas por uso (caliente/frío), pipelines de IA para análisis y búsqueda, base de datos Spanner para metadatos de billones de archivos, CDN para acceso rápido y particionado por regiones/usuarios.

**Alta disponibilitat i escalabilitat:** réplicas en múltiples regiones (casi 100% uptime), recuperación automática, aislamiento de fallos y latencia baja en todo el mundo gracias a sharding y liderazgo dinámico. 

**Comparación con extagram:** extagram corre en pocos contenedores con una sola MySQL; Google Photos expande eso a storage masivo, IA distribuida y bases globales, mostrando cómo crecer un setup simple a escala planetaria con backups infinitos y búsqueda inteligente 

## **_Gráficos_**


<div align="center">
  <img src="/media/adria/diagramadepastel.png" alt="Diagrama de Pastel - Distribución de Mercado" width="600" />
  <p><em>Figura 1: Distribución del mercado de usuarios globales</em></p>
  <br>
  <img src="/media/adria/Comparativa_de_usuarios_activos_mensuales_y_nivel_de_engagement_por_plataforma_(2026).png" alt="Comparativa Usuarios Activos y Engagement 2026" width="700" />
  <p><em>Figura 2: Comparativa de usuarios activos Anuales</em></p>
  <br>
</div>

## **_Infografía y Referencias_**

**Instagram:**  
[https://en.wikipedia.org/wiki/Instagram](https://en.wikipedia.org/wiki/Instagram) (arquitectura básica y features)​  
[https://engineering.fb.com/](https://engineering.fb.com/) (blogs Meta sobre microservicios/CDN) 

**Flickr:**  
[https://www.flickr.com/help](https://www.flickr.com/help) (funcionalidades oficiales)​  
[https://www.smugmug.com/blog/2018/](https://www.smugmug.com/blog/2018/) (arquitectura post-adquisición) 

**500px:**  
[https://500px.com/about](https://500px.com/about) (features profesionales)​  
[https://500px.com/pricing](https://500px.com/pricing) (modelos negocio) 

**Imgur:**  
[https://en.wikipedia.org/wiki/Imgur](https://en.wikipedia.org/wiki/Imgur) (historia y features)​  
[https://dev.to/hexshift/designing-a-scalable-image-upload-service-like-imgur-3men](https://dev.to/hexshift/designing-a-scalable-image-upload-service-like-imgur-3men) (arquitectura escalable)​  
[https://appsumo.com/products/imgur/](https://appsumo.com/products/imgur/) (detalles servicios)​ 

**Google Photos:**  
[https://www.google.com/photos/about/](https://www.google.com/photos/about/) (features oficiales)​  
[https://www.educative.io/blog/google-photos-system-design](https://www.educative.io/blog/google-photos-system-design) (diseño sistema)​  
[https://cloud.google.com/blog/products/databases/google-photos-builds-user-experience-on-spanner](https://cloud.google.com/blog/products/databases/google-photos-builds-user-experience-on-spanner) (Spanner y escalabilidad)

**Graficos:**  
[https://www.cnbc.com/2025/09/24/instagram-now-has-3-billion-monthly-active-users.html ](https://www.cnbc.com/2025/09/24/instagram-now-has-3-billion-monthly-active-users.html)(Instagram 3B)​  
[https://photutorial.com/flickr-statistics/](https://photutorial.com/flickr-statistics/) (Flickr ~60M)​  
[https://www.semrush.com/website/imgur.com/overview/](https://www.semrush.com/website/imgur.com/overview/) (Imgur tráfico ~130M)​  
[https://en.wikipedia.org/wiki/Google_Photos](https://en.wikipedia.org/wiki/Google_Photos) (Google Photos ~500M estimado)​ 

[Indice Principal de Arquitectura](./000-indice-arquitectura.md)