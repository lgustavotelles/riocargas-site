# riocargas-site

Código do site [riocargas.com.br](https://www.riocargas.com.br) (WordPress + Elementor) e ambiente local para desenvolvimento.

## O que fica no repositório
- `wordpress/wp-content/themes/` e `wordpress/wp-content/plugins/` (temas e plugins)
- Configuração do ambiente local (Docker)

Ficam **fora** do Git: núcleo do WordPress, `uploads/`, dumps de banco, `wp-config.php` e senhas.

Lembrete: páginas montadas no Elementor ficam no **banco de dados**, não no código. Essas mudanças são feitas e validadas no staging.

## Ambiente local
Requisitos: Docker com Docker Compose.

```bash
cp .env.example .env
# 1. Extraia o backup do cPanel e copie o conteúdo de public_html para ./wordpress
#    (sem o wp-config.php de produção)
cp config/wp-config.local.php wordpress/wp-config.php
# 2. Remova do wordpress/.htaccess os blocos "Really Simple SSL Redirect"
#    e "cPanel-generated" (localmente não há HTTPS nem handler do cPanel)
# 3. Coloque o dump do banco em ./dumps/riocargas.sql
docker compose up -d
scripts/import-db.sh dumps/riocargas.sql
# 4. Desative localmente os plugins que dependem do servidor de produção
docker compose run --rm wpcli wp plugin deactivate really-simple-ssl litespeed-cache wp-maintenance-mode
```

Site local: http://localhost:8080

## Fluxo de trabalho
1. Cada mudança vai num branch e vira um PR.
2. Testar localmente e depois no staging.
3. Publicar em produção só depois de aprovado e com backup feito.

Relatório da análise inicial: [docs/AUDITORIA.md](docs/AUDITORIA.md)
