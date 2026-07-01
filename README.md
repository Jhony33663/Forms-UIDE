# Forms-UIDE

Exportación de **todos los formularios embebidos** del sitio UIDE (HTML dentro de widgets de Elementor), organizados por categoría (`post_type`).

Cada archivo `.html` inicia con un comentario que indica la **URL absoluta** de la página donde está el formulario, más `post_id`, `post_type`, `status` y `slug`.

## Categorías (141 formularios)

| Carpeta | Descripción | Cantidad |
|---|---|---|
| `page/` | Páginas (home, programas, pregrados presenciales) | 11 |
| `post/` | Carreras y maestrías | 119 |
| `e-landing-page/` | Landing pages de Elementor (Registro General) | 3 |
| `elementor_library/` | Plantillas reutilizables (CTA-home, blogs) — embebidas en otras páginas, sin URL propia | 8 |

## Regla de campaña aplicada

Los formularios con el script nuevo (`updateUtmTracking` + `ORIGINS`) **siempre** envían una campaña: si no hay UTM reconocido se asigna la orgánica `881_MF_TRAFICO_GENERAL_ORGANICO_SEO_SEARCH_IT1_2026` por defecto; con UTM se sobreescribe a la campaña correspondiente (Meta / Google / Search / Otros medios). Los formularios con el script anterior (`setCampaignFromOrigen`) ya cumplían esta regla mediante su fallback `TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026`.
