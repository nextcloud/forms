# Changelog

## v2.0.0-beta.4 - 2020-06-09
[Full Changelog](https://github.com/nextcloud/forms/compare/v2.0.0-beta.3...v2.0.0-beta.4)

### Fixed
- Keep focus when copy Share-Link
  [\#428](https://github.com/nextcloud/forms/pull/428) ([jotoeri](https://github.com/jotoeri))
- Avoid Submit on Enter
  [\#413](https://github.com/nextcloud/forms/pull/413) ([jotoeri](https://github.com/jotoeri))
- Delete empty options from Db
  [\#388](https://github.com/nextcloud/forms/pull/388) ([jotoeri](https://github.com/jotoeri))

## v2.0.0-beta.3 - 2020-06-04
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

## v2.0.0-beta2 - 2020-05-06
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

## v2.0.0-beta1 - 2020-04-29
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
