#!/usr/bin/env python3
# Captura os popups de form abertos (Dropzone do upload modern deve renderizar).
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8080"

with sync_playwright() as p:
    b = p.chromium.launch()
    ctx = b.new_context(viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    pg.goto(f"{BASE}/wp-login.php", wait_until="networkidle")
    pg.fill("#user_login", "devin")
    pg.fill("#user_pass", "devin-local-123")
    pg.click("#wp-submit")
    pg.wait_for_load_state("networkidle")

    pg.goto(f"{BASE}/", wait_until="networkidle")
    pg.wait_for_timeout(1500)

    # abre cada popup de form e mede elementos-chave
    for pid, nome in [(3856, "fornecedor-3818"), (3823, "trabalhe-3814"), (3899, "cotacao-3821"), (3564, "sugestao-747"), (3547, "indicacao-3534")]:
        try:
            pg.evaluate(f"jQuery('#pum-{pid}').popmake('open')")
            pg.wait_for_timeout(2000)
            pop = pg.locator(f"#pum-{pid}")
            vis = pop.is_visible()
            fid = pg.evaluate(f"jQuery('#pum-{pid}').find('form.wpforms-form').attr('id')")
            dz = pg.locator(f"#pum-{pid} .wpforms-uploader").count()
            ch = pg.locator(f"#pum-{pid} .choices").count()
            iti = pg.locator(f"#pum-{pid} .iti").count()
            print(f"{nome}: popup_vis={vis} form={fid} dropzone={dz} choices={ch} intl={iti}")
            pop.locator("form.wpforms-form").screenshot(path=f".shots/popup-{nome}.png")
            pg.evaluate(f"jQuery('#pum-{pid}').popmake('close')")
            pg.wait_for_timeout(1500)
        except Exception as e:
            print(f"{nome}: ERR {e}")
    b.close()
