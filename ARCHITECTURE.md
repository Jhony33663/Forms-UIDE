# ARQUITECTURA DE FORMULARIOS — UIDE

## 1. Visión general

El sitio UIDE (WordPress + Elementor en Bitnami AlmaLinux) sirve ~140 formularios de captación de leads conectados a Pardot. Los formularios son HTML embebido dentro de widgets HTML de Elementor, almacenados en `wp_postmeta._elementor_data` como JSON escapado con backslashes.

```
┌─────────────────────────────────────────────────────────┐
│                   wp_postmeta._elementor_data            │
│  (JSON escapado: \" → comillas, \r\n → saltos,          │
│   \/ → slash, \u00f3 → acentos)                         │
├─────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────┐   │
│  │  Widget HTML de Elementor (raw HTML + CSS + JS)  │   │
│  │  ├─ <form action="go.uide.edu.ec/l/...">         │   │
│  │  ├─ <input type="hidden"> (sede, periodo, ...)   │   │
│  │  ├─ <select> anidados (sede→tp_pgm→programa)     │   │
│  │  ├─ JS: updatePeriodo()                          │   │
│  │  ├─ JS: updateUtmTracking() + ORIGINS            │   │
│  │  ├─ JS: handleConditionalRedirect()              │   │
│  │  └─ JS: validación de teléfono por país          │   │
│  └──────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────┤
│  WordPress → Elementor render → Pardot Form Handler     │
└─────────────────────────────────────────────────────────┘
```

---

## 2. Capas del sistema

### 2.1 Capa de datos — `_elementor_data`

| Elemento | Detalle |
|---|---|
| **Tabla** | `wp_postmeta` |
| **Columna** | `meta_value` (LONGTEXT, utf8mb4_unicode_ci) |
| **Clave** | `meta_key = '_elementor_data'` |
| **Formato** | JSON array de secciones/columnas/widgets. El HTML del formulario es un string dentro de un widget `html`. |
| **Escapado** | Doble nivel: JSON escapa `"` → `\"`, `\` → `\\`, `/` → `\/`, `ó` → `\u00f3`. El resultado se guarda como texto en MySQL. |

```
Ejemplo de cómo se ve en la BD:
"<input type=\"hidden\" id=\"periodo\" value=\"2026-2 Q Pregrado\">\r\n"
```

### 2.2 Capa de render — Elementor

- Widget `html` de Elementor incrusta el HTML crudo del formulario.
- El CSS del formulario se sirve inline (`.uide-frm-wrapper`, `.uide-frm-field`, etc.).
- Las páginas de tipo `post` son carreras/maestrías individuales. Las de tipo `page` son páginas generales (home, programas-academicos).

### 2.3 Capa de lógica — JavaScript embebido

El formulario contiene múltiples bloques de JS con responsabilidades distintas:

| Función | Responsabilidad | Ubicación |
|---|---|---|
| `ShowValuesSede()` | Llena el `<select>` de tipo de programa según la sede elegida | JS del form |
| `updatePeriodo()` | Calcula y asigna el período según sede + tp_pgm + programa | JS del form |
| `updateUtmTracking()` | Lee UTM de URL, busca en ORIGINS, asigna campaña/origen | JS del form (script nuevo) |
| `setCampaignFromOrigen()` | Versión legacy de UTM→campaña (sin objeto ORIGINS) | JS del form (script viejo) |
| `handleConditionalRedirect()` | Valida teléfono, normaliza código de país, submit + redirect | JS del form |
| `handleTipoProgramaChange()` | Muestra/oculta selects de modalidad y programa según tp_pgm | JS del form |
| `capturar_programa()` | Copia el valor del `<select>` de programa a `#esc_pgm` | JS del form |
| `validatePhoneByCountry()` | Valida formato de celular según código de país | JS del form |
| **Plugin `uide-campaign-catalog.php`** | Catálogo global de 62 orígenes → 5 campañas (footer de cada página) | `/wp-content/plugins/` |

### 2.4 Capa de caché — WP Rocket

- WP Rocket cachea HTML completo en `/wp-content/cache/wp-rocket/`.
- Al modificar `_elementor_data`, la caché NO se invalida automáticamente.
- **Siempre purgar después de cambios:** `rm -rf cache/wp-rocket/*`
- **Nunca borrar:** `uploads/elementor/css/*` (rompe el diseño del sitio).

---

## 3. Tipos de formulario

### 3.1 Formularios de carrera individual (post)

- Página dedicada a UNA carrera/maestría.
- Campos ocultos hardcodeados: `sede`, `tp_pgm`, `periodo`, `esc_pgm`.
- Sin `<select>` dinámico. El programa ya está preseleccionado.
- Ejemplo: `carrera-de-administracion-de-empresas`, `maestria-en-marketing-y-comunicacion`.
- **Regla de sede:** en maestrías, `sede` siempre es `Quito`.

### 3.2 Formularios generales (post/page)

- Página multi-programa con `<select>` anidados.
- El usuario elige: sede → tipo de programa → modalidad → programa.
- Los `<option>` de cada programa están en `div_programas_2` (oculto) y se mueven dinámicamente a `div_programas`.
- El `periodo` inicial es un valor hardcodeado que se sobreescribe cuando el usuario selecciona un programa vía `updatePeriodo()`.
- Ejemplo: `pregrado-quito-general-3`, `programas-academicos-pregrado-quito`, `mas-informacion`.

```
Flujo de selects en formulario general:
┌────────┐    ┌──────────┐    ┌────────────┐    ┌───────────┐
│  sede  │ → │  tp_pgm  │ → │ modalidad  │ → │ programa  │
└────────┘    └──────────┘    └────────────┘    └───────────┘
  Quito         Pregrado        (n/a)            Lista Quito
  Guayaquil     Posgrado        Online/Híbrida   Lista online
  Loja                          Semipresencial
  Distancia
```

### 3.3 Landings de registro (e-landing-page)

- Formulario simplificado para registro general por sede.
- Campos fijos: `sede` ya hardcodeado, `tp_pgm` fijo, `<select>` de programa directo.
- Ejemplo: `registro-general-quito`, `registro-general-pg`.

### 3.4 Plantillas reutilizables (elementor_library)

- Bloques de formulario incrustados en múltiples páginas vía Elementor.
- Mismo HTML embebido en cada página que los referencia.
- Ejemplo: `cta-home`, `blog-business-school`.

---

## 4. Campos del formulario

### 4.1 Campos ocultos (hidden inputs)

| Campo | ID | Origen del valor | Notas |
|---|---|---|---|
| `sede` | `#sede` | Hardcodeado en maestrías; `<select>` en generales | Siempre "Quito" para posgrados |
| `tp_pgm` | `#tp_pgm` | `<select>` dinámico en generales; hardcodeado en individuales | "Pregrado Quito", "Posgrado En Línea", etc. |
| `periodo` | `#periodo` | Hardcodeado (valor inicial) + `updatePeriodo()` (dinámico) | Ej: "2026-2 Q Pregrado" |
| `esc_pgm` | `#esc_pgm` | `capturar_programa()` | ID numérico del programa |
| `utm_campaign` | `#utm_campaign` | `updateUtmTracking()` + plugin | 5 campañas estándar |
| `utm_source` | `#utm_source` | `updateUtmTracking()` | De URL o cookie |
| `utm_medium` | `#utm_medium` | `updateUtmTracking()` | De URL o cookie |
| `origen` | `#origen` | `updateUtmTracking()` → ORIGINS | Nombre canónico del origen |
| `c_lead` | `#c_lead` | Hardcodeado: "Digital" | Canal del lead |

### 4.2 Campos visibles

| Campo | ID | Validación |
|---|---|---|
| Nombres | `#f_name` | `required` |
| Apellidos | `#l_name` | `required` |
| Email | `#email` | `pattern` de dominio |
| Celular | `#mobile` | `validatePhoneByCountry()` |
| Código país | `#country_code` | `<select>` con banderas |
| Programa | `#esc_pgm` o selects dinámicos | `required` |
| Acepto políticas | `#aut_data` | `required` checkbox |

---

## 5. Sistema de períodos

### 5.1 Nomenclatura

```
{TIPO} {PERÍODO} {SEDE/MODALIDAD}

Pregrado:
  2026-2 Q Pregrado        ← Quito
  2026-2 L Pregrado        ← Loja
  2026-2 Guayaquil         ← Guayaquil
  2026-2 Online Pregrado   ← En línea

Posgrado:
  2026-2 Online Posgrado   ← En línea
  2026-2 Presencial Posgrado ← Presencial/Híbrido
  2027-1 Online Posgrado   ← Próximo intake
```

### 5.2 Lógica de `updatePeriodo()`

```
1. Default: v = "2026-2 Online Posgrado"
2. Se define array PROGS_2027_1 con IDs de programas que requieren 2027-1
3. Si tp_pgm es "Posgrado En Línea" → Online Posgrado
4. Si sede es "Quito":
   - Si tp es "Posgrado En Línea" o "Posgrado Online" → 2027-1 Online Posgrado
   - Posgrado (otro) → Presencial Posgrado
   - Pregrado: Enfermería Internacional → "II-EIN-AGO-26", resto → Q Pregrado
5. Si sede es "Loja":
   - Posgrado → Presencial Posgrado
   - Pregrado → L Pregrado
6. Si sede es "Guayaquil":
   - Posgrado → Presencial Posgrado
   - Pregrado: Enfermería Internacional → "II-EIN-GY-AGO-26", resto → Guayaquil
7. Si sede es "Distancia" (online):
   - Posgrado → 2027-1 Online Posgrado
   - Pregrado: Validación → Online PVC, resto → Online Pregrado
8. OVERRIDE final: si esc_pgm está en PROGS_2027_1 y tp contiene "Posgrado" → 2027-1 Online Posgrado
```

### 5.4 Sistema de sedes y `ShowValuesSede()`

| Sede seleccionada | value interno | tp_pgm pregrado | tp_pgm posgrado |
|---|---|---|---|
| Quito | `Quito` | `Pregrado Quito` | `Posgrado Quito` |
| Guayaquil | `Guayaquil` | `Pregrado Guayaquil` | `Posgrado En Línea` |
| Loja | `Loja` | `Pregrado Loja` | `Posgrado En Línea` |
| Modalidad En Línea | `Distancia` | `Pregrado Distancia` | `Posgrado En Línea` |

**Regla de negocio:** en posgrados/maestrías, `sede` siempre debe enviarse como `Quito`. Esto se fuerza en `handleConditionalRedirect()` al submit (invisible al usuario). Para Guayaquil, Loja y Distancia, el tp_pgm ya viene como `Posgrado En Línea` desde `ShowValuesSede()`.

### 5.5 Programas con intake 2027-1

| ID | Programa | Período |
|---|---|---|
| 265 | Marketing y Comunicación | 2027-1 Online Posgrado |
| 278 | Gestión Deportiva | 2027-1 Online Posgrado |
| 510 | Planificación y Diseño Urbano | 2027-1 Online Posgrado |
| 515 | Dirección Publicitaria y Creativa | 2027-1 Online Posgrado |
| 546 | Educación Básica | 2027-1 Online Posgrado |
| 554 | Derecho de Empresa | 2027-1 Online Posgrado |

### 5.3 Dónde se hardcodea el período

| Ubicación | Qué es | Cuándo se usa |
|---|---|---|
| `<input id="periodo" value="...">` | Valor inicial antes de interactuar | Carga de página |
| `updatePeriodo()` — `var v = "..."` | Default del JS | Si no matchea ninguna rama |
| `updatePeriodo()` — ramas `if/else` | Fallbacks por sede/programa | Cuando el usuario selecciona |

**Los tres niveles deben mantenerse sincronizados.**

---

## 6. Sistema de campañas (UTM → Pardot)

### 6.1 Catálogo de campañas

| Campaña | Orígenes |
|---|---|
| `TRAFICO_GENERAL_META_IT1_2026` | Facebook, Instagram, Messenger, Audience Network |
| `TRAFICO_GENERAL_GOOGLE_IT1_2026` | Google Display, DemandGen, Discovery, Pmax, YouTube, DV360 |
| `LEADS_GENERAL_GOOGLE_SEARCH_IT1_2026` | Google Search, Bing Ads |
| `881_MF_TRAFICO_GENERAL_ORGANICO_SEO_SEARCH_IT1_2026` | Google/Bing Natural Search, ChatGPT (DEFAULT) |
| `TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026` | TikTok, LinkedIn, Mailing, Pinterest, Spotify, etc. (41 orígenes) |

### 6.2 Flujo de asignación

```
URL con ?utm_source=...&utm_campaign=...
         │
         ▼
  updateUtmTracking()
         │
         ├─ Busca en ORIGINS por source → medium → campaign
         ├─ Si no: heurística por keywords en campaign
         └─ Si no: default = Google Natural Search → 881_MF_...
         │
         ▼
  Plugin uide-campaign-catalog.php (footer)
         │
         └─ Sobrescribe setInterval cada 100ms (última palabra)
```

---

## 7. IDs de programa (option values)

Los `<select>` de programas usan IDs numéricos como `value`. Estos IDs son los `esc_pgm` que se envían a Pardot.

| Programa | ID Quito | ID Guayaquil | ID Loja | ID Online |
|---|---|---|---|---|
| Administración de Empresas | 558 | 473 | 79 | 410 |
| Marketing | 557 | 108 | 86 | 462 |

**Regla:** el ID de Quito es el canónico. Los IDs de otras sedes son independientes.

---

## 8. Actualización de formularios

### 8.1 Técnica correcta

1. **Backup** con `mysqldump` de los `meta_id` exactos.
2. **PHP + mysqli**: leer `meta_value`, `str_replace()`, `real_escape_string()`, escribir.
3. **Validar** `JSON_VALID()` después de cada UPDATE.
4. **Verificar** con `curl` + `grep` en el HTML servido.
5. **Purgar** WP Rocket + regenerar Elementor CSS.

### 8.2 Técnicas prohibidas

- ❌ `REPLACE()` en MySQL CLI sobre `_elementor_data` (niveles de escape inconsistentes)
- ❌ `sed` sobre el JSON (rompe el escapado)
- ❌ `rm -rf uploads/elementor/css/*` (rompe el diseño)
- ❌ Editar los `.html` del repo y esperar que afecte al sitio

### 8.3 Escape en MySQL y PHP

| Dato real | Literal SQL correcto | Incorrecto |
|---|---|---|
| `\"` | `\\"` | `\"` → produce solo `"` |
| `\u00f3` | `\\u00f3` | `\u00f3` → `\0` es NUL |
| `\r\n` | `\\r\\n` | `\r\n` → retorno real |

**Usar `LOCATE()`, no `LIKE`.** `LIKE` interpreta `\` como escape.

### 8.4 Escape en PHP `str_replace()`

**El problema de los acentos:** El `_elementor_data` guarda caracteres acentuados como secuencias `\uXXXX` (ej: `\u00f3` para ó, `\u00ed` para í). En PHP, `str_replace` con strings que contienen caracteres UTF-8 (ej: `ó`, `í`) NO encontrará coincidencias porque los bytes son diferentes.

```php
// ❌ INCORRECTO — no matchea porque 'ó' UTF-8 ≠ '\u00f3' en el DB
str_replace('Educación', 'X', $meta);

// ✓ CORRECTO — usar la secuencia de escape exacta
str_replace('Educaci\u00f3n', 'X', $meta);
// En PHP single-quoted strings, '\u00f3' son 6 caracteres literales: \ u 0 0 f 3
```

**Regla:** siempre verificar primero cómo se almacena el texto en la BD (leer con `python3` + hex dump si es necesario) antes de escribir el patrón de búsqueda.

### 8.5 Inserción de opciones en `<select>`

Para agregar un nuevo `<option>` a un `<select>` existente en todos los formularios generales:

1. Identificar una opción adyacente (anterior o siguiente en orden alfabético/numérico)
2. Verificar el texto exacto incluyendo secuencias `\uXXXX`
3. Usar `str_replace` para insertar después de esa opción

```php
$search  = 'value=\"544\">Maestr\u00eda en Educaci\u00f3n Tecnolog\u00eda e Innovaci\u00f3n<\/option>';
$insert  = '\r\n        <option value=\"546\">Maestr\u00eda en Educaci\u00f3n B\u00e1sica - O<\/option>';
$replace = $search . $insert;
$nuevo = str_replace($search, $replace, $nuevo, $c);
```

### 8.6 Errores comunes

| Error | Causa | Solución |
|---|---|---|
| `str_replace` no matchea | Acentos guardados como `\uXXXX`, no UTF-8 | Usar secuencias de escape exactas |
| JS roto en frontend | Doble inserción de código (scripts overlapped) | Verificar diff antes de aplicar; limpiar duplicados con PHP |
| Cache sirve HTML viejo | WP Rocket no se purgó después del UPDATE | `rm -rf cache/wp-rocket/*` + `wp elementor flush-css --all` |
| Form no envía leads | `handleConditionalRedirect` modificado incorrectamente | No cambiar tp_pgm en submit si ShowValuesSede ya lo normalizó |
| Select cambia visiblemente | Modificar `document.getElementById("sede").value` en evento visible | Solo cambiar en `handleConditionalRedirect` (submit, invisible) |

---

## 9. Estructura de archivos del repo

```
Forms-UIDE/
├── README.md                          # Resumen y nomenclatura
├── CAMBIO-MASIVO-FORMULARIOS.md       # Runbook de operaciones
├── DOCUMENTACION-FINAL.md             # Historial de cambios aplicados
├── ARCHITECTURE.md                    # Este archivo
├── CATEGORIZACION_ORIGEN_CAMPAIGN.md  # Mapeo 62 orígenes → 5 campañas
├── PLAN-IMPLEMENTACION-FINAL.md       # Plugin uide-campaign-catalog.php
├── post/                              # 119 formularios (carreras/maestrías)
├── page/                              # 11 formularios (home, programas)
├── e-landing-page/                    # 3 formularios (registro general)
├── elementor_library/                 # 8 plantillas (CTA, blogs)
├── scripts/                           # extract_forms.py
└── snippet-fix-campaign.php           # Snippet legacy
```

---

## 10. Servidor y credenciales

| Recurso | Detalle |
|---|---|
| **WP path** | `/opt/bitnami/wordpress` |
| **DB host** | `10.10.13.47:3306` |
| **DB name** | `bitn_uide` |
| **DB user** | `admin_uide` |
| **Config** | `/home/toor/.uide.cnf` (chmod 600) |
| **WP user** | `apache` |
| **WP-CLI** | `/usr/local/bin/wp` |
| **Cache** | `/wp-content/cache/wp-rocket/` |
| **CSS Elementor** | `/wp-content/uploads/elementor/css/` (NO borrar) |
| **Plugin campañas** | `/wp-content/plugins/uide-campaign-catalog.php` |
