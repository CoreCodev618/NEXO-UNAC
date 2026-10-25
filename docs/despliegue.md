# Despliegue del PilotoV1

Este documento cubre la ruta que se puede probar **hoy en local** y la ruta
gratuita propuesta para que el profesor abra el piloto por enlace. La app sigue
siendo CodeIgniter 4 con vistas propias; no se separa el frontend.

## 1. Requisitos

- Docker con Compose v2 (probado con Docker 29.9.1).
- Puerto libre: `8083` para la app. La base no se publica al host; solo es
  accesible dentro de la red del compose.
- Para Render: cuenta gratuita y una MySQL gratuita externa (por ejemplo Aiven).

## 2. Demo local con Docker (recomendada)

```bash
# desde la raíz del repo
docker compose up --build -d

# seguimiento del arranque (migraciones + DemoSeeder)
docker compose logs -f app

# cuando el log diga que Apache arrancó
curl -I http://localhost:8083/login
```

Abrir <http://localhost:8083/login> y usar una de las cuentas sintéticas que
muestra la pantalla:

| Cuenta | Rol en la demo |
|---|---|
| `demo@unac.edu.pe` | owner de los tres espacios |
| `ana.torres@unac.edu.pe` | editora |
| `luis.quispe@unac.edu.pe` | editor en Sistemas, viewer en Industrial |
| `camila.rojas@unac.edu.pe` | viewer |

La contraseña demo está en la propia pantalla de login y en `DemoSeeder`. No es
una credencial real.

El contenedor `app` genera su `.env` al arrancar a partir de `DB_*` y
`APP_BASE_URL`; no se hornean secretos en la imagen. Las migraciones corren con
`NEXO_AUTO_MIGRATE=true` y el seed sintético con `NEXO_AUTO_SEED=true`.

Comandos útiles:

```bash
docker compose ps
docker compose logs --tail=100 app
docker compose exec app php spark migrate:status
docker compose exec db mariadb -unexo -ppiloto-local nexo_unac_piloto \
  -e "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM document_chunks;"
docker compose down            # conserva el volumen de la base
docker compose down -v         # borra también la base local
```

> El volumen de la base es solo para la demo local (`nexo-piloto_nexo-db`). No
> contiene datos reales.

## 3. Prueba desde otro dispositivo de la misma red

Mientras el compose está levantado, la app escucha en todas las interfaces del
host. Para que el celular o la laptop del profesor entren por LAN:

```bash
ip -4 addr show scope global | awk '/inet / {print $2}' | cut -d/ -f1
# ejemplo: 192.168.1.50
# abrir http://192.168.1.50:8083/login
```

Para cambiar la URL base y que las cookies/redirecciones funcionen también por
IP, recrear el servicio con:

```bash
APP_BASE_URL=http://192.168.1.50:8083 docker compose up -d app
```

Esto es solo una prueba temporal. Para un enlace público hace falta un túnel
(por ejemplo Cloudflare Tunnel) o el despliegue de la sección 4. Un túnel no
cambia el código; solo reenvía HTTP al puerto 8083.

## 4. Render Free + MySQL gratuita

### 4.1 Base de datos

1. Crear una MySQL gratuita en Aiven (u otro proveedor con free tier vigente).
2. Anotar host, puerto, base, usuario y contraseña.
3. Permitir conexiones desde Render. Aiven normalmente exige TLS; el blueprint
   activa `DB_SSL=true`.
4. No ejecutar migraciones a mano si se usará `NEXO_AUTO_MIGRATE=true`.

### 4.2 Servicio web

La opción más simple es crear un **Blueprint** en Render apuntando a este repo y
a `render.yaml`, o crear un Web Service con runtime Docker y Dockerfile en la
raíz. Render inyecta `PORT=10000`; el entrypoint reconfigura Apache para
escuchar en ese puerto, así que no hay que fijarlo a mano.

Variables mínimas (las marcadas como secretas se pegan en el panel, nunca en el
repo):

| Variable | Valor |
|---|---|
| `APP_BASE_URL` | `https://<servicio>.onrender.com` (o dejar vacío para usar `RENDER_EXTERNAL_URL`) |
| `DB_HOST` | host de la MySQL |
| `DB_PORT` | puerto de la MySQL |
| `DB_NAME` | base creada |
| `DB_USER` | usuario de la base |
| `DB_PASS` | contraseña (secreta) |
| `DB_SSL` | `true` si el proveedor exige TLS |
| `NEXO_AUTO_MIGRATE` | `true` |
| `NEXO_AUTO_SEED` | `true` |
| `NEXO_PREVIEW` | `false` |

Opcionales para RAG externo:

| Variable | Uso |
|---|---|
| `GEMINI_API_KEY` | embeddings |
| `QDRANT_URL`, `QDRANT_API_KEY` | recuperación vectorial |
| `GROQ_API_KEY` | generación |

Sin estas claves el chat **sigue funcionando** con el fallback léxico local
sobre `document_chunks`; la respuesta se marca `retrieval: "local"` y
`degraded: true`. Es la ruta que se prueba en la demo gratuita.

### 4.3 Límites que hay que decir al profesor

- Render Free duerme el servicio tras un periodo sin tráfico; la primera visita
  puede tardar en despertar.
- El disco de Render Free es efímero: los adjuntos subidos pueden desaparecer al
  reiniciar. El contenido de la base y los chunks no dependen del disco del
  contenedor.
- La base gratuita tiene límites de conexiones y almacenamiento. No usar datos
  personales ni material real de estudiantes.
- El seed es idempotente, pero `NEXO_AUTO_SEED=true` está pensado para una sola
  instancia de demo, no para producción multiusuario.

## 5. Verificación después de desplegar

```bash
BASE=https://<servicio>.onrender.com

curl -sI "$BASE/login" | head -1
curl -s "$BASE/login" | grep -o 'csrf-token' | head -1

# Flujo autenticado mínimo con cookies:
curl -c /tmp/nexo.cookies -s "$BASE/login" -o /tmp/nexo-login.html
# extraer csrf_test_name del HTML y hacer POST a /login con una cuenta demo
```

Para una prueba automatizada completa se puede reutilizar la matriz de smoke
tests local (login por rol, documentos acotados, chat con citas). En la demo
local se ejecutó contra `http://localhost:8082` y en Docker contra
`http://localhost:8083`.

## 6. RAG: qué se probó y qué falta

- **Probado:** fallback local léxico sobre `document_chunks`, filtrado por
  `workspace_id`/`page_id`, citas solo de fuentes permitidas, respuesta sin
  invención cuando no hay evidencia.
- **Probado sin red:** 58 tests unitarios del pipeline Python.
- **No probado en esta máquina:** embeddings reales en Gemini, Qdrant y
  generación con Groq, porque no hay claves ni Qdrant levantado. El código de
  ingesta/retrieve ya envía `workspace_id` y `page_id` y los tests cubren esos
  filtros.
- Para activarlo: crear Qdrant (Docker o Qdrant Cloud free), definir
  `GEMINI_API_KEY`, `QDRANT_URL`, `QDRANT_API_KEY` y `GROQ_API_KEY`, ejecutar
  `python/ingest.py` con el mapeo de `documents.id`, y comprobar que el chat
  responda `retrieval: "vector"` (no `"local"`).

## 7. Resultados de la verificación local (24/10/2026)

| Prueba | Resultado |
|---|---|
| API HTTP por rol, alcance, mutaciones, chat y citas | 104 PASS / 0 FAIL |
| Navegador 1440×900 y 390×844 (Playwright) | 17 PASS / 0 FAIL |
| PHPUnit (MySQL `nexo_unac_test`) | 38 tests, 70 aserciones, 3 skipped por Radar diferido |
| Python unittest | 58 tests OK |
| Reinicio de contenedor con volumen persistente | usuarios/páginas/chunks sin duplicados |
| Puerto dinámico `PORT=10000` (modo Render) | login HTTP 200 |
| `scripts/test-ia.sh` contra Docker | `retrieval: local`, cita de página 1 |

Los 3 tests omitidos son el ingest de eventos: su controlador no está en el
alcance del piloto y la ruta no se publica. El 404 de `/events` sí se prueba.
