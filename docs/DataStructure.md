# Forms Data Structure
**State: Forms v3.0.0 - 23.03.2022**

This document describes the Object-Structure, that is used within the Forms App and on Forms API v2. It does partially **not** equal the actual database structure behind.

## Data Structures
### Form
| Property    | Type            | Restrictions | Description |
|-------------|-----------------|--------------|-------------|
| id          | Integer         | unique       | An instance-wide unique id of the form |
| hash        | 16-char String  | unique       | An instance-wide unique hash |
| title       | String          | max. 256 ch. | The form title |
| description | String          | max. 8192 ch. | The Form description |
| ownerId     | String          |              | The nextcloud userId of the form owner |
| created     | unix timestamp  |              | When the form has been created |
| access      | [Access-Object](#access-object) |  | Describing access-settings of the form |
| expires     | unix-timestamp  |              | When the form should expire. Timestamp `0` indicates _never_ |
| isAnonymous | Boolean         |              | If Answers will be stored anonymously |
| submitOnce  | Boolean         |              | If users are only allowed to submit once to the form |
| canSubmit   | Boolean         |              | If the user can Submit to the form, i.e. calculated information out of `submitOnce` and existing submissions. |
| permissions | Array of [Permissions](#permissions) | Array of permissions regarding the form |
| questions   | Array of [Questions](#question) | | Array of questions belonging to the form |
| shares      | Array of [Shares](#share) | | Array of shares of the form |
| submissions | Array of [Submissions](#submission) | | Array of submissions belonging to the form |

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
  "submitOnce": false,
  "canSubmit": true,
  "permissions": [
    "edit",
    "results",
    "submit"
  ],
  "questions": [],
  "submissions": [],
  "shares": []
}
```

### Question
| Property    | Type            | Restrictions | Description |
|-------------|-----------------|--------------|-------------|
| id          | Integer         | unique       | An instance-wide unique id of the question |
| formId      | Integer         |              | The id of the form, the question belongs to |
| order       | Integer         | unique within form; *not* `0` | The order of the question within that form. Value `0` indicates deleted questions within database (typ. not visible outside) |
| type        | [Question-Type](#question-types) | | Type of the question |
| isRequired  | Boolean         |              | If the question is required to fill the form |
| text        | String          | max. 2048 ch. | The question-text |
| options     | Array of [Options](#option) | | Array of options belonging to the question. Only relevant for question-type with predefined options. |
```
{
  "id": 1,
  "formId": 3,
  "order": 1,
  "type": "dropdown",
  "mandatory": false, // deprecated, will be removed in API v2
  "isRequired": false,
  "text": "Question 1",
  "options": []
}
```

### Option
Options are predefined answer-possibilities corresponding to questions with appropriate question-type.

| Property    | Type            | Restrictions | Description |
|-------------|-----------------|--------------|-------------|
| id          | Integer         | unique       | An instance-wide unique id of the option |
| questionId  | Integer         |              | The id of the question, the option belongs to |
| text        | String          | max. 1024 ch.| The option-text |
```
{
  "id": 1,
  "questionId": 1,
  "text": "Option 1"
}
```

### Share
A share-object describes a single share of the form.
| Property    | Type            | Restrictions | Description |
|-------------|-----------------|--------------|-------------|
| id          | Integer         | unique       | An instance-wide unique id of the share |
| formId      | Integer         |              | The id of the form, the share belongs to |
| shareType   | NC-IShareType (Int) | `IShare::TYPE_USER = 0`, `IShare::TYPE_GROUP = 1`, `IShare::TYPE_LINK = 3` | Type of the share. Thus also describes how to interpret shareWith. |
| shareWith   | String          |              | User/Group/Hash - depending on the shareType |
| displayName | String          |              | Display name of share-target. |

### Submission
A submission-object describes a single submission by a user to a form.
| Property    | Type            | Restrictions | Description |
|-------------|-----------------|--------------|-------------|
| id          | Integer         | unique       | An instance-wide unique id of the submission |
| formId      | Integer         |              | The id of the form, the submission belongs to |
| userId      | String          |              | The nextcloud userId of the submitting user. If submission is anonymous, this contains `anon-user-<hash>` |
| timestamp   | unix timestamp  |              | When the user submitted |
| answers     | Array of [Answers](#answer) |  | Array of the actual user answers, belonging to this submission.
| userDisplayName | String      |              | Display name of the nextcloud-user, derived from `userId`. Contains `Anonymous user` if submitted anonymously. Not stored in DB.


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

| Property    | Type            | Restrictions | Description |
|-------------|-----------------|--------------|-------------|
| id          | Integer         | unique       | An instance-wide unique id of the submission |
| submissionId | Integer        |              | The id of the submission, the answer belongs to |
| questionId  | Integer         |              | The id of the question, the answer belongs to |
| text        | String          | max. 4096 ch. | The actual answer text, the user submitted |
```
{
  "id": 5,
  "submissionId": 5,
  "questionId": 1,
  "text": "Option 2"
}
```

## Permmissions
Array of permissions, the user has on the form. Permissions are named by resp. routes on frontend.
| Permission | Description |
| -----------|-------------|
| edit       | User is allowed to edit the form |
| results    | User is allowed to access the form results |
| submit     | User is allowed to submit to the form |

## Access Object
Defines some extended options of sharing / access
| Property         | Type      | Description |
|------------------|-----------|-------------|
| permitAllUsers   | Boolean   | All logged in users of this instance are allowed to submit to the form |
| showToAllUsers   | Boolean   | Only active, if permitAllUsers is true - Show the form to all users on appNavigation |

```
{
  "permitAllUsers": false,
  "showToAllUsers": false
}
```

## Question Types
Currently supported Question-Types are:

| Type-ID         | Description |
|-----------------|-------------|
| `multiple`      | Typically known as 'Checkboxes'. Using pre-defined options, the user can select one or multiple from. Needs at least one option available. |
| `multiple_unique` | Typically known as 'Radio Buttons'. Using pre-defined options, the user can select exactly one from. Needs at least one option available. |
| `dropdown`      | Similar to `multiple_unique`, but rendered as dropdown field. |
| `short`         | A short text answer. Single text line |
| `long`          | A long text answer. Multi-line supported |
| `date`          | Showing a dropdown calendar to select a date. |
| `datetime`      | Showing a dropdown calendar to select a date **and** a time. |
