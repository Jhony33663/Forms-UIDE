# PLAN DE IMPLEMENTACIÓN — Campañas UIDE según Catálogo de Orígenes

## ✅ Implementado y Verificado

### Qué se hizo

1. **Backup completo** de `wp_postmeta` (265 MB) — `/home/toor/backup_produccion_postmeta_20260713_122003.sql`
2. **Plugin WordPress** creado: `uide-campaign-catalog.php`
3. **Plugin instalado y activado** en `/opt/bitnami/wordpress/wp-content/plugins/`
4. **Cache purgada** — WP Rocket + Elementor CSS

### Resultado

- El catálogo de **62 origenes → 5 campañas** se sirve en el footer de cada página
- El script intercepta antes del submit y asigna la campaña correcta según el origen

---

## Plan de Rollback

### Si algo falla:

```bash
# 1. Desactivar plugin
cd /opt/bitnami/wordpress && wp plugin deactivate uide-campaign-catalog

# 2. Eliminar plugin
sudo rm /opt/bitnami/wordpress/wp-content/plugins/uide-campaign-catalog.php

# 3. Restaurar BD (si es necesario)
mysql --defaults-extra-file=/home/toor/.uide.cnf bitn_uide < /home/toor/backup_produccion_postmeta_20260713_122003.sql

# 4. Purgar cache
cd /opt/bitnami/wordpress && wp elementor flush-css --all
sudo rm -rf /opt/bitnami/wordpress/wp-content/cache/wp-rocket/*
```

---

## Catálogo Implementado

| Campaña | Orígenes mapeados |
|---|---|
| `TRAFICO_GENERAL_META_IT1_2026` | 9 (Facebook, Instagram, Messenger, Audience Network, etc.) |
| `TRAFICO_GENERAL_GOOGLE_IT1_2026` | 7 (Display, DemandGen, Discovery, Pmax, Youtube, DV360, Google) |
| `LEADS_GENERAL_GOOGLE_SEARCH_IT1_2026` | 2 (Google Search, Bing Ads) |
| `881_MF_TRAFICO_GENERAL_ORGANICO_SEO_SEARCH_IT1_2026` | 3 (Google/Bing Natural Search, ChatGPT) |
| `TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026` | 41 (TikTok, LinkedIn, Mailing, Spotify, Pinterest, X Ads, etc.) |

### Default (orígen no reconocido)
`881_MF_TRAFICO_GENERAL_ORGANICO_SEO_SEARCH_IT1_2026`

---

## Verificación en producción

```bash
# Ver script servido
curl -s "https://www.uide.edu.ec/maestria-en-bienestar-animal/" | grep -c "CATALOG"

# Ver campaña en campo oculto (requiere inspeccionar elemento en navegador)
# Enviar formulario con ?utm_source=Facebook+Ads y verificar que:
# - Origen = "Facebook Ads"
# - Campaña = "TRAFICO_GENERAL_META_IT1_2026"
```

---

## Estado Final

| Tarea | Estado |
|---|---|
| Backup completo | ✅ Creado |
| Plugin instalado | ✅ Activado |
| Script servido en HTML | ✅ Verificado |
| Cache purgada | ✅ Completo |
| Plan de rollback | ✅ Documentado |

---

**Implementado:** 2026-07-13 12:25
**Autor:** Hermes Agent
