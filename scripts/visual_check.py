#!/usr/bin/env python3
# Captura screenshots e compara pixel a pixel — validação "sem mudança visual".
# Uso:
#   py -3.12 scripts/visual_check.py capture <outdir> [--login] [--mobile] [--click <sel>] [slugs...]
#   py -3.12 scripts/visual_check.py diff <dirA> <dirB>
import sys, re
from pathlib import Path

BASE = "http://localhost:8080"
DESKTOP = {"width": 1440, "height": 900}
MOBILE = {"width": 390, "height": 844}


def nome(slug):
    return re.sub(r"[^a-z0-9]+", "-", slug.strip("/")).strip("-") or "home"


def capture(outdir, pages, login=False, mobile=False, click=None):
    from playwright.sync_api import sync_playwright
    out = Path(outdir)
    out.mkdir(parents=True, exist_ok=True)
    vp = MOBILE if mobile else DESKTOP
    with sync_playwright() as p:
        b = p.chromium.launch()
        ctx = b.new_context(viewport=vp, device_scale_factor=2 if mobile else 1,
                            is_mobile=mobile, has_touch=mobile)
        pg = ctx.new_page()
        if login:
            pg.goto(f"{BASE}/wp-login.php", wait_until="networkidle")
            pg.fill("#user_login", "devin")
            pg.fill("#user_pass", "devin-local-123")
            pg.click("#wp-submit")
            pg.wait_for_load_state("networkidle")
        for slug in pages:
            url = slug if slug.startswith("http") else f"{BASE}/{slug.strip('/')}/"
            try:
                pg.goto(url, wait_until="networkidle", timeout=90000)
                pg.wait_for_timeout(1500)
                if click:
                    pg.click(click)
                    pg.wait_for_timeout(1500)
                pg.screenshot(path=str(out / f"{nome(slug)}.png"), full_page=True)
                print(f"OK  {url}")
            except Exception as e:
                print(f"ERR {url}: {e}")
        b.close()


def diff(da, db):
    from PIL import Image, ImageChops
    da, db = Path(da), Path(db)
    for f in sorted(da.glob("*.png")):
        g = db / f.name
        if not g.exists():
            print(f"{f.name}: sem par em {db}")
            continue
        a, b2 = Image.open(f).convert("RGB"), Image.open(g).convert("RGB")
        if a.size != b2.size:
            print(f"{f.name}: TAMANHO DIFERENTE {a.size} vs {b2.size}")
            continue
        d = ImageChops.difference(a, b2)
        bbox = d.getbbox()
        if not bbox:
            print(f"{f.name}: identico (0 px)")
            continue
        diff_px = sum(1 for px in d.getdata() if px != (0, 0, 0))
        total = a.size[0] * a.size[1]
        d.save(db / f"diff-{f.name}")
        print(f"{f.name}: {diff_px} px diferentes ({100*diff_px/total:.3f}%) -> diff-{f.name}")


if __name__ == "__main__":
    cmd = sys.argv[1]
    args = sys.argv[2:]
    if cmd == "capture":
        outdir = args[0]
        login = "--login" in args
        mobile = "--mobile" in args
        click = None
        rest = []
        i = 1
        while i < len(args):
            if args[i] == "--login":
                login = True
            elif args[i] == "--mobile":
                mobile = True
            elif args[i] == "--click":
                i += 1
                click = args[i]
            else:
                rest.append(args[i])
            i += 1
        capture(outdir, rest, login=login, mobile=mobile, click=click)
    elif cmd == "diff":
        diff(args[0], args[1])
