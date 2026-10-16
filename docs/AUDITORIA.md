# Auditoria inicial: riocargas.com.br (outubro/2026)

Análise feita numa **cópia local** do site, montada a partir do backup do cPanel de 15/10/2026 (arquivos + banco `riocarga_wp401`). Nenhum teste foi feito contra o site em produção.

**Resumo:** WordPress 7.1.3 com o núcleo íntegro (arquivos batem com os checksums oficiais), tema Astra, Elementor + Elementor Pro e 29 plugins ativos. Os pontos mais importantes são: um **usuário suspeito** no banco, **plugins pagos muito desatualizados**, **páginas pesadas demais** (16 MB na home) e **arquivos enviados por formulários acessíveis por link direto**.

Legenda de prioridade: 🔴 alta · 🟠 média · 🟢 baixa

---

## 1. Segurança

| # | Prioridade | Problema | O que fazer |
|---|---|---|---|
| S1 | 🔴 | Existe um usuário `wp_rest_api` com e-mail `do-not-remove@wordpress.c0m` (com **zero** no lugar do "o"), criado em 10/08/2021. Esse padrão (nome e e-mail falsos, imitando o WordPress) é típico de **malware**. Hoje ele está sem nenhuma permissão. | Excluir o usuário, trocar as senhas de todos os administradores e do banco e gerar novas chaves (salts) no `wp-config.php`. Rodar um scanner de malware no servidor (Imunify360/Wordfence). Na cópia local não achei código malicioso nos padrões verificados. |
| S2 | 🔴 | Plugins pagos parados em versões antigas: **Elementor Pro 3.7.1** (2022), rodando com Elementor 4.3.5. Já gera avisos de incompatibilidade no log (`site-logo.php`). Também **WPForms 1.7.6** (2022) e outros pacotes pagos (Master Addons Pro, PowerPack, Unlimited Elements). Provavelmente as licenças venceram. | Renovar as licenças e atualizar no staging, ou substituir/remover os plugins que não forem usados (ver P3). |
| S3 | 🟠 | Tema **Astra 3.8.2** (a versão atual é 4.14.0). Outros com atualização pendente: Contact Form CFDB7 (1.2.6.4 → 1.4.2) e WP Statistics. | Atualizar no staging e testar. |
| S4 | 🟠 | **3 administradores**: `riocargas`, `vverner` e `vverner2` (estes dois parecem ser da agência que fez o site, em 2020). | Confirmar se a agência ainda precisa de acesso; se não precisar, remover ou rebaixar. |
| S5 | ✅ **resolvido 16/10** | Arquivos enviados pelos formulários (~480 arquivos em `uploads/wpforms`, como currículos do "Trabalhe Conosco" e apresentações de fornecedores) abriam por **link direto, sem login** — dados pessoais (LGPD). | **Corrigido:** `.htaccess` `Require all denied` na pasta (gerado automaticamente pelo mu-plugin) + endpoint assinado HMAC `admin-ajax.php?action=rc_wpforms_file` que serve os arquivos; links de e-mail são reescritos para o endpoint (permanentes — e-mails antigos seguem abrindo). Validado em produção: direto 403, assinado 200, forjado 403, traversal 403. Ainda pendente: **definir prazo de retenção** dos currículos. |
| S6 | ✅ **resolvido 16/10** | A lista de usuários (logins) ficava pública em `/wp-json/wp/v2/users`, facilitando ataques de senha. | **Corrigido:** `mu-plugins/riocargas-components/security.php` remove as rotas `wp/v2/users*` para não-logados (anônimo → 404 `rest_no_route`); logados mantêm acesso (autocomplete de autor no editor). Demais rotas REST intactas. Validado em produção. |
| S7 | 🟢 | Arquivos que sobraram na raiz: `wp-login.original`, `wp-login.zerado`, `.htaccess.bk`, `readme.html` e `error_log` (2,5 MB). | Apagar. |
| S8 | 🟢 | Restos de plugins já removidos no banco: WooCommerce, CartFlows e WP Custom REST API (com um endpoint de teste `wcra_test` e uma chave salva). | Limpar as tabelas e opções órfãs (depois do backup). |

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
| P1 | 🔴 | Imagens enormes na home: `Footer.png` com **7,9 MB**, `Caminhao-RC.png` com 1,7 MB, `Numeros.png` com 1,4 MB e as imagens de "Sobre nós", com 1,3 MB e 1,0 MB. Das 1.710 imagens, **nenhuma** está em WebP. | Converter para WebP/AVIF e redimensionar. O ganho estimado é de cerca de 11 MB só na home. |
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
