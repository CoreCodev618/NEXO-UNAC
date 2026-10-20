# Progreso Backend — NEXO-UNAC

Estado del desarrollo backend por fases. Un commit por subfase, siempre con
tests verdes antes de commitear. Ramas de trabajo salen de
`feature/backend-bd`; `main` solo recibe merge aprobado.

> Última actualización: 2026-10-18 · Rama: `backend/fase1`

## Fases

| # | Fase | Estado | Commit |
|---|------|--------|--------|
| F0.1 | Rebase sobre `main` | ✅ | — (sin commit propio) |
| F0.2 | Entorno de tests en MariaDB + FK de `users` sin SQL crudo | ✅ | `1f7a220` |
| F0.3 | Config base: urls limpias, CSRF global, filtros `apiauth`/`apikey` | ✅ | `d6c0ee6` |
| F1 | Base API v1 (`BaseApiController`, rutas agrupadas, `/me`) | ✅ | `1e1f19e` |
| F2.1 | Models organización/conocimiento (9 modelos) | ⬜ | |
| F2.2 | Models productividad/eventos/IA (7 modelos) | ⬜ | |
| F3 | API workspaces + miembros con control de acceso | ⬜ | |
| F4.1 | API pages + blocks | ⬜ | |
| F4.2 | API tags + comments | ⬜ | |
| F4.3 | API attachments con validación de archivo — whitelist `pdf, docx, pptx, jpg, png, zip` (`.rar` → 422, issue #5) | ⬜ | |
| F5 | API tasks + move Kanban | ⬜ | |
| F6.1 | Event service + API admin | ⬜ | |
| F6.2 | Ingest con API key, dedupe y rate limit | ⬜ | |
| F6.3 | Interés guardado/inscrito | ⬜ | |
| F6.4 | Vista Tech Radar `/events` + `events.js` | ⬜ | |
| F7.1 | Seeder de eventos reales verificados | ⬜ | |
| F7.2 | Demo seeder FIIS | ⬜ | |
| F8.1 | Documentación: matriz de endpoints | ⬜ | |
| F8.2 | Revisión final de seguridad | ⬜ | |

## Criterios de aceptación V1

| # | Criterio | Estado |
|---|----------|--------|
| C1 | Registro/login solo acepta `@unac.edu.pe` | ✅ (regla `institutional_email` con test) |
| C2 | 17 migraciones corren sin error | ✅ (`migrate:refresh --all` verificado) |
| C3 | Workspace → página anidada → bloques + tags + comentario persiste | ⬜ F3/F4 |
| C4 | Mover tarea Kanban persiste `status` y `order_index` | ⬜ F5 |
| C5 | Ruta `/api/v1/chat` lista para el chatbot | ✅ (ruta registrada y protegida con `apiauth`; responde 404 hasta existir su controlador) |
| C6 | `/events` muestra solo eventos reales con fuente verificable | ⬜ F6/F7 |
| C7 | Ingest deduplica por `link_original` y exige API key | ⬜ F6.2 |
| C8 | Sin tocar archivos de otros módulos (auditable por rama) | ✅ |

## Cómo verificar el estado

```bash
composer test                        # suite completa (MariaDB nexo_test)
php spark migrate:status             # migraciones aplicadas
php spark migrate:refresh --all      # rehacer BD dev (borra datos)
```

## Contratos de respuesta API

```json
{ "success": true,  "data": { },  "message": null, "errors": null }
{ "success": false, "data": null, "message": "Validación fallida", "errors": { "title": "..." } }
```

| Caso | HTTP | Caso | HTTP |
|---|---|---|---|
| Lectura / actualización | 200 | Sin sesión | 401 |
| Creado | 201 | Sin permiso | 403 |
| Borrado | 200 (`data: null`) | No existe | 404 |
| JSON malformado | 400 | Validación | 422 |
| Rate limit | 429 | | |

La fuente única del contrato completo será `docs/endpoints.md` (desde F8.1).

El 400 de JSON malformado lo emite `app/Filters/JsonPayloadFilter.php` (alias
`jsonpayload`, aplicado al grupo `api/v1` y a ingest), así usa el mismo envelope.
