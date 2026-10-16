# AGENTS.md — riocargas-site

## Contexto

Site WordPress de produção `riocargas.com.br` (tema Astra, Elementor 4.3.5, WPForms, LiteSpeed Cache, hospedagem cPanel compartilhada). Este repo contém código próprio (`wordpress/wp-content/mu-plugins/`, e futuramente temas/plugins) e a configuração do ambiente local. Ficam fora do git: núcleo do WordPress, `uploads/`, dumps, `wp-config.php` e segredos.

## Regra permanente: produção é intocável

- **NUNCA** rodar sondagens/testes contra `riocargas.com.br` ou o cPanel — o firewall do servidor bloqueia IPs que procuram arquivos sensíveis. Em 15/10/2026 o IP da sessão Devin Cloud (140.232.64.4) foi bloqueado assim e a sessão perdeu acesso total. Em produção, apenas requisições benignas e pontuais (GET de páginas públicas); todo o resto só na cópia local ou staging.
- **Não** usar "Regenerate Assets" nem limpar cache do plugin Essential Addons — o formulário de Contato depende de arquivos de cache antigos e fica quebrado (estreito) se regenerado. Correção definitiva pendente em código.
- Publicação em produção só via staging + backup + aprovação humana.

## Histórico (migrado de sessão Devin Cloud em 15/10/2026)

- Site caiu por exigência do Composer de PHP `>= 8.3.0`; o domínio estava travado em PHP 7.4 no **Gerenciador MultiPHP** do cPanel (a tela "Select PHP Version" não manda no domínio). Corrigido para `ea-php83`.
- Auditoria inicial em `docs/AUDITORIA.md` (feita sobre backup de 15/10, banco `riocarga_wp401`). Destaques: usuário suspeito `wp_rest_api` (e-mail `do-not-remove@wordpress.c0m`, típico de malware — exclusão autorizada pelo dono); plugins pagos desatualizados (Elementor Pro 3.7.1, WPForms 1.7.6, Master Addons Pro, PowerPack, Unlimited Elements Pro — licenças vencidas); 482 arquivos de formulários (currículos etc.) acessíveis por link direto (LGPD); home com ~16 MB (`Footer.png` 7,9 MB); Lighthouse mobile 59.
- Plano aprovado pelo dono, nesta ordem: (1) substituir todos os plugins pagos por código próprio ou alternativas gratuitas **sem nenhuma mudança visual** — validação por comparação pixel a pixel, desktop e mobile; (2) otimização a fundo; (3) relatório de melhorias/funcionalidades sugeridas; (4) novas funcionalidades mapeadas pelo dono.

## Estado atual

- Branch de trabalho: `devin/1792042759-substituir-plugins-pagos` — mu-plugin `riocargas-components` com substitutos próprios (port verbatim dos originais + stubs de compat em `compat/`).
- **Portados e validados**: `animated-headline`, `site-logo`, `motion-fx` (Elementor Pro); card carousel e content accordion (Unlimited Elements Pro); `pp-advanced-menu`, `pp-buttons` (PowerPack); `ma-tabs`, `ma-advanced-accordion`, `ma-creative-links` + extensões wrapper-link/bg-slider/glassmorphism (Master Addons Pro).
- **Validação final (17/10)**: com PowerPack e Master Addons Pro desativados, as 9 páginas comparadas ficaram visualmente idênticas ao baseline com plugins (6 pixel-perfect; `home` ~1,2% = posição do carrossel autoplay, provado idêntico em ON-vs-ON; `sobrenos`/`politica` = timing de lazy-load). CSS gerado pelo Elementor byte-idêntico ao original em todos os posts. Teste funcional ok: tabs, accordion, botão "Rastrear Encomendas" e menu (SmartMenus inicializado).
- **Causa-raiz descoberta**: PP e Master Addons enfileiravam CSS/JS **globalmente** (bootstrap 4.4.1 com normalize afeta o site inteiro) — o mu-plugin reproduz esses enqueues com os mesmos handles. Elementor 4 removeu `elementorCommon.helpers.softDeprecated` que o Elementor Pro 3.7.1 chama; a exceção matava a cadeia `elementor/frontend/init` — resolvido com polyfill em `assets/compat/elementor-helpers-polyfill.js`.
- `FS_METHOD=direct` no wp-config local é necessário para o Elementor gravar `uploads/elementor/css/*.css` via HTTP (alternativa: regenerar via CLI).
- **WPForms Pro portado e validado (19/10)**: módulo `mu-plugins/riocargas-components/forms/` (`wpforms-compat` gated em `plugins_loaded` por `WPFORMS_VERSION`/`wpforms()`; `class-renderer`, `class-process`, `class-uploads`, `class-facade`). Contrato observável idêntico ao 1.7.6: markup dos 6 forms bit a bit (diff zero após normalizar nonce/token/timestamp), ordem de processamento do plugin (validate → nonce para logados → honeypot → token antispam → akismet → format → hooks → save), entries no formato original (`post_id=0`, `type`/`status` vazios), uploads classic (`$_FILES`) e modern (Dropzone tmp + chunks) para `uploads/wpforms/{form_id}-{md5(form_id.post_date)}/`, e-mails com smart tags. Assets do plugin copiados em `assets/wpforms-plugin/`.
- **Detalhes do token antispam v2**: `md5("dmYzW" . "::" . form_id . secret)` com janela −2d/−1d/hoje/+45min, secret em `wpforms_crypto_secret_key`. Só valida quando `settings['antispam']==='1'` (5 dos 6 forms; 747 usa honeypot). Token ausente/inválido → erro de header (anônimo: "The form was unable to submit. Please contact the site administrator."; admin: "This page isn't loading JavaScript properly…").
- **Nonce**: `wpforms[nonce]` só é emitido para `is_user_logged_in()` (`wp_create_nonce("wpforms::form_{id}")`); ausente/inválido para logado → fail silencioso (confirmação fake, sem gravar). `wpforms()->process` (FacadeProcess) é proxy por referência para `Process::$errors/$fields`.
- **Autoptimize local**: o backup trouxe `wp-content/cache/autoptimize/` praticamente vazio (só `index.html`) — na primeira visita anônima o handler 404 do Autoptimize responde `410 Gone` para bundles referenciados e a página carrega sem o CSS agregado (Font Awesome inteiro some → "ícones sumidos"). Os bundles são regenerados sob demanda nos requests seguintes; se ocorrer de novo, basta recarregar ou aquecer com `curl` nas páginas públicas. Corrigido também o 404 do `widget-mega-menu.min.css`/`widget-blockquote.min.css` (HFE enfileira handles de widgets inexistentes no Pro 3.7.1 — dequeue no mu-plugin, `wp_enqueue_scripts` pri 100).
- **20/10 — todos os plugins pagos desligados localmente**: `elementor-pro`, `unlimited-elements-for-elementor-premium`, `ultimate-carousel-for-elementor` (este último sem nenhum widget em uso), `wpforms`, PowerPack e Master Addons desativados; substitutos do mu-plugin cobrem tudo que páginas públicas usam. Baseline anterior (`baseline-desktop`) estava corrompido: foi capturado com o bundle Autoptimize 410, então widgets UE apareciam vazios — baseline válido é `baseline2-desktop`. Diff ON-vs-OFF correto: `areadofuncionario`/`normas`/`unidades` **0 px**; demais ≤0,12% (badge WPForms na admin bar). `nav-menu`/`ue_side_menu`/`slides`/`pp-image-slider` só existem em posts privados/templates não renderizados — sem substituto por ora.
- 15/10: `/unidades/` e `/manutencao/` retornam 200 em produção mas com **cache LiteSpeed corrompido** (~250-360 bytes, fragmento) — se o cache for limpo, fatal em produção. A substituição corrige isso.
- Harness: `scripts/visual_check.py capture|diff` (Playwright, login `devin`/`devin-local-123` para páginas restritas).

## Ambiente local (Windows, sem Docker)

A máquina local não tem virtualização habilitada na BIOS (SVM desativado) — Docker/WSL2 indisponíveis. Stack real em uso: **Laragon** (`C:\laragon`) com:

- Apache 2.4.68 (`C:\laragon\bin\apache\httpd-2.4.68-260617-Win64-VS18`) — VirtualHost `*:8080` → `wordpress/`; PHP 8.3.33 TS via `mod_php` (config adicionada no fim do `conf/httpd.conf`, marcada `# === riocargas local (Devin) ===`)
- MySQL 8.4.3 (`C:\laragon\bin\mysql\mysql-8.4.3-winx64`) — datadir `C:\laragon\data\mysql`, porta 3306; banco `riocargas`, user `riocargas`/`riocargas_local` (root local sem senha)
- wp-cli em `C:\laragon\bin\wp-cli.phar` — rodar com `"C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" C:\laragon\bin\wp-cli.phar --path=wordpress <cmd>`
- Site local: `http://localhost:8080` (URLs de produção já reescritas via search-replace; plugins `really-simple-ssl`, `litespeed-cache`, `wp-maintenance-mode` desativados)

Para subir os serviços após reboot:

```powershell
Start-Process 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe' -ArgumentList '--datadir=C:/laragon/data/mysql','--port=3306','--mysql-native-password=ON' -WindowStyle Hidden
Start-Process 'C:\laragon\bin\apache\httpd-2.4.68-260617-Win64-VS18\bin\httpd.exe' -WindowStyle Hidden
```

Se a virtualização for habilitada na BIOS (SVM) futuramente, o caminho Docker do `README.md` continua válido (WSL2 + Docker Desktop).

## Pendências abertas

- ~~Excluir o usuário `wp_rest_api` no wp-admin de produção~~ — resolvido pelo dono em 15/10 (senhas de admin trocadas também).
- Admins `vverner`/`vverner2` (agência, 2020): manter (decidido pelo dono).
- Confirmar se existe cron no cPanel chamando `wp-cron.php` (`DISABLE_WP_CRON=true` no wp-config).
- Criar staging (`teste.riocargas.com.br` via WP Toolkit) quando houver acesso ao cPanel.
- Backup automático externo e monitoramento de queda (sugestões da auditoria).
