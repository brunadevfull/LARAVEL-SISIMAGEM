# SISIMAGEM AI Agent Instructions

This repository is the Laravel rewrite of the SISIMAGEM document management system for PAPEM / Marinha do Brasil. Read this file before generating or changing code.

## Project context

- This is a complete rewrite from zero, not an adoption of a third-party product.
- The complete technical specification is in `docs/sisimagem-plano-tecnico.md`.
- The project uses:
  - PHP 8.3+
  - Laravel
  - PostgreSQL 16+
  - Blade + Livewire + Alpine
  - Bootstrap 5
  - Local authentication only
  - No Docker in the development environment

## Mandatory conventions

- Database tables and columns must be in Portuguese. Application code (classes, methods, variables) must be in English.
  - Example: `class Documento` with `protected $table = 'documentos';`.
- ETL scripts must live under `app/Console/Commands/Etl/` and must never use the app models.
- Never concatenate user input into SQL. Always use Eloquent or Query Builder with bindings.
- Never trust client-provided file paths. All file operations must resolve the server-side path from the internal id, never from a request parameter.
- All authorization must be enforced server-side with Laravel policies/gates, not only hidden UI elements.
- Do not invent new tables or features outside the current scope without explicit approval.
- Do not implement email-based password reset; reset is manual through the administrator.
- Do not use the literal string `"marinha"` as a default password anywhere.
- The `titulo` field may be corrupted in legacy data; treat it cautiously and check for `titulo_legado_numerico` before assuming it is clean.

## User roles

The supported profiles are `adm`, `papem40`, `sasm`, and `padrao`, mapped from the legacy groups. The access rules are defined in `docs/sisimagem-plano-tecnico.md`.

## Schema

The schema is centered on these tables:

- `users` (extended Laravel users table)
- `setores`
- `locais_arquivo`
- `tipos_documento`
- `arquivos`
- `documentos`
- `etl_rejeitos`

Refer to `docs/sisimagem-ddl.sql` and `docs/sisimagem-diagrama-er.md` before adding or changing persistence logic.

## Development order

Work in complete vertical slices, not by layer. The current active slice is:

1. Login → search → result → open document
2. Add document with upload
3. ETL of metadata (Oracle → PostgreSQL)
4. ETL of files
5. Administration (users, types, sectors)

Do not advance to ETL or administration tasks without explicit instruction.

## Working rules for this agent

- Before changing code, read the relevant project documents and current files.
- Prefer the smallest correct change that matches the requirements.
- Keep code aligned with Laravel conventions and existing app structure.
- Validate with the narrowest relevant tests or artisan checks available.
- Avoid broad refactors unrelated to the requested task.
- If a requirement is ambiguous, ask before making assumptions.

## Required files to consult

- `docs/sisimagem-plano-tecnico.md`
- `docs/sisimagem-ddl.sql`
- `docs/sisimagem-diagrama-er.md`
- `CLAUDE.md`

## Do not do

- Do not create an audit table without explicit request.
- Do not add a separate API or SPA if the project requires Blade + Livewire + Alpine.
- Do not accept ad-hoc file paths from the client.
- Do not rely on hidden UI-only authorization.
- Do not mix legacy naming patterns into the new Laravel model layer.
