# PLAN DE ACCIÓN — Cambio de Plazos en Formularios Web

## 1. Regla de actualización

| Condición | Período resultante |
|---|---|
| Programa está en listado 2026-2 | `2026-2 <Modalidad> <Nivel>` |
| Programa NO está en listado 2026-2 | `2027-1 <Modalidad> <Nivel>` |

### Modalidades por nivel
- **Posgrado**: `Presencial` / `Online`
- **Pregrado**: `Quito` / `Guayaquil` / `Loja` / `Online` / `PVC`

### Listado oficial 2026-2

#### Posgrado Presencial (4 programas)
```
MAESTRIA EN GASTRONOMIA-QUITO-PG
MAESTRIA DISEÑO INTERIORES-QUITO-PG
MAESTRIA EN BIENESTAR ANIMAL-QUITO-PG
MAESTRIA EN PRODUCCIÓN ANIMAL-QUITO-PG
```

#### Posgrado Online (37 programas)
```
MAESTRIA DE DERECHO PENAL-ONLINE-PG
MAESTRIA EN ENERGIAS RENOVABLES-ONLINE-PG
MAESTRIA SALUD PUBLICA-ONLINE-PG
MAESTRIA GERENCIA SALUD-ONLINE-PG
MAESTRIA EN CRIMINALISTICA-ONLINE-PG
MAESTRIA EN CIBERSEGURIDAD-ONLINE-PG
MAESTRIA GESTION DE RIESGOS-ONLINE-PG
MAESTRIA EN TRANSPORTE-ONLINE-PG
MAESTRIA TALENTO HUMANO-ONLINE-PG
MAESTRIA EN NUTRICION DEPORTIVA-ONLINE-PG
MAESTRIA EN GESTION EDUCATIVA-ONLINE-PG
MAESTRIA NUTRICION-ONLINE-PG
MAESTRIA EN GESTION DE LA SEGURIDAD PRIVADA-ONLINE-PG
MAESTRÍA EN DERECHO CONSTITUCIONAL-ONLINE-PG
MAESTRIA EN PROTECCION DE DATOS-ONLINE-PG
MAESTRIA EN MKT DIGITAL-ONLINE-PG
MAESTRIA EN DIRECCION FINANCIERA CON MENCION EN MERCADOS INTERNACIONALES-ONLINE-PG
MAESTRIA EN PROYECTOS-ONLINE-PG
MAESTRIA EN CIENCIAS DE DATOS Y MAQUINAS-ONLINE-PG
MAESTRIA EN DESARROLLO SOSTENIBLE Y RESPONSABILIDAD SOCIAL ORGANIZACIONAL-ONLINE-PG
MAESTRIA EN INTELIGENCIA DE NEGOCIOS Y COMPORTAMIENTO DEL CONSUMIDOR-ONLINE-PG
MAESTRIA PSICOPEDAGOGIA-ONLINE-PG
MAESTRIA EN EDUCACION TECNOLOGIA E INNOVACION-ONLINE-PG
MAESTRIA CALIDAD E INNOVACION-ONLINE-PG
MAESTRIA EN NEUROMARKETING-ONLINE-PG
MAESTRIA EN CONTABILIDAD Y AUDITORIA CON MENCION EN INTELIGENCIA ARTIFICIAL-ONLINE-PG
MAESTRIA GESTION DE SEGUROS-ONLINE-PG
MAESTRIA DERECHO DIGITAL-ONLINE-PG
MAESTRIA EN DERECHO CON MENCION EN DERECHO DE EMPRESA-ONLINE-PG
MAESTRIA MECANICA AUTOMOTRIZ-ONLINE-PG
MBA-ONLINE-PG
MAESTRÍA EN GESTIÓN PÚBLICA-ONLINE-PG
MAESTRÍA EN DERECHO LABORAL Y SEGURIDAD SOCIAL-ONLINE-PG
MAESTRIA SEGURIDAD Y SALUD OCUPACIONAL-ONLINE-PG
MAESTRÍA EN INTELIGENCIA ARTIFICIAL APLICADA-ONLINE-PG
MAESTRIA FINANZAS CORPORATIVAS-ONLINE-PG
MAESTRIA SUPPLY-ONLINE-PG
```

---

## 2. Problema actual del repositorio

Los formularios exportados **no contienen `esc_pgm`** en muchos casos, por lo que no se puede determinar automáticamente qué programa corresponde al formulario. Es necesario cruzar contra la BD.

### Estado del repo por período

| Período actual | Cantidad | Acción requerida |
|---|---|---|
| `2026-1 Online Posgrado` | 50 | Cruzar con lista 2026-2 → dividir en 2026-2 o 2027-1 |
| `2026-1 Presencial Posgrado` | 4 | Todos a 2026-2 Presencial (coinciden con lista) |
| `2026-2 Online Posgrado` | 1 | Ya está en 2026-2 |
| `2026-2A Online Pregrado` | 23 | Evaluar caso a caso (pregrado) |
| `2026-2 Q Pregrado` | 17 | Evaluar caso a caso (pregrado) |
| `2026-2 Guayaquil` | 1 | Evaluar |
| `2026-2 L Pregrado` | 1 | Evaluar |
| `2026-2A Online PVC` | 1 | Evaluar |
| `2026-1 L Pregrado` | 2 | Evaluar |
| `2026-1 Q Pregrado` | 1 | Evaluar |
| Sin período definido (`?`) | 41 | Requieren revisión manual |

---

## 3. Estrategia de ejecución

### Fase 1: Cruzar datos con BD (antes de tocar nada)

```sql
-- Obtener mapping post_id → esc_pgm desde wp_postmeta
SELECT p.ID, p.post_name, p.post_type,
       MAX(CASE WHEN m.meta_key = 'esc_pgm' THEN m.meta_value END) as esc_pgm,
       MAX(CASE WHEN m.meta_key = 'periodo' THEN m.meta_value END) as periodo_actual,
       MAX(CASE WHEN m.meta_key = 'tp_pgm' THEN m.meta_value END) as tp_pgm,
       MAX(CASE WHEN m.meta_key = 'sede' THEN m.meta_value END) as sede
FROM wp_posts p
JOIN wp_postmeta m ON m.post_id = p.ID
WHERE p.post_status = 'publish'
  AND p.post_type IN ('post', 'page', 'e-landing-page', 'elementor_library')
  AND (m.meta_key IN ('esc_pgm', 'periodo', 'tp_pgm', 'sede')
       OR m.meta_key = '_elementor_data')
GROUP BY p.ID, p.post_name, p.post_type
HAVING m.meta_key = '_elementor_data' IS NOT NULL
   AND (m.meta_value LIKE '%pardot-form%' OR m.meta_value LIKE '%go.uide.edu.ec/l/%')
ORDER BY p.post_type, p.ID;
```

### Fase 2: Clasificar cada registro

Para cada formulario:
1. Identificar `esc_pgm` → nombre del programa
2. Buscar en lista 2026-2:
   - **Está** → nuevo período = `2026-2 <Modalidad> <Nivel>`
   - **No está** → nuevo período = `2027-1 <Modalidad> <Nivel>`
3. Determinar modalidad por `tp_pgm` o `sede`:
   - `Posgrado Quito` → `Presencial`
   - `Posgrado En Línea` → `Online`
   - `Pregrado Quito` → `Quito`
   - `Pregrado Guayaquil` → `Guayaquil`
   - `Pregrado Loja` → `Loja`
   - `Pregrado Distancia` → `Online`

### Fase 3: Canary (1 carrera)

**Carrera sugerida:** Maestría en Bienestar Animal (Presencial, está en la lista)

1. Hacer backup del post_id
2. UPDATE con REPLACE en `_elementor_data` cambiando el `periodo`
3. Validar que el JSON siga válido
4. Verificar en la web que el_formulario muestre el nuevo período

### Fase 4: Cambio masivo

Aplicar el mismo `UPDATE ... REPLACE` a los demás formularios agrupados por tipo de cambio.

### Fase 5: Verificar

```sql
SELECT COUNT(*) total,
       SUM(LOCATE('2026-2', meta_value) > 0) en_2026_2,
       SUM(LOCATE('2027-1', meta_value) > 0) en_2027_1,
       SUM(LOCATE('2026-1', meta_value) > 0) en_2026_1_restantes,
       SUM(JSON_VALID(meta_value) = 1) json_validos
FROM wp_postmeta
WHERE meta_key = '_elementor_data'
  AND post_id IN (/* ids */);

-- Esperado: restantes 2026-1 = 0, json_validos = total
```

---

## 4. Períodos destino según modalidad

| Nivel | Modalidad sede | Período si está en lista | Período si NO está en lista |
|---|---|---|---|
| Posgrado | Quito (Presencial) | `2026-2 Presencial Posgrado` | `2027-1 Presencial Posgrado` |
| Posgrado | En Línea (Online) | `2026-2 Online Posgrado` | `2027-1 Online Posgrado` |
| Pregrado | Quito | `2026-2 Q Pregrado` | `2027-1 Q Pregrado` |
| Pregrado | Guayaquil | `2026-2 Guayaquil` | `2027-1 Guayaquil` |
| Pregrado | Loja | `2026-2 L Pregrado` | `2027-1 L Pregrado` |
| Pregrado | Distancia (Online) | `2026-2 Online Pregrado` | `2027-1 Online Pregrado` |
| PVC | Distancia | `2026-2 Online PVC` | `2027-1 Online PVC` |

---

## 5. Rollback

```bash
mysql --defaults-extra-file=~/.uide.cnf bitn_uide < backup_periodos_AAAAMMDD_HHMMSS.sql
```

---

## 6. Pendientes por resolver

1. **¿Cuántos formularios de posgrado online en 2026-1 están en la lista 2026-2?** →Requiere cruce BD
2. **¿Los programas de pregrado presencial (Quito, Guayaquil, Loja) se quedan en 2026-2?** → Asumo que sí porque son "continuos" y no tienen lista de excepción
3. **¿Los programas de PVC y Online Pregrado se quedan en 2026-2?** → Asumo que sí por igual
4. **¿Los formularios de home/landing/blog sin programa específico qué período reciben?** → Deben heredar según el target (pregrado/postgrado) o mantener el actual

---

## 7. Cronograma

| Paso | Acción | Estado |
|---|---|---|
| 1 | Extraer esc_pgm y datos actuales de BD | Pendiente |
| 2 | Cruzar con lista oficial 2026-2 | Pendiente |
| 3 | Clasificar cada formulario | Pendiente |
| 4 | Backup completo | Pendiente |
| 5 | Canary en 1 carrera | Pendiente |
| 6 | Validación manual del resultado | Pendiente |
| 7 | Cambio masivo | Pendiente |
| 8 | Verificación final | Pendiente |
