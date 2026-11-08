# API contract

Contract revision: 2 (draft)

This file is owned by the Laravel repository (`docs/api-contract.md`). The Flutter repository keeps a copy.

Rules:

- Update this file in the same change as any route, request, API Resource, `App\Enums\ErrorCode` or enum change, and raise the revision number.
- Flutter must not call an endpoint, read a field or branch on a value that is not described here. If something is missing, report it instead of guessing.
- If this file and the implementation disagree, report it as a defect. Do not change behavior silently to match either side.
- Items marked **(to confirm)** have not been verified against the implementation. Verify them with `php artisan route:list --path=api` and the API Resources, then remove the marker.
- The API is versioned under `/api/v1`. Released `/api/v1` behavior never changes; breaking changes go to `/api/v2`.

## Conventions

- Requests and responses are JSON. Send `Accept: application/json`.
- Authenticated routes need `Authorization: Bearer <Sanctum token>`.
- Enum values are lowercase strings and are the same in the database, the API and the client. They are never translated.
- Timestamps are ISO 8601 in UTC. `session_date` is `YYYY-MM-DD`.
- Decimal metrics (`effectiveness`, `repeatability`, `miss_rate`, `false_alarm_rate`, `between_agreement`, `all_vs_standard`) are strings with two decimals on a 0 to 100 scale, for example `"92.50"`. `miss_rate` and `false_alarm_rate` are `null` when their denominator is zero.
- Successful model responses use the Laravel API Resource default, a top-level `data` wrapper. **(to confirm)**
- Public identifiers: users and inspection sessions are identified by `uuid`. The bigint `id` of those records is never exposed. **(JSON field name to confirm, see Open items.)**

### Enum values

| Enum | Values |
| --- | --- |
| `role` | `admin`, `manager`, `inspector` |
| `status` (user) | `pending`, `active`, `inactive` |
| `registered_via` | `self`, `admin` |
| `verdict`, `answer_key` | `good`, `defect` |
| `status` (session) | `draft`, `in_progress`, `completed` |
| `judgement` | Provisional. Do not rely on it. |

A client must tolerate an enum value it does not know.

## Endpoints

| Method | Path | Access | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/v1/user` | Authenticated | Current user. Allowed while a password change is required. |
| `POST` | `/api/v1/logout` | Authenticated | Revoke the current token. Allowed while a password change is required. |
| `POST` | `/api/v1/password` | Authenticated | Change the password and clear the mandatory change. Allowed while a password change is required. |
| `POST` | `/api/v1/users` | Admin | Create an account. Returns `temporary_password` once. |
| `POST` | login route, path to confirm | Public, throttled | Log in with `employee_code` and `password`. |
| `POST` | registration route, path to confirm | Public, throttled | Guest self-registration. |

Request and response bodies are added here as each endpoint is implemented. Endpoints for master data, sessions, inspection answers and reports are not part of the contract yet.

## API error contract

Every expected API error is returned as JSON with a stable, machine-readable
`code` field next to the human-readable `message`:

```json
{
  "message": "The provided credentials are incorrect.",
  "code": "invalid_credentials"
}
```

Clients (Flutter) branch on `code`. The `message` text is not part of the
contract: it may change and must not be translated or matched on.

Codes are defined in `App\Enums\ErrorCode` and are part of the client contract.
Renaming a value requires a coordinated client release. Adding a code is
backwards compatible.

### Codes

| Code | HTTP | Meaning |
| --- | --- | --- |
| `unauthenticated` | 401 | Missing, invalid or revoked Sanctum token. |
| `invalid_credentials` | 401 | Unknown employee code or wrong password. Deliberately identical for both, so the existence of an employee code is never revealed. |
| `account_pending` | 403 | Credentials are correct but the account is still waiting for admin approval. No token is issued. |
| `account_inactive` | 403 | Credentials are correct but the account has been deactivated. No token is issued. |
| `password_change_required` | 403 | The account still uses a temporary password. Only `GET /api/v1/user`, `POST /api/v1/logout` and `POST /api/v1/password` are allowed until it is changed. |
| `forbidden` | 403 | The authenticated role may not perform this action (policy denial). |
| `not_found` | 404 | Unknown route or record, including UUID route model binding misses. |
| `method_not_allowed` | 405 | The route exists but not for this HTTP method. |
| `validation_failed` | 422 | Request validation failed. The response also carries the usual Laravel `errors` object keyed by field. |
| `too_many_requests` | 429 | Rate limit hit on a throttled route (login, registration). |
| `http_error` | varies | Fallback for other HTTP exceptions. Clients should treat it as an unexpected error. |

### Validation errors

Validation keeps the conventional Laravel shape so field errors stay
machine-readable, with `code` added at the top level:

```json
{
  "message": "The given data was invalid.",
  "code": "validation_failed",
  "errors": {
    "employee_code": ["The employee code field is required."]
  }
}
```

## Success responses

Successful responses carry no `code` field. One-off secrets are returned once and never stored in plain text:
`POST /api/v1/users` (admin-created account) generates the temporary password
server-side and returns it as `temporary_password` in the create response only.

## Open items

These decisions are not made yet. Until they are, neither side may assume an answer.

1. **Public identifier field name.** Proposed: `uuid` for users and inspection sessions, `id` (integer) for master data. This avoids two meanings of `id`.
2. **Master data identifiers.** Plants, teams, part types, samples and session samples have no UUID. Decide whether the API exposes the integer `id` or the `code`.
3. **Login success body.** Which user fields are returned, whether `must_change_password` is included, and whether a token is issued when it is true. The allowed-endpoint list under `password_change_required` implies yes.
4. **Registration response.** The HTTP status and body of the generic response, identical for a new and an already-used employee code.
5. **Business error codes.** Proposed additions (backwards compatible): `session_not_accepting_answers`, `trial_limit_reached`, `cannot_modify_own_account`, `last_admin`. Until added, these cases return `validation_failed` or `forbidden`.
6. **Pagination.** Format and parameters for list endpoints.
7. **Judgement.** Values and the thresholds that produce them, pending the confirmed AIAG criteria. `grr_evaluations.judgement` is NOT NULL today and probably needs to become nullable.