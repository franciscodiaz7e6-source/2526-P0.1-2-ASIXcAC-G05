# Guia: Stack de Monitorización ELK Minimal (Elasticsearch + Kibana + Filebeat)

**Objetivo:** Desplegar un stack de monitorización ligero para visualizar logs de Nginx, MySQL y WAF ModSecurity en tiempo real mediante Kibana, optimizado para AWS t2.medium.

**Requisitos previos:** Docker Compose operativo, stack principal de Extagram corriendo, instancia AWS t2.medium (4GB RAM / 2 vCPUs), acceso SSH al servidor.

---

## 1. Estructura de directorios

Crear los directorios donde se almacenarán las configuraciones del stack de monitorización:

```bash
cd ~/extagram/docker
mkdir -p elasticsearch
mkdir -p filebeat
```

Verificación:

```bash
ls -la | grep -E "elasticsearch|filebeat"
```
---

## 2. Variables de entorno

Añadir las credenciales al fichero `.env`. Elasticsearch y Kibana las usarán al arrancar:

```bash
echo "ELASTIC_PASSWORD=$(openssl rand -base64 24)" >> .env
echo "KIBANA_PASSWORD=$(openssl rand -base64 24)" >> .env
```

**Importante:** El `=` del final forma parte del valor generado por base64. Incluirlo al introducir la password manualmente.

---

## 3. Fichero de configuración de Elasticsearch

Crear `./elasticsearch/elasticsearch.yml` con los ajustes optimizados para t2.medium:

```bash
cat > elasticsearch/elasticsearch.yml << 'EOF'
cluster.name: extagram-cluster
node.name: extagram-node-1
network.host: 0.0.0.0
http.port: 9200

xpack.security.enabled: true
xpack.security.http.ssl.enabled: false
xpack.security.transport.ssl.enabled: false

xpack.monitoring.collection.enabled: true

indices.memory.index_buffer_size: 10%
thread_pool.write.queue_size: 100
thread_pool.search.queue_size: 100
EOF
```

---

## 4. Fichero de configuración de Filebeat

Crear `./filebeat/filebeat.yml`. Recoge logs de todos los contenedores Docker más los logs del WAF ModSecurity:

```bash
cat > filebeat/filebeat.yml << 'EOF'
filebeat.inputs:

  - type: container
    paths:
      - /var/lib/docker/containers/*/*.log
    stream: all
    fields:
      service: docker
    fields_under_root: true

  - type: log
    paths: ["/var/log/modsec/modsec.log"]
    multiline:
      pattern: '^\-\-[0-9a-f]+-[A-Z]\-\-'
      negate: true
      match: after
    fields:
      service: modsecurity
      log_type: waf
    fields_under_root: true

processors:
  - add_docker_metadata:
      host: "unix:///var/run/docker.sock"
      match_fields: ["log.file.path"]
  - add_host_metadata: ~

output.elasticsearch:
  hosts: ["http://s8-elasticsearch:9200"]
  username: "elastic"
  password: "${ELASTIC_PASSWORD}"
  indices:
    - index: "extagram-nginx-%{+yyyy.MM.dd}"
      when.contains:
        docker.container.name: "s1-nginx"
    - index: "extagram-mysql-%{+yyyy.MM.dd}"
      when.contains:
        docker.container.name: "s7-mysql"
    - index: "extagram-waf-%{+yyyy.MM.dd}"
      when.equals:
        service: "modsecurity"
    - index: "extagram-docker-%{+yyyy.MM.dd}"

setup.ilm.enabled: true
setup.template.settings:
  index.number_of_shards: 1
  index.number_of_replicas: 0

logging.level: warning
EOF
```

Ajustar permisos — Filebeat requiere que el fichero sea propiedad de root y no escribible por otros:

```bash
sudo chown root:root filebeat/filebeat.yml
sudo chmod go-w filebeat/filebeat.yml
```

---

## 5. Fichero docker-compose.monitoring.yml

Crear el compose de monitorización con los 3 servicios: Elasticsearch (heap 512MB), Kibana y Filebeat:

```bash
cat > docker-compose.monitoring.yml << 'EOF'
version: '3.9'

services:

  s8-elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.13.0
    container_name: s8-elasticsearch
    restart: unless-stopped
    environment:
      - discovery.type=single-node
      - cluster.name=extagram-cluster
      - xpack.security.enabled=true
      - xpack.security.http.ssl.enabled=false
      - xpack.security.transport.ssl.enabled=false
      - ELASTIC_PASSWORD=${ELASTIC_PASSWORD}
      - ES_JAVA_OPTS=-Xms512m -Xmx512m
    ulimits:
      memlock: { soft: -1, hard: -1 }
      nofile: { soft: 65536, hard: 65536 }
    volumes:
      - elasticsearch-data:/usr/share/elasticsearch/data
      - ./elasticsearch/elasticsearch.yml:/usr/share/elasticsearch/config/elasticsearch.yml:ro
    networks:
      - extagram-monitoring
    healthcheck:
      test: ["CMD-SHELL", "curl -sf -u elastic:${ELASTIC_PASSWORD} http://localhost:9200/_cluster/health | grep -q status"]
      interval: 15s
      timeout: 10s
      retries: 10
      start_period: 60s

  s9-kibana:
    image: docker.elastic.co/kibana/kibana:8.13.0
    container_name: s9-kibana
    restart: unless-stopped
    environment:
      - ELASTICSEARCH_HOSTS=http://s8-elasticsearch:9200
      - ELASTICSEARCH_USERNAME=kibana_system
      - ELASTICSEARCH_PASSWORD=${KIBANA_PASSWORD}
      - XPACK_SECURITY_ENABLED=true
      - TELEMETRY_ENABLED=false
    ports:
      - "5601:5601"
    depends_on:
      s8-elasticsearch:
        condition: service_healthy
    volumes:
      - kibana-data:/usr/share/kibana/data
    networks:
      - extagram-monitoring
    healthcheck:
      test: ["CMD-SHELL", "curl -sf http://localhost:5601/api/status | grep -q available"]
      interval: 15s
      timeout: 10s
      retries: 10
      start_period: 90s

  s10-filebeat:
    image: docker.elastic.co/beats/filebeat:8.13.0
    container_name: s10-filebeat
    restart: unless-stopped
    user: root
    environment:
      - ELASTIC_PASSWORD=${ELASTIC_PASSWORD}
      - DOCKER_API_VERSION=1.43
    volumes:
      - ./filebeat/filebeat.yml:/usr/share/filebeat/filebeat.yml:ro
      - ./nginx/modsec-logs:/var/log/modsec:ro
      - /var/lib/docker/containers:/var/lib/docker/containers:ro
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - filebeat-data:/usr/share/filebeat/data
    command: filebeat -e --strict.perms=false
    depends_on:
      s8-elasticsearch:
        condition: service_healthy
    networks:
      - extagram-monitoring
      - extagram-frontend

volumes:
  elasticsearch-data: { driver: local }
  kibana-data:        { driver: local }
  filebeat-data:      { driver: local }

networks:
  extagram-monitoring: { driver: bridge }
  extagram-frontend:   { driver: bridge }
  extagram-backend:    { driver: bridge }
EOF
```

---

## 6. Abrir el puerto 5601 en AWS

Kibana escucha en el puerto 5601. Por defecto AWS lo bloquea. Hay que añadir una regla de entrada en el Security Group de la instancia restringiéndola únicamente a tu IP.

**En la consola de AWS:**

```
EC2 → Instances → clic en tu instancia
→ pestaña "Security"
→ clic en el nombre del Security Group
→ "Edit inbound rules" → "Add rule"

  Type:        Custom TCP
  Protocol:    TCP
  Port range:  5601
  Source:      My IP   ← AWS rellena tu IP automáticamente
  Description: Kibana - solo mi IP

→ "Save rules"
```

**Importante:** Usar siempre `My IP` en Source, nunca `Anywhere (0.0.0.0/0)`.

---

## 7. Arranque del stack

### 7.1 Arrancar solo Elasticsearch primero

```bash
docker compose -f docker-compose.yml -f docker-compose.monitoring.yml up -d s8-elasticsearch
```

Esperar a que esté healthy (~60 segundos):

```bash
watch docker ps --filter name=s8-elasticsearch
```

Cuando la columna STATUS muestre `(healthy)` → `Ctrl+C` y continuar.

### 7.2 Configurar el usuario kibana_system

Elasticsearch tiene usuarios internos que necesitan password. Ejecutar y cuando pregunte introducir el valor de `KIBANA_PASSWORD` del `.env`:

```bash
docker exec -it s8-elasticsearch \
  elasticsearch-reset-password -u kibana_system --interactive
```

### 7.3 Levantar el resto del stack

```bash
docker compose -f docker-compose.yml -f docker-compose.monitoring.yml up -d
```

### 7.4 Verificar que todo está en pie

```bash
docker ps | grep -E "elasticsearch|kibana|filebeat"
```

---

## 8. Acceder a Kibana

Abrir en el navegador:

```
http://TU_IP_PUBLICA_AWS:5601
```

- **Usuario:** `elastic`
- **Password:** valor de `ELASTIC_PASSWORD` en el `.env`



---

## 9. Crear el Data View en Kibana

Kibana necesita saber qué índices mostrar. Crear el Data View que agrupa todos los índices de Extagram:

```
Stack Management → Kibana → Data Views → "Create data view"

  Name:            Extagram Logs
  Index pattern:   extagram-*
  Timestamp field: @timestamp

→ "Save data view to Kibana"
```

---

## 10. Ver logs en tiempo real

### Logs en stream (vista más sencilla)

```
Observability → Logs → Stream
```

### Explorador de logs con filtros

```
Analytics → Discover
→ Seleccionar "Extagram Logs" en el selector superior izquierdo
→ Cambiar rango de tiempo a "Last 15 minutes"
```

Filtros útiles en la barra de búsqueda:

```
docker.container.name: "s1-nginx"     → solo logs de Nginx
docker.container.name: "s7-mysql"     → solo logs de MySQL
service: "modsecurity"                → solo alertas del WAF
```

---

## 11. Política ILM — retención automática de logs

Desde Kibana → Dev Tools (icono llave inglesa en el menú izquierdo) ejecutar:

```json
PUT /_ilm/policy/extagram-logs-policy
{
  "policy": {
    "phases": {
      "hot":    { "actions": { "rollover": { "max_age": "1d", "max_size": "2gb" } } },
      "warm":   { "min_age": "3d",  "actions": {} },
      "delete": { "min_age": "30d", "actions": { "delete": {} } }
    }
  }
}
```

Resultado esperado:

```json
{
  "acknowledged": true
}
```

Los logs se borrarán automáticamente al cumplir 30 días, evitando que el disco se llene.

---

## Troubleshooting

**Elasticsearch en Restarting:**

```bash
docker logs s8-elasticsearch --tail 20
# Buscar "OutOfMemory" — si aparece subir heap: ES_JAVA_OPTS=-Xms768m -Xmx768m
```

**Filebeat: "client version too old":**

Añadir en el servicio s10-filebeat del compose:
```yaml
environment:
  - DOCKER_API_VERSION=1.43
```

**Filebeat: "config file must be owned by root":**

```bash
sudo chown root:root filebeat/filebeat.yml
sudo chmod go-w filebeat/filebeat.yml
```

**Puerto 5601 no accesible desde el navegador:**

Verificar la regla en AWS Security Group — asegurarse de que el Source es tu IP y no `0.0.0.0/0`.

**Ver uso de RAM de los contenedores:**

```bash
docker stats --no-stream | grep -E "elasticsearch|kibana|filebeat"
```

**Disco lleno:**

```bash
docker system prune -f
```