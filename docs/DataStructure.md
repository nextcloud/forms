# Forms Data Structure
**State: Forms v2.3.0 - 09.04.2021**

This document describes the Object-Structure, that is used within the Forms App and on Forms API v1.1. It does partially **not** equal the actual database structure behind.

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
| questions   | Array of [Questions](#question) | | Array of questions belonging to the form |
| submissions | Array of [Submissions](#submissions) | | Array of submissions belonging to the form |
| canSubmit   | Boolean         |              | If the user can Submit to the form, i.e. calculated information out of `submitOnce` and existing submissions. |

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
  "questions": [],
  "submissions": [],
  "canSubmit": true
}
```

### Question
| Property    | Type            | Restrictions | Description |
|-------------|-----------------|--------------|-------------|
| id          | Integer         | unique       | An instance-wide unique id of the question |
| formId      | Integer         |              | The id of the form, the question belongs to |
| order       | Integer         | unique within form; *not* `0` | The order of the question within that form. Value `0` indicates deleted questions within database (typ. not visible outside) |
| type        | [Question-Type](#question-types) | | Type of the question |
| _mandatory_   | _Boolean_         |              | _deprecated: will be removed in API v2, replaced by `isRequired`_ |
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


## Access Object
Defines how users are allowed to access the form.
| Property    | Type            | Description |
|-------------|-----------------|-------------|
| users       | Array of [userShares](#share-objects)  | Only relevant if `type=selected` |
| groups      | Array of [groupShares](#share-objects) | Only relevant if `type=selected` |
| type        | [ShareType](#share-types) | Share Type of the form.

```
{
    "users": [],
    "groups": [],
    "type": "public"
}
```

### Share Types
Three types of sharing options are currently available, which define the access to the form. Independent of Share-Type, the form is currently only accessible via its share-link.
| Type-ID    | Description |
|------------|-------------|
| `public`     | Everybody is allowed to access the form. Anonymous users can fill the form on its public page. |
| `registered` | Only registered & logged-in users on this instance can fill the form. |
| `selected`   | Only selected users are allowed to fill the form. Allowed users are defined in the Access-object. |

### Share Objects
Objects of userShares or groupShares.

| Property    | Type            | Description |
|-------------|-----------------|-------------|
| shareWith   | String          | Nextcloud userId or groupId of the sharee |
| displayName | String          | Nextcloud Display Name of the Sharee |
| shareType   | NC-IShareType (Int) | Nextcloud `IShare`-Type. Currently `IShare::TYPE_USER = 0` and `IShare::TYPE_GROUP = 1`
```
{
  "shareWith": "user1",
  "displayName": "User No. 1",
  "shareType": 0
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
| `date`          | Showing a dropdown menu to select a time. |
