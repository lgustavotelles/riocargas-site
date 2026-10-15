#!/usr/bin/env python3
# E2E: abre /contato/, preenche o form 3735, submete e verifica a confirmação.
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8080"

with sync_playwright() as p:
    b = p.chromium.launch()
    pg = b.new_page()
    pg.goto(f"{BASE}/wp-login.php", wait_until="networkidle")
    pg.fill("#user_login", "devin")
    pg.fill("#user_pass", "devin-local-123")
    pg.click("#wp-submit")
    pg.wait_for_load_state("networkidle")

    pg.goto(f"{BASE}/contato/", wait_until="networkidle")
    pg.wait_for_timeout(1500)

    form = pg.locator("#wpforms-form-3735")
    print("form visivel:", form.is_visible())

    form.locator('[name="wpforms[fields][0]"]').fill("Teste E2E Devin")
    # phone é intl-tel: input visivel é .iti__tel-input (o [name] fica hidden)
    tel = form.locator('#wpforms-3735-field_3')
    if not tel.is_visible():
        tel = form.locator('.iti__tel-input, .iti input[type="tel"]').first
    tel.fill("+5521999887766")
    form.locator('[name="wpforms[fields][1]"]').fill("e2e@teste.local")
    form.locator('[name="wpforms[fields][5]"]').fill("Assunto E2E")
    form.locator('[name="wpforms[fields][2]"]').fill("Mensagem E2E via browser")

    with pg.expect_response(lambda r: "admin-ajax.php" in r.url and r.request.method == "POST", timeout=30000) as ri:
        form.locator('[type="submit"]').click()
    resp = ri.value
    print("status:", resp.status)
    print("body:", resp.text()[:300])

    pg.wait_for_timeout(1500)
    conf = pg.locator("#wpforms-confirmation-3735")
    print("confirmacao visivel:", conf.is_visible())
    print("texto:", conf.inner_text()[:200] if conf.count() else "n/a")
    b.close()
