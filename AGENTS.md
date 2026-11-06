# AGENTS.md — Asset Management System (AMS)

> **Project:** Asset Management System (AMS) | **Target:** MVP v1.0  
> **Baseline bisnis:** Approved / Frozen, 2026-11-06  
> **Audience:** AI coding assistants and human contributors  
> **Language:** Communicate with the project owner in Bahasa Indonesia. Keep identifiers, code, and technical conventions in English.

## 0. Mission and non-negotiables

Build a maintainable, secure, accessible **Laravel 13** asset-management application for **one company with multiple branches**. This is a **greenfield project**, not a migration from an older application. Every asset is tracked **per physical unit**. Target scale is **fewer than 10,000 assets**.

**Never reinterpret or silently change business decisions in the frozen baseline.** Do not implement out-of-scope features. Do not report completion if code, migrations, tests, or UI have not actually been run/verified. Do not invent implementation results.

### 0.1 Source-of-truth order

Before implementing a feature, read the relevant sections of these frozen documents **if available in the repository** (recommended location: `docs/baseline/`):

1. `REQUIREMENTS_BASELINE_v1.0_APPROVED.md` — product/business invariants.
2. `PRD_AMS_v1.0_APPROVED.md` — scope, requirement IDs, workflows and acceptance criteria.
3. `ERD_AMS_v1.0_APPROVED.md` — logical schema, relationships, constraints and transactional design.
4. `DECISION_LOG_VALIDASI_01-16_APPROVED.md` — why key decisions were made.
5. `FINAL_REQUIREMENTS_AUDIT_APPROVED.md` and `APPROVAL_AND_FREEZE_RECORD.md` — traceability and freeze record.

If documents are missing, **do not invent their contents**; request or locate the approved copies before making decisions that depend on them. If an apparent conflict exists, stop that part of the task, document the conflict and propose a resolution for owner approval. The PRD and ERD complement, not override, the baseline; frozen business rules take precedence over implementation convenience.

**Changes after freeze:** any change to a business rule, MVP scope, workflow or data semantics needs a numbered Change Request (CR) with impact on PRD, ERD, authorization, tests, rollout and migration. Technical design choices that preserve requirements may be captured in an Architecture Decision Record (ADR), but must not modify frozen documents silently.

## 1. Approved technology and boundaries

| Layer | Approved choice | Rule |
|---|---|---|
| Backend | Laravel `^13`, PHP `8.3+` | Follow installed framework's actual APIs; never assume an unavailable method/package. |
| Persistence | MySQL, InnoDB | Real MySQL required for integration/concurrency tests. |
| Views | Blade | Server-rendered first. |
| Interactivity | Livewire + Alpine.js | Livewire owns server state; Alpine handles small local UI interactions. |
| Styling | Tailwind CSS | Tokenized styling, accessible responsive layouts. |
| UI | Reusable Blade components **styled in the spirit of shadcn** | Do **not** introduce shadcn React components or a React SPA. |
| Authorization | Laravel Policies + Spatie Laravel Permission | Role/permission **and** scoped resource checks. |
| Jobs | Database Queue | Redis only after measured need and ADR/approval. |
| Tests | Pest or PHPUnit | Use the repository's established runner consistently. |
| Hosting | NGINX + PHP-FPM | Portable across on-premises/cloud; configuration via environment. |
| Source control | Git | Small reviewed changes; no force-pushing or destructive reset. |

Design as a **modular monolith**, not microservices. Prefer straightforward Laravel conventions; do not add repositories, DTO layers, events or abstractions solely for fashion. Adopt dependencies only after checking their versions, license, necessity, security and compatibility.

## 2. What the MVP includes and excludes

**In scope (P1):** organization/location/asset masters; hierarchical categories/locations; flexible asset-type attributes; employees, appointments and movement history; optional user accounts, email/NIK login, optional 2FA; role/permission/organizational scope; registration, assignments, location changes, deactivate/reactivate; QR and printable labels; manual and Excel/CSV import with preview/partial success/retry; audit; in-app notifications; dashboards; current-state and change-event reports; XLSX/CSV export; localization (`id`, `en`, `zh-CN`); light/dark and mobile field use.

**Out of scope:** formal approval workflow, lending, maintenance, stock opname, depreciation, HRIS integration, as-of historical state reconstruction, irreversible asset deletion, and external email/WhatsApp/push notifications. Email for password reset is an authentication mechanism, not a license to add general email notifications.

## 3. Hard business invariants — enforce in all entry points

### 3.1 Organization, employees, and appointments

- Hierarchy: **Branch → Division → Department → Employee**. Divisions/departments belong to their own branches; no cross-branch parenting.
- NIK is required, globally unique, **never reused**. Employment tenure is derived from `joined_at`.
- Employees may have **no user account**; an employee can have **at most one** account. A user belongs to an employee, NIK login resolves through `employees`; user email is nullable and unique when provided. Normalize emails consistently. Reject ambiguous NIK/email identifiers.
- Login using email **or** NIK + password. Inactive accounts cannot log in. Optional 2FA and protected recovery codes. Admin password reset must not expose the old/new password to the admin.
- Employee transfers across branch/division/department are allowed while assets remain assigned. Warn about direct active assignments, retain them unchanged, do not move physical location, and write old/new organization and reason to history. **Reject deactivation of an employee with any direct active assignment**, including concurrent attempts.
- HOD scope = department. Director scope = division, branch or company. An officer can hold multiple appointments across units. Effective-dated definitive/Acting appointments: maximum **one of each type** for the same scope/role at a time; overlaps across **same kind** are rejected, even under concurrency. Acting wins when both are effective; definitive resumes afterward if its period remains effective. Preserve history.

### 3.2 Assets, physical locations, and assignments

- Every asset is one record, with immutable non-reused global code like `AST-000001` and stable random public QR token. Never generate an asset code in import preview.
- Required on registration: name, valid category/**asset type**, physical location, **exactly one assignee** (employee OR department OR location), operational status, physical condition, purchase date, nonnegative acquisition cost. Seed editable defaults **Tersedia** and **Baik** using stable codes (never hard-code integer IDs).
- Optional: serial number, vendor, invoice number, image, invoice attachment, brand/warranty as appropriate. Do not infer a globally unique serial number.
- `assets.is_active`, `status_id`, `condition_id` have distinct meaning. Nonactive assets cannot start new operations; the last status remains only informational.
- **Active asset = exactly one active assignment** (`ended_at IS NULL`). **Inactive asset = zero active assignments.** Exactly one assignee target FK is populated per assignment. Its responsible location **may differ** from `assets.physical_location_id`.
- Changing physical location never implicitly changes assignment; changing assignment never implicitly changes physical location. Record actor, timestamp, required reason, before/after and domain history.
- Deactivate atomically: lock asset, require reason, close active assignment, set inactive, write deactivation record (document optional) and audit. Reactivate atomically with **new** assignment; never reopen old one; preserve code/QR.
- No hard delete of assets or referenced master data; inactive/archive with history instead. Hierarchical categories and locations may not form cycles; location parent and child must belong to the same branch.

### 3.3 Access control: read and mutate are different

**Authorization = permission + explicit organization data scope + Laravel Policy/resource check.** A role/title, a visible UI button or a Livewire action alone is never sufficient.

- **Operational scope of an asset follows the branch containing its current physical location**, **not** the organization of the assignee. A Jakarta asset assigned to a Bandung employee remains operationally manageable by appropriately permissioned Jakarta personnel, not automatically by Bandung personnel.
- A staff member can **read** assets directly assigned to their `employee_id` even if physical branch differs; this exception grants **no edit** or implicit access to department/location assigned assets.
- Branch/division/department scopes limit relevant organization data; do not assume a department grant equals unrestricted branch access. Model explicit scopes and their approved expansion semantics; use centralized scoped query builders/services shared by list/detail/dashboard/export/jobs.
- When moving physical location across scope boundaries, authorize the current asset and target location **before commit**. Subsequent reads use the new physical branch. Do not give a queue job indefinite authority based only on the submitter's old scope.
- Policies and scope apply to **all** routes, direct URLs, Livewire actions, imports, PDF/label/CSV/XLSX generation, background jobs, personalized dashboards, in-app notifications and authenticated QR screens.
- **Only Super Admin** may modify global application settings. Never bypass policies with an unconditional `isAdmin` shortcut for normal asset operations.

### 3.4 QR/public exposure

- Unauthenticated QR result is an explicit **allowlist**: **asset code, asset name, category and general status only**. No NIK, employee name, serial number, pricing, invoice, exact location, detailed history or private files.
- Authenticated asset detail still requires Policy + data scope. Public QR token must be random/unpredictable, stable across reactivation, unique and not an exposed database ID.
- Labels support individual/bulk printing and configurable size/allowed fields, but label selection/export must also pass authorization. Do not interpret customizable labels as permission to publish private fields.

### 3.5 Imports: transaction per row, retry without duplication

- Accept Excel/CSV with a server-validated **1,000-row and 10-MB** limit. Provide preview and explicit create/update/skip choices according to permissions. Existing asset code = update candidate; blank code = create candidate; attribute similarity = **warning**, not automatic identity.
- Preview is non-mutating. Re-check validation, duplicates, permission and **current data scope** on execution, not only at preview.
- **Partial success:** each row has its own DB transaction. One failing row must not roll back successful rows.
- **Idempotency:** `(import_batch_id, row_number)` is unique; success is terminal and may never be replayed. Pending rows resume; failed rows require explicit authorized retry, tracked as attempts; stale processing leases require reconciliation.
- On row success, commit **asset mutation + assignment/history + audit + success status + asset linkage together**. Queue crash or double delivery must not create another asset or repeat an update. Treat batch counters as derived or update them idempotently. Record errors in a manner that survives rollback of the failed business transaction.
- Import jobs may not bypass Policies or use permissions captured indefinitely at upload time. Audit row actions and reason. Handle race, expired lease and deadlock in bounded, safe retry logic.

### 3.6 Reporting and audit

- Dashboard/list/summary/export always use the same authorization and scoped query semantics. Current-state reports may filter by chosen **purchase**, **registration** or **last-change** date; this does **not** recreate past state.
- Event reports filter history by event timestamp for changes in location, assignee, status and condition; include before/after, actor, time and reason. **As-of reporting is out of scope.**
- XLSX and CSV export must match authorized on-screen results and be safe against spreadsheet formula injection; large exports may use queued streaming/chunks after scope recheck.
- Business audit records are append-only through the application, retain actor/time/reason/sanitized old/new values, and **must never record passwords, 2FA secrets, recovery codes, tokens or sensitive file contents**. Audit history is not automatically purged for MVP. Changes to permissions, scopes, settings and identity are also audited.

## 4. Schema integrity and transaction strategy

Use the approved **ERD** as the starting logical model; don't invent migrations from scratch without checking its data dictionary and relationship constraints.

**Database invariants:**

- Foreign keys for explicit references; correct nullability; `CHECK` for exactly one assignee FK and sensible dates/cost; globally unique asset code/QR token/NIK; unique user employee FK, nullable unique normalized email.
- Max one active assignment via a MySQL generated column / indexed equivalent **validated on the actual MySQL version**. A unique index alone **cannot** guarantee at least one active assignment: ensure transactions/actions leave active assets with exactly one.
- Structural-appointment overlap requires transactional serialization/lock per unit and role; a normal unique index cannot enforce arbitrary date-range overlap.
- Asset numbering uses an atomically locked counter/sequence, never `MAX(id)+1` or an unlocked read.
- Hierarchical category/location updates must prevent circular parents, including concurrent writes. Preserve referential integrity for used masters.
- Prefer `DB::transaction(...)` and `lockForUpdate()` on stable parent rows; establish consistent lock order and bounded retry for deadlocks. Do not use MySQL triggers or stored procedures as the default solution; document exceptions in an ADR.
- Use database transactions for **RegisterAsset, ReassignAsset, MoveAssetLocation, DeactivateAsset, ReactivateAsset, TransferEmployee, AssignStructuralOfficer, ProcessImportRow**. Commit domain rows, history and audit together where required; enqueue notifications and other external effects **after commit**.
- Keep schema migrations reversible when possible; never silently drop user data or alter approved business meaning. Test constraints against MySQL, not only SQLite.

## 5. Suggested Laravel project organization

Use conventions consistently; adapt to the actual repository rather than forcibly migrating existing working structure:

```text
app/
  Actions/
    Assets/                 # RegisterAsset, ReassignAsset, MoveAssetLocation, ...
    Employees/              # TransferEmployee, DeactivateEmployee, ...
    Imports/                # ProcessImportRow, ...
    Organization/           # AssignStructuralOfficer, ...
  Models/
  Policies/
  Livewire/
    Assets/
    Employees/
    Organization/
    Imports/
    Reports/
    Settings/
  Queries/                  # Reusable scoped read queries
  Services/                 # Only domain services that add value
  Jobs/
  Notifications/
  Support/
resources/
  views/
    components/ui/          # Shared Blade UI primitives
    livewire/
  css/
  js/
lang/
  id/ en/ zh-CN/
database/
  migrations/ factories/ seeders/
tests/
  Feature/ Unit/
docs/
  baseline/                 # Frozen files, never silently edited
  adr/                      # Technical decisions
  change-requests/          # Business change requests
```

**Responsibilities:** Livewire = presentation and orchestration only; Form Requests/Livewire validation = input constraints; Policies + scoped queries = authorization; Actions = transactional business operations; Eloquent Models = persistence/relationships; Jobs = asynchronous orchestration; Blade components = reusable UI. Avoid fat Livewire components and business logic hidden in model observers/Blade/Alpine.

Use meaningful names and typed PHP; prefer PHP enums only for **fixed** system values, not admin-configurable status/condition lists. Apply translation keys to user-visible copy. Use currency decimal arithmetic, never floating-point calculations for money.

## 6. Frontend, design system and Impeccable

- Build with Blade, Livewire, Alpine.js and Tailwind CSS, **not React/Vue**. Reusable Blade primitives can mirror shadcn's visual principles (clarity, spacing, state, contrast), not its framework-specific source code.
- Respect existing `PRODUCT.md` (product users/goals) and `DESIGN.md` (approved design tokens/components) when they exist. Do not overwrite a reviewed design system based only on AI taste.
- When Impeccable is **installed and available** in the coding assistant, use its supported workflow for focused UI work: initialize context (`/impeccable init`, or the assistant-specific equivalent), inspect or document the design system, audit/critique, then polish/harden/adapt. The exact invocation depends on the installed integration; verify its commands before use. Do not claim Impeccable has run when it has not. See https://impeccable.style/docs/.
- UI design checks: mobile-first compatibility for field staff and QR scanning; keyboard navigation and visible focus; semantic labels; contrast and light/dark consistency; error/empty/loading/success states; accessible tables/filter forms; responsive dense data lists; confirmation for destructive/irreversible business transitions; locale text expansion and Chinese characters.
- Never replace secure server validation with client-side Alpine checks. For camera access, provide a manual code-search fallback. Public QR content obeys strict allowlist even when UI templates change.
- All interface labels, validation messages and navigation must support Indonesian (default), English, and Simplified Chinese (`zh-CN`). Do not translate user-entered master data automatically. Locale and theme settings should persist according to user preference where applicable.

## 7. Security and operations baseline

- Session idle expiration: **30 minutes**. Login throttle: **5 attempts/minute per account/IP combination**. Optional 2FA with safe recovery flow.
- Photo upload: JPG/PNG/WebP, max **5 MB**. Invoice attachment: PDF/JPG/PNG, max **10 MB**. Validate MIME **and** file content server-side; randomize stored filenames; protect private documents using authenticated, policy-checked delivery; avoid executable uploads and user-controlled paths.
- Import file max **10 MB**, **1,000 rows**. Temporary import results retained **90 days**; business audit remains.
- Database backup **daily**, encrypted, retained **30 days**. Target **RPO 24h**, **RTO 8h**; do not claim objectives are met without a demonstrated restore drill. Keep credentials out of Git; use `.env` and secret management.
- Database Queue is the default. Jobs must be retry-safe, bounded, observe idempotency and authorization; run on a supervised worker in production. Use `afterCommit` or equivalent for notifications/events tied to transactions.
- Database in-app notifications: import results to importer; employee transfer with active assets to relevant admin; asset deactivation/reactivation to relevant admin; assignment change to old/new assignees **when they have accounts and permission**; near-expiry Acting appointment to Super Admin. No external mass-notification channel in MVP.
- Deploy on NGINX/PHP-FPM in either on-premises or cloud. Ensure scheduler, queue worker, logs, private storage, HTTPS, migrations, backups and restore procedures are documented and tested.

## 8. Test requirements — map every change to a requirement ID

Use the testing framework already configured in the repository; prefer **Feature tests** for policies, DB transactions and cross-module workflows. Factories/seeders must preserve the invariants (e.g. active assets need assignment) and never use production data.

Minimum high-risk scenarios:

| Scenario | Related requirement IDs |
|---|---|
| Asset registration missing assignment rolls back entirely | AST-001, ASN-001 |
| Two concurrent assignments cannot both become active | ASN-001 |
| Physical move does not modify assignee and vice versa | ASN-002 |
| Deactivation closes assignment and reactivation creates a new one | AST-003, AST-004 |
| Transferred employee retains asset; deactivation blocked while assigned | EMP-002, EMP-003 |
| Acting overrides definitive; same-kind overlapping dates rejected concurrently | ORG-002 |
| NIK uniqueness, optional email, one account per employee, login/2FA | IAM-001, IAM-002 |
| Cross-branch URL, export, QR authenticated view, job and label access denied | IAM-003, QR-001, QR-002 |
| Public QR leaks none of the prohibited fields | QR-001 |
| Import partial success and row transaction rollback | IMP-001, IMP-002 |
| Duplicate queue deliveries/crashes/retries do not duplicate assets or audits | IMP-003 |
| Dashboard and export totals equal properly scoped list | RPT-001, RPT-002 |
| Historical event report does not claim as-of state | RPT-003 |
| Default Tersedia/Baik and strict required field validation | AST-001 |
| I18n, responsive interactive states, upload limits, sessions and restore procedures | I18N-001, OPS-001 |

**Required verification sequence:**
1. Run the most focused affected test(s).
2. Run the relevant domain suite and policy/security tests.
3. Run the full suite before declaring milestone complete.
4. Run formatting/static analysis if configured (e.g. Pint and PHPStan/Larastan); frontend build/lint if configured.
5. Perform manual functional and mobile/light-dark/locale checks for UI tasks.
6. For MySQL-specific generated-column/lock behavior, run concurrency tests against supported **real MySQL**.

Never claim an unrun test passed. Report exact commands and pass/fail, blockers, and known gaps.

## 9. Mandatory workflow for each coding task

**Before writing code**

1. Identify the applicable **PRD IDs**, relevant frozen baseline rules, tables and dependencies. Read their text.
2. Inspect existing repository files, dependency versions, conventions, routes, middleware and tests. Do not assume a blank Laravel installation or overwrite user changes.
3. Provide a brief implementation plan: scope, changed files, data/permission impacts, tests, and whether a migration is required. Resolve non-business technical details consistently; escalate frozen-rule conflicts via CR.
4. Implement one small vertical slice at a time: schema/model → validation/Action/Policy/scoped query → Livewire/Blade → tests → UI review.

**During implementation**

5. Use migrations/seeders/foreign keys/constraints before exposing new features; take care with migration ordering.
6. Apply Policy **inside server actions** and scoped queries, not only route middleware or hidden UI controls.
7. Keep side effects after commit, avoid N+1 queries with targeted eager loading, paginate/filter large lists server-side.
8. Update tests and technical documentation whenever behavior changes. Never silently edit `docs/baseline/*`.
9. Do not change `.env`, production data, deployment settings, approvals, or perform destructive Git/DB commands without explicit authorization.

**Before reporting completion**

10. Review acceptance criteria and trace them to tests; verify all touched code paths (manual, import, queue, export where applicable).
11. Report: requirement IDs covered, implementation summary, files changed, migrations, commands/tests run and outcomes, security/accessibility review, assumptions, risks, and next logical task. Clearly call out anything not actually verified.

### Definition of Done (DoD)

A task is **Done** only when required behavior and scope are correct, authorization is server-enforced, persistence invariants hold, relevant tests pass, responsive/i18n/a11y states are checked for UI tasks, no forbidden fields leak to QR/log/export, code is formatted, and documentation/traceability are updated. If any condition is unverified, mark the task **In Review / Blocked**, not Done.

## 10. Git and delivery discipline

- Use short feature branches (e.g. `feat/asset-registration`), small commits and descriptive messages (e.g. `feat(assets): enforce active assignment invariant`).
- Do not commit secrets, backups, uploaded user files, vendor-generated assets unless project policy calls for them, or test production fixtures.
- Provide concise PR descriptions: context and PRD IDs, changes, migrations, authorization effects, screenshots for UI, tests and rollout/rollback plan.
- Avoid unrelated refactors during a business feature. Do not force-push protected branches or reset/delete uncommitted work.

## 11. Quick task prompt template for the coding assistant

```text
Implement [feature] for AMS MVP v1.0.
Reference PRD IDs: [IDs]. Read docs/baseline/ approved docs and the existing code first.
Use Laravel 13, PHP 8.3+, MySQL, Blade + Livewire + Alpine, Tailwind.
Preserve frozen business rules, Policies + data scope, audit, and transactional invariants.
Deliver: implementation plan; migration/model/action/policy/UI/tests as needed;
verify real commands and report results, including unverified items.
Do not expand scope or modify frozen docs without an approved change request.
For UI, follow PRODUCT.md/DESIGN.md and use installed Impeccable where available.
```

---

**Governance:** This file instructs coding agents **how** to implement AMS. It is not a replacement for approved PRD/ERD and cannot authorize business-rule changes.
