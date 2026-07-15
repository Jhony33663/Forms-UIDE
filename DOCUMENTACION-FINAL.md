# CAMBIO MASIVO DE PERÍODOS E IDs — Documentación Final

## 1. Cambios Aplicados

### 1.1 IDs de programa (option values + JS)

| Campo | Antes | Después | Contexto |
|---|---|---|---|
| Marketing | `value="2"` | `value="557"` | `<option>` en selects de Quito |
| Marketing | `programId = "2"` | `programId = "557"` | JS en página específica |
| Administración | `value="1"` | `value="558"` | `<option>` en selects de Quito |
| Administración | `programId = "1"` | `programId = "558"` | JS en página específica |

**URLs afectadas (34 publicadas):** formularios generales Quito, pregrado-quito-general-*, carreras-general-m-uide, home, programas-academicos-*, mas-informacion, financiamiento, blogs, landing pages.

### 1.2 Períodos por defecto (hidden input + JS updatePeriodo)

| Página | Período | Tipo |
|---|---|---|
| `registro-general-pg` | `2026-2 Online Posgrado` | Posgrado |
| `programas-academicos-posgrado-presencial` | `2026-2 Presencial Posgrado` | Posgrado |
| `programas-academicos-posgrado-online` | `2026-2 Online Posgrado` | Posgrado |
| `posgrados-en-linea-y-presenciales-2` | `2026-2 Online Posgrado` | Posgrado |
| `programas-academicos-pregrado-quito` | `2026-2 Q Pregrado` | Pregrado |
| `programas-academicos-pregrado-guayaquil` | `2026-2 Guayaquil` | Pregrado |
| `programas-academicos-pregrado-loja` | `2026-2 L Pregrado` | Pregrado |
| `programas-academicos-pregrado-online` | `2026-2 Online Pregrado` | Pregrado |
| `proceso-de-admision-pregrado` | `2026-2 Q Pregrado` | Pregrado |
| `home-version-thirteen` | `2026-2 Q Pregrado` | Pregrado |
| `mas-informacion` | `2026-2 Q Pregrado` | Pregrado |
| `mas-informacion-quito` | `2026-2 Q Pregrado` | Pregrado |
| `programas-academicos` | `2026-2 Q Pregrado` | Pregrado |

### 1.3 Maestrías 2027-1

| Programa | Período | Acción |
|---|---|---|
| Transformación Digital de Negocios | `2027-1 Online Posgrado` | Ya estaba correcto |
| Gestión Deportiva | `2027-1 Online Posgrado` | Ya estaba correcto |
| Desarrollo WEB | `2027-1 Online Posgrado` | Ya estaba correcto |
| Educación Básica | `2027-1 Online Posgrado` | Ya estaba correcto |
| Comunicación Política | `2027-1 Online Posgrado` | Ya estaba correcto |
| Dirección Publicitaria y Creativa | `2027-1 Online Posgrado` | Ya estaba correcto |
| Marketing y Comunicación | `2027-1 Online Posgrado` | Ya estaba correcto |
| Turismo | `2027-1 Online Posgrado` | Ya estaba correcto |
| Planificación y Diseño Urbano | `2027-1 Online Posgrado` | Ya estaba correcto |
| Nutrición y Dietética | `2026-2 Online Posgrado` | Se queda en 2026-2 |
| Emergencias Sanitarias | — | Pasado a draft (oculto) |

---

## 2. Técnica utilizada

**PHP vía mysqli** (no SQL REPLACE, no sed, no MySQL CLI directo).

### ¿Por qué PHP y no SQL?

El `_elementor_data` es JSON escapado. Dentro del JSON, el HTML tiene backslash-escapes (`\"`, `\r\n`, `\u00f3`). Al usar SQL `REPLACE()` directamente, los niveles de escape se pierden:
- `\\"` en literal SQL → `\"` en string (correcto)
- `\"` en literal SQL → `"` (INCORRECTO, el backslash se ignora)

PHP no tiene este problema porque `str_replace('value=\"1\">', 'value=\"558\">', $meta)` trabaja con los bytes exactos tal cual vienen de la BD.

**Sin embargo**, los caracteres acentuados en `_elementor_data` se almacenan como secuencias `\uXXXX` (ej: `\u00f3`, `\u00ed`). En PHP, `str_replace` con strings UTF-8 (ej: `ó`) NO encontrará coincidencias. Hay que usar las secuencias de escape exactas: `str_replace('Educaci\u00f3n', ...)`.

### Flujo

1. **Backup** con `mysqldump` de las filas afectadas
2. **PHP** lee `meta_value`, aplica `str_replace`, escribe con `real_escape_string`
3. **Validación** JSON con `JSON_VALID()` después de cada UPDATE
4. **Verificación** con `curl` + `grep` en el HTML servido
5. **Cache** WP Rocket purgado + Elementor CSS regenerado vía WP-CLI

---

## 3. Lecciones aprendidas

1. **La BD manda.** El repo es solo un export. Editar los `.html` no cambia nada en el sitio.
2. **El cache miente.** WP Rocket sirve HTML stale incluso después de actualizar la BD. Siempre purgar + verificar con curl.
3. **Escapar es traicionero.** `\"` ≠ `\\"` en MySQL. Usar `LOCATE()` no `LIKE`. Preferir PHP sobre SQL para manipular JSON escapado.
4. **No todo es el `<input>`.** El JS `updatePeriodo()` tiene fallbacks que también necesitan actualizarse.
5. **El período suelto (`value="2026-1">`) necesita contexto.** No reemplazar por un valor genérico; cada página tiene su propio período destino.
6. **Los acentos son `\uXXXX` en la BD.** `str_replace('Educación', ...)` no funciona; usar `str_replace('Educaci\u00f3n', ...)`.
7. **No cambiar el DOM en handlers de submit.** El usuario ve el cambio. Usar solo `handleConditionalRedirect` para cambios silenciosos.
8. **Normalizar valores entre ShowValuesSede y handleConditionalRedirect.** Si ya coinciden, no forzar de nuevo.
9. **Scripts solapados corrompen.** Verificar el estado del DB antes de aplicar otro script sobre la misma zona.

---

## 4. Cambios en forms generales (2026-07-14)

### 4.1 Educación Básica (ID 546)

Agregada al `<select name="posgrado_online">` de 21 formularios generales. Inserción después de ID 544 (Educación Tecnología e Innovación) usando `str_replace` con secuencias `\uXXXX`.

```
<option value="546">Maestría en Educación Básica - O</option>
```

### 4.2 PROGS_2027_1

Array de IDs de programas con intake 2027-1 en `updatePeriodo()`:
```javascript
var PROGS_2027_1 = ["510","515","265","278","546","554"];
// Override al final de updatePeriodo:
if (PROGS_2027_1.indexOf(car) !== -1 && tp.indexOf("Posgrado") !== -1) {
    v = "2027-1 Online Posgrado";
}
```

### 4.3 Distancia → Quito

- **ShowValuesSede:** valor de opción posgrado para Distancia cambiado de `Posgrado Online` a `Posgrado En Línea` (consistente con Loja/Guayaquil).
- **handleConditionalRedirect:** agregado `|| sf.value === "Distancia"` a la condición que fuerza sede="Quito" para posgrados (mismo patrón que Loja/Guayaquil, invisible al usuario).

---

## 4. Backups

| Archivo | Fecha | Contenido |
|---|---|---|
| `/home/toor/backup_cambio_ids_20260714_095416.sql` | 2026-07-14 | IDs programa (55 posts) |
| `/home/toor/backup_formularios_general_20260714_102042.sql` | 2026-07-14 | Períodos generales (9 posts) |

---

**Documentado:** 2026-07-14
