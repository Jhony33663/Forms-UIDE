# Cambio masivo de formularios UIDE — Runbook

Procedimiento para aplicar reglas (ej. asignación de campaña) a **todos** los formularios embebidos del sitio de una sola vez, con respaldo, canary y rollback.

> Los formularios son HTML embebido dentro de widgets de **Elementor**, guardados en `wp_postmeta.meta_value` con `meta_key = '_elementor_data'` (JSON escapado). NO están en `post_content`. Editar ese JSON por SQL es la vía masiva; el editor de Elementor solo sirve uno a uno.

---

## 1. Inventario (139 formularios)

| Categoría (`post_type`) | Qué es | Cant. |
|---|---|---|
| `post` | Carreras y maestrías | 117 |
| `page` | Home, programas, pregrados presenciales | 11 |
| `e-landing-page` | Landing pages de Elementor | 3 |
| `elementor_library` | Plantillas reutilizables (CTA-home, blogs) | 8 |

Dos variantes de script conviven:

- **103 "script nuevo"** → función `updateUtmTracking()` + objeto `ORIGINS`.
- **36 "script viejo"** → función `setCampaignFromOrigen()` (sin `ORIGINS`).

Todo vive en el sitio principal (`wp_posts`). El blog multisitio (`wp_10_*`) no tiene formularios.

---

## 2. La regla de campaña

`utm_campaign` (→ campo Pardot **Nombre de la Campaña**) **nunca** debe quedar vacío. Si hay UTM se sobreescribe a la campaña estándar; si no, se asigna la **orgánica por defecto**.

| Caso | Entrada (UTM/origen) | `utm_campaign` resultante |
|---|---|---|
| Meta | facebook / instagram / `*meta*` | `TRAFICO_GENERAL_META_IT1_2026` |
| Google (pmax/display/yt/dv360) | `*google*` / pmax / discovery | `TRAFICO_GENERAL_GOOGLE_IT1_2026` |
| Search pago | google/bing search, `*search/marca*` | `LEADS_GENERAL_GOOGLE_SEARCH_IT1_2026` |
| Otros medios | tiktok / linkedin / pinterest / mailing | `TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026` |
| **Orgánico (default)** | sin UTM / natural search / chatgpt | `881_MF_TRAFICO_GENERAL_ORGANICO_SEO_SEARCH_IT1_2026` |

Estas 5 cadenas deben coincidir con las **completion actions** del Form Handler en Pardot (`Agregar a campaña de CRM`).

### Estado actual
- **103 (script nuevo):** el `else` no asignaba campaña → corregido. Ahora:
  ```js
  } else {
      var oriKey = ((oriF && oriF.value) || 'Google Natural Search').toLowerCase().trim();
      var def = ORIGINS[oriKey] || ORIGINS['google natural search'];
      if (oriF) oriF.value = def[0];
      if (ldF) ldF.value = def[1];
      if (cmpF) cmpF.value = def[2];   // nunca vacío
  }
  ```
- **36 (script viejo):** ya cumplían vía su fallback terminal `campaignField.value = 'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026'` + `origen` por defecto `Google Natural Search`. **No requirieron cambios.**

---

## 3. Procedimiento de cambio masivo

Credenciales de BD: ver `Credenciales_Accesos.md` (NO se versionan aquí). Crear un `~/.uide.cnf` local con `chmod 600`:

```ini
[client]
host=10.10.13.47
port=3306
user=admin_uide
password=********
database=bitn_uide
```

### 3.1 Backup (siempre primero)
```bash
# IDs afectados → lista
mysql --defaults-extra-file=~/.uide.cnf -N -e "
 SELECT GROUP_CONCAT(p.ID) FROM wp_posts p
 JOIN wp_postmeta m ON m.post_id=p.ID AND m.meta_key='_elementor_data'
 WHERE p.post_status='publish' AND p.post_type<>'revision'
   AND (m.meta_value LIKE '%pardot-form%' OR m.meta_value LIKE '%go.uide.edu.ec/l/%')" > ids.txt

# Dump de esas filas (defaults SIN 'database' para mysqldump)
mysqldump --defaults-extra-file=~/.dump.cnf --no-create-info --skip-extended-insert --complete-insert \
  bitn_uide wp_postmeta --where="meta_key='_elementor_data' AND post_id IN ($(cat ids.txt))" \
  > backup_elementor_$(date +%Y%m%d_%H%M%S).sql
```

### 3.2 Canary (1 post, validar JSON)
```sql
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;   -- evita "Illegal mix of collations"
SET @old = 'TEXTO VIEJO ...';
SET @new = 'TEXTO NUEVO ...';
UPDATE wp_postmeta SET meta_value = REPLACE(meta_value,@old,@new)
WHERE meta_key='_elementor_data' AND post_id=69809;
SELECT LOCATE(@old,meta_value) old_resta, LOCATE(@new,meta_value) new_ok, JSON_VALID(meta_value) json_ok
FROM wp_postmeta WHERE post_id=69809 AND meta_key='_elementor_data';
-- Esperado: old_resta=0, new_ok>0, json_ok=1
```

### 3.3 Aplicar al lote
```sql
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @old='...'; SET @new='...';
UPDATE wp_postmeta SET meta_value=REPLACE(meta_value,@old,@new)
WHERE meta_key='_elementor_data' AND post_id IN (/* ids.txt */);
```

### 3.4 Verificar
```sql
SELECT COUNT(*) total,
 SUM(LOCATE(@old,meta_value)>0) viejos_restantes,
 SUM(JSON_VALID(meta_value)=1) json_validos
FROM wp_postmeta WHERE meta_key='_elementor_data' AND post_id IN (/* ids */);
-- viejos_restantes=0 y json_validos=total
```

### 3.5 Limpiar caché
Editar el JSON por SQL **no** regenera el render de Elementor. Purgar:
- Plugin de caché del sitio (si existe), y/o
- Elementor → Tools → **Regenerate CSS & Data** / Sync Library.

### 3.6 Rollback
```bash
mysql --defaults-extra-file=~/.uide.cnf bitn_uide < backup_elementor_AAAAMMDD_HHMMSS.sql
```

---

## 4. Gotchas de escapado (CRÍTICO)

El HTML dentro de `_elementor_data` guarda los saltos como literal `\r\n` y `/` como `\/`. Al escribir `@old/@new` en SQL:

| Quieres en el contenido | En literal SQL |
|---|---|
| `\r\n` (backslash-r-backslash-n) | `\\r\\n` |
| `'` (comilla simple) | `''` |
| `/` | `/` (no hace falta escapar al comparar) |

- Siempre `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci` (la columna es `utf8mb4_unicode_ci`; sin esto, `LIKE`/comparaciones dan *Illegal mix of collations*).
- Filtra por **`post_id IN (...)`**, no por `LIKE` en `meta_value` (más seguro y evita el choque de colación).
- Excluir `post_type='revision'` y exigir `post_status='publish'`.

---

## 5. Exportar formularios al repo

Re-generar este repo desde la BD (HTML por categoría + URL absoluta en comentario):

```bash
# 1) metadatos
mysql --defaults-extra-file=~/.uide.cnf --batch --skip-column-names -e "
 SELECT p.ID,p.post_type,p.post_name,p.post_status,
        REPLACE(REPLACE(p.post_title,'\t',' '),'\n',' ')
 FROM wp_posts p JOIN wp_postmeta m ON m.post_id=p.ID AND m.meta_key='_elementor_data'
 WHERE p.post_status='publish' AND p.post_type<>'revision'
   AND (m.meta_value LIKE '%pardot-form%' OR m.meta_value LIKE '%go.uide.edu.ec/l/%')
 ORDER BY p.post_type,p.ID" > meta.tsv

# 2) JSON crudo por post (--raw = sin doble-escapar)
mkdir -p raw
while IFS=$'\t' read -r id _; do
  mysql --defaults-extra-file=~/.uide.cnf --raw --batch --skip-column-names \
    -e "SELECT meta_value FROM wp_postmeta WHERE post_id=$id AND meta_key='_elementor_data'" > raw/$id.json
done < meta.tsv

# 3) extraer (ver scripts/extract_forms.py)
python3 scripts/extract_forms.py
```

`extract_forms.py` recorre el JSON, toma los widgets cuyo HTML contiene `pardot-form`/`updateUtmTracking`/`setCampaignFromOrigen`, antepone un comentario con la URL (`https://www.uide.edu.ec/<slug>/`, según `permalink_structure=/%postname%/`) y escribe `<post_type>/<slug>-<id>.html` en la raíz del repo.

> **El repo es un export (solo lectura).** La fuente de verdad es la BD. "Cambiar" un formulario = editar la regla en SQL (sección 3) y re-exportar; un `git pull` trae la última foto, no modifica el sitio.

---

## 6. Pendientes / mejoras

- **Persistencia de UTM** (caso JULIO): los UTM solo se leen de la URL al enviar; si el usuario navega antes de enviar, se pierden y cae a orgánico. Persistir en `localStorage` (como ya se hace con `gclid`, ~8 líneas) para atribuir bien el tráfico pago.
- Unificar los 36 formularios "viejos" al script nuevo (`ORIGINS`) para tener una sola base de código.

---

## 7. Seguridad

- **Nunca** versionar `Credenciales_Accesos.md`, archivos `*.cnf` ni PAT (ver `.gitignore`).
- Rotar cualquier token o credencial que se haya compartido en texto plano.
