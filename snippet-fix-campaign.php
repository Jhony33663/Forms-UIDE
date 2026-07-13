<?php
/**
 * UIDE: Asigna la campaña correcta según el origen asignado en el formulario.
 *
 * El catálogo de origenes se mapea a 5 campañas UIDE.
 * Si el origen no coincide con algún mapeo, se usa el default orgánico.
 *
 * Instalación: agregar este código en functions.php del child theme o en
 * un plugin de snippets (Code Snippets, WPCode, etc).
 *
 * Flujo:
 *  - El usuario llega a la página (con o sin UTM)
 *  - updateUtmTracking() existente (en el JSON del widget) asigna el origen según UTM/origen default
 *  - Este snippet intercepta antes del submit y asigna la campaña correspondiente
 */

add_action('wp_footer', function () {
    if (!is_singular(['post', 'page', 'e-landing-page'])) return;
?>
<script>
(function() {
    // Catalogo UIDE: Origen (Display Name) → Campaña Pardot
    var CATALOG = {
        // META
        'Audience Network Ads': 'TRAFICO_GENERAL_META_IT1_2026',
        'Facebook':              'TRAFICO_GENERAL_META_IT1_2026',
        'Facebook Ads':          'TRAFICO_GENERAL_META_IT1_2026',
        'Facebook Ads Trafico':  'TRAFICO_GENERAL_META_IT1_2026',
        'Direct Messenger Ads':  'TRAFICO_GENERAL_META_IT1_2026',
        'Instagram':             'TRAFICO_GENERAL_META_IT1_2026',
        'Instagram Ads':         'TRAFICO_GENERAL_META_IT1_2026',
        'Messenger Ads':         'TRAFICO_GENERAL_META_IT1_2026',
        'Meta Trafico':          'TRAFICO_GENERAL_META_IT1_2026',

        // GOOGLE DISPLAY/VIDEO
        'Google':                'TRAFICO_GENERAL_GOOGLE_IT1_2026',
        'Google Display':        'TRAFICO_GENERAL_GOOGLE_IT1_2026',
        'Google Demandgen':      'TRAFICO_GENERAL_GOOGLE_IT1_2026',
        'Google Discovery':      'TRAFICO_GENERAL_GOOGLE_IT1_2026',
        'Google Pmax':           'TRAFICO_GENERAL_GOOGLE_IT1_2026',
        'Youtube Ads':           'TRAFICO_GENERAL_GOOGLE_IT1_2026',
        'DV360':                 'TRAFICO_GENERAL_GOOGLE_IT1_2026',

        // SEARCH PAGO
        'Google Search':         'LEADS_GENERAL_GOOGLE_SEARCH_IT1_2026',
        'Bing Ads':              'LEADS_GENERAL_GOOGLE_SEARCH_IT1_2026',

        // ORGÁNICO
        'Google Natural Search': '881_MF_TRAFICO_GENERAL_ORGANICO_SEO_SEARCH_IT1_2026',
        'Bing Natural Search':   '881_MF_TRAFICO_GENERAL_ORGANICO_SEO_SEARCH_IT1_2026',
        'Chatgpt':               '881_MF_TRAFICO_GENERAL_ORGANICO_SEO_SEARCH_IT1_2026',

        // OTROS MEDIOS (todos los demás)
        'Calls':                 'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Capacitación empresas': 'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Charla empresas':       'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Charla FS':             'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Chat En Línea':         'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Chat Tiktok':           'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Connected TV':          'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Eventos':               'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Feria empresas':        'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Ferias FS':             'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Formulario Web':        'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Graduide':              'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'HBO Ads':               'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Influencers':           'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Linkedin':              'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Linkedin Ads':          'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Mailing':               'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Pinterest':             'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Pinterest Ads':         'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'ReactivadosMKT':        'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Referidos Admisiones':  'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Referidos empleados':   'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Referidos MKT':         'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Revistas Digitales':    'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Spotify':               'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Stock Cierre':          'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Stock NE':              'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'StockP1':               'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'StockP2':               'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'StockP3':               'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'StockP4':               'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Teads':                 'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Tiktok':                'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Tiktok Ads':            'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Tiktok Ads Trafico':    'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Tik Tok Trafico':       'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Visita a campus':       'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Vix':                   'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Walk in':               'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'Webinars':              'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026',
        'X Ads':                 'TRAFICO_GENERAL_OTROS_MEDIOS_IT1_2026'
    };

    /**
     * Asigna la campaña UIDE según el origen actual del formulario.
     * Se llama antes de cada submit para asegurar que la campaña sea correcta.
     */
    window.setCampaignFromCatalog = function() {
        var oriF = document.getElementById('origen'),
            cmpF = document.getElementById('utm_campaign'),
            ldF  = document.getElementById('c_lead');

        if (!cmpF) return;

        // Leer origen actual (ya sea por UTM procesado o default de la página)
        var origen = (oriF && oriF.value) ? oriF.value.trim() : 'Google Natural Search';

        // Buscar campaña en el catálogo
        var campaign = CATALOG[origen] || CATALOG['Google Natural Search'];

        // Asignar
        cmpF.value = campaign;
        if (ldF) ldF.value = 'Digital';
    };

    // Auto-ejecutar al cargar (después de un delay para que updateUtmTracking original haya corrido)
   function init() {
        setTimeout(window.setCampaignFromCatalog, 150);
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        init();
    } else {
        window.addEventListener('load', init);
    }

    // Re-asignar antes de cada submit (para evitar que Pardot use campañas viejas)
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.id === 'pardot-form') {
            window.setCampaignFromCatalog();
        }
    }, true);
})();
</script>
<?php
});
