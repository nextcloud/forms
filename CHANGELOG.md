# Changelog

## 3.3.0 - 2023-05-22

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.2.0...3.3.0)

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

[Full Changelog](https://github.com/nextcloud/forms/compare/v3.1.0...3.2.0)

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
