#!/usr/bin/env python3
"""Exporta los formularios embebidos de Elementor a <post_type>/<slug>-<id>.html.
Ejecutar desde la raíz del repo, con meta.tsv y raw/<id>.json ya generados
(ver CAMBIO-MASIVO-FORMULARIOS.md, sección 5)."""
import json, os, datetime, sys

BASE = "https://www.uide.edu.ec"
RAW = "raw"
OUT = "."
MARKERS = ("pardot-form", "go.uide.edu.ec/l/", "uide-frm-wrapper",
           "updateUtmTracking", "setCampaignFromOrigen")
TODAY = datetime.date.today().isoformat()

def find_form_chunks(node, out):
    if isinstance(node, dict):
        st = node.get("settings")
        if isinstance(st, dict):
            for v in st.values():
                _collect(v, out)
        for child in node.get("elements", []) or []:
            find_form_chunks(child, out)
    elif isinstance(node, list):
        for x in node:
            find_form_chunks(x, out)

def _collect(v, out):
    if isinstance(v, str):
        if any(m in v for m in MARKERS):
            out.append(v)
    elif isinstance(v, dict):
        for x in v.values():
            _collect(x, out)
    elif isinstance(v, list):
        for x in v:
            _collect(x, out)

def url_for(ptype, slug):
    if ptype == "elementor_library":
        return "(plantilla reutilizable de Elementor — embebida en otras páginas, sin URL pública propia)"
    return f"{BASE}/{slug}/" if slug else f"{BASE}/  (slug vacío, ver post_id)"

def main():
    written, empty, counts = 0, [], {}
    for line in open("meta.tsv", encoding="utf-8"):
        line = line.rstrip("\n")
        if not line:
            continue
        parts = (line.split("\t") + ["", "", "", "", ""])[:5]
        pid, ptype, slug, status, title = parts
        data = json.loads(open(os.path.join(RAW, f"{pid}.json"), encoding="utf-8").read().strip())
        chunks = []
        find_form_chunks(data, chunks)
        seen, uniq = set(), []
        for c in chunks:
            if c not in seen:
                seen.add(c); uniq.append(c)
        if not uniq:
            empty.append(pid); continue
        header = (
            "<!--\n  UIDE - Formulario embebido\n"
            f"  Pagina: {title}\n  URL: {url_for(ptype, slug)}\n"
            f"  post_id: {pid} | post_type: {ptype} | status: {status} | slug: {slug}\n"
            f"  Origen: wp_postmeta._elementor_data (bitn_uide)\n  Exportado: {TODAY}\n-->\n\n")
        body = "\n\n<!-- ===== siguiente bloque HTML del mismo widget/pagina ===== -->\n\n".join(uniq)
        d = os.path.join(OUT, ptype); os.makedirs(d, exist_ok=True)
        fn = (slug or f"post-{pid}").strip("/").replace("/", "-")
        with open(os.path.join(d, f"{fn}-{pid}.html"), "w", encoding="utf-8") as fo:
            fo.write(header + body + "\n")
        written += 1; counts[ptype] = counts.get(ptype, 0) + 1
    print("Escritos:", written, "| Por categoria:", counts)
    if empty:
        print("SIN formulario detectado:", empty, file=sys.stderr)

if __name__ == "__main__":
    main()
