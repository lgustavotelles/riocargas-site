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

## Resultado da validação em staging (executado em 21/10)

Staging `teste.riocargas.com.br` validado com os 6 pagos desativados + mu-plugin instalado:

- [x] Todas as páginas públicas renderizam; `unidades`/`manutencao` saíram **melhores**
      que o estado original (que as servia vazias por cache LiteSpeed corrompido)
- [x] Diff visual vs baseline: 0 px em `areadofuncionario`/`politica`; ≤1,25 % ruído
      dinâmico nas demais
- [x] Owl carousel `/unidades/`: 11 itens, nav, dots
- [x] WPForms: markup + `data-token` **idênticos ao local** (segredo `wpforms_crypto_secret_key`
      preservado no clone — tokens interoperáveis); validação em PT-BR; **submit real →
      `success` + confirmação + entry gravada + e-mail disparado**
- [x] Dropzone do popup "Fornecedor" inicializa (mensagem PT-BR)
- [x] Editor Elementor abre, preview renderiza, painel de widgets ok
- [x] `areadofuncionario` logado: 14 accordions + 23 tabs; anônimo → 404 (correto, restrita UM)
- [x] Menu mobile abre com submenus; 0 erros JS; 0 requests falhos; 0 ícones sem fonte
- [!] Admin do WPForms não existe (substituto é frontend-only): entries gravam na tabela
      e notificações vão por e-mail como antes, mas **não há tela wp-admin para consultar
      entries** — pendente decisão (construir lista mínima ou aceitar e-mail como canal)
- [i] Erro CORS de fonte `google-fonts` no staging = artefato do clone (CSS estático com
      URL de produção); não existe em produção
- [i] Plugins legados (CF7 stack, Conditional Menus etc.) seguem ativos/inativos no
      staging como estavam em produção — remoção é fase posterior, não bloqueia

## Resultado do deploy em produção (executado em 21/10)

Deploy executado em `riocargas.com.br` (backup via WP Toolkit antes, mu-plugin subido,
6 pagos desativados, purge LiteSpeed+Autoptimize, Regenerate CSS Elementor):

- [x] Todas as páginas públicas: 200; `unidades`/`manutencao` voltaram a renderizar
      (estavam vazias por cache corrompido — **bug de produção corrigido**)
- [x] Diff vs staging validado: `unidades` idêntica; demais ≤1,25 % ruído dinâmico
- [x] Owl carousel: 11 itens; accordions/tabs; popups; Dropzone fornecedor (PT-BR)
- [x] WPForms: `data-token` idêntico ao local/staging (`wpforms_crypto_secret_key`
      preservada em todos os ambientes); honeypot; submit real → `success` +
      confirmação + entry gravada + e-mail entregue
- [x] Menu mobile, ícones (0 sem fonte), 0 erros JS, 0 requests falhos
- [x] `areadofuncionario`: 404 anônimo (correto — restrita UM) e renderiza logado

**Estado**: produção rodando 100 % com mu-plugin `riocargas-components`; 6 plugins
pagos desativados (arquivos intactos — remoção só após dias de observação).
E-mail de teste "TESTE DEPLOY" foi enviado à caixa de contato (ignorar).

## Pacote de segurança LGPD — uploads de formulários (commit 3f9ad8f)

Bloqueia o acesso direto aos ~480 arquivos de `uploads/wpforms/` (currículos,
apresentações) que estavam públicos por URL — exposição de dados pessoais.

**O que o pacote faz sozinho** (sem passo manual de .htaccess):

- No primeiro request, o módulo grava `Require all denied` em
  `wp-content/uploads/wpforms/.htaccess` (bloco `BEGIN/END RioCargas LGPD`,
  fora dos marcadores do WPForms, idempotente).
- Acesso direto a qualquer arquivo da pasta → **403**.
- E-mails de notificação passam a conter links assinados HMAC para
  `admin-ajax.php?action=rc_wpforms_file&p=...&s=...`, que servem o arquivo
  (`Content-Disposition: inline`, `X-Robots-Tag: noindex`). Link é permanente
  — e-mails antigos continuam abrindo.
- O endpoint fica ativo mesmo com o WPForms original reativado (rollback).

**Deploy:** subir o novo `dist/riocargas-mu-plugin.zip` por cima do existente
(extrair em `wp-content/`, sobrescrevendo) → carregar qualquer página do site
uma vez para o `.htaccess` ser criado → validar:

```
# URL crua de um arquivo conhecido deve retornar 403:
curl -I https://DOMINIO/wp-content/uploads/wpforms/<dir>/<arquivo>   # 403
# Link assinado (de um e-mail novo ou gerado por wp eval) deve retornar 200.
```

**Rollback:** reativar WPForms + apagar `mu-plugins/riocargas-components*` +
remover o bloco `RioCargas LGPD` do `.htaccess` de `uploads/wpforms/`.

Validado localmente: direto 403, assinado 200, adulterado 403, traversal 404,
submit real com upload → entry + e-mail com link funcional.

## Otimização de imagens — WebP com fallback (P1)

Pacote `dist/riocargas-webp.zip` (~40 MB): 615 arquivos `.webp` gerados ao lado
dos `.png`/`.jpg` originais em `wp-content/uploads/` (economia ~108 MB, q82) +
`.htaccess` que serve a versão `.webp` só quando o navegador declara
`Accept: image/webp` e o arquivo existe — originais intactos como fallback.

**Deploy:** File Manager → `wp-content/uploads/` → upload do ZIP → **Extract**
(cria os `.webp` ao lado dos originais e o `.htaccess`) → apagar o ZIP.

**Validar:**

```bash
# sem Accept webp → PNG/JPG original:
curl -s -o nul -w "%{size_download} %{content_type}" https://DOMINIO/wp-content/uploads/2022/06/Caminhao-RC.png
# com Accept webp → ~103 KB image/webp:
curl -s -o nul -H "Accept: image/webp,*/*" -w "%{size_download} %{content_type}" https://DOMINIO/wp-content/uploads/2022/06/Caminhao-RC.png
```

Cópia versionada da regra: `scripts/uploads-webp.htaccess`.

**Rollback:** apagar o `.htaccess` de `wp-content/uploads/` (os `.webp` podem
ficar — ficam inertes). Imagens novas enviadas depois não terão `.webp` até
gerar novamente.

## Observações

- `litespeed-cache` e `really-simple-ssl` devem **permanecer** em produção
  (cache/HTTPS) — estão inativos só na cópia local.
- O substituto WPForms assume a tabela `wpforms_entries` existente; se o prefixo for
  diferente de `wpg8_` em staging, nada muda — o código usa `$wpdb->prefix`.
- Widgets sem substituto (só em conteúdo privado/não renderizado): `nav-menu`,
  `ue_side_menu`, `slides`, `pp-image-slider`, `ucaddon_content_grid2`,
  `ucaddon_uc_bullet_tabs` — exibem "widget ausente" se algum for publicado no futuro.
