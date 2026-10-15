# BENIAL CONSULTING – platformă SSM (prototip)

PHP 8.3 + PDO (PostgreSQL pe Render, MySQL/MariaDB pe cPanel, SQLite local).

- Local: `php -S 127.0.0.1:8090 -t public` (folosește data.sqlite)
- Variabile: `DATABASE_URL` (postgres:// sau mysql://), opțional `DB_SCHEMA` (schemă separată în Postgres), `ADMIN_PASSWORD` (cont `admin`). Fără DATABASE_URL se folosește SQLite (`SQLITE_PATH`).
- Date demo: doar fictive (DEMO CONSTRUCT SRL); cod angajat demo `DEMO2026`.
