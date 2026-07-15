# Cambio masivo de formularios UIDE — Runbook

Procedimiento para actualizar valores en formularios Elementor embebidos en WordPress, con respaldo, canary y rollback.

> **Fuente de verdad:** `wp_postmeta._elementor_data` (JSON escapado). Los archivos `.html` del repo son solo export de referencia. Cambiar un formulario = cambiar la BD y re-exportar.

---

## 0. Nomenclatura y leyes

| Ley | Regla |
|---|---|
| **Fuente única** | La BD (`wp_postmeta._elementor_data`) es la verdad. El repo (`Forms-UIDE/`) es un snapshot. |
| **PHP sobre SQL** | Usar PHP (`mysqli`) para leer y reemplazar. No usar `mysql CLI REPLACE()` sobre JSON escapado. |
| **Escape doble siempre** | En literales MySQL, `\\"` produce `\"` en el string. `\"` produce solo `"` (backslash ignorado). |
| **Backup primero** | `mysqldump` de las filas exactas antes de cualquier `UPDATE`. |
| **Cache al final** | Purgar WP Rocket (`rm -rf cache/wp-rocket/*`) + regenerar Elementor CSS (`wp elementor flush-css --all`). |
| **Nunca borrar** | `sudo rm -rf uploads/elementor/css/*` — rompe el CSS del sitio. Solo WP-CLI. |
| **Verificar frontend** | `curl` + `grep` en el HTML servido. La BD puede estar limpia pero el cache sucio. |
| **Período suelto** | `value=\"2026-1\">` se reemplaza con el valor con nivel (`value=\"2026-2 Q Pregrado\">`). No sirve un `2026-2` genérico. |
| **Sede posgrados** | En maestrías/posgrados, `sede` siempre es `Quito`. Los formularios generales lo fuerzan vía JS cuando `tp_pgm` contiene "Posgrado". |

---

## 1. Tipos de operación

### 1.1 Cambio de IDs de programa (option values)

Cambiar el `value` en `<option value="X">Programa</option>` y referencias JS (`programId = "X"`).

**Ejemplo:** Marketing `2→557`, Administración `1→558`

**Técnica:** PHP con `str_replace` exacto. Buscar contexto completo (`value=\"2\">Marketing`) para evitar falsos positivos.

**Páginas afectadas:** formularios generales que listan carreras en `<select>` (Quito pregrado).

### 1.2 Cambio de período por defecto (hidden input)

Cambiar el valor inicial hardcodeado en `<input id="periodo" value="...">` y en el JS `updatePeriodo()`.

**Ejemplo:** `2026-1 Q Pregrado → 2026-2 Q Pregrado`

**Técnica:** PHP con `str_replace` por página (cada página tiene su propio período destino).

**Páginas afectadas:** formularios generales y páginas de programas académicos.

---

## 2. Procedimiento PHP (técnica obligatoria)

### 2.1 Backup

```bash
# Dump de las filas exactas (usa un .cnf SIN 'database' para mysqldump)
cat > /tmp/.dump.cnf << 'EOF'
[client]
host=10.10.13.47
port=3306
user=admin_uide
password=********
EOF
chmod 600 /tmp/.dump.cnf

IDS="id1,id2,id3,..."
mysqldump --defaults-extra-file=/tmp/.dump.cnf --no-create-info \
  --skip-extended-insert --complete-insert bitn_uide wp_postmeta \
  --where="meta_key='_elementor_data' AND post_id IN ($IDS)" \
  > /home/toor/backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2.2 Script PHP

```php
<?php
$cnf = parse_ini_file('/home/toor/.uide.cnf');
$mysqli = new mysqli($cnf['host'], $cnf['user'], $cnf['password'], 
                     'bitn_uide', (int)$cnf['port']);
$mysqli->set_charset('utf8mb4');

foreach ($posts as $post_id => $cfg) {
    // PASO 1: Leer
    $result = $mysqli->query(
        "SELECT meta_value FROM wp_postmeta 
         WHERE post_id = $post_id AND meta_key = '_elementor_data'");
    $meta = $result->fetch_assoc()['meta_value'];
    $original_len = strlen($meta);

    // PASO 2: Reemplazar
    $nuevo = $meta;
    $nuevo = str_replace($cfg['old'], $cfg['new'], $nuevo, $count);

    // PASO 3: Validar
    if (strlen($nuevo) < 100 || strlen($nuevo) < $original_len * 0.5) {
        echo "CANCELADO: tamaño sospechoso\n"; continue;
    }
    if ($nuevo === $meta) { echo "Sin cambios\n"; continue; }

    // PASO 4: Escribir
    $esc = $mysqli->real_escape_string($nuevo);
    $mysqli->query(
        "UPDATE wp_postmeta SET meta_value = '$esc' 
         WHERE post_id = $post_id AND meta_key = '_elementor_data'");

    // PASO 5: Validar JSON
    $check = $mysqli->query(
        "SELECT JSON_VALID(meta_value) AS v 
         FROM wp_postmeta WHERE post_id = $post_id 
         AND meta_key = '_elementor_data'")->fetch_assoc();
    if ($check['v'] != 1) { echo "ERROR: JSON inválido\n"; continue; }

    echo "OK: $count reemplazos, JSON válido\n";
}
```

### 2.3 Verificación frontend

```bash
# Verificar que el HTML servido tiene el valor nuevo
curl -sL --max-time 10 "https://www.uide.edu.ec/SLUG/" 2>/dev/null \
  | grep -oP 'id="periodo"[^>]*value="\K[^"]*' | head -1

# Barrido de residuales
for slug in pagina1 pagina2 pagina3; do
  bad=$(curl -sL --max-time 10 "https://www.uide.edu.ec/$slug/?_=$(date +%s)" \
    2>/dev/null | grep -c 'VALOR-VIEJO')
  [ "$bad" -gt 0 ] && echo "✗ $slug: $bad" || echo "✓ $slug"
done
```

---

## 3. Escape en MySQL — tabla de referencia

El HTML en `_elementor_data` usa backslash-escapes (`\"`, `\r\n`, `\/`, `\u00f3`). Al escribir literales en SQL:

| Dato almacenado | Literal SQL correcto | Literal SQL INCORRECTO |
|---|---|---|
| `\"` (backslash + comilla) | `\\"` | `\"` → produce solo `"` |
| `\r\n` | `\\r\\n` | `\r\n` → retorno de carro real |
| `\u00f3` | `\\u00f3` | `\u00f3` → `u00f3` sin backslash |
| `\/` | `\\/` | `\/` → solo `/` |

**Regla mnemotécnica:** En MySQL, `\` solo escapa `\`, `'`, `"`, `n`, `t`, `r`, `b`, `Z`, `0`, `%`, `_`. Cualquier otra letra después de `\` → el `\` se ignora. Para producir un `\` literal en el string, usar `\\`.

**Usar `LOCATE()`, no `LIKE`:** `LIKE` trata `\` como carácter de escape. `LOCATE()` hace comparación binaria exacta.

```sql
-- Correcto
SELECT SUM(LOCATE('value=\\"558\\">', meta_value) > 0) FROM wp_postmeta;

-- Incorrecto (LIKE pierde los backslashes)
SELECT SUM(meta_value LIKE '%value=\\"558\\">%') FROM wp_postmeta;
```

---

## 4. Post-cambio

```bash
# 1. Purgar cache WP Rocket (NO borrar elementor/css/*)
sudo rm -rf /opt/bitnami/wordpress/wp-content/cache/wp-rocket/*

# 2. Regenerar CSS Elementor (solo WP-CLI)
sudo -u apache /usr/local/bin/wp elementor flush-css \
  --path=/opt/bitnami/wordpress --all

# 3. Verificar que el sitio carga
curl -sI https://www.uide.edu.ec/ | grep HTTP
```

### Prohibido

- ❌ `sudo rm -rf /opt/bitnami/wordpress/wp-content/uploads/elementor/css/*` — rompe el diseño
- ❌ `INSERT INTO wp_postmeta` sin verificar `meta_id` duplicado
- ❌ Reemplazar `2026-1` en URLs de PDFs (ej: `malla-2026-1.pdf`)
- ❌ Usar `LIKE` en vez de `LOCATE` para buscar en `_elementor_data`
- ❌ `str_replace('value=\"2026-1\">', 'value=\"2026-2\">', ...)` — el reemplazo sin nivel deja el período genérico

---

## 5. Rollback

```bash
# Restaurar desde backup
mysql --defaults-extra-file=/home/toor/.uide.cnf bitn_uide < backup_AAAAMMDD_HHMMSS.sql

# O restaurar fila por fila (si hay duplicados de meta_id):
# 1. DELETE FROM wp_postmeta WHERE post_id = X AND meta_key = '_elementor_data';
# 2. INSERT INTO wp_postmeta (meta_id, post_id, meta_key, meta_value) VALUES (...);
```

---

## 6. Historial de cambios aplicados

| Fecha | Operación | Detalle | Filas |
|---|---|---|---|
| 2026-07-14 | IDs programa | Marketing `2→557`, Administración `1→558` en `<option>` y JS | ~46 |
| 2026-07-14 | Períodos | `2026-1 → 2026-2` en hidden inputs de 13 páginas generales | 13 |
| 2026-07-14 | JS updatePeriodo | `2026-1 → 2026-2` en fallbacks JS de 13 páginas | 13 |
| 2026-07-14 | Maestrías 2027-1 | 9 páginas ya en `2027-1 Online Posgrado`; 1 oculta (Emergencias Sanitarias → draft) | 1 |
| 2026-07-14 | Educación Básica | Agregada al `posgrado_online` de 21 forms generales (ID 546, etiqueta "Maestría en Educación Básica - O") | 21 |
| 2026-07-14 | PROGS_2027_1 | Array `[510,515,265,278,546,554]` en `updatePeriodo()` con override al final | 21 |
| 2026-07-14 | Distancia→Quito | Forzar sede="Quito" en submit para posgrados desde Distancia (mismo patrón Loja/Guayaquil) | 21 |
| 2026-07-14 | ShowValuesSede | Normalizado `Posgrado Online` → `Posgrado En Línea` para Distancia (consistente con Loja/Guayaquil) | 11 |
| 2026-07-14 | Limpieza duplicado | Eliminado `sf.value = "Quito"` duplicado en handleConditionalRedirect | 11 |

### 6.1 Lecciones aprendidas de esta sesión

1. **Los acentos en `_elementor_data` son `\uXXXX`:** `str_replace('Educación', ...)` NO funciona. Usar `str_replace('Educaci\u00f3n', ...)`.
2. **No cambiar el DOM visible en handlers de submit:** el usuario ve el cambio. Solo en `handleConditionalRedirect`.
3. **Normalizar valores entre ShowValuesSede y handleConditionalRedirect:** si ShowValuesSede ya pone "Posgrado En Línea", no forzarlo de nuevo en submit.
4. **Un script puede corromper lo que otro arregló:** solapar PHP scripts sobre el mismo código produce duplicados. Limpiar con un script separado.
5. **Verificar con `curl` DESPUÉS de purgar cache:** el cache de WP Rocket miente.

---

## 7. Archivos de backup

| Archivo | Tamaño | Descripción |
|---|---|---|
| `/home/toor/backup_cambio_ids_20260714_095416.sql` | 12 MB | Backup IDs programa |
| `/home/toor/backup_formularios_general_20260714_102042.sql` | 1.1 MB | Backup períodos generales |
