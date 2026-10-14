## Qué hace
<!-- ia:/backend:/frontend:/events: + descripción corta -->

## Rama
- [ ] Mi PR sale de mi rama (`feature/backend-bd` / `feature/frontend-ux` / `feature/ia-chatbot`), no de `main`
- [ ] Solo toqué mis carpetas (ver `docs/workflow-git.md`)

## Test
<!-- IA: pega salida de ./scripts/test-ia.sh o curl. Backend: migrate:status. Frontend: captura -->
```bash
# pega comando + salida
```

## Arquitectura
- [ ] Respeta contratos sección 8 (`AIService::ask()` único puente IA, backend no toca Qdrant, IA no toca migraciones)
- [ ] Eventos con `link_original + source_url` real (prohibido inventar)
- [ ] Auth `@unac.edu.pe` no rota

## Conflictos
- [ ] `git fetch origin main && git rebase origin/main` sin conflictos, o los resolví solo en mis archivos
