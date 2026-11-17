# Staff & Roles (GROW: `staff.custom_roles`)

Settings → **Staff & Roles** (`tutor/staff.php`, `assets/js/enlixo/pages/app-tutor-staff.js`).

- **Roles** (tab): the tutor's roles (`tutor_roles` + `tutor_role_permissions`, API `/api/v1/tutor/roles`). Each role ticks what it may do
  from `PermissionCatalog` (students, classes, recordings, tutes, payments, attendance, messages, settings). Owner = the tutor, always everything
  the plan allows. A role in use can be deleted only by moving its people to another role.
- **Staff** (tab): `StaffController` (`/api/v1/tutor/staff`). Invite = a `users` row with role 3 (Staff) + a `tutor_staff` row (tutor, role, status).
  A "Set my password" e-mail goes out (link 7 days, `tutor/setup-password`) and the link also shows on the page to send on WhatsApp.
  Change role, Switch Off / On (off = signed out at the next page, devices marked inactive), Send Link Again.
- **Sign-in:** the tutor sign-in page (`tutor/api/signInWithEmail.php`) accepts a Staff user with an active `tutor_staff` row whose tutor is
  active and on a plan with `staff.custom_roles` (`includes/enlixo/staff-access.php`). The session is the tutor's (`user_id` = the tutor, so the
  older pages show the tutor's classes and students) plus `staff_user_id`, `staff_role_id`, `employer_name`.
- **What staff can open:** the API works as `AuthContext(kind 'staff')` (`LegacySessionAuthenticator`), so every `/api/v1` call is limited to
  their role (`PermissionService`). Pages: `enlixo_staff_page_permission()` maps each tutor page to a permission; the side menu hides the rest
  and opening one sends them to the dashboard. Subscription, billing, upgrade, My Profile and the form settings are the tutor's only.
  The dashboard shows "Your pages" when their role cannot see the class figures.
- **Limit:** the older `tutor/api/*.php` endpoints behind some legacy pages do not check the role themselves; the page guard keeps staff off
  those pages, but the endpoints trust the signed-in session.
- Tests: `tests/e2e/staff-journey.e2e.js` (8 cases; `setup-qa-staff.php`, `setup-qa-tutor.php grow`).
