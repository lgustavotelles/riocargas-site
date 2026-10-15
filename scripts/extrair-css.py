#!/usr/bin/env python3
# Extrai regras CSS por prefixo de seletor dos plugins pagos -> assets do mu-plugin.
import re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "wordpress/wp-content/mu-plugins/riocargas-components/assets"

JOBS = [
    (
        ROOT / "wordpress/wp-content/plugins/powerpack-elements/assets/css/frontend.css",
        ["pp-advanced-menu", "pp-menu-", "pp-hamburger", "pp-buttons", ".pp-button "],
        OUT / "pp-compat.css",
    ),
    (
        ROOT / "wordpress/wp-content/plugins/master-addons-pro/assets/css/master-addons-styles.css",
        ["ma-el-advance-tab", "ma-accordion", "ma-advanced-accordion", "ma-el-creative-link"],
        OUT / "ma-compat.css",
    ),
]

def extract(css_path, markers, out_path):
    text = css_path.read_text(encoding="utf-8", errors="replace")
    # divide em blocos { } preservando regras @media
    rules = re.findall(r"[^{}]+\{[^{}]*\}", text)
    keep = []
    for r in rules:
        sel = r.split("{")[0]
        if any(m in sel for m in markers):
            keep.append(r.strip())
    header = "/* Extraido de %s — ver scripts/extrair-css.py */\n" % css_path.name
    out_path.write_text(header + "\n".join(keep) + "\n", encoding="utf-8")
    print(out_path, len(keep), "regras")

for css, markers, out in JOBS:
    extract(css, markers, out)
