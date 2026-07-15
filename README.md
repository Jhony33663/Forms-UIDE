# Forms-UIDE

Export de formularios Elementor del sitio UIDE. **Fuente de verdad: `wp_postmeta._elementor_data` en la BD de producción.** Este repo es un snapshot de referencia.

## Categorías

| Carpeta | Descripción | Cant. |
|---|---|---|
| `post/` | Carreras, maestrías y páginas de información | 119 |
| `page/` | Home, programas académicos, landing pages | 11 |
| `e-landing-page/` | Landing pages de Elementor (Registro General) | 3 |
| `elementor_library/` | Plantillas reutilizables (CTA, blogs) | 8 |

## Cambios aplicados (2026-07-14)

| Cambio | Detalle |
|---|---|
| IDs programa | Marketing `2→557`, Administración `1→558` en `<option>` y JS |
| Períodos pregrado/posgrado | `2026-1 → 2026-2` en hidden inputs y JS de 13 páginas |
| Maestrías 2027-1 | 9 ya correctos, 1 oculto (Emergencias Sanitarias) |
| Educación Básica | Agregada a 21 forms generales (ID 546) |
| PROGS_2027_1 | Array de excepciones en `updatePeriodo()` para 6 programas |
| Distancia→Quito | Sede forzada a Quito en submit para posgrados desde Distancia |

## Nomenclatura

- **Período con nivel:** `2026-2 Q Pregrado`, `2026-2 Online Posgrado`, `2026-2 L Pregrado`
- **Período suelto** (`value="2026-1">`): NUNCA debe existir. Siempre reemplazar con el valor completo.
- **`2026-2A`:** formato legacy. Reemplazar por `2026-2` equivalente.
- **Sede posgrados:** siempre `Quito`. El JS de formularios generales lo fuerza para `tp_pgm` que contenga "Posgrado".

## Reglas para cambios futuros

1. PHP sobre SQL (`str_replace` en PHP, no `REPLACE()` en MySQL CLI)
2. Backup antes de cualquier UPDATE
3. `\\"` no `\"` en literales MySQL
4. Verificar con `curl` después de purgar cache
5. Nunca borrar `uploads/elementor/css/*`
