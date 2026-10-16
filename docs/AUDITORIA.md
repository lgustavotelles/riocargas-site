# Auditoria inicial: riocargas.com.br (outubro/2026)

Análise feita numa **cópia local** do site, montada a partir do backup do cPanel de 15/10/2026 (arquivos + banco `riocarga_wp401`). Nenhum teste foi feito contra o site em produção.

**Resumo:** WordPress 7.1.3 com o núcleo íntegro (arquivos batem com os checksums oficiais), tema Astra, Elementor + Elementor Pro e 29 plugins ativos. Os pontos mais importantes são: um **usuário suspeito** no banco, **plugins pagos muito desatualizados**, **páginas pesadas demais** (16 MB na home) e **arquivos enviados por formulários acessíveis por link direto**.

Legenda de prioridade: 🔴 alta · 🟠 média · 🟢 baixa

---

## 1. Segurança

| # | Prioridade | Problema | O que fazer |
|---|---|---|---|
| S1 | ✅ **parcialmente resolvido 16/10** | Usuário `wp_rest_api` (e-mail falso `do-not-remove@wordpress.c0m`, criado 2021, sem permissão) — **não existe mais** em produção (ausente no clone atual do staging; o banco local é de snapshot antigo). | Salts do `wp-config.php` **renovados** em produção (novo conjunto gerado pela API oficial) — invalida sessões antigas. Pendente preventivo: **trocar senhas dos admins** e rodar scan Imunify360. |
| S2 | ✅ **resolvido** | Plugins pagos parados em versões antigas: **Elementor Pro 3.7.1** (2022), **WPForms 1.7.6** (2022), Master Addons Pro, PowerPack, Unlimited Elements, Ultimate Carousel. | **Substituídos** pelo mu-plugin `riocargas-components` (renderer próprio dos widgets usados + implementação compatível de WPForms com entries, uploads, antispam e e-mails). 19 plugins removidos; deploy em produção validado. |
| S3 | ✅ **resolvido 16/10** | Tema **Astra 3.8.2** desatualizado. | **Atualizado para 4.14.1** — primeiro no staging (comparação visual antes/depois idêntica; diffs de pixels eram só animação do carrossel), depois em produção. Páginas 200 e zero erros de JS. CFDB7 foi removido na limpeza de plugins (S2). Pendente leve: WP Statistics segue com update disponível. |
| S4 | ✅ **decidido 16/10** | **3 administradores**: `riocargas`, `vverner` e `vverner2` (agência). | Usuário confirmou que `vverner`/`vverner2` **permanecem** admins. Recomendação pendente: trocar as senhas dos 3 por prevenção. |
| S5 | ✅ **resolvido 16/10** | Arquivos enviados pelos formulários (~480 arquivos em `uploads/wpforms`, como currículos do "Trabalhe Conosco" e apresentações de fornecedores) abriam por **link direto, sem login** — dados pessoais (LGPD). | **Corrigido:** `.htaccess` `Require all denied` na pasta (gerado automaticamente pelo mu-plugin) + endpoint assinado HMAC `admin-ajax.php?action=rc_wpforms_file` que serve os arquivos; links de e-mail são reescritos para o endpoint (permanentes — e-mails antigos seguem abrindo). Validado em produção: direto 403, assinado 200, forjado 403, traversal 403. Ainda pendente: **definir prazo de retenção** dos currículos. |
| S6 | ✅ **resolvido 16/10** | A lista de usuários (logins) ficava pública em `/wp-json/wp/v2/users`, facilitando ataques de senha. | **Corrigido:** `mu-plugins/riocargas-components/security.php` remove as rotas `wp/v2/users*` para não-logados (anônimo → 404 `rest_no_route`); logados mantêm acesso (autocomplete de autor no editor). Demais rotas REST intactas. Validado em produção. |
| S7 | ✅ **resolvido 16/10** | Arquivos que sobraram na raiz: `wp-login.original`, `wp-login.zerado`, `.htaccess.bk`, `readme.html` e `error_log` (2,5 MB). | **Apagados** em produção e staging via File Manager — agora 404. Nota: no staging o `.htaccess` raiz foi junto por engano e as páginas internas caíram em 404 do servidor; recuperado via wp-admin → Links Permanentes → Salvar. |
| S8 | ✅ **resolvido 16/10** | Restos de plugins já removidos no banco: WooCommerce, CartFlows e WP Custom REST API. | **Limpos** via `scripts/s8-limpeza-banco.sql` (phpMyAdmin): 38 tabelas `wc_*`/`woocommerce_*`/`cartflows_ca_*`/`wcra_*`, ~70 posts órfãos (produtos/funis/pedidos) e ~167 options. `actionscheduler_*` preservada — em uso por Ultimate Member e LiteSpeed. Validado em produção. |

## 2. Desempenho (Lighthouse, cópia local)

| Página inicial | Celular | Computador |
|---|---|---|
| Desempenho | **59** | 73 |
| Maior elemento visível (LCP) | **35,5 s** | 5,5 s |
| Tamanho total da página | **15,8 MB** | 16,3 MB |
| Acessibilidade | 100 | 100 |
| Boas práticas | 79 | 78 |
| SEO | 85 | 85 |

> A cópia local não tem o cache do LiteSpeed. Em produção o tempo de resposta é menor, mas o peso das imagens é o mesmo.

| # | Prioridade | Problema | O que fazer |
|---|---|---|---|
| P1 | ✅ **resolvido 16/10** | Imagens enormes na home: `Footer.png` com **7,9 MB**, `Caminhao-RC.png` com 1,7 MB etc. Nenhuma das ~1.680 imagens era WebP. | **615 versões `.webp`** (q82) geradas ao lado dos originais em `uploads/` — ~108 MB economizados; `.htaccess` em `uploads/` serve o `.webp` só a navegadores com `Accept: image/webp` (originais intactos como fallback). Deploy via FTP em staging e produção; validado: `Caminhao-RC.png` 1,7 MB → 103 KB, 0 falhas. Pendente: gerar `.webp` das imagens enviadas **depois** desta data (rodar a conversão periodicamente). |
| P2 | 🟠 | As imagens fora da tela carregam todas de uma vez, sem *lazy load*, e não têm largura e altura definidas. | Ativar o *lazy load* e definir as dimensões. |
| P3 | 🟠 | **Plugins demais e repetidos**: 7 pacotes de add-ons para Elementor (Essential Addons, Master Addons Pro, PowerPack, Unlimited Elements, Ultimate Carousel, Header Footer Elementor, Templately), mais Astra Sites; 2 sistemas de formulário (Contact Form 7 + 3 extensões, e WPForms); 2 otimizadores (Autoptimize e LiteSpeed Cache). | Mapear quais widgets cada página usa e remover o que sobrar. Menos plugins deixa o site mais leve e reduz a superfície de ataque. |
| P4 | 🟢 | Fontes do Google carregadas por `http://` (gera aviso de conteúdo inseguro) e sem `font-display`. | Hospedar as fontes localmente. |
| P5 | 🟢 | Banco com 835 revisões antigas, 13 MB de estatísticas de visitas e uma base GeoIP de 60 MB em `uploads`. | Limitar as revisões e limpar as estatísticas antigas. |

## 3. SEO

| # | Prioridade | Problema | O que fazer |
|---|---|---|---|
| E1 | 🟠 | As páginas não têm **meta description** e o site não usa nenhum plugin de SEO. | Instalar Rank Math ou Yoast e escrever título e descrição para cada página. |
| E2 | 🟢 | Há links que os buscadores não conseguem seguir e 5 imagens sem texto alternativo (`alt`) na home. | Corrigir no Elementor. |
| E3 | 🟢 | Faltam dados estruturados de empresa (endereço, telefone, filiais). | Adicionar o schema `LocalBusiness`/`Organization`. |

## 4. Erros e estabilidade

- O `error_log` tem 2.218 erros fatais do incidente do PHP 7.4 (resolvido em 14/10 com a troca para o PHP 8.3) e alguns erros de arquivos faltando durante atualizações antigas. Hoje o núcleo está íntegro.
- No PHP 8.3, vários plugins antigos geram avisos (*deprecated*): Master Addons Pro, PowerPack, WPForms e Elementor Pro. Isso não derruba o site, mas indica código desatualizado.
- O `wp-config.php` tem `DISABLE_WP_CRON = true`. É preciso **confirmar se existe uma tarefa cron no cPanel** chamando o `wp-cron.php`; sem ela, e-mails agendados, limpezas e publicações programadas não rodam.

## 5. Sugestões de melhorias e novas funcionalidades (para discutir)

- **Rastreamento integrado**: hoje o menu leva para a página genérica `ssw.inf.br`. Dá para colocar um campo "digite seu CNPJ/nota fiscal" que já abre o rastreio certo.
- **Cotação mais completa**: formulário em etapas (origem, destino, peso, tipo de carga), com aviso por e-mail/WhatsApp para a equipe comercial.
- **Página de unidades/filiais** com mapa e contato de cada uma.
- **Política de privacidade e consentimento de cookies** de acordo com a LGPD.
- **Monitoramento**: aviso automático se o site cair, além de backup automático externo.

## Próximos passos propostos

1. **Segurança urgente** (S1, S4, S6, S7): pode ser feita direto em produção com backup, em cerca de 30 min.
2. **Staging + atualizações** (S2, S3): atualizar e testar no staging antes de publicar.
3. **Desempenho** (P1–P3): otimizar imagens e reduzir plugins.
4. **SEO** (E1–E3).
5. **Novas funcionalidades**, conforme a sua prioridade.
