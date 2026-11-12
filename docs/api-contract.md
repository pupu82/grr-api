# API contract

Contract revision: 14

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
- Timestamps are ISO 8601 with offset, in UTC, for example `2026-11-08T07:30:00+00:00`. `session_date` is `YYYY-MM-DD`.
- Pagination is Laravel's default: `{"data": [...], "links": {...}, "meta": {...}}`. Use `?page=N`. The page size is fixed by the server: 20 for `/users`, 50 for every other list. Clients must not request a different page size.
- List order is fixed by the server: plants by `code`, part types by `name`, teams by `name`, samples by `code`, users by `name`, sessions by newest `session_date` first, answer key history newest first.
- Unknown response fields and unknown enum values must be tolerated by the client.

### Practice trials

Practice trials are performed **outside the application**: the Quality Engineer asks the inspector "is this sample good or defect?", and each answer is one practice trial (at most three). The application is used only for the real inspection that follows.

Therefore practice trials are never stored and never sent. There is no trial resource, no trial endpoint and no trial field. The application records exactly **one real-inspection verdict per inspector per sample**.

## 2. Identifiers

- Users are identified by `uuid`. The JSON field is named `id` and contains the uuid string. The bigint primary key is never exposed. Route parameter `{user}` is the uuid.
- Inspection sessions are identified by `uuid` and bind routes by `{session}`.
- Master data (plants, teams, part types, samples) and their nested references use the integer `id`, both in JSON and in route paths.

## 3. Response shapes

| Case | Shape |
| --- | --- |
| Single model | `{"data": {...}}` |
| Model list | `{"data": [...], "links": {...}, "meta": {...}}` |
| Action with a message | `{"message": "..."}` |
| `DELETE` | `204`, empty body |
| Admin account created | `{"data": {...}, "temporary_password": "..."}` |
| Password reset by an admin | `{"data": {...}, "temporary_password": "..."}` |
| Login | `{"token": "...", "token_type": "Bearer", "user": {...}}` — `user` is **not** wrapped in `data` |

Errors never use `data`. See section 8.

## 4. Enum values

| Enum | Field | Values |
| --- | --- | --- |
| `role` | `user.role` | `admin`, `manager`, `inspector` |
| `status` (user) | `user.status` | `pending`, `active`, `inactive` |
| `registered_via` | `user.registered_via` | `self`, `admin` |
| `verdict` | `answer_key`, `verdict` | `good`, `defect` |
| `status` (session) | `session.status` | `draft`, `in_progress`, `completed` |
| `outcome` | `result.outcome` | `answered`, `timed_out` |
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

### Forgotten password and break-glass recovery

`POST /api/v1/users/{user}/reset-password` — Admin only, and never for the admin's own account.

- Replaces the target's password with a server-generated `temporary_password`, returned once in that response only.
- Sets `must_change_password: true`, so the target must change it on the next login before using any other endpoint.
- Revokes every token the target still holds.
- Leaves role, status and team unchanged. A soft-deleted account cannot be reset (`404` `not_found`).
- An admin resets their own password through `POST /password` with `current_password`; the admin route refuses self-targeting with `403` `forbidden`.
- If another admin is still able to log in, that admin performs the reset. This is the normal recovery path.

If the **last active admin** is locked out, no API route can help by design: resetting the highest-privilege account must not be possible with only a known employee code. A server operator uses the break-glass command:

```bash
php artisan grr:reset-password EMP-1000 --force
```

The command prints the new temporary password once, revokes existing tokens, sets `must_change_password` and keeps role/status. It only works with server access, and it does not bypass any HTTP authorization because it is not an HTTP route. Without `--force` it asks for confirmation; without an interactive terminal it refuses to run. It is not exposed to the Flutter client.

## 6. Endpoints

`Access` uses these labels: **Public**, **Any active user**, **Admin**, **Admin or self**, **Admin and Manager (read)**, **Participating Inspector**.

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
| `PATCH` | `/api/v1/users/{user}` | Admin or self | Update the display name. Only an admin may change `team_id`. |
| `POST` | `/api/v1/users/{user}/approve` | Admin | Activate a `pending` account and assign a team. |
| `POST` | `/api/v1/users/{user}/reset-password` | Admin (not self) | Replace a forgotten password. Returns `temporary_password` once and revokes tokens. |
| `POST` | `/api/v1/users/{user}/reactivate` | Admin (not self) | Return an `inactive` account to `active`. |
| `POST` | `/api/v1/users/{user}/deactivate` | Admin | Set the account to `inactive` and revoke its tokens. |
| `DELETE` | `/api/v1/users/{user}` | Admin | Soft delete the account. |

An admin cannot approve, reset the password of, reactivate, deactivate or delete their own account, and cannot deactivate or delete the last active admin (`403` `forbidden`). Approving an account that is not `pending` returns `422` `validation_failed` keyed on `user`.

`PATCH /users/{user}` only changes `name` and `team_id`. `employee_code`, `role`, `status`, `password` and the approval columns are rejected with `422` `validation_failed`; those have their own actions. A user editing their own account can change `name` but not `team_id` (`403` `forbidden`). Changing a team does not reset the account status or require re-approval.

`POST /users/{user}/reactivate` only accepts an `inactive` account; anything else returns `422` `validation_failed` keyed on `user`. The previous approval trail is kept. A `team_id` is required only when the account has no team, because an inspector without a team cannot join a session.

### Master data

Standard REST routes for each resource: `GET` list, `POST` create, `GET` one, `PUT` or `PATCH` update, `DELETE` soft delete.

| Resource | Path | Extra | List filters |
| --- | --- | --- | --- |
| Plants | `/api/v1/plants` | | `?search=` on code and name |
| Part types | `/api/v1/part-types` | | `?search=` on name |
| Teams | `/api/v1/teams` | | `?plant_id=`, `?search=` on name |
| Samples | `/api/v1/samples` | `GET .../{sample}/key-histories` | `?part_type_id=`, `?search=` on code |

Access: read is Admin and Manager, write is Admin. Inspectors have no access to master data at all (`403` `forbidden`); they receive the samples they must inspect through `/sessions`, never with the answer key.

`answer_key` is only present for `admin` and `manager`. For an inspector the field is omitted entirely, not `null`.

### Inspection sessions

| Method | Path | Access | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/v1/sessions` | Admin, Manager, Inspector (own) | List sessions. Filters: `?status=`, `?team_id=`, `?part_type_id=`. |
| `POST` | `/api/v1/sessions` | Admin | Create a `draft` session: snapshot the selected samples and register the selected inspectors. |
| `GET` | `/api/v1/sessions/{session}` | Admin, Manager, participating Inspector | Show one session with its samples and participants. |
| `POST` | `/api/v1/sessions/{session}/start` | Admin | Move `draft` to `in_progress` and freeze samples, participants and the time limit. |
| `POST` | `/api/v1/sessions/{session}/start-run` | Participating Inspector | Start this inspector's run and return the first sample. Repeating it is harmless. |
| `GET` | `/api/v1/sessions/{session}/current-sample` | Participating Inspector | The next sample this inspector must answer, or `null` when finished. |
| `GET` | `/api/v1/sessions/{session}/results` | Admin, Manager, participating Inspector (own) | List the stored outcomes, grouped by participant. |
| `POST` | `/api/v1/sessions/{session}/results` | Participating Inspector | Submit one outcome (`answered` or `timed_out`) for the current sample. |
| `GET` | `/api/v1/sessions/{session}/evaluation` | Admin, Manager | Computed per-participant attribute agreement metrics and session-level agreement for a completed session. |
| `POST` | `/api/v1/sessions/{session}/close` | Admin | Force-close an `in_progress` session. |
| `DELETE` | `/api/v1/sessions/{session}` | Admin | Delete a `draft` session. |

- An inspector only lists and opens sessions they participate in. Another session's uuid returns `403` `forbidden`.
- A session starts as `draft`. Only a `draft` can be started or deleted; any other status returns `422` `validation_failed` keyed on `status`.
- `team_id` decides the plant; send no `plant_id`.
- `sample_ids` order is the inspection order. Each sample is copied into the session with its answer key and `position` 1..N, so later master-data changes cannot alter the session.
- Participants are active inspectors of the chosen team. Pending, inactive, non-inspector or out-of-team users are rejected.
- The token is readable, for example `SES-2026-0001`, and is unique.
- Inspectors may arrive one at a time and on different days. Each participant has their own `started_at` and `completed_at`; the session `started_at` is when the admin started it.
- An inspector may only answer as themselves. An admin cannot start a run or submit an answer for someone.
- Answers are strictly sequential by `position`: the current sample is the only one accepted, and an inspector cannot skip ahead or go back. A sample already answered (`answered` or `timed_out`) is locked.
- A participant is complete when they have an outcome for every sample. The session becomes `completed` automatically when every participant is complete.
- An admin may force-close an `in_progress` session for an inspector who will never finish. Unfinished participants keep `completed_at: null`, keep the answers they already submitted and are not evaluated. No verdict or timeout is invented for missing samples.
- A force-closed or completed session accepts no new answers (`422` keyed on `status`). Repeating an already accepted answer with an identical payload still returns `200`, so an offline queue can drain.
- `GET /sessions/{session}/results` returns every stored outcome for admins and managers, and only the inspector's own participant row for an inspector. It is readable in every session status; a draft has no outcomes yet.
- Results are ordered by sample `position`. A participant's `results_count` counts every outcome and `timed_out_count` counts missing data. `answer_key` is present only for admins and managers.
- A soft-deleted inspector still appears in historical results with their stored outcomes.

### Submitting an answer

`POST /api/v1/sessions/{session}/results`:

```json
{
  "session_sample_id": 41,
  "outcome": "answered",
  "verdict": "defect",
  "response_time_ms": 15240,
  "answered_at": "2026-11-12T08:15:12+00:00"
}
```

- `outcome` is `answered` or `timed_out`.
- `verdict` is required for `answered` and must be omitted or `null` for `timed_out`. Sending both a timeout and a verdict is a validation error.
- `response_time_ms` is the elapsed stopwatch time measured on the device in whole milliseconds. The server stores it and rejects an `answered` outcome above the session's `response_time_limit_ms`. It never rejects an answer only because it arrived later in wall-clock time: answers may be queued offline and synced later.
- `answered_at` is the device time of the answer, stored for audit. It is not used to enforce the limit.
- The first successful submission returns `201`. Repeating the same `outcome`, `verdict` and `response_time_ms` for the same sample returns `200` with the stored record and creates no second row. A different payload for a locked sample returns `422` keyed on `session_sample_id`.
- A timed-out sample has no verdict. The client moves to the next sample once `response_time_ms` reaches the limit; the server does not invent a verdict.

### Session evaluation

`GET /api/v1/sessions/{session}/evaluation` — Admin and Manager only.

Computes the AIAG attribute agreement metrics from the stored verdicts of a completed session. Nothing is persisted and the numbers are recomputed on demand.

- Only a `completed` session can be evaluated. A `draft` or `in_progress` session returns `422` `validation_failed` keyed on `status`.
- A force-closed session counts as completed. Unfinished participants are evaluated from the verdicts they actually stored; no verdict or timeout is invented for a missing sample.
- A `timed_out` sample has no verdict. It is missing data: it never counts as `good` or `defect`, never enters a denominator, and is reported in `timed_out_samples`.
- `agreement` holds the session-level result. Only participants with `completed_at` are compared; fewer than two of them makes both rates `null`. An unfinished participant of a force-closed session is never compared.
- A sample is excluded when any compared participant timed out on it, or has no stored result. It is missing data, so it leaves both session rates instead of counting as a disagreement. `excluded_samples` counts these; `compared_samples` is the rest. Zero compared samples makes both rates `null`.
- `between_agreement` = samples where every compared participant gave the same verdict divided by compared samples.
- `all_vs_standard` = samples where every compared participant gave the same verdict and that verdict equals the session sample's `answer_key` divided by compared samples. A sample where the participants disagree never counts, even if the majority is correct.
- `between_agreement` and `all_vs_standard` are strings with two decimals, or `null`, never `0`. This is not Kappa.
- No judgement, threshold or repeatability is returned. Those criteria are not confirmed (see section 10).
- An inspector cannot read this endpoint (`403` `forbidden`). They keep access to their own stored results.

### Request bodies

| Endpoint | Body |
| --- | --- |
| `POST /users` | `employee_code` (required, max 30, unique among non-deleted), `name` (required, max 100), `role` (required, `admin`/`manager`/`inspector`), `team_id` (optional, existing non-deleted team) |
| `PATCH /users/{user}` | `name` (optional, max 100), `team_id` (optional, existing non-deleted team or `null`; admin only). All other fields are rejected. |
| `POST /users/{user}/approve` | `team_id` (required, existing non-deleted team) |
| `POST /users/{user}/reactivate` | `team_id` (required only when the account has no team; existing non-deleted team) |
| `POST /plants`, `PATCH /plants/{plant}` | `code` (required on create, max 30, unique among non-deleted), `name` (required on create, max 100), `is_active` (optional boolean, defaults to `true`) |
| `POST /part-types`, `PATCH /part-types/{part_type}` | `name` (required on create, max 50) |
| `POST /teams`, `PATCH /teams/{team}` | `plant_id` (required on create, existing non-deleted plant), `name` (required on create, max 100) |
| `POST /samples`, `PATCH /samples/{sample}` | `code` (required on create, max 30, unique among non-deleted), `part_type_id` (required on create, existing non-deleted part type), `answer_key` (required on create, `good`/`defect`) |
| `POST /sessions` | `team_id` (required, existing non-deleted team), `part_type_id` (required, existing non-deleted part type), `session_date` (required, `YYYY-MM-DD`), `sample_ids` (required, non-empty array of samples of that part type, in inspection order), `participant_ids` (required, non-empty array of active inspector uuids of that team), `response_time_limit_ms` (optional, 5000–600000, default `90000`) |
| `POST /sessions/{session}/results` | `session_sample_id` (required, integer of this session), `outcome` (required, `answered`/`timed_out`), `verdict` (required when `answered`, must be `null`/omitted when `timed_out`), `response_time_ms` (required integer, 0–600000, and at most the session limit for `answered`), `answered_at` (required ISO 8601 device time) |

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

Session:

```json
{
  "id": "6c1f0f7e-...",
  "token": "SES-2026-0001",
  "plant_id": 1,
  "plant": { "id": 1, "code": "BCP", "name": "BCP", "is_active": true, "created_at": "2026-11-08T07:30:00+00:00" },
  "team_id": 3,
  "team": { "id": 3, "name": "Line 1", "plant_id": 1, "plant": { "...": "..." }, "created_at": "2026-11-08T07:30:00+00:00" },
  "part_type_id": 2,
  "part_type": { "id": 2, "name": "Upper", "samples_count": 20, "created_at": "2026-11-08T07:30:00+00:00" },
  "session_date": "2026-11-12",
  "status": "draft",
  "response_time_limit_ms": 90000,
  "started_at": null,
  "completed_at": null,
  "samples_count": 20,
  "participants_count": 3,
  "session_samples": [],
  "participants": [],
  "created_at": "2026-11-08T07:30:00+00:00"
}
```

`session_samples` and `participants` are only present on `POST /sessions` and `GET /sessions/{session}`; list responses use `samples_count` and `participants_count` only. `started_at` is `null` until started, `completed_at` until completed. The internal creator id is never exposed.

Session sample: `id` (integer, only meaningful inside this session), `sample_id`, `code`, `position`, `answer_key` (admin/manager only, omitted for inspectors).

Participant: `id` (integer), `user` (`id` = uuid, `employee_code`, `name`), `started_at`, `completed_at`, `results_count`, `is_complete`. `results_count` is present on `GET /sessions/{session}` and `POST /sessions/{session}/close`. On `GET /sessions/{session}/results` it also carries `timed_out_count` and the `results` array.

Progress (returned by `start-run`, `current-sample` and `results`):

```json
{
  "data": {
    "session": {
      "id": "6c1f0f7e-...",
      "token": "SES-2026-0001",
      "status": "in_progress",
      "response_time_limit_ms": 90000,
      "completed_at": null
    },
    "participant": {
      "started_at": "2026-11-12T08:00:00+00:00",
      "completed_at": null,
      "answered_count": 1,
      "samples_count": 20,
      "is_complete": false
    },
    "current_sample": {
      "id": 41,
      "sample_id": 5,
      "code": "SMP-0001",
      "position": 2
    },
    "result": {
      "id": 77,
      "session_sample_id": 40,
      "position": 1,
      "outcome": "answered",
      "verdict": "good",
      "response_time_ms": 15240,
      "answered_at": "2026-11-12T08:15:12+00:00",
      "created_at": "2026-11-12T08:15:40+00:00"
    },
    "result_created": true
  }
}
```

- `current_sample` is `null` when the participant has finished every sample or when the session is no longer `in_progress`. A force-closed session never offers a next sample.
- `current_sample` never contains `answer_key`.
- `result` and `result_created` are `null` on `start-run` and `current-sample`. On `results`, `result` is the stored row and `result_created` is `true` for a new row (`201`) or `false` for an idempotent repeat (`200`).
- `result.verdict` is `null` for `timed_out`; `result.answered_at` is the device time.

Results list (`GET /sessions/{session}/results`):

```json
{
  "data": {
    "session": {
      "id": "6c1f0f7e-...",
      "token": "SES-2026-0001",
      "status": "completed",
      "session_date": "2026-11-12",
      "response_time_limit_ms": 90000,
      "started_at": "2026-11-12T08:00:00+00:00",
      "completed_at": "2026-11-12T09:00:00+00:00",
      "samples_count": 20,
      "participants_count": 3
    },
    "samples": [
      { "id": 41, "sample_id": 5, "code": "SMP-0001", "position": 1, "answer_key": "good" }
    ],
    "participants": [
      {
        "id": 7,
        "user": { "id": "0f1c7c1e-...", "employee_code": "EMP-1000", "name": "Siti Inspector" },
        "started_at": "2026-11-12T08:00:00+00:00",
        "completed_at": "2026-11-12T08:40:00+00:00",
        "results_count": 20,
        "timed_out_count": 1,
        "is_complete": true,
        "results": [
          {
            "id": 77,
            "session_sample_id": 41,
            "position": 1,
            "outcome": "answered",
            "verdict": "good",
            "response_time_ms": 15240,
            "answered_at": "2026-11-12T08:15:12+00:00",
            "created_at": "2026-11-12T08:15:40+00:00"
          }
        ]
      }
    ]
  }
}
```

- `samples[].answer_key` is omitted for inspectors.
- A `timed_out` row keeps `verdict: null`; it is missing data, never `good` or `defect`.
- An inspector receives only their own participant row, while `participants_count` stays the session total.
- `results` is empty for a participant who has not started, and for a draft session. `timed_out_count` is only present on this endpoint.

Evaluation (`GET /sessions/{session}/evaluation`):

```json
{
  "data": {
    "session": {
      "id": "6c1f0f7e-...",
      "token": "SES-2026-0001",
      "status": "completed",
      "session_date": "2026-11-12",
      "response_time_limit_ms": 90000,
      "started_at": "2026-11-12T08:00:00+00:00",
      "completed_at": "2026-11-12T09:00:00+00:00",
      "samples_count": 20,
      "participants_count": 3
    },
    "participants": [
      {
        "participant_id": 7,
        "user": { "id": "0f1c7c1e-...", "employee_code": "EMP-1000", "name": "Siti Inspector" },
        "started_at": "2026-11-12T08:00:00+00:00",
        "completed_at": "2026-11-12T08:40:00+00:00",
        "is_complete": true,
        "metrics": {
          "effectiveness": "95.00",
          "miss_rate": "10.00",
          "false_alarm_rate": "0.00",
          "decisions": 19,
          "correct_decisions": 18,
          "defect_decisions": 10,
          "good_decisions": 9,
          "missed_defects": 1,
          "false_alarms": 0,
          "timed_out_samples": 1
        }
      }
    ],
    "agreement": {
      "participants_compared": 3,
      "compared_samples": 19,
      "excluded_samples": 1,
      "between_agreement": "89.47",
      "all_vs_standard": "78.95",
      "judgement": "conditional"
    }
  }
}
```

| metric | formula | notes |
| --- | --- | --- |
| `decisions` | \( \text{number of answered samples} \) | Excludes timed out samples |
| `correct_decisions` | \( \text{verdict} = \text{answer\_key} \) | Samples where verdict matches key |
| `defect_decisions` | verdict = `defect` and matches key | |
| `good_decisions` | verdict = `good` and matches key | |
| `missed_defects` | verdict = `good` when key = `defect` | Incorrect defects |
| `false_alarms` | verdict = `defect` when key = `good` | Incorrectly flagged |
| `timed_out_samples` | \( \text{number of outcomes} = \text{timed\_out} \) | Missing data, never counted in rates |
| `miss_rate` | \( \dfrac{ \text{missed\_defects} }{ \text{defect\_decisions} + \text{missed\_defects} } \) | 0 when \( \text{defect\_decisions} + \text{missed\_defects} = 0 \) |
| `false_alarm_rate` | \( \dfrac{ \text{false\_alarms} }{ \text{good\_decisions} + \text{false\_alarms} } \) | 0 when \( \text{good\_decisions} + \text{false\_alarms} = 0 \) |
| `effectiveness` | \( \dfrac{ \text{correct\_decisions} }{ \text{decisions} } \) | 0 when \( \text{decisions} = 0 \) |
| `between_agreement` | \( \dfrac{ \text{samples where all compared participants agree} }{ \text{compared\_samples} } \times 100 \) | **String**; 0 or null when undefined |
| `all_vs_standard` | \( \dfrac{ \text{samples where all agree and match key} }{ \text{compared\_samples} } \times 100 \) | **String**; 0 or null when undefined |

| field name | endpoint | notes |
| --- | --- | --- |
| `timed_out_samples` | evaluation | Count of timed out outcomes |
| `timed_out_count` | results | Count of timed out outcomes |

- `judgement` is derived from `all_vs_standard`: `acceptable` (>90%), `marginal` (80–90%), `unacceptable` (<80%), or `null` when undefined.
- `effectiveness`, `miss_rate`, `false_alarm_rate`, `between_agreement` and `all_vs_standard` are strings with exactly two decimals, or `null` when their denominator is zero or the comparison is undefined.
- `is_complete` is `false` for a participant of a force-closed session who never finished. Their metrics cover only the stored verdicts, and they are not part of `agreement`.
- A soft-deleted inspector still appears in the evaluation.
- `agreement` is additive: older clients may ignore it without breaking.

## 7. Access matrix

| Area | `admin` | `manager` | `inspector` |
| --- | --- | --- | --- |
| Own profile, logout, password change | yes | yes | yes |
| User management | yes | no | no |
| Master data read | yes | yes | no |
| Master data write | yes | no | no |
| Sessions | all | read-only | own |
| Results | all | read-only | own |
| Evaluations | all | read-only | no |

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

Per-participant attribute agreement metrics and session-level agreement exist for a completed session. Not implemented: judgement, thresholds, persisted evaluations and report exports.

`timed_out` rows are missing data, not a verdict. They never count as `good` or `defect` in any metric. Repeatability is not measured.

## 10. Open items

These decisions are not made. Until they are, neither side may assume an answer.

1. **Judgement.** Per-participant judgement is not implemented. Session-level judgement (`acceptable`, `marginal`, `unacceptable`) is computed from `all_vs_standard` and returned in the `agreement` object. Thresholds for per-participant or session-level judgement remain provisional.
2. **Session and result error codes.** Proposed additions, backwards compatible: `session_not_accepting_answers`, `sample_already_answered`, `cannot_modify_own_account`, `last_admin`, `resource_in_use`. Until added, those cases return `validation_failed` or `forbidden`.
3. **Deleting master data that sessions refer to.** Either refuse with `resource_in_use` or allow the soft delete and keep it visible in historical results.
4. **Changing a role or employee code.** Neither is editable today. Decide whether an admin ever needs to change a role in place, or whether a new account replaces the old one. `employee_code` is an identity and should stay immutable.
5. **Repeatability.** Inspector repeatability requires repeated verdicts for the same sample under identical conditions. Practice trials are performed outside the application and are not stored; repeatability is not measured.
6. **Editing a draft session.** The time limit, samples and participants can be chosen at creation only; there is no update endpoint yet. Decide whether the client needs to edit a draft before it starts.
7. **Removing a participant.** A participant who never starts leaves the session open. Today the admin can only force-close the whole session. Decide whether removing an untouched participant is needed.
8. **Persisting evaluations.** `grr_evaluations` and `session_evaluations` remain unused because saving a row would require a judgement. Decide whether to persist computed metrics later, or keep recalculating on demand.
