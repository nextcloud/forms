<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Agent Guidelines for Nextcloud Forms

This file provides instructions for AI coding agents (Claude Code, GitHub Copilot, Cursor, Windsurf, and others) operating on this repository. Read it before generating any code, commits, or pull requests.

## What This App Is

**Nextcloud Forms** is a Nextcloud app (PHP backend under `OCA\Forms` + Vue 3/TypeScript frontend) for creating surveys and questionnaires, sharing them with users, groups, teams or public links, collecting responses, and viewing or exporting results (CSV/XLSX, or synced to a linked spreadsheet file). It ships a versioned OCS REST API (v3) and integrates with Nextcloud activity, search, audit log, webhooks and the Analytics app. Supported server range is in `appinfo/info.xml` (`<nextcloud min-version max-version>`).

## Nextcloud Contribution Policy

All contributions generated or assisted by this agent must fully comply with:

- **[AI Contribution Policy](https://github.com/nextcloud/.github/blob/master/AI_POLICY.md)** - the primary reference for AI-specific rules, covering disclosure, author accountability, communication, security, licensing, code quality, and autonomous agent behavior.
- **[Contribution Guidelines](https://github.com/nextcloud/.github/blob/master/CONTRIBUTING.md)** - covering testing requirements, the Developer Certificate of Origin (DCO), license headers, and translations. These apply in full to all contributions regardless of how they were produced.

### What this agent must always do

- Add an `Assisted-by: AGENT_NAME:MODEL_VERSION` git trailer to every commit containing AI-assisted content (see [Commits](#commits)).
- Follow the repository's commit conventions, including [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/#specification) (see [Commits](#commits)).
- Declare AI use in every pull request description by ticking the AI checkbox of the pull request template, and end AI-drafted comments and issues with a footer such as `Assisted by AGENT_NAME:MODEL_VERSION`.
- Write issues, PR descriptions and replies for human readers: plain, concise and skimmable, without restating the diff file by file, filler or raw tool output. Keep the repository's template intact and complete every section. Put agent-only material (prompts, plans, tool logs) in a collapsed `<details>` block.
- State in a PR description which problem it solves, what changed at a high level, and how to verify the change.
- State in an AI-drafted bug report whether the bug was validated and reproduced on a live Nextcloud instance.
- Keep each pull request to exactly one concern, with a description that matches its diff; break large changes into focused commits or separate PRs.
- Clean the output before submission: remove dead code, redundant logic, excessive comments, inconsistent style, unused variables, garbled characters, structural drift and unrelated file changes.
- Write code comments that document the code itself (behaviour, signatures, constraints the code cannot express), never the process that produced it; keep them brief and matching the comment density of the surrounding code.
- Reuse existing helpers instead of re-implementing them, and when correcting a flawed pattern, fix every occurrence of it.
- Unit test all changed and added code.
- Validate every runtime change on a live Nextcloud instance in every context it is reachable from: the logged-in app, public share links and embedded forms. The contributor tests it on the live instance themselves as well, not only with AI.
- Manually verify all dependencies, access control logic, authentication patterns and other security implications of generated code.
- Keep generated code free of material from sources incompatible with this app's license (AGPL-3.0-or-later); replace code that is identical or suspiciously similar to such sources with an original implementation. For license headers see [HowToApplyALicense.md](https://github.com/nextcloud/server/blob/master/contribute/HowToApplyALicense.md); every new file needs an SPDX header or a `REUSE.toml` entry (CI runs REUSE lint).

### What this agent must never do, and what to do instead

- Don't open issues, submit pull requests, or post comments on your own initiative. Instead, post only what the contributor asked for, on their behalf, and tell them what you posted.
- Don't add `Signed-off-by` tags to commits. Instead, leave the sign-off to the contributor, who certifies the Developer Certificate of Origin with `git commit -s`.
- Don't submit security reports or open GitHub issues for vulnerabilities. Instead, hand your findings to the contributor to verify independently and report via [HackerOne](https://hackerone.com/nextcloud).
- Don't fully automate the resolution of issues labeled [`good first issue`](https://github.com/issues?q=org%3Anextcloud+label%3A%22good+first+issue%22) or similar beginner-friendly labels. Instead, explain the code and guide the contributor so they can solve the issue themselves.
- Don't hand over large AI code blobs without meaningful oversight, code that has never been executed, or changes that shift debugging and cleanup work onto maintainers. Instead, review, run and clean up the result, and ask the contributor to review it before submitting.
- Don't edit `l10n/`. Instead, leave translations to [Transifex](https://explore.transifex.com/nextcloud).

## Development Setup

The app must be cloned into `<nextcloud>/apps/forms`.

```bash
composer install     # PHP dependencies, plus the tools in vendor-bin/* (phpunit, psalm, php-cs-fixer, rector, openapi-extractor)
npm ci               # JS dependencies
npm run dev:watch    # Frontend dev build with watch (README's `npm run watch` does not exist)
```

## Common Commands

### Building
```bash
npm run build        # Production build into js/
npm run dev          # Development build
make appstore        # App store tarball (expects built js/ and vendor/)
```

### Linting
```bash
composer cs:check    # PHP coding standards check
composer cs:fix      # PHP coding standards fix
composer lint        # PHP syntax check
composer psalm       # PHP static analysis
composer rector      # Rector refactorings
npm run lint         # ESLint (JS/TS/Vue)
npm run lint:fix     # ESLint auto-fix
npm run stylelint    # SCSS/Vue style linting
npm run format       # Prettier check (format:fix to write)
npm run typecheck    # vue-tsc
```

### Testing
```bash
composer test:unit         # PHP unit tests
composer test:integration  # PHP integration tests
npm run playwright         # Playwright E2E tests
```

Both PHPUnit suites bootstrap a real Nextcloud (`tests/bootstrap.php` requires `../../../lib/base.php`), so they only run from inside a server checkout with the app enabled. The integration suite also needs the server reachable at `http://localhost:8080` (CI uses `php -S localhost:8080`): `tests/Integration/Api/ApiV3Test.php` makes real HTTP calls to the OCS API.

Playwright starts a Nextcloud Docker container on port 8089 (branch `stable<max-version>` from `info.xml`) via `npm run start:nextcloud` and tests the current `js/`, so build first. Specs are in `playwright/e2e/`, page objects in `playwright/support/sections/`, fixtures in `playwright/support/fixtures/`.

There are no frontend unit tests.

To run a single PHP test:
```bash
composer test:unit -- --filter testMethodName tests/Unit/Service/SubmissionServiceTest.php
```

### Code Generation
```bash
composer openapi     # Regenerate openapi.json from controller attributes and docblocks
```

## Architecture

### Backend (PHP — `lib/`)

- **`lib/Controller/`** — `ApiController` and `ShareApiController` are `OCSController`s exposing the REST API under `/ocs/v2.php/apps/forms/api/v3/…`. `PageController` serves the HTML shells and provides initial state. `ConfigController` handles the admin settings.
- **`lib/Service/`** — Business logic. `FormsService` owns permissions, access and sharing decisions; `SubmissionService` validates and stores answers and builds CSV/XLSX exports; `ConfirmationEmailService`, `CirclesService` and `UploadedFilesShareService` cover their integrations.
- **`lib/Db/`** — Entity + mapper pairs: Form, Question, Option, Share, Submission, Answer, UploadedFile.
- **`lib/Migration/`** — Schema migrations named `Version<MMmmpp>Date<YYYYMMDDHHMMSS>`.
- **`lib/Middleware/`** — `ThrottleFormAccessMiddleware` rate-limits access to public forms.
- **`lib/Events/` + `lib/Listener/`** — `FormSubmittedEvent` (exposed to webhooks); listeners for user deletion, Analytics datasource and comments.
- **`lib/BackgroundJob/`** — Confirmation mails, linked-file sync (`SyncSubmissionsWithLinkedFileJob`), upload cleanup, user-deletion cleanup.
- **`lib/Constants.php`** — Question types, `extraSettings` whitelists, string length limits and other shared constants.
- **`lib/ResponseDefinitions.php`** — Psalm array shapes for API responses; feeds `openapi.json`.

Routes are attribute-based (`#[ApiRoute]`, `#[FrontpageRoute]`); there is no `appinfo/routes.php`. Listeners and middleware are registered in `lib/AppInfo/Application.php`; activity, admin settings and background jobs in `appinfo/info.xml`.

### Frontend (Vue 3 — `src/`)

Built with **Vue 3**, **Vue Router** and **Vite**, without a store library. Entry points are defined in `vite.config.js`:

- **`main`** — the logged-in app: `src/Forms.vue` + `src/router.ts`, views `Create`, `Submit`, `Results` and `Sidebar` in `src/views/`.
- **`submit`** — the public link-share and embedded submission page (`src/FormsSubmit.vue`).
- **`settings`** — admin settings (`src/FormsSettings.vue`).
- **`emptyContent`** — empty state page.

Server data arrives via `@nextcloud/initial-state` keys provided in `PageController`; API responses are unwrapped with `src/utils/OcsResponse2Data.ts`. Entity types are hand-written in `src/types/Entities.d.ts`. Question components live in `src/components/Questions/`, shared question behaviour in `src/composables/`, results rendering in `src/components/Results/`.

### Database

CI runs the PHP tests against SQLite, MySQL, MariaDB, PostgreSQL and Oracle, so queries and migrations must be portable across all five.

### API

The v3 API is a public contract. `openapi.json` is generated — never edit it by hand. The human-readable documentation is `docs/API_v3.md` and `docs/DataStructure.md`; update them together with `openapi.json` whenever API behaviour changes.

## Commits

- All commits must be signed off (`git commit -s`) by the human contributor per the Developer Certificate of Origin (DCO), using their real name. PRs target `main`.
- Commit messages must follow the [Conventional Commits v1.0.0 specification](https://www.conventionalcommits.org/en/v1.0.0/#specification), in this repository's form `type(scope): lowercase description`, e.g. `fix(submission): only accept a list of values as an answer`, `feat(grid): …`. The message body explains why the change was made.
- CI rejects `fixup!` and `squash!` commits; squash them before the PR is merged.
- Every commit made with AI assistance must include the `Assisted-by` trailer mandated by the [AI Contribution Policy](https://github.com/nextcloud/.github/blob/master/AI_POLICY.md):

  ```
  Assisted-by: ClaudeCode:claude-opus-5-5
  ```

  General pattern: `Assisted-by: AGENT_NAME:MODEL_VERSION`.

## Pull Requests

- This repository has no pull request template of its own; use the [Nextcloud org-wide template](https://github.com/nextcloud/.github/blob/master/.github/pull_request_template.md) and keep its sections and checklist intact.
## Code Style

- After writing or modifying PHP code, run `composer cs:fix`, `composer psalm` and `composer lint` before considering the task complete.
- After writing or modifying frontend code, run `npm run lint`, `npm run stylelint`, `npm run typecheck` and `npm run build`.
- Use `t('forms', …)` / `n('forms', …)` for user-facing frontend strings and `IL10N` in PHP.

## Architecture Patterns

### Question types are defined twice

Adding or changing a question type means changing both sides:

- Backend: `lib/Constants.php` (`ANSWER_TYPE_*`, `ANSWER_TYPES`, `ANSWER_TYPES_PREDEFINED`, and the `EXTRA_SETTINGS_*` whitelist for that type's `extraSettings`) plus the per-type branches in `SubmissionService::validateSubmission()` and answer storage.
- Frontend: `src/models/AnswerTypes.ts`, which maps each type to its component, labels and icons. Several types share a component (`multiple`/`multiple_unique`, `date`/`datetime`/`time`).

### OpenAPI regeneration

Run `composer openapi` and commit the result after adding, removing or renaming a route, changing a controller method signature or its `@param`/`@return` docblock, or changing a shape in `lib/ResponseDefinitions.php`. CI fails if `openapi.json` is stale.

### Public endpoints

Endpoints reachable without login carry `#[PublicPage]` and are protected with `#[BruteForceProtection]` and `#[UserRateLimit]`/`#[AnonRateLimit]`; see the submission endpoints in `ApiController`. A change to submission or form access must work for logged-in users, public share links and embedded forms alike.

### Migrations

A new migration only runs after `<version>` in `appinfo/info.xml` is bumped. The class name encodes the app version it ships in (`Version050500…` = 5.5.0) plus a date stamp.

### Workflow files

Workflows in `.github/workflows/` are synced from the Nextcloud organization templates. Local deviations go in the matching `*.yml.patch` file, never directly in the workflow.
