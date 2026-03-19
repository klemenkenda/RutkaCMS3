# RutkaCMS Backend (PHP API)

This backend reads your legacy RutkaCMS config file and exposes a dynamic admin API.

## Endpoints

- `GET /health`
- `GET /api/forms`
- `GET /api/forms/{form}`
- `GET /api/forms/{form}/entries?limit=50&offset=0`
- `POST /api/forms/{form}/entries`
- `PUT /api/forms/{form}/entries/{id}`
- `DELETE /api/forms/{form}/entries/{id}`

## Setup

1. Copy `.env.example` to `.env`.
2. Update DB credentials in `.env`.
3. Put your full legacy config into `config/legacy-config.example.txt` or point `LEGACY_CONFIG_PATH` to another file.
4. Start PHP built-in server from `backend`:

```bash
php -S localhost:8080 -t public
```

## Notes

- Table name is expected to match form slug, for example `[form: pages]` -> table `pages`.
- This starter expects an `id` primary key for update/delete operations.
- SQL order from config is sanitized before use.
- Keep credentials only in `.env`, never in config files committed to git.
