# RutkaCMS3 Rebuild Starter

This workspace is a practical rebuild starter for your legacy RutkaCMS setup:

- `backend/` -> PHP API that parses your legacy config format and exposes dynamic CRUD endpoints.
- `admin/` -> Next.js admin UI to manage records for each configured form.

Your public frontend can stay unchanged and consume data from DB/API as before.

## Why this matches your goal

- You keep PHP on the backend.
- You use React/Next only for admin data management.
- Your old config format (`[form: ...]`, `<field: ...>`, `key = value`) remains useful as metadata.

## Quick start

### Docker development (recommended)

From workspace root:

```bash
docker compose up --build
```

Services:

- Admin (Next.js dev): `http://localhost:3000/forms`
- PHP API: `http://localhost:8080/health`
- MySQL: `localhost:3306`

Default database credentials:

- Database: `rutkacms`
- User: `rutka`
- Password: `rutka123`
- Root password: `root`

The example schema and seed data are created from:

- `docker/mysql/init/001-schema.sql`

### Local (without Docker)

1. Backend

```bash
cd backend
copy .env.example .env
php -S localhost:8080 -t public
```

2. Admin

```bash
cd admin
copy .env.local.example .env.local
npm install
npm run dev
```

3. Open admin

- `http://localhost:3000/forms`

## Data model expectations

- Each legacy form maps to a DB table with same name.
- Example: `[form: pages]` -> table `pages`.
- Generic update/delete assumes `id` is primary key.

## Migration notes from legacy RutkaCMS

- Move DB credentials and secrets to `backend/.env`.
- Keep the legacy config only for schema/meta, not secrets.
- Replace hardcoded API keys from old fields (for example maps keys) with env variables.
- Keep upload handling as a dedicated endpoint/service, not as raw DB field writes.

## Next recommended step

Implement field-type specific handling in backend for:

- `upload_image` and `filename_upload`
- `richtextarea` sanitization policy
- `dropdown_list` relation lookup helpers
