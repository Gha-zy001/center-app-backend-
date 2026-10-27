# PUT /api/v1/teacher/attendance/status

Manual 3-way attendance toggle: present, absent, or `null` (not recorded = the row is removed).

Request: `{ "groupId": "12", "date": "YYYY-MM-DD", "studentId": "34", "status": "present" | "absent" | null }`

- ids are digit strings; `date` is a real calendar date, at most tomorrow (same rule as scan/close).
- `status` must be present in the body. `""`, `"late"`, `0` and other values are invalid. Extra fields are ignored.

Response 200 (usual envelope): `{ "success": true, "message": "OK", "data": { "studentId": "34", "status": "present" } }`

Errors (envelope with a `code`): `401` no teacher token · `400 invalid_request` bad JSON, ids, date or status ·
`404 student_not_found` group or student missing, owned by another teacher, or the student is not in that group
(all indistinguishable) · `429` rate limit (`user`: 120 requests/min per teacher).

Behaviour
- Idempotent: the same status twice changes nothing. One row per student/group/date (unique key); a lost race
  on the key falls back to an update, so last write wins and no 500.
- Allowed on closed sessions; never opens or closes a session.
- Scan after a manual absent flips to present (`alreadyRecorded: false`); close only creates absent rows for
  students with no row, so manual absents are not counted twice.

Parent notifications (Telegram, only if a parent is linked)
- to `present` (from absent or not recorded): the same text as a scan.
- to `absent` (from present or not recorded): the same text as close. Close skips this student afterwards.
- to `null`, or the same status again: nothing is sent.
- No debounce: every real transition sends one message, in request order, so the last message matches the stored
  state. Messages go out after the response; a Telegram failure is logged and never changes the status or the 200.