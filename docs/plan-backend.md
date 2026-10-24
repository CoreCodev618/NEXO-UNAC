# PLAN DE IMPLEMENTACIÓN — BACKEND/BD (Int. 1)

> **Naturaleza de este documento:** es el contrato de ejecución del integrante
> Backend/BD. Sus reglas derivan 1:1 de `docs/arquitectura.md` (la "ley") y
> `docs/workflow-git.md`. Si algo de este plan contradice esos documentos,
> gana `arquitectura.md` y este plan se corrige.
>
> Rama: `feature/backend-bd` · Dueño: Int.1 · Estado: **EN EJECUCIÓN**

---

## 0. Fuente de verdad y no-alcance

### 0.1 Ley (obligatoria, de arquitectura.md)
| # | Regla | Cómo la cumple este plan |
|---|-------|--------------------------|
| L1 | CodeIgniter 4.5 + PHP 8.2 + MariaDB | Fase F0 fija versiones |
| L2 | 17 migraciones (15 V1 + 2 stub V2), mínimo 10 exigidas | Fase F1, una migración por tabla |
| L3 | Auth solo `@unac.edu.pe` vía `ALLOWED_EMAIL_DOMAINS` | Filtro + validación en registro (F3/F4) |
| L4 | `AIService::ask()` es ÚNICO puente backend↔IA | Backend NO crea `AIService*` ni `AIChatController.php` (Int.3) |
| L5 | Backend NO toca Qdrant / vectorial | No existe `QdrantService` en este plan |
| L6 | Vista `events/` + `public/js/events.js` + `workers/` = exclusiva Int.1 | Fase F5/F6 |
| L7 | Todo evento con `link_original` + `source_url` reales (prohibido inventar) | Seeds F6 = solo eventos verificables con link |
| L8 | API REST `/api/v1/*` para que Front consuma sin SQL directo | Fase F4 |
| L9 | `POST /api/v1/chat` la CREA backend (la implementa Int.3) | F3 declara la ruta; F4 deja stub que redirige a futuro `AIChatController` |
| L10 | PRs <300 líneas, 1 tema, prefijo `backend:`/`events:`/`docs:` | Reglas de commit §7 |

### 0.2 NO-alcance explícito (no hacer, no "arreglar")
- ❌ `app/Services/AIService*`, `GeminiProvider`, `AIChatController`, `QdrantService` (Int.3).
- ❌ `app/Views/` (salvo `events/`), `public/css`, `public/js` (salvo `events.js`) (Int.2).
- ❌ Editar tablas V2 (`documents`, `document_chunks`) más allá de crearlas vacías.
- ❌ Integración con SGA ni Google Drive.
- ❌ Soft deletes, APIs de terceros no listadas, SPA frameworks.

---

## 1. Diagnóstico de entorno (verificado 2026-10-16)

| Componente | Estado | Acción requerida | ¿Bloqueante? |
|---|---|---|---|
| PHP 8.5.11 CLI | ✅ OK | — | No |
| Extensiones php: mysqli, pdo_mysql, mbstring, curl, openssl, xml | ✅ OK | — | No |
| **ext-intl** | ❌ FALTA | `sudo pacman -Syu php-intl` (requiere clave) | **Sí** — CI4 la exige |
| **Composer** | ❌ FALTA | Instalar **local sin sudo**: `composer.phar` en `~/.local/bin/composer` | Sí, pero resuelvable sin root |
| MariaDB 13 | ⚠️ Instalado pero `inactive (dead)` | `sudo systemctl enable --now mariadb` (requiere clave) | **Sí** para migrar |
| Docker | ✅ presente (perfil roto ver AGENTS.md) | NO usar para BD — usar MariaDB nativo | No |
| Node/npm | ✅ presente | Solo si se implementa el Worker (F6 opcional) | No |
| Internet (packagist) | ✅ OK | — | No |

**Consecuencia operativa:** mientras el usuario no levante MariaDB y agregue
`php-intl`, el código se escribe y versiona igual, pero `php spark migrate`
/ `db:seed` / `spark serve` queda **pendiente de validación** y así se marca
en cada PR (sección "Test" del template).

---

## 2. Buenas prácticas adoptadas (obligatorias en este plan)

1. **Migraciones reversibles**: toda migración implementa `up()` y `down()`.
   `down()` en orden inverso respetando FKs.
2. **Una migración = una tabla**, nombre `<Fecha>_<Hora>_Create<X>Table`.
3. **Models como única puerta de escritura**: reglas `$validationRules`,
   `$allowedFields`, casts de fechas, y relaciones (`hasOne/hasMany/belongsToMany`).
   Los Controllers **nunca** usan `\Config\Database::connect()` directo.
4. **Respuestas JSON uniforme** en `/api/v1`: `{success, data, message, errors}`
   vía trait `ApiResponseTrait` (200/201/204/400/401/403/404/422/429).
5. **Seguridad**: passwords con `password_hash()` (BCRYPT, vía Shield o helper),
   escaping de entrada, límite de payload, API key comparada con `hash_equals()`,
   rate-limit básico en ingest y chat (F4/F5).
6. **Transacciones** en operaciones en cadena (p.ej. crear página + bloques,
   ingest de evento + dedupe).
7. **Nada de secrets en repo**: `.env` está en `.gitignore`; solo viaja `.env.example`.
8. **Commits atómicos** con prefijo (§7). Nada de "wip" o "fix" solo.
9. **Seeders idempotentes** (usar `upsert`/existe-previo) para poder re-correrlos.
10. **Estilo**: PSR-12, type hints y `declare(strict_types=1)` en clases nuevas.
11. **Sin código muerto**: si no se usa en V1, no se escribe (KISS/YAGNI).
12. **Docs vivas**: cada endpoint nuevo se anota en `docs/endpoints.md` (F4+).

---

## 3. Estructura objetivo (solo carpetas dueñas de Int.1)

```
app/
  Config/          App.php, Database.php, Routes.php, Env.php*, Filters.php
  Controllers/
    Web/           EventViewController (F5)
    Api/V1/        WorkspaceController, PageController, BlockController,
                   TagController, CommentController, TaskController,
                   EventController, EventIngestController, AiChatRouteStub (F4)
  Database/
    Migrations/    17 archivos (F1)
    Seeders/       FacultySeeder, WorkspaceSeeder, EventSeeder (F6), DemoSeeder (F6)
  Filters/         InstitutionalDomainFilter, ApiKeyFilter, AuthFilter
  Models/          14 models (F2)
  Services/        EventService.php + EventSourceInterface.php (F5)
  Views/events/    index.php (F5, exclusiva)
public/js/events.js (F5, exclusiva)
workers/           (opcional, F6)
docs/plan-backend.md, docs/endpoints.md
```

---

## 4. Fases y commits planificados

> Cada fila = 1 commit (o PR, si supera 300 líneas se parte en 2).
> Orden topológico: una fase no empieza si la anterior no está commiteada.

| # | Fase | Archivos clave | Commit esperado | Bloqueo |
|---|------|----------------|-----------------|---------|
| F0a | Scaffold CI4 + composer.json (fijar `codeigniter4/framework:^4.5`) + .gitignore/.env.example ajustados + spark local | `composer.json`, `spark`, `app/Config/*`, `public/index.php`, `writable/**` | `backend: scaffold CodeIgniter 4.5 + config base (env, rutas, spark)` | composer local |
| F0b | Verificar `php spark serve` + home page 200 | — | `backend: smoke test spark serve + healthcheck /` | php-intl, MariaDB |
| F1a | Migraciones **Organización** (4): `faculties`, `users`, `workspaces`, `workspace_members` | 4 migrations | `backend: migracion 4 tablas organizacion (faculties, users, workspaces, members)` | F0 |
| F1b | Migraciones **Conocimiento** (6): `pages`, `blocks`, `tags`, `page_tags`, `comments`, `attachments` | 6 migrations | `backend: migracion 6 tablas conocimiento notion (pages, blocks, tags, comments, attachments)` | F1a |
| F1c | Migraciones **Productividad+Radar** (3): `tasks`, `events`, `event_interests` | 3 migrations | `backend: migracion 3 tablas productividad (tasks, events, event_interests)` | F1a |
| F1d | Migraciones **IA (solo esquema)** (2): `chat_sessions`, `chat_messages` + **stubs V2** (2): `documents`, `document_chunks` | 4 migrations | `backend: migracion 4 tablas ia chat + stubs rag v2 (esquema vacio)` | F1a |
| F1e | Seeders mínimos: `FacultySeeder` (FIIS) + `WorkspaceSeeder` (EP Sistemas, EP Industrial, Club IA) | 2 seeders | `backend: seeders faculty fiis + 3 workspaces iniciales` | F1a |
| F2 | Models (14) con `$validationRules`, `$allowedFields`, casts y relaciones | `app/Models/*` | `backend: models ci4 con validacion, casts y relaciones (14 entidades)` | F1 |
| F3 | Filters (`AuthFilter`, `InstitutionalDomainFilter`, `ApiKeyFilter`) + `Config/Routes.php` (grupos `web` + `api/v1`) + `Helpers/nexo_helper.php` (respuesta json, dominio) | 3 filters, Routes, helper | `backend: filtros auth+dominio+api-key y rutas api/v1` | F2 |
| F4a | API CRUD **Workspace + Page + Block** (con `ApiResponseTrait`) | 3 controllers + trait | `backend: api v1 workspaces pages blocks crud` | F3 |
| F4b | API CRUD **Tags + Comments + Attachments** | 3 controllers | `backend: api v1 tags comments attachments` | F4a |
| F4c | API **Tasks + move Kanban** | 1 controller | `backend: api v1 tasks + move kanban` | F4a |
| F4d | Stub de ruta `POST /api/v1/chat` (deja contrato listo p/Int.3) | 1 controller stub | `backend: stub ruta api/v1/chat (contrato int.3)` | F3 |
| F5a | `EventService` + `EventSourceInterface` + `EventController` (admin CRUD) + `EventIngestController` (API-key, dedupe por `link_original`) | services + 2 controllers | `events: event service + ingest con dedupe + api key` | F4a |
| F5b | Vista Tech Radar `/events` (index.php) + `public/js/events.js` (filtro, publicar/draft, interés) | `Views/events/index.php`, `public/js/events.js` | `events: vista tech radar /events + events.js (solo backend)` | F5a |
| F5c | Endpoint `POST /api/v1/events/{id}/interest` (guardado/inscrito) | EventController | `events: endpoint interest guardado/inscrito` | F5a |
| F6a | `EventSeeder` con **10–15 eventos reales verificables** (NASA Space Apps, Devpost, Luma/GDG Lima, Kaggle, MLH…) | 1 seeder | `events: seeder 12+ eventos reales verificables (solo links oficiales)` | F5a |
| F6b | `DemoSeeder` FIIS: usuarios demo (alumno/docente/admin) + páginas/bloques/tags/comentarios/tareas de ejemplo | 1 seeder | `backend: demo seeder fiis (usuarios, paginas, bloques, tareas demo)` | F2 |
| F7 | `docs/endpoints.md` (matriz completa) + índices faltantes + rate-limit chat/ingest + pass final de revisión | docs + ajustes | `docs: matriz endpoints v1 + ajustes finales seguridad` | F5, F6 |
| F6o | (OPCIONAL, bajo demanda) Worker Cloudflare `workers/cloudflare-event-worker.js` + `wrangler.toml` | fuera de CI4 | `events: worker cloudflare ingest cron (opcional)` | F5a |

**Total estimado:** ~18 commits. Cada PR <300 líneas o se parte.

---

## 5. Criterios de aceptación V1 (espejo 1:1 de arquitectura.md §11)

- [ ] C1: registro/login solo acepta `@unac.edu.pe` (`.env` configurable).
- [ ] C2: 17 migraciones corren sin error (`spark migrate --all`), 15 con datos reales mínimos y 2 stubs V2 vacías.
- [ ] C3: crear workspace FIIS → página anidada → bloques + tags + comentario persiste en BD.
- [ ] C4: mover tarea Kanban (`PUT /tasks/{id}/move`) persiste `status` y `order_index`.
- [ ] C5: ruta `/api/v1/chat` existe (stub 501) y está lista para que Int.3 la implemente.
- [ ] C6: `/events` muestra solo eventos reales con `link_original`+`source_url`, con estados `draft`/`published`.
- [ ] C7: ingest deduplica por `link_original` (índice UNIQUE) y exige `X-API-KEY` correcta.
- [ ] C8: sin tocar Qdrant ni archivos de Int.2/Int.3 (`git log --name-only` auditable por rama).

---

## 6. Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| MariaDB/ext-intl siguen caídos | No poder validar migrate/serve | El plan avanza escribiendo; PR se marca "test pendiente" y se cierra el ciclo al restaurar el entorno. **Se pide al usuario** levantar `mariadb` e instalar `php-intl` |
| Luma sin API oficial | Ingest automático frágil | V1 = **manual curado** (cumple mínimo). Worker opcional solo si el usuario lo pide |
| Choque de ramas (Int.2/Int.3) | Conflictos de merge | No tocar sus carpetas; PR chicos; rebase ante cada cambio |
| PHP 8.5 vs requerimiento 8.2 | Incompatibilidad menor CI4 | CI4.5 soporta ≥8.1; verificar con `composer check-platform-reqs` |
| Índice UNIQUE `link_original(255)` en MySQL utf8mb4 | Posible exceso de bytes | Usar `VARCHAR(255)` + `utf8mb4` OK (255×4=1020 < 3072) |

---

## 7. Reglas de commit / PR (workflow-git.md)

- Prefijo obligatorio: `backend:` · `events:` · `docs:` (no `frontend:`/`ia:`).
- Cuerpo del commit: qué + por qué + criterion de prueba (ej. `Test: spark migrate:status`).
- Un commit = un cambio coherente y compilable/ejecutable por sí solo.
- PR por fase (o sub-fase si >300 líneas) contra `main`, usando el template.
- Anti-conflictos: `git fetch origin main && git rebase origin/main` antes de push.

---

## 8. Checklist final antes de cada PR

- [ ] `php spark migrate:status` sin pendientes (o "pendiente de entorno" documentado).
- [ ] No hay cambios fuera de carpetas de Int.1 (`git diff --name-only origin/main...HEAD`).
- [ ] No hay secrets (`.env` no incluido).
- [ ] README/endpoints docs actualizados si cambió un contrato.
- [ ] Criterios C1–C8 de §5 revisados para la fase correspondiente.
