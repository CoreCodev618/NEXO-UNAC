# NEXO UNAC - Arquitectura Final
Knowledge Workspace Inteligente estilo Notion para la UNAC + Chatbot IA (RAG como V2)

## 1. Idea de proyecto
**Nombre:** NEXO UNAC
**Tipo:** Plataforma web colaborativa estilo Notion para estudiantes y docentes UNAC.
**Problema real:** Información académica dispersa (WhatsApp, PDFs, Facebook), apuntes que se pierden cada ciclo, trámites confusos, poca cultura tech/IA y de documentación.
**Solución:** Un workspace central por Facultad/Curso con páginas anidadas, bloques, wikis, Kanban, calendario, Tech Radar de eventos IA reales, y Chatbot Tutor contextual.

Diferencia con SGA: SGA es transaccional-administrativo (notas, matrícula, asistencia a clases). NEXO es productivo-colaborativo (aprender, documentar, organizar proyectos) + agenda tech extracurricular. No lo reemplaza ni lo integra. No se registra asistencia académica aquí.

## 2. Restricciones del cliente
1. Sin integración con SGA ni Drive privado (esa info queda fuera del alcance).
2. Acceso exclusivo con correo institucional UNAC (ej. `@unac.edu.pe` - configurable en `.env`).
3. RAG con BD vectorial queda como Versión 2 (solo si hay tiempo). V1 es chatbot sin RAG pero con `chat_sessions` obligatorio.
4. Stack obligatorio: CodeIgniter 4.
5. Mínimo 10 tablas exigido: se entrega 17 (15 V1 + 2 stub V2).
6. Piloto solo FIIS (no hay data de otras facus). Arquitectura mult-facultad, seed solo FIIS.

## 3. Stack V1
- **Backend:** CodeIgniter 4.5 + PHP 8.2 + Composer + Spark
- **BD Principal:** MariaDB / MySQL 10.6+ (única BD en V1)
- **Auth:** Shield (session) + Filtro de dominio institucional + Google OAuth opcional solo restringido a dominio
- **Frontend:** Vistas CI4 + Bootstrap 5 + JS Vanilla (editor bloques, kanban, chatbot widget) - sin SPA framework para no complicar
- **IA V1:** Proveedor LLM vía HTTP (Gemini / Groq) a través de interfaz `AIService`. Sin vectorial. Con sesiones e historial.
- **IA V2 (futuro):** Qdrant (Docker) como BD vectorial + pipeline ingest/chunk/embed. Dueño exclusivo: integrante IA.
- **Deploy:** XAMPP/local + hosting compartido/VPS con Apache
- **Ingesta eventos (sugerencia, decisión libre backend):** Cloudflare Worker + Cron Trigger externo (ver sección 9)

## 4. Arquitectura lógica
```
[Browser] -> [CI4 Web Controllers + Views] -> [CI4 Api/V1 JSON]
                                            -> [MySQL 15 tablas V1]
                                            -> [AIService -> LLM Provider (V1)]
                                            -> [V2: AIService -> Qdrant -> LLM]
[Cloudflare Worker cron] -> POST /api/v1/events/ingest -> [MySQL events draft] (solo si backend lo elige)
```

Principios:
- MVC estricto de CI4, capa `Services/` para lógica IA y eventos.
- API REST `/api/v1/*` para que Frontend consuma sin SQL directo.
- `AIServiceInterface::ask()` es el único puente Backend <-> IA.
- Backend/BD no toca vectorial. IA no toca migraciones relacionales.
- Vista Tech Radar / Eventos es propiedad exclusiva del integrante Backend (ver sección 8).

Por qué MySQL + vectorial en V2 y no solo una:
- MySQL = sistema de verdad (users, workspaces, pages, auth, relaciones, ACID). CI4 + Shield están hechos para relacional.
- Qdrant = solo búsqueda semántica (embeddings + similitud coseno). MySQL no hace esto eficiente.
- V1 funciona sin Qdrant (contexto inyectado desde MySQL + `chat_sessions`). V2 agrega Qdrant sin romper nada.

## 5. Estructura de carpetas CI4
```
app/
 Config/Routes.php (grupos web + api/v1 + filtro auth + domain-check)
 Controllers/Web/ (Home, Workspace, Page, Dashboard, EventViewController [solo backend])
 Controllers/Api/V1/ (WorkspaceController, PageController, BlockController, TaskController, EventController, EventIngestController, AIChatController)
 Models/ (FacultyModel, UserModel, WorkspaceModel, PageModel, BlockModel, TagModel, CommentModel, AttachmentModel, TaskModel, EventModel, EventInterestModel, ChatSessionModel, ChatMessageModel, DocumentModel)
 Database/Migrations/ (una por tabla, 17) + Seeders (FacultySeeder solo FIIS + workspaces EP Sistemas/Industrial/Club IA)
 Filters/ (InstitutionalDomainFilter, AuthFilter, ApiKeyFilter para ingest)
 Services/ (AIServiceInterface.php, GeminiProvider.php, EventService.php, EventSourceInterface.php, QdrantService.php [V2 stub])
 Views/layouts/ (main.php), Views/partials/ (navbar, sidebar, chatbot-widget), Views/modules/ (workspaces, editor, kanban) + Views/events/ (solo backend)
 Libraries/ (InstitucionalValidator)
public/js/ (editor-bloques.js, kanban.js, chatbot.js) + public/js/events.js (solo backend)
public/css/ (nexo.css)
workers/ (sugerido, fuera de CI4: cloudflare-event-worker.js + wrangler.toml) - decisión libre backend
.env (ALLOWED_EMAIL_DOMAINS=unac.edu.pe, GEMINI_API_KEY=, EVENT_INGEST_API_KEY=)
```

## 6. Modelo BD relacional (17 tablas: 15 V1 + 2 V2)
Cumple mínimo 10 con diseño elegante por módulos. Sin tablas relleno.

Organización (4):
- `faculties`: id, name, short_code. Seed único: `FIIS`. Escalable a otras facus sin cambiar código.
- `users`: id, email UNIQUE (validado dominio), name, faculty_id FK -> faculties, role alumno/docente/admin, password_hash, created_at
- `workspaces`: id, faculty_id FK, name, type (EP-Sistemas/EP-Industrial/club/tesis), owner_id FK, created_at. Seeds iniciales: EP Sistemas, EP Industrial, Club IA-FIIS.
- `workspace_members`: id, workspace_id FK, user_id FK, role, UNIQUE(workspace_id,user_id)

Conocimiento estilo Notion (6):
- `pages`: id, workspace_id FK, parent_id NULL FK (anidadas), title, icon, cover, order_index, created_by, updated_at
- `blocks`: id, page_id FK, type (parrafo/checklist/codigo/tabla/quote/callout), content JSON/TEXT, order_index
- `tags`: id, name UNIQUE, color
- `page_tags`: page_id FK, tag_id FK, PK compuesta. Para buscador y filtro.
- `comments`: id, page_id FK, user_id FK, content TEXT, created_at. Colaboración.
- `attachments`: id, page_id FK, user_id FK, file_path, file_type, file_size, created_at. Reemplaza Drive disperso.

Productividad + Tech Radar (3 V1):
- `tasks`: id, workspace_id FK, page_id NULL, title, status (todo/doing/done), priority, due_date, assigned_to FK
- `events`: id, title, description, category (hackathon/workshop/competencia IA/feria), event_date, end_date NULL, mode (presencial/virtual/híbrido), location, link_original TEXT (obligatorio, fuente real), source (nasa/devpost/luma/manual), source_url TEXT, external_id VARCHAR NULL, status (draft/published), created_by, created_at. Índice UNIQUE(link_original(255)), índice event_date + status.
- `event_interests`: id, event_id FK, user_id FK, status (guardado/inscrito), UNIQUE(event_id,user_id). OJO: no es asistencia académica del SGA, es solo interés en evento extracurricular tech.

IA Chat con memoria (2 V1 obligatorias):
- `chat_sessions`: id, user_id FK, page_id NULL (contexto donde se abrió), title, created_at
- `chat_messages`: id, session_id FK -> chat_sessions, role (user/assistant), content TEXT, tokens INT NULL, created_at

Stub V2 RAG (2, vacías en V1):
- `documents`: id, page_id FK NULL, title, file_path, indexed_v2 BOOLEAN DEFAULT 0
- `document_chunks`: id, document_id FK, chunk_index INT, content TEXT, token_count INT, embedding_id VARCHAR NULL (id en Qdrant)

Reglas: cascada workspace->pages->blocks/comments/attachments, session->messages, event->interests, document->chunks. Índices en workspace_id, page_id, session_id, email, event_date.

## 7. Auth solo institucional
- Registro/Login valida regex: `/^[a-z0-9._%+-]+@unac\.edu\.pe$/i` + `ALLOWED_EMAIL_DOMAINS` en `.env`.
- `InstitutionalDomainFilter` en `Routes.php` para `/register`, `/login`, `/auth/*`. Rechaza 403 si no es dominio.
- Si se usa Google OAuth: verificar `hd=unac.edu.pe`.
- Docente validado por rol manual de admin en V1.

## 8. División en 3 integrantes (contratos)

### Int. 1 - Backend / BD + Eventos (NO toca vectorial, dueño exclusivo vista eventos)
Dueño: Migraciones (17), Models, Api/V1, Filters, Config/Routes, `Views/events/` + `public/js/events.js` en exclusiva, `workers/` si lo elige.
Es libre de decidir cómo actualizar eventos: manual curado, cron Spark en CI4, o Worker Cloudflare. Nadie más toca esa vista.
Endpoints que debe exponer:
```
GET/POST /api/v1/workspaces
GET/POST /api/v1/pages | PUT/DELETE /api/v1/pages/{id}
GET/POST /api/v1/blocks | PUT/DELETE /api/v1/blocks/{id}
GET/POST /api/v1/tasks | PUT /api/v1/tasks/{id}/move
GET /api/v1/events?status=published
POST /api/v1/events (admin, manual)
POST /api/v1/events/ingest (X-API-KEY, guarda draft, deduplica por link_original)
POST /api/v1/events/{id}/interest (guardado/inscrito)
POST /api/v1/chat (pasa a Int.3, pero ruta la crea Int.1)
GET /events (vista completa, solo backend)
```

### Int. 2 - Layout / UX-UI / Frontend (NO SQL, NO toca vista eventos)
Dueño: `Views/layouts, partials, modules/workspaces,editor,kanban`, `public/js|css` excepto events, responsive, editor bloques, Kanban drag-drop, calendario general, widget chatbot (solo UI).
Consume solo API JSON del Int.1. Archivos: `editor-bloques.js`, `kanban.js`, `chatbot.js`.
Entrega maqueta: sidebar Notion + página + kanban. No interviene en `Views/events/`.

### Int. 3 - IA / Chatbot-RAG (único dueño vectorial, NO toca migraciones MySQL)
V1 obligatorio (chatbot con memoria, sin RAG):
- `Services/AIServiceInterface.php` + `GeminiProvider.php` (cURL con system prompt UNAC + contexto página actual desde MySQL + historial `chat_sessions`)
- `Controllers/Api/V1/AIChatController.php` (rate-limit, crea/recupera `chat_sessions`, guarda en `chat_messages`)
- Widget conectado, prompt con facultad FIIS/curso/página.
V2 opcional (RAG):
- `Services/QdrantService.php` + colección `nexo_docs`, pipeline chunk (500 tokens) + embeddings, `documents.indexed_v2=1`, `document_chunks.embedding_id`
- Retrieval top-5 + citas. No cambia esquema MySQL salvo leer `documents/chunks`.

## 9. Módulo Tech Radar - eventos reales (propiedad exclusiva backend)
Requisito: prohibido inventar eventos. Todo `events` debe tener `link_original + source_url` verificable. Ejemplo válido: NASA Space Apps Challenge, hackathones de Devpost, calendarios Luma Lima (Tech Lima, GDG Lima), Kaggle, MLH. Se actualiza cuando sale uno nuevo, local o no.

V1 mínimo: CRUD manual admin con link real (10-15 seeds reales para demo).
V1.5 sugerido (decisión libre backend, no obligatorio): automatizar ingesta.

Sugerencia Cloudflare para backend (libre de usar o no):
```
Cloudflare Worker + Cron Trigger diario -> fetch fuentes (Devpost discover, NASA, Luma slugs curados, Eventbrite) -> normaliza title/date/link/source -> POST CI4 /api/v1/events/ingest con EVENT_INGEST_API_KEY -> CI4 valida key, deduplica por link_original/external_id, guarda status=draft -> admin aprueba a published
```
- Por qué Worker y no cron del hosting: gratis, siempre prendido, no carga PHP con scraping.
- Límite honesto: Luma no tiene API oficial, se lee vía JSON/ICS público de 2-3 calendarios fijos. Si Luma cambia frontend o el evento solo está en Instagram/Discord cerrado, queda manual. Worker en JS (~80 líneas, `scheduled()` + `fetch()`), vive en `workers/` fuera de CI4, no afecta CodeIgniter.
- Alternativas válidas si backend prefiere: Spark Task + cron cPanel, o 100% manual. Cualquiera vale mientras `source_url` sea real.
- Siempre citar fuente y link original, no copiar contenido completo.

Dueño total: Int.1 (modelo + ingest + vista). Int.2 no toca esta vista. Int.3 solo resume descripción con IA si se le pide.

## 10. Roadmap maquetado
- S1: Setup CI4 + MySQL + Shield + DomainFilter + migraciones 15 V1 + FacultySeeder solo FIIS (Int.1), layout base + sidebar (Int.2), prueba Gemini key + `chat_sessions` (Int.3)
- S2: CRUD Workspaces/Pages/Blocks/Tags/Comments + editor bloques funcional
- S3: Kanban + Tasks + Tech Radar real (manual mínimo + ingest draft si backend elige Worker) + chatbot V1 con memoria por sesión flotante
- S4: Pulido UX, seeders demo EP Sistemas/Industrial, 10+ eventos reales publicados, presentación. V2 RAG (`documents/chunks` + Qdrant) solo si sobra tiempo.

## 11. Criterios de aceptación V1
- Solo deja registrar/loguear `@unac.edu.pe`.
- 17 migraciones corren sin error (15 con datos, 2 V2 vacías).
- Crear workspace FIIS > página anidada > bloques + tags + comentario y verlo persistido.
- Mover tarea en Kanban y persiste estado.
- Chatbot crea `chat_sessions` y mantiene historial por sesión usando contexto página actual.
- `/events` muestra solo eventos reales con link verificable, con `draft/published`. Sin eventos inventados.
- Código Qdrant + Worker como stub/sugerencia sin romper V1 si backend no los usa.

## 12. Workflow GitHub + test IA en terminal (repo NEXO-UNAC)
Repo: https://github.com/CoreCodev618/NEXO-UNAC (público).
Ramas: `main` (protegida, 1 approve, sin force) + `feature/backend-bd` + `feature/frontend-ux` + `feature/ia-chatbot`.
Todo a `main` vía PR chico (<300 líneas) con template `.github/pull_request_template.md`. Anti-conflictos: cada uno solo toca sus carpetas (sección 8). Conflicto: `git fetch origin main && git rebase origin/main`.
IA (tú) testeas sin front: `php spark serve` + `./scripts/test-ia.sh` (curl a `/api/v1/chat`, historial `chat_sessions`). Ver `docs/workflow-git.md`.
Cloudflare Worker sugerido vive en `workers/` (dueño backend, libre).
