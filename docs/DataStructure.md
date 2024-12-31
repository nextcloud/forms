<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-only
-->

# Forms Data Structure

**State: Forms v3.3.1 - 08.10.2023**

This document describes the Object-Structure, that is used within the Forms App and on Forms API v2. It does partially **not** equal the actual database structure behind.

## Data Structures

### Form

| Property          | Type                                 | Restrictions                            | Description                                                                                                                      |
| ----------------- | ------------------------------------ | --------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| id                | Integer                              | unique                                  | An instance-wide unique id of the form                                                                                           |
| hash              | 16-char String                       | unique                                  | An instance-wide unique hash                                                                                                     |
| title             | String                               | max. 256 ch.                            | The form title                                                                                                                   |
| description       | String                               | max. 8192 ch.                           | The Form description                                                                                                             |
| ownerId           | String                               |                                         | The nextcloud userId of the form owner                                                                                           |
| submissionMessage | String                               | max. 2048 ch.                           | Optional custom message, with Markdown support, to be shown to users when the form is submitted (default is used if set to null) |
| created           | unix timestamp                       |                                         | When the form has been created                                                                                                   |
| access            | [Access-Object](#access-object)      |                                         | Describing access-settings of the form                                                                                           |
| expires           | unix-timestamp                       |                                         | When the form should expire. Timestamp `0` indicates _never_                                                                     |
| isAnonymous       | Boolean                              |                                         | If Answers will be stored anonymously                                                                                            |
| state             | Integer                              | [Form state](#form-state)               | The state of the form                                                                                                            |
| submitMultiple    | Boolean                              |                                         | If users are allowed to submit multiple times to the form                                                                        |
| allowEdit         | Boolean                              |                                         | If users are allowed to edit or delete their response                                                                            |
| showExpiration    | Boolean                              |                                         | If the expiration date will be shown on the form                                                                                 |
| canSubmit         | Boolean                              |                                         | If the user can Submit to the form, i.e. calculated information out of `submitMultiple` and existing submissions.                |
| permissions       | Array of [Permissions](#permissions) | Array of permissions regarding the form |
| questions         | Array of [Questions](#question)      |                                         | Array of questions belonging to the form                                                                                         |
| shares            | Array of [Shares](#share)            |                                         | Array of shares of the form                                                                                                      |
| submissions       | Array of [Submissions](#submission)  |                                         | Array of submissions belonging to the form                                                                                       |

```
{
  "id": 3,
  "hash": "em4djk8B9BpXnkYG",
  "title": "Form 1",
  "description": "Description Text",
  "ownerId": "jonas",
  "created": 1611240961,
  "access": {},
  "expires": 0,
  "isAnonymous": false,
  "submitMultiple": true,
  "allowEdit": false,
  "showExpiration": false,
  "canSubmit": true,
  "permissions": [
    "edit",
    "results",
    "submit"
  ],
  "questions": [],
  "state": 0,
  "shares": []
  "submissions": [],
}
```

#### Form state

The form state is used for additional states, currently following states are defined:

| Value | Meaning                                                                                  |
| ----- | ---------------------------------------------------------------------------------------- |
| 0     | Form is active and open for new submissions                                              |
| 1     | Form is closed and does not allow new submissions                                        |
| 2     | Form is archived, it does not allow new submissions and can also not be modified anymore |

### Question

| Property      | Type                              | Restrictions                  | Description                                                                                                                  |
| ------------- | --------------------------------- | ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| id            | Integer                           | unique                        | An instance-wide unique id of the question                                                                                   |
| formId        | Integer                           |                               | The id of the form, the question belongs to                                                                                  |
| order         | Integer                           | unique within form; _not_ `0` | The order of the question within that form. Value `0` indicates deleted questions within database (typ. not visible outside) |
| type          | [Question-Type](#question-types)  |                               | Type of the question                                                                                                         |
| isRequired    | Boolean                           |                               | If the question is required to fill the form                                                                                 |
| text          | String                            | max. 2048 ch.                 | The question-text                                                                                                            |
| name          | String                            |                               | Technical identifier of the question, e.g. used as HTML name attribute                                                       |
| options       | Array of [Options](#option)       |                               | Array of options belonging to the question. Only relevant for question-type with predefined options.                         |
| extraSettings | [Extra Settings](#extra-settings) |                               | Additional settings for the question.                                                                                        |

```
{
  "id": 1,
  "formId": 3,
  "order": 1,
  "type": "dropdown",
  "isRequired": false,
  "text": "Question 1",
  "name": "firstname",
  "options": [],
  "extraSettings": {}
}
```

### Option

Options are predefined answer-possibilities corresponding to questions with appropriate question-type.

| Property   | Type    | Restrictions  | Description                                   |
| ---------- | ------- | ------------- | --------------------------------------------- |
| id         | Integer | unique        | An instance-wide unique id of the option      |
| questionId | Integer |               | The id of the question, the option belongs to |
| text       | String  | max. 1024 ch. | The option-text                               |

```
{
  "id": 1,
  "questionId": 1,
  "text": "Option 1"
}
```

### Share

A share-object describes a single share of the form.
| Property | Type | Restrictions | Description |
|-------------|-----------------|--------------|-------------|
| id | Integer | unique | An instance-wide unique id of the share |
| formId | Integer | | The id of the form, the share belongs to |
| shareType | NC-IShareType (Int) | `IShare::TYPE_USER = 0`, `IShare::TYPE_GROUP = 1`, `IShare::TYPE_LINK = 3` | Type of the share. Thus also describes how to interpret shareWith. |
| shareWith | String | | User/Group/Hash - depending on the shareType |
| displayName | String | | Display name of share-target. |

### Submission

A submission-object describes a single submission by a user to a form.
| Property | Type | Restrictions | Description |
|-------------|-----------------|--------------|-------------|
| id | Integer | unique | An instance-wide unique id of the submission |
| formId | Integer | | The id of the form, the submission belongs to |
| userId | String | | The nextcloud userId of the submitting user. If submission is anonymous, this contains `anon-user-<hash>` |
| timestamp | unix timestamp | | When the user submitted |
| answers | Array of [Answers](#answer) | | Array of the actual user answers, belonging to this submission.
| userDisplayName | String | | Display name of the nextcloud-user, derived from `userId`. Contains `Anonymous user` if submitted anonymously. Not stored in DB.

```
{
  "id": 5,
  "formId": 3,
  "userId": "jonas",
  "timestamp": 1611274433,
  "answers": [],
  "userDisplayName": "jonas"
}
```

### Answer

The actual answers of users on submission.

| Property     | Type    | Restrictions  | Description                                     |
| ------------ | ------- | ------------- | ----------------------------------------------- |
| id           | Integer | unique        | An instance-wide unique id of the submission    |
| submissionId | Integer |               | The id of the submission, the answer belongs to |
| questionId   | Integer |               | The id of the question, the answer belongs to   |
| text         | String  | max. 4096 ch. | The actual answer text, the user submitted      |

```
{
  "id": 5,
  "submissionId": 5,
  "questionId": 1,
  "text": "Option 2"
}
```

## Permissions

Array of permissions, the user has on the form. Permissions are named by resp. routes on frontend.
| Permission | Description |
| ---------------|-------------|
| edit | User is allowed to edit the form |
| results | User is allowed to access the form results |
| results_delete | User is allowed to delete form submissions |
| submit | User is allowed to submit to the form |

## Access Object

Defines some extended options of sharing / access
| Property | Type | Description |
|------------------|-----------|-------------|
| permitAllUsers | Boolean | All logged in users of this instance are allowed to submit to the form |
| showToAllUsers | Boolean | Only active, if permitAllUsers is true - Show the form to all users on appNavigation |

```
{
  "permitAllUsers": false,
  "showToAllUsers": false
}
```

## Question Types

Currently supported Question-Types are:

| Type-ID           | Description                                                                                                                                |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `multiple`        | Typically known as 'Checkboxes'. Using pre-defined options, the user can select one or multiple from. Needs at least one option available. |
| `multiple_unique` | Typically known as 'Radio Buttons'. Using pre-defined options, the user can select exactly one from. Needs at least one option available.  |
| `dropdown`        | Similar to `multiple_unique`, but rendered as dropdown field.                                                                              |
| `short`           | A short text answer. Single text line                                                                                                      |
| `long`            | A long text answer. Multi-line supported                                                                                                   |
| `date`            | Showing a dropdown calendar to select a date.                                                                                              |
| _`datetime`_      | _deprecated: No longer available for new questions. Showing a dropdown calendar to select a date **and** a time._                          |
| `time`            | Showing a dropdown menu to select a time.                                                                                                  |

## Extra Settings

Optional extra settings for some [Question Types](#question-types)

| Extra Setting           | Question Type                         | Type             | Values                                      | Description                                                                 |
| ----------------------- | ------------------------------------- | ---------------- | ------------------------------------------- | --------------------------------------------------------------------------- |
| `allowOtherAnswer`      | `multiple, multiple_unique`           | Boolean          | `true/false`                                | Allows the user to specify a custom answer                                  |
| `shuffleOptions`        | `dropdown, multiple, multiple_unique` | Boolean          | `true/false`                                | The list of options should be shuffled                                      |
| `optionsLimitMax`       | `multiple`                            | Integer          | -                                           | Maximum number of options that can be selected                              |
| `optionsLimitMin`       | `multiple`                            | Integer          | -                                           | Minimum number of options that must be selected                             |
| `validationType`        | `short`                               | string           | `null, 'phone', 'email', 'regex', 'number'` | Custom validation for checking a submission                                 |
| `validationRegex`       | `short`                               | string           | regular expression                          | if `validationType` is 'regex' this defines the regular expression to apply |
| `allowedFileTypes`      | `file`                                | Array of strings | `'image', 'x-office/document'`              | Allowed file types for file upload                                          |
| `allowedFileExtensions` | `file`                                | Array of strings | `'jpg', 'png'`                              | Allowed file extensions for file upload                                     |
| `maxAllowedFilesCount`  | `file`                                | Integer          | -                                           | Maximum number of files that can be uploaded, 0 means no limit              |
| `maxFileSize`           | `file`                                | Integer          | -                                           | Maximum file size in bytes, 0 means no limit                                |
