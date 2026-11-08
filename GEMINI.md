# Gauge R&R

Laravel REST API backend for the Gauge R&R Android app: it stores attribute inspection results (Good/Defect), computes Attribute Agreement Analysis (AAA), and serves the results to Quality Engineers, QC Inspectors and QC Managers.

Stack: Laravel 13, PHP 8.3+, Pest, Laravel Sanctum (token auth for the Flutter client). PostgreSQL on Supabase for both local development and production, in two separate Supabase projects (dev and prod). SQLite in memory in tests. API only: no Blade, Livewire, Inertia or Vue.

## Roles
Stored in `users.role` as an enum (`App\Enums\Role`). There are exactly three:
- `admin` (Quality Engineer and their team): manages users, master data (plants, part types, samples, answer keys, teams) and inspection sessions; views all results
- `manager` (QC Manager): read-only; views and exports reports, cannot change any data
- `inspector` (QC Inspector): runs an inspection session and views their own results
- Login uses `employee_code` and password. Enforce roles with policies, never in the Flutter app. Every route requires `auth:sanctum` except login and register, which are throttled.
- An admin cannot delete or deactivate their own account, or the last remaining admin.

## Data rules
- Registration: an unauthenticated guest can self-register, or an admin can create the account. Self-registration always creates an `inspector` with status `pending`. The role is set on the server and is never read from the request. A pending account cannot log in or join a session until an admin activates it and assigns a team and plant.
- Auth responses must not reveal whether an `employee_code` exists. Register returns the same "received, pending approval" response whether or not the code is already taken; a duplicate creates nothing and is logged. Do not use the `unique` validation rule on the register request, because its error message reveals the code exists.
- Login returns one generic error for a wrong code or a wrong password. The "pending" or "inactive" message is shown only after the credentials are correct. Run a dummy password hash check when the code is unknown, so response time does not reveal it either.
- `users.status` is an enum: `pending`, `active`, `inactive`. Also store `registered_via` (`self` or `admin`), and who approved the account and when.
- An account created by an admin gets a temporary password and must change it on first login (`must_change_password`).
- Users have a unique `employee_code` (internal employee code, not a national ID number) and a `uuid`. The `uuid` is the public id in the API; the bigint primary key stays internal.
- Master data (users, plants, part types, samples, teams) uses soft deletes. Inspection results and evaluations are never deleted.
- Plants are buildings (BCP, UCP, Plant A to G, Warehouse A to G). They are a master table that admins can extend from the app. Never hard-code the list; the seeder only provides the initial rows.
- Sample answer keys can change after use. When a session is created, copy the samples and their keys into `session_samples`, so old results never change. Record key changes in `sample_key_histories`.
- A session has at most 3 trials. Enforce it in the form request and with a check constraint on PostgreSQL.
- Soft delete and unique columns: use a partial unique index `WHERE deleted_at IS NULL` (raw SQL in the migration; PostgreSQL and SQLite both support it) so a deleted record's code can be reused.

## Commands
- First setup: `composer run setup` (install, .env, app key, migrate, npm build)
- Dev: `composer run dev` (server, queue worker and Vite; http://localhost:8000)
- Tests: `php artisan test`. One file: `php artisan test tests/Feature/InspectionTest.php`
- Filter by name: `php artisan test --filter="submits an inspection"`
- Stop on first failure: `php artisan test --stop-on-failure`
- Format: `./vendor/bin/pint --dirty`. Check only: `./vendor/bin/pint --test`
- Routes: `php artisan route:list --path=api`. Command help: `php artisan help <command>`
- New migration: `php artisan make:migration add_trial_number_to_inspection_results_table`
- Migrate: `php artisan migrate`. Status: `php artisan migrate:status`
- Preview migration SQL: `php artisan migrate --pretend`
- Done means: `./vendor/bin/pint --test` and `php artisan test` both pass

## Project structure
- `app/Http/Controllers/`: controllers. `app/Http/Requests/`: form requests. `app/Http/Resources/`: API resources
- `app/Actions/`: domain logic, including the AAA calculation (`app/Actions/Grr/`)
- `app/Enums/`: string-backed enums for roles, statuses, verdicts and judgements
- `app/Models/`: Eloquent models. `app/Jobs/`: queued jobs. `app/Policies/`: authorization
- `routes/api.php`: the API (versioned under `/api/v1`). `routes/console.php`: closure commands and the schedule
- `bootstrap/app.php`: middleware, exception handling and route registration
- `config/grr.php`: AAA thresholds and verdict settings
- `database/migrations/`, `database/factories/`, `database/seeders/`
- `tests/Feature/`: most tests. `tests/Unit/`: tests that do not boot the app

## Domain rules
- Verdict vocabulary: `Good` / `Defect` in code and the database. The app may label them G / N; do not mix OK, No OK, Good, Defect and G/N inside the backend.
- Metrics follow the AIAG MSA attribute study. Name them by what they measure: `miss_rate` = defect samples judged Good ÷ all defect samples; `false_alarm_rate` = good samples judged Defect ÷ all good samples; `effectiveness` = correct decisions ÷ all decisions. The thesis calls `miss_rate` "P false" and `false_alarm_rate` "P mis"; those are display labels only, never identifiers.
- Thresholds come from the AIAG MSA manual. They live in `config/grr.php`, never hard-coded. Do not invent or change a value; ask me.
- Put the calculation in an action class under `app/Actions/Grr/`. Never in a controller, a model or the Flutter app.
- Guard against division by zero (no defect samples, no good samples) and return an explicit result, not an exception.
- Enums live in `app/Enums/` as string-backed enums: `Role`, `UserStatus`, `RegistrationSource`, `Verdict` (good/defect, used for `answer_key` and `inspection_results.verdict`), `Judgement` (final AAA result; provisional names until the AIAG criteria are confirmed) and `SessionStatus`. Their values are stored in the database and in check constraints, so never rename a value without a migration.
- Stored results are append-only after an inspection session is finished. Corrections create a new record.
- Real employee names, real inspection data and company documents never go into seeders, factories, tests, fixtures or commits. Use factories and fake data.

## Conventions
- Create classes with `php artisan make:*` so names and folders line up
- Validate in form requests (`php artisan make:request StoreInspectionRequest`)
- Save with `$request->validated()`, never `$request->all()`
- Every model lists its mass-assignable fields with `#[Fillable([...])]`; no `$guarded = []`, no `Model::unguard()`
- Return JSON through API resources (`php artisan make:resource`), never raw models. Never expose password hashes or answer keys to Inspectors.
- Call `env()` only inside `config/` files; app code reads `config('...')`
- Eager load relationships used in lists with `with()`; no N+1 queries
- Slow or external work (report export, bulk recalculation) goes in a job that implements `ShouldQueue`
- Controllers stay thin: validate, call an action class, return a resource

## Migrations
- Never edit a migration that has run anywhere but your machine; write a new one
- Never run `migrate:fresh` or `migrate --force` unless I ask
- Never run migrations against the Supabase production database unless I ask. Show the `--pretend` output first.
- Every migration has a working `down()` method
- Migrations target PostgreSQL but must also run on SQLite, the test database. PostgreSQL-only SQL (for example check constraints) goes behind a `DB::getDriverName() === 'pgsql'` check.

## Testing
- Pest. New feature test: `php artisan make:test InspectionTest --pest`; unit: add `--unit`
- Prefer feature tests that hit routes over unit tests of internals
- Test each role: allowed, forbidden (403) and unauthenticated (401)
- Test registration: sending `role=admin` is ignored, a pending account cannot log in, and the register route is throttled
- Test that register and login give identical responses for an existing and an unknown `employee_code`
- Test the AAA action with hand-computed examples and edge cases: all correct, all wrong, zero defect samples, zero good samples
- Use factories for test data; no hand-written inserts
- Every bug fix comes with a test that failed before the fix

## Secrets
- `.env` is never committed, printed or logged. Add new keys to `.env.example` with placeholder values only.
- The Supabase database password and any Supabase API keys are secrets
- Test-only values go in `phpunit.xml` or `.env.testing`

## Do not
- Do not add a Composer or npm package without asking
- Do not edit anything in `vendor/`
- Do not run `php artisan config:cache` locally
- Do not disable CSRF protection or mass assignment protection
- Do not put production Supabase credentials in a local `.env`. Never run tests, `migrate:fresh`, seeders or `db:wipe` against the production project.
- Do not change the database settings in `phpunit.xml`: tests must keep using SQLite in memory so they never touch Supabase.
- Do not use Supabase Auth, its client libraries or row level security from this app. Laravel owns authentication and authorization; Supabase is only the PostgreSQL database.

## Gotchas
- Queue workers keep old code in memory. Restart them after changing a job
- After editing `phpunit.xml` env values, run `php artisan config:clear`
- Supabase connection: the direct connection (`db.<project-ref>.supabase.co:5432`) is IPv6 only unless the project has the IPv4 add-on. On an IPv4-only network use the session pooler (`aws-<n>-<region>.pooler.supabase.com:5432`, user `postgres.<project-ref>`). Do not use the transaction pooler (port 6543): it does not support prepared statements. Run migrations over the direct or session connection.
- Supabase requires SSL on the connection (`sslmode=require`)
- Tests run on SQLite but production is PostgreSQL (strict types, case-sensitive text comparison, check constraints). Do not assume a query that passes on SQLite behaves the same on PostgreSQL.