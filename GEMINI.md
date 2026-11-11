# GEMINI.md

# Gauge R&R

Laravel REST API backend for the Gauge R&R Flutter application. It stores attribute inspection results (`good` / `defect`), computes Attribute Agreement Analysis (AAA), and serves the results to Quality Engineers, QC Inspectors and QC Managers.

## 1. Responsibilities And Source Documents

Laravel owns authentication, authorization, business rules, validation, data integrity, AAA calculations and persisted results.

Flutter owns presentation, user interaction, client-side usability validation, localization and API communication. Never move business logic into Flutter.

- `docs/api-contract.md` is the client contract: error codes, formats, identifiers, endpoints and open decisions.
- Update `docs/api-contract.md` in the same change as any route, request, API Resource, `ErrorCode` or enum change.
- The Flutter repository keeps a copy of the contract and has its own `AGENTS.md`.
- Never change the behavior of a released `/api/v1` endpoint. Breaking changes go to `/api/v2`.
- Do not invent or silently change response fields, error codes or enum values.
- If the contract and the implementation disagree, report it instead of changing behavior silently.

## 2. Stack

- Laravel 13, PHP 8.3+, Pest.
- Laravel Sanctum for token authentication with the Flutter client.
- PostgreSQL on Supabase for development and production, in two separate Supabase projects.
- SQLite in memory for automated tests.
- API only. No Blade, Livewire, Inertia or Vue.

## 3. Roles And Authorization

Roles are stored in `users.role` using `App\Enums\Role`. There are exactly three:

- `admin` (Quality Engineer and their team): manages users, master data (plants, part types, samples, answer keys, teams) and inspection sessions. Views all results.
- `manager` (QC Manager): read-only. Views and exports reports, never modifies data, even if a request is sent manually.
- `inspector` (QC Inspector): runs inspection sessions and views only their own results.

Rules:

- Enforce every permission through Laravel Policies. Never rely on Flutter UI restrictions.
- Every protected API route requires `auth:sanctum`. Only login and self-registration are public, and both are throttled.
- An admin cannot delete or deactivate their own account, or the last remaining admin.

## 4. Authentication And Registration

### Login

Login accepts `employee_code` and `password`.

- Return one generic `invalid_credentials` error for an unknown employee code or a wrong password.
- Run a dummy password hash check when the code is unknown, so response time does not reveal it.
- Reveal `pending` or `inactive` status only after the credentials are correct (`account_pending`, `account_inactive`). Issue no token in those cases.
- While `must_change_password` is true, allow only the endpoints listed in `docs/api-contract.md`. Clear the flag only after a successful password change.

### Self-Registration

An unauthenticated guest can register.

- Always create an `inspector` with status `pending`. The server assigns the role and never reads one from the request.
- A pending account cannot log in or join a session until an admin activates it and assigns a team (the team belongs to a plant).
- Return the same public response for a new request and an already-used `employee_code`. A duplicate creates no account and is logged without sensitive data.
- Do not use the `unique` validation rule on the registration request, because its error reveals the code exists. Enforce uniqueness in the database and handle unique-constraint violations safely.

### Admin-Created Accounts

- Created through an authenticated and authorized admin route. Assign the role explicitly in the action.
- Generate a temporary password server-side, return it once in the create response only, and set `must_change_password`.

### Password Recovery

- An admin may reset another account's password through `POST /users/{user}/reset-password`. Never their own: self-change still requires `current_password`.
- A reset generates a temporary password, returns it once, sets `must_change_password` and revokes every token of the target. Role, status and team stay unchanged.
- For the last active admin, who has no other admin to reset them, use the server-only break-glass command `php artisan grr:reset-password <employee_code>`. It requires server access, prints the temporary password once and never bypasses HTTP authorization because it is not an HTTP route.
- Never add a public "reset by employee_code" route. An employee code is not a secret.

### Account Status

`users.status` is `pending`, `active` or `inactive`. Also store `registered_via` (`self` or `admin`), who approved the account, when, and `must_change_password`. A pending or inactive account never receives a token or access to business operations.

## 5. Data Rules

### Users

- Unique `employee_code` (an internal employee code, not a national ID number).
- Every user has a `uuid`. The `uuid` is the public identifier. The bigint `id` stays internal and is never exposed for users.

### Master Data

Users, plants, part types, samples and teams use soft deletes.

- Plants are buildings (BCP, UCP, Plant A to G, Warehouse A to G). They are a master table that admins extend from the application. Never hard-code the list.
- Seeders provide only initial rows: plants, part types and the first admin. The initial admin password comes from `.env`, never from code.
- Inspection results and evaluations are never deleted. Do not delete master data that results refer to.

### Sample Answer Keys

- Answer keys can change after use. When a session is created, copy the samples and their keys into `session_samples`, so historical results never change.
- Record every key change in `sample_key_histories`.
- Never return answer keys to Inspectors.

### Inspection Sessions

- A session plans at most three trials. Trials are simulations performed outside the application: the Quality Engineer asks the inspector whether a sample is good or defect, and each answer is one trial. Trials are never stored, so there is no trial table, no `trial_number` and no `trial_count` in the application.
- The application records exactly one real-inspection verdict per inspector per sample, enforced by a unique constraint on `inspection_results (participant_id, session_sample_id)`.
- Store status with `SessionStatus`. A session starts as `draft`; starting it freezes the sample snapshot, the participants and the response time limit.
- The admin selects the samples and the participating inspectors when the session is created. The samples are copied with their answer keys and a `position` (the inspection order).
- Results are append-only once a session is finished. Never update or delete completed results. Corrections create a new record.
- Each inspector answers in `position` order, one sample at a time. Skipping ahead and returning to an earlier sample are both forbidden.
- Each participant has exactly one outcome per session sample: `answered` with a `good`/`defect` verdict, or `timed_out` without a verdict.
- The client runs the response stopwatch and sends the elapsed `response_time_ms`; the server validates and stores it. The limit is per session, defaults to `config('grr.inspection.response_time_limit_ms')` (90 seconds) and may only change while the session is a draft. Answers may be queued offline and synced later, so the server must not reject an answer only because it arrived after the limit in wall-clock time.
- A session is `completed` when every participant has an outcome for every session sample. Participants may start and finish on different days; each has their own `started_at` and `completed_at`.
- An admin can force-close an `in_progress` session when an inspector will never finish. Unfinished participants keep `completed_at` null, keep their stored answers and are not evaluated; never invent a verdict or a timeout for them.
- Repeating an identical answer payload for the same sample is idempotent and returns the stored row, so an offline queue can retry after the session completed. A different payload for a locked sample is rejected.

### Soft Deletes And Unique Columns

Use a partial unique index `WHERE deleted_at IS NULL` (raw SQL in the migration, valid on PostgreSQL and SQLite) so a deleted record's code can be reused. Never replace a database constraint with an application-level check.

## 6. Domain Rules

### Verdict

The verdict values in code, the database and the API are exactly `good` and `defect` (lowercase, `App\Enums\Verdict`). Never mix `OK`, `No OK`, `Good`, `Defect` and `G`/`N` in backend logic. Display labels and abbreviations are presentation-only.

### AAA Metrics

Metrics follow the AIAG MSA attribute study. Name them by what they measure:

- `miss_rate`: defect samples judged `good` divided by all defect samples.
- `false_alarm_rate`: good samples judged `defect` divided by all good samples.
- `effectiveness`: correct decisions divided by all decisions.

A `timed_out` sample has no verdict. Never count it as `good` or as `defect`; handle it explicitly in AAA and report it as missing data.

The thesis labels `miss_rate` as "P false" and `false_alarm_rate` as "P mis". These are display labels only, never identifiers, columns or API fields.

### Calculation, Thresholds And Judgement

- Put calculations in action classes under `app/Actions/Grr/`. Never in controllers, models or Flutter.
- Thresholds come from the AIAG MSA manual and live in `config/grr.php`. Never hard-code, invent or change a value; ask first.
- Guard against division by zero (no defect samples, no good samples). Return an explicit `null` for the affected metric instead of throwing.
- `Judgement` values and criteria are provisional. Do not implement unconfirmed criteria. `grr_evaluations.judgement` is currently NOT NULL, so ask before writing a migration that makes it nullable.

### Enums

Enums live in `app/Enums/` as string-backed enums: `Role`, `UserStatus`, `RegistrationSource`, `Verdict`, `Judgement`, `SessionStatus`, `ErrorCode`.

- Their values are stored in the database and in check constraints. Never rename a value without a migration and a contract update.
- Never store translated labels as enum values.

## 7. API Rules

- Versioned under `/api/v1`. JSON only.
- Return model data through API Resources, never raw Eloquent models.
- Never expose password hashes, internal bigint IDs where a UUID is public, or answer keys to Inspectors.
- Expected errors carry a stable machine-readable `code` from `App\Enums\ErrorCode`. Adding a code is backwards compatible; renaming one needs a coordinated client release. Never expose stack traces, SQL errors or secrets.
- Keep validation in Form Requests, authorization in Policies, and controllers thin.
- Backend values and error codes are language-independent. Never translate role, status, verdict or metric values, and never base business decisions on message text.
- Backend-generated documents, emails or notifications that need localization require an explicit language requirement for that feature.

## 8. Application Structure

- `app/Http/Controllers/`, `app/Http/Requests/`, `app/Http/Resources/`
- `app/Actions/` (domain logic, AAA under `app/Actions/Grr/`), `app/Enums/`, `app/Models/`, `app/Jobs/`, `app/Policies/`, `app/Console/Commands/`
- `routes/api.php` (versioned under `/api/v1`), `routes/console.php`, `bootstrap/app.php`
- `config/grr.php` (AAA thresholds)
- `database/migrations/`, `database/factories/`, `database/seeders/`
- `docs/api-contract.md`
- `tests/Feature/` (most tests), `tests/Unit/` (tests that do not boot the app)

## 9. Conventions

- Create classes with `php artisan make:*`.
- Validate through Form Requests and use `$request->validated()`, never `$request->all()`.
- Every model lists mass-assignable fields with `#[Fillable([...])]`. No `$guarded = []`, no `Model::unguard()`.
- Sensitive columns never appear in `#[Fillable]`; set them explicitly inside an authorized action: `users.role`, `users.status`, `users.registered_via`, `users.must_change_password`, `users.approved_by`, `users.approved_at`, `inspection_sessions.status`, `inspection_sessions.created_by`.
- `users` and `inspection_sessions` use `HasUuids` with `uniqueIds()` returning `['uuid']` and bind routes by `uuid`.
- Put casts in `casts()`, and use enum classes for enum columns.
- Eager load relationships used in lists with `with()`. No N+1 queries.
- Call `env()` only inside `config/`; application code reads `config('...')`.
- Controllers validate through Form Requests, call an action class and return an API Resource. Business logic lives in action classes.
- Slow or external work (report exports, bulk recalculation) goes in a job implementing `ShouldQueue`.

## 10. Migrations

- Never edit a migration that has run outside your machine. Write a new one.
- Every migration has a working `down()` method.
- Never run `migrate:fresh` or `migrate --force` unless explicitly requested.
- Never migrate the Supabase production project unless explicitly requested. Show the `--pretend` output first.
- Migrations target PostgreSQL but must also run on SQLite. PostgreSQL-only SQL (for example check constraints) goes behind `DB::getDriverName() === 'pgsql'`. Do not weaken production constraints to make SQLite tests pass.

## 11. Testing

Use Pest. Prefer feature tests that hit routes. Use factories, never hand-written inserts. Every bug fix comes with a test that failed before the fix.

Cover at least:

- Each role: allowed, forbidden (`403`) and unauthenticated (`401`). Manager cannot modify data. Inspector cannot read other users' results.
- Self-registration: always an Inspector with `pending` status, a supplied `role=admin` is ignored, identical responses for an existing and an unknown employee code, and the route is throttled.
- Login: identical error for an unknown code and a wrong password, throttled, no token for `pending` or `inactive`, and admin-created accounts must change their password.
- An admin cannot delete or deactivate their own account or the last remaining admin. A password reset is admin-initiated, refuses self-targeting, revokes tokens and forces a password change. The break-glass command rescues the last admin and refuses to run non-interactively without `--force`.
- AAA action with hand-computed examples: all correct, all wrong, zero defect samples, zero good samples.
- Exactly one real-inspection verdict per inspector per sample (trials are simulations outside the application and are not stored). Completed results cannot be modified or deleted. Historical results stay unchanged after an answer key update.
- A timed-out sample is stored without a verdict and never counted as good or defect. An inspector cannot answer out of order or return to an earlier sample.
- Error responses carry the documented `code`.

## 12. Database And Supabase

- Supabase is used only as the PostgreSQL database. Laravel owns authentication and authorization.
- Never use Supabase Auth, its client libraries or RLS as the authorization mechanism.
- Never connect automated tests to Supabase. Never use production credentials in local development.
- SSL is required (`sslmode=require`).
- The direct connection (`db.<project-ref>.supabase.co:5432`) is IPv6-only unless the project has the IPv4 add-on. On an IPv4-only network use the session pooler (`aws-<n>-<region>.pooler.supabase.com:5432`, user `postgres.<project-ref>`).
- Do not use the transaction pooler (port `6543`): it does not support prepared statements. Run migrations over the direct or session connection.

## 13. Commands

```bash
composer run setup                       # first setup
composer run dev                         # server, queue worker, Vite (http://localhost:8000)
php artisan test                         # all tests
php artisan test tests/Feature/InspectionTest.php
php artisan test --filter="submits an inspection"
php artisan test --stop-on-failure
./vendor/bin/pint --dirty                # format
./vendor/bin/pint --test                 # check only
php artisan route:list --path=api
php artisan help <command>
php artisan grr:reset-password EMP-1000 --force   # server-only break-glass recovery
php artisan make:migration add_session_id_to_inspection_results_table
php artisan migrate
php artisan migrate:status
php artisan migrate --pretend
```

Done means `./vendor/bin/pint --test` and `php artisan test` both pass, and `docs/api-contract.md` is updated when the API changed.

## 14. Secrets

- `.env` is never committed, printed or logged. Add new keys to `.env.example` with placeholder values only.
- Supabase database passwords and API keys are secrets.
- Test-only values belong in `phpunit.xml` or `.env.testing`.

## 15. Do Not

- Do not add a Composer or npm package without asking.
- Do not edit anything in `vendor/`.
- Do not run `php artisan config:cache` locally.
- Do not disable CSRF protection or mass assignment protection.
- Do not put production Supabase credentials in a local `.env`.
- Do not run tests, `migrate:fresh`, seeders or `db:wipe` against the production project.
- Do not change the database settings in `phpunit.xml`. Tests must keep using SQLite in memory.
- Do not calculate AAA metrics in Flutter, and do not hard-code AIAG thresholds anywhere.

## 16. Gotchas

- Queue workers keep old code in memory. Restart them after changing a job.
- After editing `phpunit.xml` environment values, run `php artisan config:clear`.
- Tests run on SQLite while development and production use PostgreSQL (strict typing, text comparison and constraint behavior differ). Verify database-specific behavior against PostgreSQL before relying on it.