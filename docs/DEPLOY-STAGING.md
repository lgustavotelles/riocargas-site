# Deploy: substituição de plugins pagos → staging → produção

Guia para publicar a cópia local validada em `teste.riocargas.com.br` (staging) e,
após aprovação humana, em `riocargas.com.br`. **Nunca** executar direto em produção.

## O que muda

- **Upload de 1 pacote**: `dist/riocargas-mu-plugin.zip` (742 KB, 61 arquivos) → extrai
  `mu-plugins/riocargas-components.php` + `mu-plugins/riocargas-components/`. O pacote é
  autocontido (assets próprios, sem dependência dos plugins originais).
- **Desativação** de 6 plugins pagos (substituídos): `elementor-pro`, `wpforms`,
  `powerpack-elements`, `master-addons-pro`, `unlimited-elements-for-elementor-premium`,
  `ultimate-carousel-for-elementor`.
- **Remoção** (após staging validar) de 13 plugins legados sem uso: stack CF7
  (`contact-form-7`, `contact-form-7-skins`, `cf7-repeatable-fields`, `contact-form-cfdb7`),
  `header-footer-elementor`, `conditional-menus`, `members`, `essential-addons-elementor-old`,
  `astra-sites`, `templately`, `image-optimization`, `wp-sweep`, `wp-rollback`.
- **Nada muda no banco**: `wpforms_*` (entries de produção) continua em uso pelo substituto;
  o token antispam usa a option `wpforms_crypto_secret_key` já existente — tokens emitidos
  antes do deploy seguem válidos na janela normal (−2d/−1d/hoje/+45min).

## Pré-requisitos

1. Staging criado no WP Toolkit (cPanel) como clone de produção.
2. Backup completo de produção (arquivos + banco) antes do deploy.
3. Aprovação humana antes de replicar em produção.

## Passo a passo (staging)

```bash
# 1. Backup do staging (por precaução)
wp db export backup-pre-substituicao.sql --path=/home/riocargas/teste.riocargas.com.br

# 2. Upload + extração do pacote (via cPanel File Manager ou scp)
#    Destino: wp-content/mu-plugins/
#    Resultado: wp-content/mu-plugins/riocargas-components.php
#               wp-content/mu-plugins/riocargas-components/

# 3. Desativar os 6 plugins pagos
cd /home/riocargas/teste.riocargas.com.br
wp plugin deactivate elementor-pro wpforms powerpack-elements master-addons-pro \
  unlimited-elements-for-elementor-premium ultimate-carousel-for-elementor

# 4. Limpar caches
wp litespeed-purge all 2>/dev/null || true
# Autoptimize: limpar via admin (Autoptimize → "Delete Cache") ou:
rm -rf wp-content/cache/autoptimize/* 2>/dev/null || true

# 5. Smoke test
curl -s -o /dev/null -w "%{http_code}" https://teste.riocargas.com.br/
```

## Validação em staging (checklist)

- [ ] `/` — home renderiza (carrossel de logos, animated headline, menu)
- [ ] `/contato/` — form 3735 visível; enviar teste → confirmação + entry nova em
      `wpforms_entries` + e-mail de notificação entregue
- [ ] Popup "Trabalhe Conosco"/"Fornecedor" — abre; Dropzone inicia; upload de teste
      grava em `uploads/wpforms/{form_id}-{hash}/`
- [ ] `/unidades/` — carrossel Owl das unidades gira
- [ ] `/areadofuncionario/` (logado) — accordions e cardápio por tabs funcionam
- [ ] `/login/` — logo + form UM renderizam
- [ ] Mobile — menu hambúrguer abre submenus
- [ ] Admin — `wp-admin/plugins.php` carrega sem fatal; sem avisos de plugins faltando
      (depois de remover os legados)
- [ ] Console do browser — 0 erros JS; Network — 0 falhos (exceto favicon)

## Validação funcional WPForms (submit real)

```bash
# AJAX válido (token do form renderizado + campos required)
curl -X POST 'https://teste.riocargas.com.br/wp-admin/admin-ajax.php' \
  -d 'action=wpforms_submit&wpforms[id]=3735&wpforms[token]=<TOKEN_DA_PAGINA>' \
  -d 'wpforms[fields][0]=Teste' -d 'wpforms[fields][1]=t@t.com' \
  -d 'wpforms[fields][3]=21999998888' -d 'wpforms[fields][5]=Assunto' \
  -d 'wpforms[fields][2]=msg'
# Esperado: {"success":true,"data":{"confirmation":"..."}}
```

## Rollback

Reverter = reativar os 6 plugins + remover o mu-plugin + purge de cache:

```bash
wp plugin activate elementor-pro wpforms powerpack-elements master-addons-pro \
  unlimited-elements-for-elementor-premium ultimate-carousel-for-elementor
rm -rf wp-content/mu-plugins/riocargas-components wp-content/mu-plugins/riocargas-components.php
wp litespeed-purge all
```

## Depois de staging validado → produção

Mesmos passos, mas em produção **não apagar** os diretórios dos plugins pagos —
só desativar. Remoção definitiva só depois de dias de observação estável em produção.
Plugins legados (CF7 etc.) podem ser removidos junto — não têm dados ligados.

## Observações

- `litespeed-cache` e `really-simple-ssl` devem **permanecer** em produção
  (cache/HTTPS) — estão inativos só na cópia local.
- O substituto WPForms assume a tabela `wpforms_entries` existente; se o prefixo for
  diferente de `wpg8_` em staging, nada muda — o código usa `$wpdb->prefix`.
- Widgets sem substituto (só em conteúdo privado/não renderizado): `nav-menu`,
  `ue_side_menu`, `slides`, `pp-image-slider`, `ucaddon_content_grid2`,
  `ucaddon_uc_bullet_tabs` — exibem "widget ausente" se algum for publicado no futuro.
