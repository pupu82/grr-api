# API contract

Contract revision: 4

This file is owned by the Laravel repository. The Flutter repository keeps a copy and must treat it as the only source of truth for the HTTP API.

Rules:

- Update this file in the same change as any route, request, API Resource, `App\Enums\ErrorCode` or enum change, and raise the revision number.
- Flutter must not call an endpoint, read a field or branch on a value that is not described here. If something is missing, report it instead of guessing.
- If this file and the implementation disagree, report it as a defect. Do not change behavior silently to match either side.
- The API is versioned under `/api/v1`. Released `/api/v1` behavior never changes; breaking changes go to `/api/v2`.
- Items marked **(planned)** do not exist yet. They describe an agreed direction and must not be called.
- Items marked **(open)** are undecided. Neither side may assume an answer.

## 1. Conventions

- Requests and responses are JSON. Send `Accept: application/json` and, when there is a body, `Content-Type: application/json`.
- Authenticated routes need `Authorization: Bearer <Sanctum token>`.
- Enum values are lowercase strings and are identical in the database, the API and the client. Never translate them.
- Timestamps are ISO 8601 with offset, in UTC, for example `2026-11-08T07:30:00+00:00`. `session_date` is `YYYY-MM-DD` **(planned)**.
- Pagination is Laravel's default: `{"data": [...], "links": {...}, "meta": {...}}`. Use `?page=N`. The page size is fixed by the server: 20 for `/users`, 50 for every other list. Clients must not request a different page size.
- List order is fixed by the server: plants by `code`, part types by `name`, teams by `name`, samples by `code`, users by `name`, answer key history newest first.
- Unknown response fields and unknown enum values must be tolerated by the client.

## 2. Identifiers

- Users are identified by `uuid`. The JSON field is named `id` and contains the uuid string. The bigint primary key is never exposed. Route parameter `{user}` is the uuid.
- Inspection sessions are identified by `uuid` and bind routes by `{session}` **(planned)**.
- Master data (plants, teams, part types, samples) and their nested references use the integer `id`, both in JSON and in route paths.

## 3. Response shapes

| Case | Shape |
| --- | --- |
| Single model | `{"data": {...}}` |
| Model list | `{"data": [...], "links": {...}, "meta": {...}}` |
| Action with a message | `{"message": "..."}` |
| `DELETE` | `204`, empty body |
| Admin account created | `{"data": {...}, "temporary_password": "..."}` |
| Login | `{"token": "...", "token_type": "Bearer", "user": {...}}` — `user` is **not** wrapped in `data` |

Errors never use `data`. See section 8.

## 4. Enum values

| Enum | Field | Values |
| --- | --- | --- |
| `role` | `user.role` | `admin`, `manager`, `inspector` |
| `status` (user) | `user.status` | `pending`, `active`, `inactive` |
| `registered_via` | `user.registered_via` | `self`, `admin` |
| `verdict` | `answer_key`, `verdict` | `good`, `defect` |
| `status` (session) | `session.status` **(planned)** | `draft`, `in_progress`, `completed` |
| `judgement` | evaluation **(planned)** | Provisional. Do not rely on it. |

## 5. Authentication and account flows

### Login

`POST /api/v1/login` — public, throttled to 5 attempts per minute per IP.

Request:

```json
{ "employee_code": "EMP-1000", "password": "secret" }
```

| Outcome | HTTP | `code` |
| --- | --- | --- |
| Valid `active` credentials | `200` with `token`, `token_type`, `user` | — |
| Unknown `employee_code` **or** wrong password | `401` | `invalid_credentials` |
| Correct credentials, account `pending` | `403`, no token | `account_pending` |
| Correct credentials, account `inactive` | `403`, no token | `account_inactive` |

The two credential failures are byte-identical, so the client must not try to communicate whether the employee code exists.

### Mandatory password change

An admin-created account logs in normally and receives a token, but `user.must_change_password` is `true`.

While it is `true`, only these endpoints work:

- `GET /api/v1/user`
- `POST /api/v1/logout`
- `POST /api/v1/password`

Everything else returns `403` with `code: password_change_required`.

`POST /api/v1/password`:

```json
{
  "current_password": "temporary password",
  "password": "new password",
  "password_confirmation": "new password"
}
```

Rules: new password minimum 8 characters, confirmed, and different from the current one. A wrong current password or a failed rule returns `422` `validation_failed` keyed on `current_password` or `password`. Success returns `200` `{"message": "Password changed."}` and clears the flag.

### Self-registration

`POST /api/v1/register` — public, throttled to 5 attempts per minute per IP.

```json
{
  "employee_code": "EMP-1000",
  "name": "Siti Inspector",
  "password": "secret-password",
  "password_confirmation": "secret-password"
}
```

- Always `202` with `{"message": "Registration received, pending approval."}`.
- The response is identical for a new and an already-used `employee_code`. The client must not claim that an account exists or was created.
- `role` is ignored if sent. The server always creates an `inspector` with status `pending`.
- The account cannot log in until an admin approves it and assigns a team.

### Admin-created accounts

`POST /api/v1/users` returns the generated `temporary_password` once, in that response only. The account is created `active`, `registered_via: admin`, `must_change_password: true`. Never store or log the temporary password client-side beyond showing it to the admin.

## 6. Endpoints

`Access` uses these labels: **Public**, **Any active user**, **Admin**, **Admin or self**, **Admin and Manager (read)**.

### Authentication

| Method | Path | Access | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/v1/login` | Public | Log in. |
| `POST` | `/api/v1/register` | Public | Guest self-registration. |
| `GET` | `/api/v1/user` | Any active user | Current user. |
| `POST` | `/api/v1/logout` | Any active user | Revoke the current token. |
| `POST` | `/api/v1/password` | Any active user | Change password. |

### Users

| Method | Path | Access | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/v1/users` | Admin | List users. Filters: `?status=`, `?role=`. |
| `POST` | `/api/v1/users` | Admin | Create an account. Returns `temporary_password` once. |
| `GET` | `/api/v1/users/{user}` | Admin or self | Show one user. |
| `POST` | `/api/v1/users/{user}/approve` | Admin | Activate a `pending` account and assign a team. |
| `POST` | `/api/v1/users/{user}/deactivate` | Admin | Set the account to `inactive` and revoke its tokens. |
| `DELETE` | `/api/v1/users/{user}` | Admin | Soft delete the account. |

An admin cannot approve, deactivate or delete their own account, and cannot deactivate or delete the last active admin (`403` `forbidden`). Approving an account that is not `pending` returns `422` `validation_failed` keyed on `user`.

### Master data

Standard REST routes for each resource: `GET` list, `POST` create, `GET` one, `PUT` or `PATCH` update, `DELETE` soft delete.

| Resource | Path | Extra | List filters |
| --- | --- | --- | --- |
| Plants | `/api/v1/plants` | | `?search=` on code and name |
| Part types | `/api/v1/part-types` | | `?search=` on name |
| Teams | `/api/v1/teams` | | `?plant_id=`, `?search=` on name |
| Samples | `/api/v1/samples` | `GET .../{sample}/key-histories` | `?part_type_id=`, `?search=` on code |

Access: read is Admin and Manager, write is Admin. Inspectors have no access to master data at all (`403` `forbidden`); they receive what they need through session endpoints **(planned)**.

`answer_key` is only present for `admin` and `manager`. For an inspector the field is omitted entirely, not `null`.

### Request bodies

| Endpoint | Body |
| --- | --- |
| `POST /users` | `employee_code` (required, max 30, unique among non-deleted), `name` (required, max 100), `role` (required, `admin`/`manager`/`inspector`), `team_id` (optional, existing non-deleted team) |
| `POST /users/{user}/approve` | `team_id` (required, existing non-deleted team) |
| `POST /plants`, `PATCH /plants/{plant}` | `code` (required on create, max 30, unique among non-deleted), `name` (required on create, max 100), `is_active` (optional boolean, defaults to `true`) |
| `POST /part-types`, `PATCH /part-types/{part_type}` | `name` (required on create, max 50) |
| `POST /teams`, `PATCH /teams/{team}` | `plant_id` (required on create, existing non-deleted plant), `name` (required on create, max 100) |
| `POST /samples`, `PATCH /samples/{sample}` | `code` (required on create, max 30, unique among non-deleted), `part_type_id` (required on create, existing non-deleted part type), `answer_key` (required on create, `good`/`defect`) |

`PATCH` accepts a subset. Sending `null` for a required field is a validation error. A soft-deleted code can be reused, hence "unique among non-deleted".

Changing `answer_key` through `PATCH /samples/{sample}` records an entry in the answer key history in the same transaction. Changing any other field does not.

### Response fields

User:

```json
{
  "id": "0f1c7c1e-...",
  "employee_code": "EMP-1000",
  "name": "Siti Inspector",
  "role": "inspector",
  "status": "active",
  "registered_via": "self",
  "must_change_password": false,
  "approved_at": "2026-11-08T07:30:00+00:00",
  "team": { "id": 3, "name": "Line 1" },
  "created_at": "2026-11-08T07:30:00+00:00"
}
```

`team` is `null` when no team is assigned. `approved_at` is `null` until approved.

Plant: `id`, `code`, `name`, `is_active`, `created_at`.

Part type: `id`, `name`, `samples_count`, `created_at`. `samples_count` is only present when the server counted samples (list and show).

Team: `id`, `name`, `plant_id`, `plant` (a Plant object), `created_at`.

Sample: `id`, `code`, `part_type_id`, `part_type` (a Part type object), `answer_key` (admin/manager only), `created_at`.

Answer key history entry: `id`, `sample_id`, `old_key`, `new_key`, `changed_by` (actor uuid), `changed_by_name`, `changed_at`.

## 7. Access matrix

| Area | `admin` | `manager` | `inspector` |
| --- | --- | --- | --- |
| Own profile, logout, password change | yes | yes | yes |
| User management | yes | no | no |
| Master data read | yes | yes | no |
| Master data write | yes | no | no |
| Sessions and results **(planned)** | all | read-only | own |

`403` from a policy is `code: forbidden`. Inspectors may read only their own user record; another user's uuid returns `403`.

## 8. Errors

Every expected error is JSON with a stable `code` next to a human-readable `message`:

```json
{ "message": "The provided credentials are incorrect.", "code": "invalid_credentials" }
```

Branch on `code`. `message` may change, is not translated by the client, and is never used to make business decisions.

| Code | HTTP | Meaning |
| --- | --- | --- |
| `unauthenticated` | 401 | Missing, invalid or revoked token. |
| `invalid_credentials` | 401 | Unknown employee code or wrong password. |
| `account_pending` | 403 | Credentials correct, account waiting for approval. No token. |
| `account_inactive` | 403 | Credentials correct, account deactivated. No token. |
| `password_change_required` | 403 | Temporary password still in use. Only the three endpoints in section 5 work. |
| `forbidden` | 403 | The role may not perform this action. |
| `not_found` | 404 | Unknown route or record, including uuid binding misses. |
| `method_not_allowed` | 405 | Route exists, wrong HTTP method. |
| `validation_failed` | 422 | Validation failed; `errors` maps field names to message arrays. |
| `too_many_requests` | 429 | Rate limit hit; `Retry-After` header is set. |
| `http_error` | varies | Fallback for other HTTP errors. Treat as unexpected. |

Validation errors keep Laravel's `errors` object:

```json
{
  "message": "The given data was invalid.",
  "code": "validation_failed",
  "errors": { "employee_code": ["The employee code field is required."] }
}
```

Codes are defined in `App\Enums\ErrorCode`. Adding a code is backwards compatible; renaming one needs a coordinated client release.

## 9. Not implemented yet

Inspection sessions, session samples, participants, trials, inspections, evaluations, AAA metrics and reports. No endpoint exists and no field is final. Do not build against guesses.

## 10. Open items

These decisions are not made. Until they are, neither side may assume an answer.

1. **Judgement.** Values and thresholds are provisional, pending confirmed AIAG MSA criteria. `grr_evaluations.judgement` is `NOT NULL` today and probably must become nullable. Do not implement or display a judgement.
2. **Session and result error codes.** Proposed additions, backwards compatible: `session_not_accepting_answers`, `trial_limit_reached`, `cannot_modify_own_account`, `last_admin`, `resource_in_use`. Until added, those cases return `validation_failed` or `forbidden`.
3. **Deleting master data that sessions refer to.** Either refuse with `resource_in_use` or allow the soft delete and keep it visible in historical results.
4. **User management gaps.** No route yet to update a user's name or team, reactivate an `inactive` account, or reset a forgotten password. Decide which the client needs.
5. **Session sample identifiers.** Session samples need a public identifier once the session endpoints exist.
6. **Evaluation serialization.** How decimal metrics are encoded in JSON (string with two decimals vs number) is unverified because the endpoints do not exist yet.
