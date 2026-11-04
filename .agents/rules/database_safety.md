# Strict Database & Production Safety Rules

- NEVER run `migrate:fresh`, `migrate:reset`, `db:wipe`, or table drops under any circumstance.
- `--env=testing` in Laravel CLI does NOT disconnect from the live pgsql database in `.env`.
- NEVER alter live production passwords or seed default credentials on production.
- Always ask explicit confirmation before any schema migration or build command.
