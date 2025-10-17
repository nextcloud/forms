<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-only
-->

# Changelog

## v5.2.2 - 2025-10-17

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.2.1...v5.2.2)

### Fixed

- l10n(pt_BR): fixed a placeholder translation in the owner transfer dialog by @hdpoliveira

### Merged

- further translation updates
- dependency updates

## v5.2.1 - 2025-10-02

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.2.0...v5.2.1)

### Fixed

- fix(Results): enhance response loading logic based on active response view by @Chartman123 in [\#2944](https://github.com/nextcloud/forms/pull/2944)

## v5.2.0 - 2025-09-25

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.1.2...v5.2.0)

### Enhancements

- feat(docs): Add embed permission description for link shares in DataStructure.md by @Chartman123 in [\#2932](https://github.com/nextcloud/forms/pull/2932)
- feat: make full-text search case-insensitive and search by submission author as well by @Koc in [\#2761](https://github.com/nextcloud/forms/pull/2761)
- feat: add color question type by @Chartman123 in [\#2748](https://github.com/nextcloud/forms/pull/2748)
- feat: don't show default submission header if custom message is set by @Chartman123 in [\#2747](https://github.com/nextcloud/forms/pull/2747)
- feat: add form locking mechanism and share `edit` permission by @Chartman123 in [\#2737](https://github.com/nextcloud/forms/pull/2737)
- feat: add question name to form answer output by @susnux [\#2723](https://github.com/nextcloud/forms/pull/2723)
- feat: allow editing of submission by the user by @Chartman123 in [\#2715](https://github.com/nextcloud/forms/pull/2715)
- feat(time): Add time restrictions and range support by @Chartman123 in [\#2712](https://github.com/nextcloud/forms/pull/2712)
- feat: add pagination for submissions by @Koc in [\#2710](https://github.com/nextcloud/forms/pull/2710)
- feat: add linear scale questions by @Chartman123 in [\#2609](https://github.com/nextcloud/forms/pull/2609)

### Fixed

- fix(linear-scale): add custom wrapper instead of CSS hack by @susnux in [\#2914](https://github.com/nextcloud/forms/pull/2914)
- fix: remove unused props and fix event name in user select by @Chartman123 [\#2893](https://github.com/nextcloud/forms/pull/2893)
- fix: remove name from icon for better a11y by @Chartman123 [\#2881](https://github.com/nextcloud/forms/pull/2881)
- fix: Search field loses focus after search by @Koc in [\#2870](https://github.com/nextcloud/forms/pull/2870)
- fix: show toast on errors with empty response by @hamza221 in [\#2750](https://github.com/nextcloud/forms/pull/2750)
- fix: prevent modifications to archived forms in ApiController and ShareApiController by @Chartman123 in [\#2741](https://github.com/nextcloud/forms/pull/2741)
- fix: disable edit switch when the form is archived by @Chartman123 in [\#2739](https://github.com/nextcloud/forms/pull/2739)
- fix: Add missing documentation by @Chartman123 in [\#2735](https://github.com/nextcloud/forms/pull/2735)
- fix: only show extended label for linear scale questions by @Chartman123 in [\#2718](https://github.com/nextcloud/forms/pull/2718)

### Merged

- chore(QuestionMultiple): use v-model for better data binding by @Chartman123 in [\#2912](https://github.com/nextcloud/forms/pull/2912)
- style(icon): update icons to outline variants in TopBar and Results components by @Chartman123 [\#2894](https://github.com/nextcloud/forms/pull/2894)
- Migrate to outline, Material Symbols like variants by @AndyScherzinger [\#2892](https://github.com/nextcloud/forms/pull/2892)
- test: Allow a 10 seconds delta for lockedUntil in form tests by @Chartman123 [\#2891](https://github.com/nextcloud/forms/pull/2891)
- chore: unify debounce timing for input handling by @Chartman123 in [\#2721](https://github.com/nextcloud/forms/pull/2721)
- chore: adapt changes in nc-vue by @Chartman123 [\#2709](https://github.com/nextcloud/forms/pull/2709)

## v5.1.2 - 2025-08-19

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.1.1...v5.1.2)

### Fixed

- fix(export): escape CSV export for spreadsheet applications by @susnux in [\#2804](https://github.com/nextcloud/forms/pull/2804)

## v5.1.1 - 2025-08-19

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.1.0...v5.1.1)

### Fixed

- fix: Preserve intermediate changes when updating answer text by @Chartman123 in [\#2714](https://github.com/nextcloud/forms/pull/2714)
- fix: properly set string values in exports to escape formulas by @susnux in [\#2772](https://github.com/nextcloud/forms/pull/2772)
- fix(validation): Update validationType handling by @Chartman123 in [\#2685](https://github.com/nextcloud/forms/pull/2685)

### Merged

- chore(QuestionMultiple): update "Other" answer handling to use v-model and add change handler by @Chartman123 in [\#2689](https://github.com/nextcloud/forms/pull/2689)
- fix: address vue warnings in console by @Chartman123 in [\#2691](https://github.com/nextcloud/forms/pull/2691)
- chore: fix linting on CI by @susnux in [\#2703](https://github.com/nextcloud/forms/pull/2703)
- chore(QuestionDate): refactor handling of computed props by @Chartman123 in [\#2688](https://github.com/nextcloud/forms/pull/2688)
- chore: Simplify props usage by removing unnecessary bindings in various components by @Chartman123 in [\#2683](https://github.com/nextcloud/forms/pull/2683)
- Mention file type in docs by @Koc in [\#2686](https://github.com/nextcloud/forms/pull/2686)

## v5.1.0 - 2025-04-03

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.0.4...v5.1.0)

### Enhancements

- feat(date): allow restrictions for dates and query date ranges by @Chartman123 in [\#2646](https://github.com/nextcloud/forms/pull/2646)

### Fixed

- fix: quote cell values to prevent formula evaluation by @Chartman123 in [\#2674](https://github.com/nextcloud/forms/pull/2674)
- Fix: Handle invalid form hashes correctly by @Chartman123 in [\#2655](https://github.com/nextcloud/forms/pull/2655)

## v5.0.4 - 2025-03-20

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.0.3...v5.0.4)

### Fixed

- Fix(migration): Rename primary key of uploaded_files table by @Chartman123 in [\#2649](https://github.com/nextcloud/forms/pull/2649)

## v5.0.3 - 2025-03-11

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.0.2...v5.0.3)

### Fixed

- fix: parameter confusion in `update:answer` events for multiple questions by @Chartman123 in [\#2635](https://github.com/nextcloud/forms/pull/2635)
- fix: Update QRDialog title to include escape and sanitize options by @Chartman123 in [\#2633](https://github.com/nextcloud/forms/pull/2633)
- fix: misaligned result button by @pReya in [\#2618](https://github.com/nextcloud/forms/pull/2618)

## v5.0.2 - 2025-02-28

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.0.1...v5.0.2)

### Fixed

- fix: remove group restriction on server level by @Chartman123 in [\#2603](https://github.com/nextcloud/forms/pull/2603)

## v5.0.1 - 2025-02-28

[Full Changelog](https://github.com/nextcloud/forms/compare/v5.0.0...v5.0.1)

### Fixed

- fix(32bit): pin `maennchen/zipstream-php` dependency to v2 for 32-bit compatibility by @susnux in [\#2600](https://github.com/nextcloud/forms/pull/2600)

## v5.0.0 - 2025-02-25

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.3.0...v5.0.0)

### Enhancements

- feat: make options draggable by @Chartman123 in [\#2579](https://github.com/nextcloud/forms/pull/2579)
- feat: Improve error messages for invalid submissions by @Koc in [\#2533](https://github.com/nextcloud/forms/pull/2533)
- feat: Allow to reorder options of "checkbox" "radio" and "dropdown" question types in frontend by @susnux in [\#2092](https://github.com/nextcloud/forms/pull/2092)
- feat: replace drag icon for questions by @Chartman123 in [\#2584](https://github.com/nextcloud/forms/pull/2584)
- chore: make Forms OpenAPI compliant by @Chartman123 in [\#2358](https://github.com/nextcloud/forms/pull/2358)
- feat: allow cloning archived forms by @Chartman123 in [\#2490](https://github.com/nextcloud/forms/pull/2490)
- feat: integration of unified search by @Chartman123 in [\#2479](https://github.com/nextcloud/forms/pull/2479)
- feat: Ask for restarting submission if form was changed by @Koc in [\#2319](https://github.com/nextcloud/forms/pull/2319)

### Fixed

- Fix(routes): Add hash requirements for frontpage routes by @Chartman123 in [\#2555](https://github.com/nextcloud/forms/pull/2555)
- fix: Add brute force protection to form endpoints by @susnux in [\#2269](https://github.com/nextcloud/forms/pull/2269)
- Fix: only show confirmation dialog for active forms by @Chartman123 in [\#2504](https://github.com/nextcloud/forms/pull/2504)
- fix: subtraction in access_enum by @Chartman123 in [\#2501](https://github.com/nextcloud/forms/pull/2501)
- Fix misaligned form elements by @Elsensee in [\#2578](https://github.com/nextcloud/forms/pull/2578)

### Merged

- Chore(api): Add CORS support description to multiple endpoints by @Chartman123 in [\#2592](https://github.com/nextcloud/forms/pull/2592)
- Chore(psalm): Update psalm settings and baseline by @Chartman123 in [\#2550](https://github.com/nextcloud/forms/pull/2550)
- fix(migration): Replace execute() with executeQuery() and executeStatement() for improved query execution by @Chartman123 in [\#2551](https://github.com/nextcloud/forms/pull/2551)
- chore: remove unused access variable in hasPublicLink method by @Chartman123 in [\#2478](https://github.com/nextcloud/forms/pull/2478)
- chore: remove API v2 by @Chartman123 in [\#2351](https://github.com/nextcloud/forms/pull/2351)
- chore: Add reuse compliance by @hamza221 in [\#2455](https://github.com/nextcloud/forms/pull/2455)
- chore: use attributes to define routes by @Chartman123 in [\#2353](https://github.com/nextcloud/forms/pull/2353)
- chore: remove legacy link support by @Chartman123 in [\#2355](https://github.com/nextcloud/forms/pull/2355)
- chore: set min NC30 and add stable4 to dependabot by @Chartman123 in [\#2352](https://github.com/nextcloud/forms/pull/2352)

### Known Issues

- Menu for re-ordering options must be improved (doesn't always keep focus)

## v4.3.8 - 2025-02-24

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.3.7...v4.3.8)

### Fixed

- Fix: Add disabled state to PillMenu and update Results view when there are no submissions by @Chartman123 in [\#2530](https://github.com/nextcloud/forms/pull/2530)
  forms/pull/2580)
- fix: remove linked file from cloned form by @Chartman123 in [\#2581](https://github.com/nextcloud/forms/pull/2581)
- fix: Improve compatibility with Windows for uploaded files by @Koc in [\#2513](https://github.com/nextcloud/forms/pull/2513)

### Merged

- chore(stable4): Move to min version 29 by @Chartman123 in [\#2575](https://github.com/nextcloud/forms/pull/2575)

## v4.3.7 - 2025-02-14

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.3.6...v4.3.7)

### Fixed

- Fix: Implement debounced input handling for AnswerInput component by @Chartman123 in [\#2553](https://github.com/nextcloud/forms/pull/2553)
- fix: Correct validationTypeMenuId to use local index instead of $attrs by @Chartman123 in [\#2549](https://github.com/nextcloud/forms/pull/2549)

### Merged

- Chore: Refactor submission handling in Results.vue by @Chartman123 in [\#2541](https://github.com/nextcloud/forms/pull/2541)

## v4.3.6 - 2025-01-31

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.3.5...v4.3.6)

### Fixed

- Fix: Show sidebar toggle in all views by @Chartman123 in [\#2532](https://github.com/nextcloud/forms/pull/2532)
- fix: Fix spreadsheet unlinking by @Koc in [\#2534](https://github.com/nextcloud/forms/pull/2534)

## v4.3.5 - 2025-01-11

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.3.4...v4.3.5)

### Fixed

- fix: Check admin settings when fetching shared forms by @susnux in [\#2485](https://github.com/nextcloud/forms/pull/2485)
- Fix: Typo broke background sync by @toad in [\#2470](https://github.com/nextcloud/forms/pull/2470)
- Fix translation problem in notifications by @Chartman123 in [\#2447](https://github.com/nextcloud/forms/pull/2447)

## v4.3.4 - 2024-11-25

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.3.3...v4.3.4)

### Fixed

- fix: simplify mime-type checks to support jpg and other image formats by @Koc in [\#2401](https://github.com/nextcloud/forms/pull/2401)

### Merged

- feat: Refactor form sync to run as a background job with retry by @AIlkiv in [\#2408](https://github.com/nextcloud/forms/pull/2408)

## v4.3.3 - 2024-11-11

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.3.2...v4.3.3)

### Fixed

- Add padding and max width so they don't stick to right screen border by @Elsensee in [\#2400](https://github.com/nextcloud/forms/pull/2400)
- (fix) default timezone for export by @AIlkiv in [\#2397](https://github.com/nextcloud/forms/pull/2397)

## v4.3.2 - 2024-10-20

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.3.1...v4.3.2)

### Fixed

- fix: Update transfer ownership logic by @Chartman123 in [\#2371](https://github.com/nextcloud/forms/pull/2371)
- fix: show expiration message again in submit view by @Chartman123 in [\#2359](https://github.com/nextcloud/forms/pull/2359)

## v4.3.1 - 2024-10-05

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.3.0...v4.3.1)

### Fixed

- fix(stable4): add missing annotations by @Chartman123 in [\#2354](https://github.com/nextcloud/forms/pull/2354)

## v4.3.0 - 2024-10-04

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.2.4...v4.3.0)

### Enhancements

- feat: Allow to reorder options for "multiple" question type in backend by @Chartman123 in [\#2333](https://github.com/nextcloud/forms/pull/2333)
- Add support for file question by @Koc in [\#2040](https://github.com/nextcloud/forms/pull/2040)
- feat: Allow listening to form submissions via events and webhooks by @marcelklehr in [\#2265](https://github.com/nextcloud/forms/pull/2265)
- enh: make show to all users an admin setting by @Chartman123 in [\#2306](https://github.com/nextcloud/forms/pull/2306)
- fix: add `target="_blank"` to links in description by @Chartman123 in [\#2280](https://github.com/nextcloud/forms/pull/2280)
- feat: add warning about removing legacy links by @Chartman123 in [\#2277](https://github.com/nextcloud/forms/pull/2277)
- Add QR-Code for Share Links by @Himmelxd in [\#2162](https://github.com/nextcloud/forms/pull/2162)
- feat: add multiple options with one paste by @hamza221 in [\#1407](https://github.com/nextcloud/forms/pull/1407)

### Fixed

- fix(export): remove new lines from form title in the exported filename by @tcitworld in [\#2343](https://github.com/nextcloud/forms/pull/2343)
- fix: update values in QuestionMultiple component correctly when `isUnique === true` by @Chartman123 in [\#2323](https://github.com/nextcloud/forms/pull/2323)
- Do not submit fields that not exists anymore by @Koc in [\#2312](https://github.com/nextcloud/forms/pull/2312)
- Fix: Show complete title in TransferOwnership dialog by @Chartman123 in [\#2292](https://github.com/nextcloud/forms/pull/2292)
- fix(submit): `access` is unset for public forms so check for existance first by @susnux in [\#2291](https://github.com/nextcloud/forms/pull/2291)
- Bug. Question type File. When multiple types are selected, only one is used. by @AIlkiv in [\#2241](https://github.com/nextcloud/forms/pull/2241)
- fix: Improve styles of layout for QuestionFile #2253 by @Koc in [\#2259](https://github.com/nextcloud/forms/pull/2259)
- fix: Adjust app to be compatible with Nextcloud 30 by @susnux in [\#2278](https://github.com/nextcloud/forms/pull/2278)
- fix: Fix form view without permissions by @Koc in [\#2268](https://github.com/nextcloud/forms/pull/2268)
- fix: Add support for adding new entries with IME input by @Chartman123 in [\#2232](https://github.com/nextcloud/forms/pull/2232)
- fix: Fix merging of options for cancelable request by @Koc in [\#2260](https://github.com/nextcloud/forms/pull/2260)
- fix: Correctly label forms lists in the app navigation by @susnux in [\#2208](https://github.com/nextcloud/forms/pull/2208)

### Merged

- chore: add API v3 by @Chartman123 in [\#2222](https://github.com/nextcloud/forms/pull/2222)
- Data source for the Analytics App by @Rello in [\#2195](https://github.com/nextcloud/forms/pull/2195)
- Optimization method FormsService::canSubmit by @AIlkiv in [\#2225](https://github.com/nextcloud/forms/pull/2225)
- Replace app icon with Material Symbols version by @AndyScherzinger in [\#2233](https://github.com/nextcloud/forms/pull/2233)
- Drop NC27 support for dependabot by @Chartman123 in [\#2223](https://github.com/nextcloud/forms/pull/2223)
- fix(i18n): Fixed grammar by @rakekniven in [\#2224](https://github.com/nextcloud/forms/pull/2224)
- feat: Switch to PlayWright for E2E and component tests by @susnux in [\#2077](https://github.com/nextcloud/forms/pull/2077)
- chore: Use prettier for stylistic rules by @Chartman123 in [\#2143](https://github.com/nextcloud/forms/pull/2143)

## v4.2.4 - 2024-05-24

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.2.3...v4.2.4)

### Fixed

- fix(a11y): Add missing page headings by @susnux in [\#2151](https://github.com/nextcloud/forms/pull/2151)
- fix(a11y): Add missing label for `nav` element by @susnux in [\#2148](https://github.com/nextcloud/forms/pull/2148)
- fix: Adjust code for `@nextcloud/vue` 8.12 providing native app sidebar toggle by @susnux in [\#2170](https://github.com/nextcloud/forms/pull/2170)
- fix: add icon to title of required questions in edit mode by @Chartman123 in [\#2099](https://github.com/nextcloud/forms/pull/2099)
- fix(i18n): Aligned grammar by @rakekniven in [\#2095](https://github.com/nextcloud/forms/pull/2095)
- fix: Parse momentFormat and storageFormat by @Chartman123 in [\#2093](https://github.com/nextcloud/forms/pull/2093)

## v4.2.3 - 2024-04-16

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.2.2...v4.2.3)

### Merged

- Fix fetching shared forms and return array values in ApiController.php by @Chartman123 in [\#2076](https://github.com/nextcloud/forms/pull/2076)
- Fix toggle between Summary and Responses Submission overview by @Koc in [\#2073](https://github.com/nextcloud/forms/pull/2073)

## v4.2.2 - 2024-04-15

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.2.1...v4.2.2)

### Fixed

- fix(DB): Correctly fetch shared forms by @susnux in [\#2069](https://github.com/nextcloud/forms/pull/2069)
- fix(Form): If `permitAllUsers` is not set then no public access is granted by @susnux in [\#2070](https://github.com/nextcloud/forms/pull/2070)

## v4.2.1 - 2024-04-15

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.2.0...v4.2.1)

### Fixed

- fix: remove setup() and move code to data() in Results.vue by @Chartman123 in [\#2065](https://github.com/nextcloud/forms/pull/2065)
- fix: Move non-reactive props and composables to `setup` by @susnux in [\#2068](https://github.com/nextcloud/forms/pull/2068)
- Set lastUpdated on link/unlink file by @Chartman123 in [\#2066](https://github.com/nextcloud/forms/pull/2066)
- fix: `legacyLink` access handling by @Chartman123 in [\#2060](https://github.com/nextcloud/forms/pull/2060)
- fix: don't filter expired forms in navigation by @Chartman123 in [\#2062](https://github.com/nextcloud/forms/pull/2062)

## v4.2.0 - 2024-04-14

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.1.1...v4.2.0)

### Enhancements

- Get only forms shared with user from database [\#2029](https://github.com/nextcloud/forms/pull/2029) ([Chartman123](https://github.com/Chartman123))
- Allow embedding forms within other websites [\#1353](https://github.com/nextcloud/forms/pull/1353) ([susnux](https://github.com/susnux))
- feat: Add forms `state` to close and archive forms [\#1925](https://github.com/nextcloud/forms/pull/1925) ([susnux](https://github.com/susnux))

### Fixed

- fix: remove positive lookbehind for finding unescaped slashes [\#2033](https://github.com/nextcloud/forms/pull/2033) ([Chartman123](https://github.com/Chartman123))
- fix: Do not show trailing button for technical name input [\#2021](https://github.com/nextcloud/forms/pull/2021) ([susnux](https://github.com/susnux))
- Show expired shared forms to users with results permission [\#2013](https://github.com/nextcloud/forms/pull/2013) ([Chartman123](https://github.com/Chartman123))
- fix(TopBar): provide optional button text via new slot syntax [\#2015](https://github.com/nextcloud/forms/pull/2015) ([ShGKme](https://github.com/ShGKme))

### Merged

- chore: Add Nextcloud 29 support [\#2003](https://github.com/nextcloud/forms/pull/2003) ([susnux](https://github.com/susnux))
- Rename Circles to Teams in UI/logging [\#2000](https://github.com/nextcloud/forms/pull/2000) ([Chartman123](https://github.com/Chartman123))
- Reuse top bar component for responses view toggle [\#1576](https://github.com/nextcloud/forms/pull/1576) ([susnux](https://github.com/susnux))
- Update dependencies and translations

## v4.1.1 - 2024-02-18

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.1.0...v4.1.1)

### Fixed

- fix: Update moment format for date parsing [\#1969](https://github.com/nextcloud/forms/pull/1969) ([Chartman123](https://github.com/Chartman123))
- fix: Load styles also for settings section [\#1963](https://github.com/nextcloud/forms/pull/1963) ([susnux](https://github.com/susnux))

## v4.1.0 - 2024-02-02

[Full Changelog](https://github.com/nextcloud/forms/compare/v4.0.0...v4.1.0)

### Enhancements

- enh: Use NcDialog for confirmation when deleting all submissions [\#1910](https://github.com/nextcloud/forms/pull/1910) ([Chartman123](https://github.com/Chartman123))
- enh: Use NcDialog to confirm leaving [\#1880](https://github.com/nextcloud/forms/pull/1880) ([Chartman123](https://github.com/Chartman123))
- Added possibility to link spreadsheet for automatic submission export in multiple formats [\#1758](https://github.com/nextcloud/forms/pull/1758) ([Koc](https://github.com/Koc))
- enh: Better text contrast for form description (closes #1878) [\#1881](https://github.com/nextcloud/forms/pull/1881) ([mschmidm](https://github.com/mschmidm))
- enh: Replace confirm dialog for deletion with NcDialog [\#1663](https://github.com/nextcloud/forms/pull/1663) ([Chartman123](https://github.com/Chartman123))
- Question duplication [\#1423](https://github.com/nextcloud/forms/pull/1423) ([KaasKop97](https://github.com/KaasKop97))

### Fixed

- Fix sharing form [\#1907](https://github.com/nextcloud/forms/pull/1907) ([avinash-0007](https://github.com/avinash-0007))
- fix empty content public page view [\#1904](https://github.com/nextcloud/forms/pull/1904) ([Chartman123](https://github.com/Chartman123))
- stop filtering result while sharing [\#1895](https://github.com/nextcloud/forms/pull/1895) ([avinash-0007](https://github.com/avinash-0007))
- fix: Prevent race condition on unique-submission forms [\#1841](https://github.com/nextcloud/forms/pull/1841) ([susnux](https://github.com/susnux))

### Merged

- Update API version to v2.4 [\#1932](https://github.com/nextcloud/forms/pull/1932) ([Chartman123](https://github.com/Chartman123))
- refactoring: move code to separate function storeAnswersForQuestion [\#1866](https://github.com/nextcloud/forms/pull/1866) ([tpokorra](https://github.com/tpokorra))
- chore: replace isMobile mixin with useIsMobile composable [\#1863](https://github.com/nextcloud/forms/pull/1863) ([Chartman123](https://github.com/Chartman123))

## v4.0.0 - 2023-12-12

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.4.0...v4.0.0)

### Enhancements

- transfer ownership of a form [\#1416](https://github.com/nextcloud/forms/pull/1416) ([hamza221](https://github.com/hamza221))
- Add Forms to header title on public link view [\#1828](https://github.com/nextcloud/forms/pull/1828) ([Chartman123](https://github.com/Chartman123))
- Allow to set `results_delete` permission on the frontend [\#1805](https://github.com/nextcloud/forms/pull/1805) ([susnux](https://github.com/susnux))
- use PUT/PATCH for updating and move to API v2.2 [\#1809](https://github.com/nextcloud/forms/pull/1809) ([Chartman123](https://github.com/Chartman123))
- Show confirmation dialog before submitting an empty form [\#1803](https://github.com/nextcloud/forms/pull/1803) ([susnux](https://github.com/susnux))
- Move away from deprecated icon classes and allow to search user by email [\#1802](https://github.com/nextcloud/forms/pull/1802) ([susnux](https://github.com/susnux))

### Fixed

- Remove deprecated setModal [\#1826](https://github.com/nextcloud/forms/pull/1826) ([hamza221](https://github.com/hamza221))
- fix(QuestionMultiple): Fix setting the `allowOtherAnswer` option [\#1800](https://github.com/nextcloud/forms/pull/1800) ([susnux](https://github.com/susnux))
- Fix missing column in DataStructure.md [\#1791](https://github.com/nextcloud/forms/pull/1791) ([Chartman123](https://github.com/Chartman123))
- fix: add canMoveUp/Down props to QuestionMixin [\#1806](https://github.com/nextcloud/forms/pull/1806) ([Chartman123](https://github.com/Chartman123

### Merged

- Migrate from webpack to vite for building the frontend [\#1827](https://github.com/nextcloud/forms/pull/1827) ([susnux](https://github.com/susnux))))
- Make components compatible to nextcloud-vue 8 [\#1696](https://github.com/nextcloud/forms/pull/1696) ([Chartman123](https://github.com/Chartman123))
- Min server 28 [\#1796](https://github.com/nextcloud/forms/pull/1796) ([Chartman123](https://github.com/Chartman123))

## v3.4.0 - 2023-11-27

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.3.1...v3.4.0)

### Breaking

- Move to min-version 26 and drop PHP 7.4 [\#1727](https://github.com/nextcloud/forms/pull/1727) ([Chartman123](https://github.com/Chartman123))

### Enhancements

- Add subtypes for short input, like email, phone or custom regex [\#1491](https://github.com/nextcloud/forms/pull/1491) ([susnux](https://github.com/susnux))
- Remember input in LocalStorage [\#1382](https://github.com/nextcloud/forms/pull/1382) ([hamza221](https://github.com/hamza221))
- Make form editable with keyboard [\#1750](https://github.com/nextcloud/forms/pull/1750) ([Chartman123](https://github.com/Chartman123))
- Allow reordering questions using the keyboard [\#1532](https://github.com/nextcloud/forms/pull/1532) ([susnux](https://github.com/susnux))
- feat: Implement custom submission message [\#1659](https://github.com/nextcloud/forms/pull/1659) ([susnux](https://github.com/susnux))
- feat(ActivityManager): Send notification about new submissions to circle memebers [\#1746](https://github.com/nextcloud/forms/pull/1746) ([susnux](https://github.com/susnux))
- Implement warning when leaving an unsubmitted form [\#1310](https://github.com/nextcloud/forms/pull/1310) ([Chartman123](https://github.com/Chartman123))
- Feature: Allow to share forms with Circles [\#1467](https://github.com/nextcloud/forms/pull/1467) ([susnux](https://github.com/susnux))
- Notify users on new submissions for shared forms [\#1496](https://github.com/nextcloud/forms/pull/1496) ([susnux](https://github.com/susnux))
- Optimization of FormMapper::findById calls [\#1707](https://github.com/nextcloud/forms/pull/1707) ([AIlkiv](https://github.com/AIlkiv))
- feat: Add 'Other' option for radio/checkbox questions. [\#1694](https://github.com/nextcloud/forms/pull/1694) ([AIlkiv](https://github.com/AIlkiv))
- Reduce white space between questions [\#1658](https://github.com/nextcloud/forms/pull/1658) ([Chartman123](https://github.com/Chartman123))
- Add technical identifiers for questions [\#1553](https://github.com/nextcloud/forms/pull/1553) ([susnux](https://github.com/susnux))
- Support RTL languages - migrate css from physical to logical positioning [\#1654](https://github.com/nextcloud/forms/pull/1654) ([susnux](https://github.com/susnux))

### Fixed

- fix: Enhance extraSettings handling and fix XML output [\#1705](https://github.com/nextcloud/forms/pull/1705) ([Chartman123](https://github.com/Chartman123))
- fix: Warning about missing label for other answer [\#1731](https://github.com/nextcloud/forms/pull/1731) ([Chartman123](https://github.com/Chartman123))
- fix(docs): Add changes of API 2.1 to the API docs [\#1745](https://github.com/nextcloud/forms/pull/1745) ([susnux](https://github.com/susnux))
- fix(Submit): Make `answers` reactive and fix invalid mutation of computed property [\#1786](https://github.com/nextcloud/forms/pull/1786) ([susnux](https://github.com/susnux))
- fix: Fix import of debounce [\#1784](https://github.com/nextcloud/forms/pull/1784) ([susnux](https://github.com/susnux))
- Fix inverted sorting in frontend for shared forms [\#1759](https://github.com/nextcloud/forms/pull/1759) ([Chartman123](https://github.com/Chartman123))
- fix: Handle questions props as props and not as attrs [\#1763](https://github.com/nextcloud/forms/pull/1763) ([susnux](https://github.com/susnux))
- fix: Make sure "other" answers are correctly handled [\#1764](https://github.com/nextcloud/forms/pull/1764) ([susnux](https://github.com/susnux))
- fix: Sanitize file name when writing a CSV file [\#1660](https://github.com/nextcloud/forms/pull/1660) ([susnux](https://github.com/susnux))
- fix: no styling applied to h1-headings in markdown (closes #1668) [\#1743](https://github.com/nextcloud/forms/pull/1743) ([mschmidm](https://github.com/mschmidm))
- fix(tests): Update phpunit workflow to fix OCI tests [\#1729](https://github.com/nextcloud/forms/pull/1729) ([susnux](https://github.com/susnux))
- fix(lint): add missing trailing commas [\#1770](https://github.com/nextcloud/forms/pull/1770) ([Chartman123](https://github.com/Chartman123))
- fix: Incorrect type for empty extraSettings in frontend [\#1730](https://github.com/nextcloud/forms/pull/1730) ([Chartman123](https://github.com/Chartman123))

### Merged

- Move parameter typing for extraSettings [\#1769](https://github.com/nextcloud/forms/pull/1769) ([Chartman123](https://github.com/Chartman123))
- Update README.md [\#1739](https://github.com/nextcloud/forms/pull/1739) ([Chartman123](https://github.com/Chartman123))
- Increase font size to default 15px [\#1738](https://github.com/nextcloud/forms/pull/1738) ([marcoambrosini](https://github.com/marcoambrosini))
- Replace outdated screenshots in Readme.md [\#1736](https://github.com/nextcloud/forms/pull/1736) ([Chartman123](https://github.com/Chartman123))
- chore: replace deprecated qb->execute() with executeStatement() [\#1706](https://github.com/nextcloud/forms/pull/1706) ([Chartman123](https://github.com/Chartman123))
- Added test for insertSubmission in ApiController [\#1704](https://github.com/nextcloud/forms/pull/1704) ([AIlkiv](https://github.com/AIlkiv))
- Add pr feedback action [\#1703](https://github.com/nextcloud/forms/pull/1703) ([Fenn-CS](https://github.com/Fenn-CS))
- phpunit: use custom db images to fix rate limiting [\#1685](https://github.com/nextcloud/forms/pull/1685) ([Chartman123](https://github.com/Chartman123))
- Add healtcheck options to PHPUnit for OCI [\#1678](https://github.com/nextcloud/forms/pull/1678) ([Chartman123](https://github.com/Chartman123))

## 3.3.1 - 2023-06-23

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.3.0...v3.3.1)

### Fixed

- Allow to right click share-link buttons to copy link manually [\#1653](https://github.com/nextcloud/forms/pull/1653) ([susnux](https://github.com/susnux))

### Merged

- Drop 'Nextcloud' from connected responses string [\#1635](https://github.com/nextcloud/forms/pull/1635) ([Chartman123](https://github.com/Chartman123))
- Updated translations
- Updated dependencies

## 3.3.0 - 2023-05-22

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.2.0...v3.3.0)

### Enhancements

- Improve and unify Markdown output style [\#1575](https://github.com/nextcloud/forms/pull/1575) ([susnux](https://github.com/susnux))

### Fixed

- fix(markdown): Add margin for all new paragraphs [\#1568](https://github.com/nextcloud/forms/pull/1568) ([susnux](https://github.com/susnux))
- Place actions popover so it does not overflow the page [\#1554](https://github.com/nextcloud/forms/pull/1554) ([susnux](https://github.com/susnux))
- fix: Replace `NcRichContenteditable` with `textarea` [\#1574](https://github.com/nextcloud/forms/pull/1574) ([susnux](https://github.com/susnux))

### Merged

- Prepare for NC27 [\#1625](https://github.com/nextcloud/forms/pull/1625) ([Chartman123](https://github.com/Chartman123))
- Replace deprecated code and fix issues found by static code analysis [\#1577](https://github.com/nextcloud/forms/pull/1577) ([susnux](https://github.com/susnux))

## 3.2.0 - 2023-03-06

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.1.0...v3.2.0)

### Enhancements

- Make timestamp in csv export ISO 8601 compliant [\#1531](https://github.com/nextcloud/forms/pull/1531) ([Chartman123](https://github.com/Chartman123))
- Add lastUpdated property to Form [\#1479](https://github.com/nextcloud/forms/pull/1479) ([Chartman123](https://github.com/Chartman123))

### Fixed

- Fix paragraph rendering [\#1542](https://github.com/nextcloud/forms/pull/1542) ([jotoeri](https://github.com/jotoeri))
- Move API to v2.1 [\#1539](https://github.com/nextcloud/forms/pull/1539) ([Chartman123](https://github.com/Chartman123))
- Make skip to content buttons work [\#1530](https://github.com/nextcloud/forms/pull/1530) ([susnux](https://github.com/susnux))

### Merged

- Replace deprecated NcMultiselect with recommended NcSelect [\#1471](https://github.com/nextcloud/forms/pull/1471) ([susnux](https://github.com/susnux))
- Added test for `cloneForm` in `ApiController` [\#1488](https://github.com/nextcloud/forms/pull/1488) ([susnux](https://github.com/susnux))
- Add information about API to appinfo [\#1529](https://github.com/nextcloud/forms/pull/1529) ([susnux](https://github.com/susnux))

## 3.1.0 - 2023-02-20

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.0.4...v3.1.0)

### Enhancements

- Allow formatting question and form descriptions using markdown [\#1394](https://github.com/nextcloud/forms/pull/1394) ([susnux](https://github.com/susnux))
- Add Nextcloud 26 support [\#1489](https://github.com/nextcloud/forms/pull/1489) ([susnux](https://github.com/susnux))
- Add test for method `newForm` of `ApiController` [\#1480](https://github.com/nextcloud/forms/pull/1480) ([susnux](https://github.com/susnux))
- Drop `v-clipboard` in favor of native browser API [\#1478](https://github.com/nextcloud/forms/pull/1478) ([susnux](https://github.com/susnux))
- Allow sharees to see results | Implement `PERMISSION_RESULTS` [\#1461](https://github.com/nextcloud/forms/pull/1461) ([susnux](https://github.com/susnux))
- feat: Add slot for additional actions to the question component [\#1470](https://github.com/nextcloud/forms/pull/1470) ([susnux](https://github.com/susnux))

### Fixed

- Fix Create edit mode [\#1498](https://github.com/nextcloud/forms/pull/1498) ([jotoeri](https://github.com/jotoeri))
- Fix public view [\#1492](https://github.com/nextcloud/forms/pull/1492) ([jotoeri](https://github.com/jotoeri))
- Fix some small `SubmissionService` issues [\#1490](https://github.com/nextcloud/forms/pull/1490) ([susnux](https://github.com/susnux))
- Add missing computed property to edit view [\#1473](https://github.com/nextcloud/forms/pull/1473) ([Chartman123](https://github.com/Chartman123))

### Merged

- Add shareHash parameter [\#1476](https://github.com/nextcloud/forms/pull/1476) ([Chartman123](https://github.com/Chartman123))

## 3.0.4 - 2023-01-31

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.0.3...v3.0.4)

### Enhancements

- Show relative expiration date inside form [\#1432](https://github.com/nextcloud/forms/pull/1432) ([Chartman123](https://github.com/Chartman123))

### Fixed

- Fix insert [\#1465](https://github.com/nextcloud/forms/pull/1465) ([jotoeri](https://github.com/jotoeri))
- Fix cors on ShareApi [\#1463](https://github.com/nextcloud/forms/pull/1463) ([jotoeri](https://github.com/jotoeri))
- Fix public insert [\#1456](https://github.com/nextcloud/forms/pull/1456) ([jotoeri](https://github.com/jotoeri))
- Import form question extra settings [\#1440](https://github.com/nextcloud/forms/pull/1440) ([Copephobia](https://github.com/Copephobia))

### Merged

- Show info message only if available [\#1441](https://github.com/nextcloud/forms/pull/1441) ([susnux](https://github.com/susnux))
- Update phpunit.yml to work with current Server master [\#1460](https://github.com/nextcloud/forms/pull/1460) ([Chartman123](https://github.com/Chartman123))
- Enable PHPUnit integration tests and fix them [\#1449](https://github.com/nextcloud/forms/pull/1449) ([Chartman123](https://github.com/Chartman123))
- Scope SCSS in Create.vue and adjust Results.vue [\#1447](https://github.com/nextcloud/forms/pull/1447) ([Chartman123](https://github.com/Chartman123))
- Fix Typo [\#1450](https://github.com/nextcloud/forms/pull/1450) ([jotoeri](https://github.com/jotoeri))

## 3.0.3 - 2022-12-30

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.0.2...v3.0.3)

### Fixed

- Fix export [\#1433](https://github.com/nextcloud/forms/pull/1433) ([jotoeri](https://github.com/jotoeri))

## 3.0.2 - 2022-12-23

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.0.1...v3.0.2)

### Fixed

- Fix question header title for extra long text [\#1393](https://github.com/nextcloud/forms/pull/1393) ([susnux](https://github.com/susnux))
- make forms OCS API CORS compatible [\#1139](https://github.com/nextcloud/forms/pull/1139) ([everlanes](https://github.com/everlanes))
- Init all answers from props [\#1383](https://github.com/nextcloud/forms/pull/1383) ([susnux](https://github.com/susnux))
- More usable and accessible navigation between View/Edit/Results [\#1381](https://github.com/nextcloud/forms/pull/1381) ([jancborchardt](https://github.com/jancborchardt))

## 3.0.1 - 2022-10-25

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.0.0...v3.0.1)

### Fixed

- Fix `setExtraSettings` to accept same type as `getExtraSettings` [\#1391](https://github.com/nextcloud/forms/pull/1391) ([susnux](https://github.com/susnux))
- Use user's timezone for timestamps in CSV export [\#1389](https://github.com/nextcloud/forms/pull/1389) ([Chartman123](https://github.com/Chartman123))

### Merged

- Fix CI runs for postgres [\#1388](https://github.com/nextcloud/forms/pull/1388) ([Chartman123](https://github.com/Chartman123))

## 3.0.0 - 2022-10-13

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.5.1...v3.0.0)

### Breaking ⚠️

- Moving completely from API v1 to v2. With this, we fundamentally change the way how the forms sharing works, now much more flexible and closer to how it is done in server.
- Also inverting SubmitOnce to SubmitMultiple with ApiV2
- The question Type `datetime` has been replaced by `date` and `time` question types. Existing questions remain usable, but no `datetime` questions can be created anymore.

### Enhancements

- Use NcCheckboxRadioSwitch for QuestionMultiple [\#1322](https://github.com/nextcloud/forms/pull/1322) ([Chartman123](https://github.com/Chartman123))
- Make dropdown searchable [\#1342](https://github.com/nextcloud/forms/pull/1342) ([Chartman123](https://github.com/Chartman123))
- Add UI to preview and fill out own forms [\#1320](https://github.com/nextcloud/forms/pull/1320) ([susnux](https://github.com/susnux))
- Make CHANGELOG.md parseable by the appstore [\#1306](https://github.com/nextcloud/forms/pull/1306) ([Chartman123](https://github.com/Chartman123))
- More Icons! [\#1305](https://github.com/nextcloud/forms/pull/1305) ([jotoeri](https://github.com/jotoeri))
- Move Question Icons to Material Design [\#1304](https://github.com/nextcloud/forms/pull/1304) ([jotoeri](https://github.com/jotoeri))
- Update Forms Icon and create Component [\#1303](https://github.com/nextcloud/forms/pull/1303) ([jotoeri](https://github.com/jotoeri))
- Feature: Allow shuffling of answer options | Sort options [\#1271](https://github.com/nextcloud/forms/pull/1271) ([susnux](https://github.com/susnux))
- User migration [\#1243](https://github.com/nextcloud/forms/pull/1243) ([jotoeri](https://github.com/jotoeri))
- Add UID to export [\#1204](https://github.com/nextcloud/forms/pull/1204) ([jotoeri](https://github.com/jotoeri))
- Restrict Form Creation & Sharing Settings [\#1199](https://github.com/nextcloud/forms/pull/1199) ([jotoeri](https://github.com/jotoeri))
- Add Question Description [\#1172](https://github.com/nextcloud/forms/pull/1172) ([jotoeri](https://github.com/jotoeri))
- Rework Navigation [\#1168](https://github.com/nextcloud/forms/pull/1168) ([Chartman123](https://github.com/Chartman123))
- New Sharing [\#1087](https://github.com/nextcloud/forms/pull/1087) ([jotoeri](https://github.com/jotoeri))
- Make date/time answers consistent [\#1001](https://github.com/nextcloud/forms/pull/1001) ([Chartman123](https://github.com/Chartman123))

### Fixed

- Remove isDropdown condition [\#1368](https://github.com/nextcloud/forms/pull/1368) ([Chartman123](https://github.com/Chartman123))
- Fix public View [\#1365](https://github.com/nextcloud/forms/pull/1365) ([jotoeri](https://github.com/jotoeri))
- Adjust look of long text questions [\#1364](https://github.com/nextcloud/forms/pull/1364) ([Chartman123](https://github.com/Chartman123))
- Fix datepicker height [\#1363](https://github.com/nextcloud/forms/pull/1363) ([jotoeri](https://github.com/jotoeri))
- Fix upgrading [\#1361](https://github.com/nextcloud/forms/pull/1361) ([jotoeri](https://github.com/jotoeri))
- Remove css variables [\#1360](https://github.com/nextcloud/forms/pull/1360) ([jotoeri](https://github.com/jotoeri))
- Fix Actions Container [\#1359](https://github.com/nextcloud/forms/pull/1359) ([jotoeri](https://github.com/jotoeri))
- Use `--gradient-primary-background` for summary [\#1356](https://github.com/nextcloud/forms/pull/1356) ([Chartman123](https://github.com/Chartman123))
- Fix alignment of "Add question" button [\#1351](https://github.com/nextcloud/forms/pull/1351) ([Chartman123](https://github.com/Chartman123))
- Adjust design based on Design Review [\#1344](https://github.com/nextcloud/forms/pull/1344) ([Chartman123](https://github.com/Chartman123))
- Fixing several small things [\#1335](https://github.com/nextcloud/forms/pull/1335) ([jotoeri](https://github.com/jotoeri))
- Fix migration 20220414 not working on SQLite [\#1334](https://github.com/nextcloud/forms/pull/1334) ([susnux](https://github.com/susnux))
- Fix Add Question a11y [\#1269](https://github.com/nextcloud/forms/pull/1269) ([jotoeri](https://github.com/jotoeri))
- Fix sharing icons [\#1242](https://github.com/nextcloud/forms/pull/1242) ([jotoeri](https://github.com/jotoeri))
- Fix titles [\#1239](https://github.com/nextcloud/forms/pull/1239) ([jotoeri](https://github.com/jotoeri))

### Merged

- Fix lint warning [\#1371](https://github.com/nextcloud/forms/pull/1371) ([Chartman123](https://github.com/Chartman123))
- Fix aria warning [\#1370](https://github.com/nextcloud/forms/pull/1370) ([jotoeri](https://github.com/jotoeri))
- Fix question movement [\#1362](https://github.com/nextcloud/forms/pull/1362) ([jotoeri](https://github.com/jotoeri))
- Adjust testing matrix for Nextcloud 25 on master [\#1354](https://github.com/nextcloud/forms/pull/1354) ([nickvergessen](https://github.com/nickvergessen))
- Remove unneeded css [\#1352](https://github.com/nextcloud/forms/pull/1352) ([Chartman123](https://github.com/Chartman123))
- minversion 25 [\#1348](https://github.com/nextcloud/forms/pull/1348) ([Chartman123](https://github.com/Chartman123))
- Adjust styling for NC25 & Update `@nextcloud/vue` [\#1338](https://github.com/nextcloud/forms/pull/1338) ([susnux](https://github.com/susnux))
- Empty Content to Vue [\#1321](https://github.com/nextcloud/forms/pull/1321) ([jotoeri](https://github.com/jotoeri))
- Move to NcEmptyContent and even more icons [\#1308](https://github.com/nextcloud/forms/pull/1308) ([jotoeri](https://github.com/jotoeri))
- Use NC/vue beta 3 [\#1298](https://github.com/nextcloud/forms/pull/1298) ([jotoeri](https://github.com/jotoeri))
- Update Marcos Email [\#1285](https://github.com/nextcloud/forms/pull/1285) ([jotoeri](https://github.com/jotoeri))
- Use MutliSelect for dropdown questions [\#1283](https://github.com/nextcloud/forms/pull/1283) ([Chartman123](https://github.com/Chartman123))
- Move to button component & first material icons [\#1281](https://github.com/nextcloud/forms/pull/1281) ([jotoeri](https://github.com/jotoeri))
- Move to Psr\Log\LoggerInterface [\#1275](https://github.com/nextcloud/forms/pull/1275) ([Chartman123](https://github.com/Chartman123))
- Use `@nextcloud/logger` for frontend logging [\#1274](https://github.com/nextcloud/forms/pull/1274) ([susnux](https://github.com/susnux))
- Set border color to maxcontrast [\#1270](https://github.com/nextcloud/forms/pull/1270) ([Chartman123](https://github.com/Chartman123))
- Invert submitOnce [\#1252](https://github.com/nextcloud/forms/pull/1252) ([jotoeri](https://github.com/jotoeri))
- Remove old code [\#1240](https://github.com/nextcloud/forms/pull/1240) ([jotoeri](https://github.com/jotoeri))
- Removed trailing dot [\#1228](https://github.com/nextcloud/forms/pull/1228) ([rakekniven](https://github.com/rakekniven))
- Rename Radio buttons [\#1215](https://github.com/nextcloud/forms/pull/1215) ([jotoeri](https://github.com/jotoeri))
- Remove old forms tables [\#1156](https://github.com/nextcloud/forms/pull/1156) ([jotoeri](https://github.com/jotoeri))
- Simple API Test, bump php dependencies [\#1148](https://github.com/nextcloud/forms/pull/1148) ([jotoeri](https://github.com/jotoeri))
- API v2 [\#1126](https://github.com/nextcloud/forms/pull/1126) ([jotoeri](https://github.com/jotoeri))

## 2.5.1 - 2022-05-26

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.5.0...v2.5.1)

### Fixed

- Fix upgrading [\#1212](https://github.com/nextcloud/forms/pull/1212) ([nickvergessen](https://github.com/nickvergessen))

## 2.5.0 - 2022-04-08

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.4.0...v2.5.0)

### Enhancements

- Include Capabilities Response [\#1158](https://github.com/nextcloud/forms/pull/1158) ([jotoeri](https://github.com/jotoeri))

### Fixed

- Rollback Capabilities
  [\#1162](https://github.com/nextcloud/forms/pull/1162) ([jotoeri](https://github.com/jotoeri))
- Update master php testing versions
  [\#1161](https://github.com/nextcloud/forms/pull/1161) ([nickvergessen](https://github.com/nickvergessen))
- Update master php testing versions
  [\#1157](https://github.com/nextcloud/forms/pull/1157) ([nickvergessen](https://github.com/nickvergessen))
- Fix tests
  [\#1151](https://github.com/nextcloud/forms/pull/1151) ([jotoeri](https://github.com/jotoeri))
- Minversion 22
  [\#1150](https://github.com/nextcloud/forms/pull/1150) ([jotoeri](https://github.com/jotoeri))
- Move to NC-Internal Db-Types
  [\#1149](https://github.com/nextcloud/forms/pull/1149) ([jotoeri](https://github.com/jotoeri))
- Replace deprecated String.prototype.substr\(\)
  [\#1141](https://github.com/nextcloud/forms/pull/1141) ([CommanderRoot](https://github.com/CommanderRoot))
- Improve Question Text
  [\#1127](https://github.com/nextcloud/forms/pull/1127) ([jotoeri](https://github.com/jotoeri))
- Fix Linting Warnings
  [\#1082](https://github.com/nextcloud/forms/pull/1082) ([jotoeri](https://github.com/jotoeri))
- Update version on master
  [\#1071](https://github.com/nextcloud/forms/pull/1071) ([nickvergessen](https://github.com/nickvergessen))
- Update master target versions
  [\#1070](https://github.com/nextcloud/forms/pull/1070) ([nickvergessen](https://github.com/nickvergessen))

## 2.4.0 - 2021-11-10

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.3.0...v2.4.0)

### Enhancements

- 2.4.0
  [\#1068](https://github.com/nextcloud/forms/pull/1068) ([skjnldsv](https://github.com/skjnldsv))
- Enable HMR
  [\#1024](https://github.com/nextcloud/forms/pull/1024) ([jotoeri](https://github.com/jotoeri))

## 2.3.0 - 2021-07-28

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.2.4...v2.3.0)

### Deprecated ⚠️

- Question property `mandatory` is deprecated and replaced by `isRequired`. The old property will be removed in API version 2.
  [\#882](https://github.com/nextcloud/forms/pull/882) ([chartman123](https://github.com/Chartman123))

### Enhancements

- Add server-side validation of submissions
  [\#895](https://github.com/nextcloud/forms/pull/895) ([Chartman123](https://github.com/Chartman123))
- Delete a deleted Users Forms
  [\#856](https://github.com/nextcloud/forms/pull/856) ([jotoeri](https://github.com/jotoeri))

### Fixed

- Fix Activity Link
  [\#976](https://github.com/nextcloud/forms/pull/976) ([jotoeri](https://github.com/jotoeri))
- Allow non-admins to export to files
  [\#923](https://github.com/nextcloud/forms/pull/923) ([skjnldsv](https://github.com/skjnldsv))
- Fix result view for long text answers
  [\#913](https://github.com/nextcloud/forms/pull/913) ([Chartman123](https://github.com/Chartman123))
- Fix boolean columns nullable
  [\#881](https://github.com/nextcloud/forms/pull/881) ([jotoeri](https://github.com/jotoeri))

### Merged

- Minversion 20
  [\#1021](https://github.com/nextcloud/forms/pull/1021) ([jotoeri](https://github.com/jotoeri))
- Fix phpunit typo
  [\#1020](https://github.com/nextcloud/forms/pull/1020) ([jotoeri](https://github.com/jotoeri))
- Update version on master
  [\#994](https://github.com/nextcloud/forms/pull/994) ([nickvergessen](https://github.com/nickvergessen))
- Update master target versions
  [\#993](https://github.com/nextcloud/forms/pull/993) ([nickvergessen](https://github.com/nickvergessen))
- Bump node and npm version in package.json
  [\#989](https://github.com/nextcloud/forms/pull/989) ([nickvergessen](https://github.com/nickvergessen))
- Test FormsService
  [\#921](https://github.com/nextcloud/forms/pull/921) ([jotoeri](https://github.com/jotoeri))
- Some more tests
  [\#893](https://github.com/nextcloud/forms/pull/893) ([jotoeri](https://github.com/jotoeri))
- Refactor Mandatory
  [\#882](https://github.com/nextcloud/forms/pull/882) ([Chartman123](https://github.com/Chartman123))

## 2.2.4 - 2021-03-30

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.2.3...v2.2.4)

### Fixed

- Fix export again
  [\#871](https://github.com/nextcloud/forms/pull/871) ([jotoeri](https://github.com/jotoeri))

### Merged

- Bump dependencies
  [\#872](https://github.com/nextcloud/forms/pull/872) ([skjnldsv](https://github.com/skjnldsv))

## 2.2.3 - 2021-03-25

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.2.2...v2.2.3)

### Fixed

- Fix export multiple answers
  [\#860](https://github.com/nextcloud/forms/pull/860) ([jotoeri](https://github.com/jotoeri))
- Fix activity l10n
  [\#845](https://github.com/nextcloud/forms/pull/845) ([jotoeri](https://github.com/jotoeri))

## 2.2.2 - 2021-03-15

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.2.1...v2.2.2)

### Fixed

- Fix Routing
  [\#846](https://github.com/nextcloud/forms/pull/846) ([jotoeri](https://github.com/jotoeri))
- Fix half access objects
  [\#844](https://github.com/nextcloud/forms/pull/844) ([jotoeri](https://github.com/jotoeri))

## 2.2.1 - 2021-03-10

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.2.0...v2.2.1)

### Fixed

- Fix router naming conflict
  [\#837](https://github.com/nextcloud/forms/pull/837) ([jotoeri](https://github.com/jotoeri))
- Fix Summary Aggregation
  [\#835](https://github.com/nextcloud/forms/pull/835) ([jotoeri](https://github.com/jotoeri))

## 2.2.0 - 2021-03-09

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.1.0...v2.2.0)

### Enhancements

- Create Activities
  [\#789](https://github.com/nextcloud/forms/pull/789) ([jotoeri](https://github.com/jotoeri))
- Export csv to cloud
  [\#780](https://github.com/nextcloud/forms/pull/780) ([jotoeri](https://github.com/jotoeri))
- Show shared forms on navigation
  [\#763](https://github.com/nextcloud/forms/pull/763) ([jotoeri](https://github.com/jotoeri))
- Clone Forms
  [\#756](https://github.com/nextcloud/forms/pull/756) ([jotoeri](https://github.com/jotoeri))
- Include API Docs
  [\#748](https://github.com/nextcloud/forms/pull/748) ([jotoeri](https://github.com/jotoeri))

### Fixed

- Disable iPhone Auto-Zoom
  [\#816](https://github.com/nextcloud/forms/pull/816) ([jotoeri](https://github.com/jotoeri))
- Clarify translations
  [\#815](https://github.com/nextcloud/forms/pull/815) ([jotoeri](https://github.com/jotoeri))
- Fix anonymous text
  [\#804](https://github.com/nextcloud/forms/pull/804) ([jotoeri](https://github.com/jotoeri))
- Fix header-height for NC19
  [\#802](https://github.com/nextcloud/forms/pull/802) ([jotoeri](https://github.com/jotoeri))
- Manually set Types
  [\#801](https://github.com/nextcloud/forms/pull/801) ([jotoeri](https://github.com/jotoeri))
- Fix Sharing if deleted users are in list
  [\#796](https://github.com/nextcloud/forms/pull/796) ([jotoeri](https://github.com/jotoeri))
- Show all winners bold
  [\#793](https://github.com/nextcloud/forms/pull/793) ([jotoeri](https://github.com/jotoeri))
- Properly Scroll for required question
  [\#792](https://github.com/nextcloud/forms/pull/792) ([jotoeri](https://github.com/jotoeri))
- Fix toast svg
  [\#791](https://github.com/nextcloud/forms/pull/791) ([jotoeri](https://github.com/jotoeri))
- Properly delete Answers
  [\#765](https://github.com/nextcloud/forms/pull/765) ([jotoeri](https://github.com/jotoeri))
- Harden update restrictions
  [\#750](https://github.com/nextcloud/forms/pull/750) ([jotoeri](https://github.com/jotoeri))
- Make new option return similar to new question and form
  [\#749](https://github.com/nextcloud/forms/pull/749) ([jotoeri](https://github.com/jotoeri))
- Add csv export and prevents CSV formula injection
  [\#746](https://github.com/nextcloud/forms/pull/746) ([skjnldsv](https://github.com/skjnldsv))
- Add indexes
  [\#744](https://github.com/nextcloud/forms/pull/744) ([skjnldsv](https://github.com/skjnldsv))

### Merged

- Use new RichObject
  [\#820](https://github.com/nextcloud/forms/pull/820) ([jotoeri](https://github.com/jotoeri))
- Introduce php-constants, use for predefined answerTypes
  [\#795](https://github.com/nextcloud/forms/pull/795) ([jotoeri](https://github.com/jotoeri))
- Include TopBar Share-button
  [\#781](https://github.com/nextcloud/forms/pull/781) ([jotoeri](https://github.com/jotoeri))
- Remove stale code
  [\#757](https://github.com/nextcloud/forms/pull/757) ([jotoeri](https://github.com/jotoeri))

## 2.1.0 - 2021-01-04

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.4...v2.1.0)

### Merged

- Add Date & Datetime Component
  [\#672](https://github.com/nextcloud/forms/pull/672) ([jotoeri](https://github.com/jotoeri))
- Bump eslint to 7 and associated dependencies
  [\#661](https://github.com/nextcloud/forms/pull/661) ([skjnldsv](https://github.com/skjnldsv))
- Inform user if the form is anonymous
  [\#635](https://github.com/nextcloud/forms/pull/635) ([Nienzu](https://github.com/Nienzu))

### Fixed

- Fix datetime mandatory
  [\#696](https://github.com/nextcloud/forms/pull/696) ([jotoeri](https://github.com/jotoeri))
- Fix Popover-Reference
  [\#695](https://github.com/nextcloud/forms/pull/695) ([jotoeri](https://github.com/jotoeri))
- Fix time-formatting
  [\#671](https://github.com/nextcloud/forms/pull/671) ([jotoeri](https://github.com/jotoeri))

## 2.0.4 - 2020-09-01

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.3...v2.0.4)

### Merged

- NC 20 compatibility
- Move to OCS API
  [\#556](https://github.com/nextcloud/forms/pull/556) ([skjnldsv](https://github.com/skjnldsv))
- Translations update
- Dependencies update

## 2.0.3 - 2020-08-20

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.2...v2.0.3)

### Merged

- Translations update
- Dependencies update

## 2.0.2 - 2020-07-30

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.1...v2.0.2)

### Fixed

- Increase description and long-text max length
  [\#533](https://github.com/nextcloud/forms/pull/533) ([jotoeri](https://github.com/jotoeri))

## 2.0.1 - 2020-07-29

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.0...v2.0.1)

### Fixed

- Fix substring utf8
  [\#528](https://github.com/nextcloud/forms/pull/528) ([jotoeri](https://github.com/jotoeri))

## 2.0.0 - 2020-07-28

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.0-rc.1...v2.0.0)

### Fixed

- Fix dropdown submission insert
  [\#520](https://github.com/nextcloud/forms/pull/520) ([skjnldsv](https://github.com/skjnldsv))

## 2.0.0-rc.1 - 2020-07-24

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.0-beta.4...v2.0.0-rc.1)

### Enhancements

- Add screenshot of response visualization, adjust readme
  [\#513](https://github.com/nextcloud/forms/pull/513) ([jancborchardt](https://github.com/jancborchardt))
- Move to webpack vue global config & clean routes
  [\#508](https://github.com/nextcloud/forms/pull/508) ([skjnldsv](https://github.com/skjnldsv))
- Include version on feature-request template
  [\#478](https://github.com/nextcloud/forms/pull/478) ([jotoeri](https://github.com/jotoeri))
- Change 'Mandatory' to simpler 'Required'
  [\#464](https://github.com/nextcloud/forms/pull/464) ([jancborchardt](https://github.com/jancborchardt))
- Dropdown question type, ref \#340
  [\#461](https://github.com/nextcloud/forms/pull/461) ([jancborchardt](https://github.com/jancborchardt))
- Add summary response visualization, fix \#314
  [\#460](https://github.com/nextcloud/forms/pull/460) ([jancborchardt](https://github.com/jancborchardt))
- Add '\(responses\)' to export file name
  [\#450](https://github.com/nextcloud/forms/pull/450) ([jancborchardt](https://github.com/jancborchardt))
- Enh/invalid warning
  [\#415](https://github.com/nextcloud/forms/pull/415) ([jotoeri](https://github.com/jotoeri))
- Show MultipleInput icons in Edit-Mode
  [\#409](https://github.com/nextcloud/forms/pull/409) ([jotoeri](https://github.com/jotoeri))
- Put AppNavigationItems into proper Container
  [\#406](https://github.com/nextcloud/forms/pull/406) ([jotoeri](https://github.com/jotoeri))
- Question-specific placeholders
  [\#389](https://github.com/nextcloud/forms/pull/389) ([jotoeri](https://github.com/jotoeri))

### Fixed

- Fix questions & submissions assignment
  [\#485](https://github.com/nextcloud/forms/pull/485) ([skjnldsv](https://github.com/skjnldsv))
- Revert "Allow navigation through edit via Tab-Key"
  [\#484](https://github.com/nextcloud/forms/pull/484) ([skjnldsv](https://github.com/skjnldsv))
- Fix submitting form with expiration-date.
  [\#469](https://github.com/nextcloud/forms/pull/469) ([jotoeri](https://github.com/jotoeri))
- Invert submitOnce on UI
  [\#452](https://github.com/nextcloud/forms/pull/452) ([jotoeri](https://github.com/jotoeri))
- Allow navigation through edit via Tab-Key
  [\#427](https://github.com/nextcloud/forms/pull/427) ([jotoeri](https://github.com/jotoeri))
- Fix public template header
  [\#420](https://github.com/nextcloud/forms/pull/420) ([jotoeri](https://github.com/jotoeri))
- Fix some small MultipleInput issues
  [\#394](https://github.com/nextcloud/forms/pull/394) ([jotoeri](https://github.com/jotoeri))

## 2.0.0-beta.4 - 2020-06-09

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.0-beta.3...v2.0.0-beta.4)

### Fixed

- Keep focus when copy Share-Link
  [\#428](https://github.com/nextcloud/forms/pull/428) ([jotoeri](https://github.com/jotoeri))
- Avoid Submit on Enter
  [\#413](https://github.com/nextcloud/forms/pull/413) ([jotoeri](https://github.com/jotoeri))
- Delete empty options from Db
  [\#388](https://github.com/nextcloud/forms/pull/388) ([jotoeri](https://github.com/jotoeri))

## 2.0.0-beta.3 - 2020-06-04

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.0-beta2...v2.0.0-beta.3)

### Enhancements

- Sort Navigation newest forms first
  [\#402](https://github.com/nextcloud/forms/pull/402) ([jotoeri](https://github.com/jotoeri))
- Focus title after form load
  [\#369](https://github.com/nextcloud/forms/pull/369) ([jancborchardt](https://github.com/jancborchardt))

### Fixed

- Use icon-add in primary-text color
  [\#429](https://github.com/nextcloud/forms/pull/429) ([jotoeri](https://github.com/jotoeri))
- Fix linebreak in description
  [\#424](https://github.com/nextcloud/forms/pull/424) ([jotoeri](https://github.com/jotoeri))
- Fix Screenshot directory
  [\#421](https://github.com/nextcloud/forms/pull/421) ([jotoeri](https://github.com/jotoeri))
- Fix variable-typo
  [\#418](https://github.com/nextcloud/forms/pull/418) ([jotoeri](https://github.com/jotoeri))
- Fix expiration editable
  [\#414](https://github.com/nextcloud/forms/pull/414) ([jotoeri](https://github.com/jotoeri))
- Adjust cut descenders on formtitle
  [\#410](https://github.com/nextcloud/forms/pull/410) ([jotoeri](https://github.com/jotoeri))
- Prevent question menu icon and menu itself overlapping top right actions
  [\#404](https://github.com/nextcloud/forms/pull/404) ([jancborchardt](https://github.com/jancborchardt))
- Fix remove empty questions on submit
  [\#397](https://github.com/nextcloud/forms/pull/397) ([jotoeri](https://github.com/jotoeri))
- Fix saving options on fast proceed
  [\#396](https://github.com/nextcloud/forms/pull/396) ([jotoeri](https://github.com/jotoeri))
- Use cancelable request
  [\#393](https://github.com/nextcloud/forms/pull/393) ([jotoeri](https://github.com/jotoeri))
- Fix key-exists error
  [\#392](https://github.com/nextcloud/forms/pull/392) ([jotoeri](https://github.com/jotoeri))
- Fix newQuestions console error mandatory null
  [\#387](https://github.com/nextcloud/forms/pull/387) ([jotoeri](https://github.com/jotoeri))
- Use proper exit code for composer lint
  [\#384](https://github.com/nextcloud/forms/pull/384) ([MorrisJobke](https://github.com/MorrisJobke))
- Close navigation on mobile on new form
  [\#380](https://github.com/nextcloud/forms/pull/380) ([jotoeri](https://github.com/jotoeri))
- Fix Navigation active on results
  [\#379](https://github.com/nextcloud/forms/pull/379) ([jotoeri](https://github.com/jotoeri))
- Fix redirects
  [\#377](https://github.com/nextcloud/forms/pull/377) ([jotoeri](https://github.com/jotoeri))
- Fix Results initalState missing
  [\#376](https://github.com/nextcloud/forms/pull/376) ([jotoeri](https://github.com/jotoeri))
- Correct fix window title
  [\#375](https://github.com/nextcloud/forms/pull/375) ([jotoeri](https://github.com/jotoeri))
- Set max-version to 20 for new development version of Nextcloud
  [\#370](https://github.com/nextcloud/forms/pull/370) ([jancborchardt](https://github.com/jancborchardt))
- Update window title
  [\#368](https://github.com/nextcloud/forms/pull/368) ([jotoeri](https://github.com/jotoeri))
- Do not prefill form or question title for less confusion
  [\#367](https://github.com/nextcloud/forms/pull/367) ([jancborchardt](https://github.com/jancborchardt))
- Fix overlapping text of long answers
  [\#366](https://github.com/nextcloud/forms/pull/366) ([jancborchardt](https://github.com/jancborchardt))
- Include more sharing links
  [\#363](https://github.com/nextcloud/forms/pull/363) ([jotoeri](https://github.com/jotoeri))
- Fix export-button
  [\#362](https://github.com/nextcloud/forms/pull/362) ([jotoeri](https://github.com/jotoeri))
- Fix Error-Messages
  [\#360](https://github.com/nextcloud/forms/pull/360) ([jotoeri](https://github.com/jotoeri))

## 2.0.0-beta2 - 2020-05-06

[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.0-beta1...v2.0.0-beta2)

### Enhancements

- Mandatory option on questions
  [\#347](https://github.com/nextcloud/forms/pull/347) ([jotoeri](https://github.com/jotoeri))
- Fix users & groups sharing
  [\#346](https://github.com/nextcloud/forms/pull/346) ([skjnldsv](https://github.com/skjnldsv))
- New Result View
  [\#341](https://github.com/nextcloud/forms/pull/341) ([jotoeri](https://github.com/jotoeri))
- Fix multiple choice icon to make obvious it’s radio buttons
  [\#329](https://github.com/nextcloud/forms/pull/329) ([jancborchardt](https://github.com/jancborchardt))
- Comply to new Actions primary/title standard
  [\#313](https://github.com/nextcloud/forms/pull/313) ([skjnldsv](https://github.com/skjnldsv))
- Bump copyrights & add php cs & fixed linting
  [\#311](https://github.com/nextcloud/forms/pull/311) ([skjnldsv](https://github.com/skjnldsv))
- Cleanup old src code
  [\#310](https://github.com/nextcloud/forms/pull/310) ([skjnldsv](https://github.com/skjnldsv))

### Fixed

- Revert topbar changes
  [\#351](https://github.com/nextcloud/forms/pull/351) ([skjnldsv](https://github.com/skjnldsv))
- Fix Submission Access
  [\#345](https://github.com/nextcloud/forms/pull/345) ([jotoeri](https://github.com/jotoeri))
- Prevent leaking personnal infos on forms
  [\#343](https://github.com/nextcloud/forms/pull/343) ([skjnldsv](https://github.com/skjnldsv))
- l10n: Changed casing of words
  [\#339](https://github.com/nextcloud/forms/pull/339) ([rakekniven](https://github.com/rakekniven))
- Provide DBs max string lengths as InitialState
  [\#338](https://github.com/nextcloud/forms/pull/338) ([jotoeri](https://github.com/jotoeri))
- Move "Add a question" button to bottom
  [\#328](https://github.com/nextcloud/forms/pull/328) ([jotoeri](https://github.com/jotoeri))
- Prevent letter debounce erasing when creating new answers
  [\#327](https://github.com/nextcloud/forms/pull/327) ([skjnldsv](https://github.com/skjnldsv))
- Fix expiration display
  [\#326](https://github.com/nextcloud/forms/pull/326) ([skjnldsv](https://github.com/skjnldsv))
- Design fixes for submission view
  [\#325](https://github.com/nextcloud/forms/pull/325) ([jancborchardt](https://github.com/jancborchardt))
- l10n: Fixed typo
  [\#320](https://github.com/nextcloud/forms/pull/320) ([rakekniven](https://github.com/rakekniven))
- Add title on public page
  [\#315](https://github.com/nextcloud/forms/pull/315) ([skjnldsv](https://github.com/skjnldsv))
- Fix question icons broken in dark theme
  [\#312](https://github.com/nextcloud/forms/pull/312) ([skjnldsv](https://github.com/skjnldsv))

## 2.0.0-beta1 - 2020-04-29

[Full Changelog](https://github.com/nextcloud/forms/compare/v1.1.1...v2.0.0-beta1)

### Implemented enhancements:

- New creation UI with direct preview
- New voting UI
- Editing existing forms is now possible
- Removed dropdown question
- Added navigation
- Removed breadcrumbs

### Fixed bugs:

- Lots of bug fixed. The list is too complex
