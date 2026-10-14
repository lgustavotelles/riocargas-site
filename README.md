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
# 1. Copie os arquivos do site (public_html) para ./wordpress
# 2. Coloque o dump do banco em ./dumps/riocargas.sql
docker compose up -d
scripts/import-db.sh dumps/riocargas.sql
```

Site local: http://localhost:8080

## Fluxo de trabalho
1. Cada mudança vai num branch e vira um PR.
2. Testar localmente e depois no staging.
3. Publicar em produção só depois de aprovado e com backup feito.
