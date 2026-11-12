<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-only
-->

# Forms Public API

This file contains the API-Documentation. For more information on the returned Data-Structures, please refer to the [corresponding Documentation](DataStructure.md).

## Generals

- Base URL for all calls to the forms API is `<nextcloud_base_url>/ocs/v2.php/apps/forms`
- All Requests need to provide some authentication information.
- All Requests to OCS-Endpoints require the Header `OCS-APIRequest: true`
- Unless otherwise specified, all parameters are mandatory.

- By default, the API returns data formatted as _xml_. If formatting as _json_ is desired, the request should contain the header `Accept: application/json`. For simple representation, the output presented in this document is all formatted as _json_.
- The OCS-Endpoint _always returns_ an object called `ocs`. This contains an object `meta` holding some meta-data, as well as an object `data` holding the actual data. In this document, the response-blocks only show the `data`, if not explicitely stated different.

- OpenAPI spec: a machine-readable OpenAPI description is available at `/openapi.json` in the repository root. Prefer it as source-of-truth for exact request/response shapes.
- When the server changes data beyond what a request asked for, it reports this in the response header `X-Forms-Adjusted`, see [Adjustments by the server](#adjustments-by-the-server).

```
"ocs": {
  "meta": {
    "status": "ok",
    "statuscode": 200,
    "message": "OK"
  },
  "data": <Actual data>
}
```

### Adjustments by the server

Some requests succeed with their usual status, and their payload keeps its shape, but the server changes data beyond what the request asked for. The response header `X-Forms-Adjusted` then reports this as a [structured field dictionary (RFC 8941)](https://www.rfc-editor.org/rfc/rfc8941#name-dictionaries); the header is absent when nothing was adjusted. `Access-Control-Expose-Headers` makes it readable for cross-origin clients. Clients should ignore keys they do not know, as more keys may follow.

| Key | Value | Set by | Meaning |
|-----|-------|--------|---------|
| `removed-jumps` | Inner list of question ids, e.g. `(3 7)` | Updating, reordering and copying questions; deleting options; changing the bounds of a linear scale | Jumps to the question itself or to an earlier question, jumps of the deleted option, or jumps for values outside the scale, were removed from the `extraSettings` of these questions, see [Jumps](DataStructure.md#jumps) |

```
X-Forms-Adjusted: removed-jumps=(3 7)
```

## API changes

### Deprecation info

- Starting with Forms v4.3 API v2 will be deprecated and removed with Forms v5
- Starting with API v2.2 all endpoints that update data will use PATCH/PUT as method. POST is now deprecated and will be removed in API v3

### Breaking changes on API v3

- Most routes changed from API v2 to v3. Please adjust your applications accordingly.
- Removed possibility to get a single partial form

### Breaking changes on API v2

- The `mandatory` property of questions has been removed. It is replaced by `isRequired`.
- Completely new way of handling access & shares.

### Other API changes

- Questions can jump to a later question or to the end of the form with the extra settings `goTo` (per answer, on dropdown, radio and linear scale questions) or `next` (after the question, on the other types with jumps), never both (see [Jumps](DataStructure.md#jumps)). Jumps to questions of other forms or to options that are not options of the question are rejected, deleting an option removes its jumps, and narrowing a linear scale removes the jumps of the values that are gone. Submissions and submission updates ignore answers to the questions the jumps skip, and an answer "Other" needs a text. Updating, reordering and copying questions removes jumps to the question itself or to an earlier question; the response header `X-Forms-Adjusted` then lists the questions concerned under `removed-jumps` (see [Adjustments by the server](#adjustments-by-the-server)). Copying or importing a form drops such jumps as well, without the header, as these create a new form.
- In API version 3.0 the following endpoints were introduced/changed:
    - `GET /api/v3/forms/{formId}/questions` to get all questions of a form
    - `GET /api/v3/forms/{formId}/questions/{questionId}` to get a single question
    - `POST /api/v3/forms/{formId}/questions/{questionId}/options` does now accept more options at once
    - `PATCH /api/v3/forms/{formId}/questions/{questionId}/options` to reorder the options (request body `newOrder`, optional `optionType`)
    - `POST /api/v3/forms/{formId}/submissions/files/{questionId}` to upload a file to a file question before submitting the form (response includes `uploadToken`; submissions must include it with `uploadedFileId`)
    - `GET /api/v3/forms/{formId}/submissions/{submissionId}` to get a single submission
    - `PUT /api/v3/forms/{formId}/submissions/{submissionId}` to update an existing submission
- In API version 2.5 the following endpoints were introduced:
    - `POST /api/v2.5/uploadFiles/{formId}/{questionId}` to upload files to answer before form submitting
- In API version 2.4 the following endpoints were introduced:
    - `POST /api/v2.4/form/link/{fileFormat}` to link form to a file
    - `POST /api/v2.4/form/unlink` to unlink form from a file
- In API version 2.4 the following endpoints were changed:
    - `GET /api/v2.4/submissions/export/{hash}` was extended with optional parameter `fileFormat` to export submissions in different formats
    - `GET /api/v2.4/submissions/export` was extended with optional parameter `fileFormat` to export submissions to cloud in different formats
    - `GET /api/v2.4/form/{id}` was extended with optional parameters `fileFormat`, `fileId`, `filePath` to link form to a file
- In API version 2.3 the endpoint `/api/v2.3/question/clone` was added to clone a question
- In API version 2.2 the endpoint `/api/v2.2/form/transfer` was added to transfer ownership of a form
- In API version 2.1 the endpoint `/api/v2.1/share/update` was added to update a Share

## Form Endpoints

### List owned Forms

Returns condensed objects of all Forms beeing owned by the authenticated user.

- Endpoint: `/api/v3/forms[?type=owned]`
- Method: `GET`
- Parameters: None
- Response: Array of condensed Form Objects, sorted as newest first.

```
"data": [
  {
    "id": 6,
    "hash": "yWeMwcwCwoqRs8T2",
    "title": "Form 2",
    "expires": 0,
    "opens": 0,
    "permissions": [
      "edit",
      "results",
      "submit"
    ],
    "partial": true,
    "state": 0,
    "lockedBy": null,
    "lockedUntil": null
  },
  {
    "id": 3,
    "hash": "em4djk8B9BpXnkYG",
    "title": "Form 1",
    "expires": 0,
    "opens": 0,
    "permissions": [
      "edit",
      "results",
      "submit"
    ],
    "partial": true,
    "state": 0,
    "lockedBy": "someUser"
    "lockedUntil": 123456789
  }
]
```

### List shared Forms

Returns condensed objects of all Forms, that are shared & shown to the authenticated user and that have not expired yet.

- Endpoint: `/api/v3/forms?type=shared`
- Method: `GET`
- Parameters: None
- Response: Array of condensed Form Objects, sorted as newest first, similar to [List owned Forms](#list-owned-forms).

```
See above, 'List owned forms'
```

### Create a new Form

- Endpoint: `/api/v3/forms`
- Method: `POST`
- Parameters: None
- Response: The new form object, similar to requesting an existing form.

```
See next section, 'Request full data of a form'
```

### Request full data of a form

Returns the full-depth object of the requested form (without submissions).

- Endpoint: `/api/v3/forms/{formId}`
- Method: `GET`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form to request |
- Response: A full object of the form, including access, questions and options in full depth.
  Before the opening date (`opens`) of the form, `questions` is empty for users who can neither edit the form nor see its results.

```
"data": {
  "id": 3,
  "hash": "em4djk8B9BpXnkYG",
  "title": "Form 1",
  "description": "Description Text",
  "ownerId": "jonas",
  "submissionMessage": "Thank **you** for submitting the form."
  "created": 1611240961,
  "access": {
    "permitAllUsers": false,
    "showToAllUsers": false
  },
  "expires": 0,
  "opens": 0,
  "fileFormat": "csv",
  "fileId": 157,
  "filePath": "foo/bar",
  "isAnonymous": false,
  "submitMultiple": true,
  "allowEditSubmissions": false,
  "showExpiration": false,
  "canSubmit": true,
  "state": 0,
  "lockedBy": null,
  "lockedUntil": null,
  "confirmationEmailEnabled": false,
  "confirmationEmailSubject": null,
  "confirmationEmailBody": null,
  "confirmationEmailQuestionId": null,
  "permissions": [
    "edit",
    "results",
    "submit"
  ],
  "questions": [
    {
      "id": 1,
      "formId": 3,
      "order": 1,
      "type": "dropdown",
      "isRequired": false,
      "text": "Question 1",
      "name": "something",
      "options": [
        {
          "id": 1,
          "questionId": 1,
          "text": "Option 1",
          "order": null
        },
        {
          "id": 2,
          "questionId": 1,
          "text": "Option 2",
          "order": null
        }
      ],
      "accept": [],
      "extraSettings": {}
    },
    {
      "id": 2,
      "formId": 3,
      "order": 2,
      "type": "file",
      "isRequired": true,
      "text": "Question 2",
      "name": "something_other",
      "options": [],
      "extraSettings": {}
      "accept": ["image/*", ".pdf"],
    }
  ],
  "shares": [
    {
      "id": 1,
      "formId": 3,
      "shareType": 0,
      "shareWith": "user1",
      "displayName": "User 1 Displayname"
    },
    {
      "id": 2,
      "formId": 3,
      "shareType": 3,
      "shareWith": "dYcTWjrSsxjMFFQzFAywzq5J",
      "displayName": ""
    }
  ],
  "submissionCount": 0,
  "submissionMessage": "",
}
```

### Clone a form

Creates a clone of a form (without submissions).

- Endpoint: `/api/v3/forms?fromId={formId}`
- Method: `POST`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form to clone |
- Response: Returns the full object of the new form. See [Request full data of a Form](#request-full-data-of-a-form). The jumps of the copied questions point at the copied questions and options; jumps whose target or option was not copied, jumps that would go to the question itself or to an earlier question, and `goTo` or `next` on a question type without it, are dropped, without the header `X-Forms-Adjusted` (see [Jumps](DataStructure.md#jumps)).

```
See section 'Request full data of a form'.
```

### Update form properties

Update a single or multiple properties of a form-object. Concerns **only** the Form-Object, properties of Questions, Options and Submissions, as well as their creation or deletion, are handled separately.

- Endpoint: `/api/v3/forms/{formId}`
- Method: `PATCH`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form to update |
- Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _keyValuePairs_ | Array | Array of key-value pairs to update |
- Restrictions:
    - It is **not allowed** to update one of the following key-value pairs: _id, hash, ownerId, created_
    - To transfer the ownership of a form to another user, you must only send a single _keyValuePair_ containing the key `ownerId` and the user id of the new owner.
    - To link a file for submissions, the _keyValuePairs_ need to contain the keys `path` and `fileFormat`
    - To unlink a file for submissions, the _keyValuePairs_ need to contain the keys `fileId` and `fileFormat` need to contain the value `null`
    - `opens` and `expires` must be non-negative unix timestamps, `0` means not set
    - If both `opens` and `expires` are set (not `0`), `opens` must be before `expires`
- Response: **Status-Code OK**, as well as the id of the updated form.

```
"data": 3
```

### Delete a form

- Endpoint: `/api/v3/forms/{formId}`
- Method: `DELETE`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form to delete |
- Response: **Status-Code OK**, as well as the id of the deleted form.

```
"data": 3
```

## Question Endpoints

### Get all questions of a form

Returns the questions and options of the given form (without submissions).

- Endpoint: `/api/v3/forms/{formId}/questions`
- Method: `GET`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form |
- Response: An array of all questions of the form including options.
  Before the opening date (`opens`) of the form, users who can neither edit the form nor see its results get a **Forbidden** response.

```
"data": [
  {
    "id": 1,
    "formId": 3,
    "order": 1,
    "type": "dropdown",
    "isRequired": false,
    "text": "Question 1",
    "name": "something",
    "options": [
      {
        "id": 1,
        "questionId": 1,
        "text": "Option 1",
        "order": null
      },
      {
        "id": 2,
        "questionId": 1,
        "text": "Option 2",
        "order": null
      }
    ],
    "accept": [],
    "extraSettings": {}
  },
  {
    "id": 2,
    "formId": 3,
    "order": 2,
    "type": "file",
    "isRequired": true,
    "text": "Question 2",
    "name": "something_other",
    "options": [],
    "extraSettings": {}
    "accept": ["image/*", ".pdf"],
  }
]
```

### Create a new question

- Endpoint: `/api/v3/forms/{formId}/questions`
- Method: `POST`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form |
- Parameters:
  | Parameter | Type | Optional | Description |
  |-----------|---------|----------|-------------|
  | _type_ | [QuestionType](DataStructure.md#question-types) | | The question-type of the new question |
  | _text_ | String | yes | _Optional_ The text of the new question. |
  | _position_ | Integer | yes | _(optional)_ 1-based position to insert the new question. When omitted the question is appended at the end. |
- Response: The new question object.

```
"data": {
  "id": 3,
  "formId": 3,
  "order": 3,
  "type": "short",
  "isRequired": false,
  "name": "",
  "text": "",
  "options": []
  "extraSettings": {}
}
```

### Get all questions of a form

Returns the requested question and options of the given form (without submissions).

- Endpoint: `/api/v3/forms/{formId}/questions/{questionId}`
- Method: `GET`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form |
  | _questionId_ | Integer | ID of the question |
 - Response: An object of the requested question including options.
   Before the opening date (`opens`) of the form, users who can neither edit the form nor see its results get a **Forbidden** response.

```
"data": {
    "id": 1,
    "formId": 3,
    "order": 1,
    "type": "dropdown",
    "isRequired": false,
    "text": "Question 1",
    "name": "something",
    "options": [
      {
        "id": 1,
        "questionId": 1,
        "text": "Option 1",
        "order": null
      },
      {
        "id": 2,
        "questionId": 1,
        "text": "Option 2"
        "order": null
      }
    ],
    "accept": [],
    "extraSettings": {}
}
```

### Update question properties

Update a single or multiple properties of a question-object.

- Endpoint: `/api/v3/forms/{formId}/questions/{questionId}`
- Method: `PATCH`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form to request |
  | _questionId_ | Integer | ID of the question to update |
- Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _keyValuePairs_ | Array | Array of key-value pairs to update |
- Restrictions: It is **not allowed** to update one of the following key-value pairs: _id, formId, order, type_.
- Response: **Status-Code OK**, as well as the id of the updated question. Jumps in `extraSettings` that go to the question itself or to an earlier question, and jumps of a linear scale for values outside its bounds, are removed before saving (see [Jumps](DataStructure.md#jumps)), and the response header `X-Forms-Adjusted` then holds `removed-jumps=(<question id>)` (see [Adjustments by the server](#adjustments-by-the-server)). Jumps with an invalid structure, `goTo` or `next` on a question type without it, and ids of questions of other forms or of options that are not options of the question are rejected with **Status-Code Bad Request** "Invalid extraSettings, will not update."

```
"data": 1
```

### Reorder questions

Reorders all Questions of a single form

- Endpoint: `/api/v3/forms/{formId}/questions`
- Method: `PATCH`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form, the questions belong to |
- Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _newOrder_ | Array | Array of **all** Question-IDs, ordered in the desired order |
- Restrictions: The Array **must** contain all Question-IDs corresponding to the specified form and **must not** contain any duplicates.
- Response: Array of questionIDs and their corresponding order. Jumps that would go to the question itself or to an earlier question are removed (see [Jumps](DataStructure.md#jumps)): the questions concerned also carry their new `extraSettings`, and the response header `X-Forms-Adjusted` then lists their ids, e.g. `X-Forms-Adjusted: removed-jumps=(3 7)` (see [Adjustments by the server](#adjustments-by-the-server)).

```
"data": {
  "1": {
    "order": 1
  },
  "2": {
    "order": 3
  },
  "3": {
    "order": 2
  }
}
```

### Delete a question

- Endpoint: `/api/v3/forms/{formId}/questions/{questionId}`
- Method: `DELETE`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the question |
  | _questionId_ | Integer | ID of the question to delete |
- Response: **Status-Code OK**, as well as the id of the deleted question.

```
"data": 4
```

### Clone a question

Creates a clone of a question with all its options.

- Endpoint: `/api/v3/forms/{formId}/questions?fromId={questionId}`
- Method: `POST`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the question |
  | _questionId_ | Integer | ID of the question to clone |
- Parameters:
  | Parameter | Type | Optional | Description |
  |-----------|---------|----------|-------------|
  | _position_ | Integer | yes | _(optional)_ 1-based position to insert the cloned question. When omitted the clone is appended at the end. |
- Response: Returns cloned question object with the new ID set. Jumps that would go to the clone itself or to an earlier question, for example when it is appended at the end, are removed from its `extraSettings` (see [Jumps](DataStructure.md#jumps)), and the response header `X-Forms-Adjusted` then holds `removed-jumps=(<clone id>)` (see [Adjustments by the server](#adjustments-by-the-server)).

```
See section 'Create a new question'.
```

## Option Endpoints

Contains only manipulative question-endpoints. To retrieve options, request the full form data.

### Create a new Option

- Endpoint: `/api/v3/forms/{formId}/questions/{questionId}/options`
- Method: `POST`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the question |
  | _questionId_ | Integer | ID of the question, the new option will belong to |
- Parameters:
  | Parameter | Type | Optional | Description |
  |-----------|---------|----------|-------------|
  | _optionTexts_ | Array | | Array of strings containing the new options |
  | _optionType_ | [Option Types](DataStructure.md#Option-Types) | yes | The option-type to assign to the new options (e.g. `row`). |
- Response: The new array of option objects

```
"data": [
  {
    "id": 7,
    "questionId": 1,
    "order": 1,
    "text": "test",
    "optionType": "choice"
  }
]
```

### Update option properties

Update a single or all properties of an option-object

- Endpoint: `/api/v3/forms/{formId}/questions/{questionId}/options/{optionId}`
- Method: `PATCH`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the question and option |
  | _questionId_ | Integer | ID of the question, the new option will belong to |
  | _optionId_ | Integer | ID of the option to update |
- Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _keyValuePairs_ | Array | Array of key-value pairs to update |
- Restrictions: It is **not allowed** to update one of the following key-value pairs: _id, questionId_.
- Response: **Status-Code OK**, as well as the id of the updated option.

```
"data": 7
```

### Delete an option

- Endpoint: `/api/v3/forms/{formId}/questions/{questionId}/options/{optionId}`
- Method: `DELETE`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the question and option |
  | _questionId_ | Integer | ID of the question, the new option will belong to |
  | _optionId_ | Integer | ID of the option to delete |
- Response: **Status-Code OK**, as well as the id of the deleted option. The jumps of the question for this option are removed (see [Jumps](DataStructure.md#jumps)), and the response header `X-Forms-Adjusted` then holds `removed-jumps=(<question id>)` (see [Adjustments by the server](#adjustments-by-the-server)).

```
"data": 7
```

### Reorder options

- Endpoint: `/api/v3/forms/{formId}/questions/{questionId}/options`
- Method: `PATCH`
- Url-Parameter:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the question and option |
  | _questionId_ | Integer | ID of the question, the new option will belong to |
- Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _newOrder_ | Array | Array of **all** option IDs, ordered in the desired order |
  | _optionType_ | [QuestionType](DataStructure.md#question-types) | _optional_ option type to reorder (e.g. `row`). |
- Restrictions: The Array **must** contain all option IDs corresponding to the specified question and **must not** contain any duplicates.
- Response: Array of optionIds and their corresponding order.

## Sharing Endpoints

### Add a new Share

- Endpoint: `/api/v3/forms/{formId}/shares`
- Method: `POST`
- Url-Parameters:
  | Parameter | Type | Description |
  |--------------|----------|-------------|
  | _formId_ | Integer | ID of the form to share |
- Parameters:
  | Parameter | Type | Description |
  |--------------|----------|-------------|
  | _shareType_ | String | NC-shareType, out of the used shareTypes. |
  | _shareWith_ | String | User/Group for the share. Not used for link-shares. |
  | _permissions_ | String[] | Permissions of the sharees, see [DataStructure](DataStructure.md#Permissions). |
- Response: **Status-Code OK**, as well as the new share object.

```
"data": {
  "id": 3,
  "formId": 3,
  "shareType": 0,
  "shareWith": "user3",
  "permissions": ["submit"],
  "displayName": "User 3 Displayname"
}
```

### Update a Share

- Endpoint: `/api/v3/forms/{formId}/shares/{shareId}`
- Method: `PATCH`
- Url-Parameters:
  | Parameter | Type | Description |
  |------------------|----------|-------------|
  | _formId_ | Integer | ID of the form containing the share |
  | _shareId_ | Integer | ID of the share to update |
- Parameters:
  | Parameter | Type | Description |
  |------------------|----------|-------------|
  | _keyValuePairs_ | Array | Array of key-value pairs to update |
- Restrictions:
  - Allowed keys are _permissions_ and _token_.
  - _token_ updates are only available when the admin setting _allowCustomPublicShareTokens_ is enabled.
  - _token_ can only be updated on link shares.
  - _token_ must be unique among link shares and only contain alphanumeric characters.
  - _token_ length must be between 8 and 256 characters.
- Response: **Status-Code OK**, as well as the id of the share object.

```
"data": 5
```

### Delete a Share

- Endpoint: `/api/v3/forms/{formId}/shares/{shareId}`
- Method: `DELETE`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the share |
  | _shareId_ | Integer | ID of the share to delete |
- Response: **Status-Code OK**, as well as the id of the deleted share.

```
"data": 5
```

### Generate a new Share Token

Generates a new random token that can be used as a custom share link token. This endpoint is used when custom public share tokens are enabled by the admin setting _allowCustomPublicShareTokens_.

- Endpoint: `/api/v3/token`
- Method: `GET`
- Parameters: None
- Response: **Status-Code OK**, as well as the generated token string.

```
"data": {
  "token": "abcdefghijklmn1234567890"
}
```

## Submission Endpoints

### Get Form Submissions

Get all Submissions to a Form

- Endpoint: `/api/v3/forms/{formId}/submissions`
- Method: `GET`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form to get the submissions for |
- Parameters:
  | Parameter | Type | Description |
  |------------------|----------|-------------|
  | _query_ | String | Phrase for full text search |
  | _limit_ | Integer | How many items to get |
  | _offset_ | Integer | How many items to skip for a pagination |
- Response: An Array of all submissions, sorted as newest first, as well as an array of the corresponding questions.

```
"data": {
  "submissions": [
    {
      "id": 6,
      "formId": 3,
      "userId": "jonas",
      "timestamp": 1611274453,
      "answers": [
        {
          "id": 8,
          "submissionId": 6,
          "questionId": 1,
          "text": "Option 3"
        },
        {
          "id": 9,
          "submissionId": 6,
          "questionId": 2,
          "text": "One more."
        },
      ],
      "userDisplayName": "jonas"
    },
    {
      "id": 5,
      "formId": 3,
      "userId": "jonas",
      "timestamp": 1611274433,
      "answers": [
        {
          "id": 5,
          "submissionId": 5,
          "questionId": 1,
          "text": "Option 2"
        },
        {
          "id": 6,
          "submissionId": 5,
          "questionId": 2,
          "text": "This is an answer."
        },
      ],
      "userDisplayName": "jonas"
    }
  ],
  "questions": [
    {
      "id": 1,
      "formId": 3,
      "order": 1,
      "type": "dropdown",
      "isRequired": false,
      "text": "Question 1",
      "options": [
        {
          "id": 1,
          "questionId": 1,
          "text": "Option 1",
          "order": null
        },
        {
          "id": 27,
          "questionId": 1,
          "text": "Option 2",
          "order": null
        },
        {
          "id": 30,
          "questionId": 1,
          "text": "Option 3",
          "order": null
        }
      ],
      "extraSettings": {}
    },
    {
      "id": 2,
      "formId": 3,
      "order": 2,
      "type": "short",
      "isRequired": true,
      "text": "Question 2",
      "options": [],
      "extraSettings": {}
    }
  ],
  "filteredSubmissionsCount": 40
}
```

### Get Submissions as csv (Download)

Returns all submissions to the form in form of a csv-file.

- Endpoint: `/api/v3/forms/{formId}/submissions?fileFormat={fileFormat}`
- Method: `GET`
- Url-Parameters:
  | Parameter | Type | Description |
  |--------------|---------|-------------|
  | _formId_ | Integer | Id of the form to get the submissions for |
  | _fileFormat_ | String | `csv|ods|xlsx` |
- Response: A Data Download Response containing the headers `Content-Disposition: attachment; filename="Form 1 (responses).csv"` and `Content-Type: text/csv;charset=UTF-8`. The actual data contains all submissions to the referred form, formatted as comma separated and escaped csv. For file format `ods` or `xlsx` the Download Response contains an Open Document Spreadsheet or an Office Open XML Spreadsheet file.

```
"User display name","Timestamp","Question 1","Question 2"
"jonas","Friday, January 22, 2021 at 12:47:29 AM GMT+0:00","Option 2","Answer"
"jonas","Friday, January 22, 2021 at 12:45:57 AM GMT+0:00","Option 3","NextAnswer"
```

### Export Submissions to Cloud (Files-App)

Creates a csv file and stores it to the cloud, resp. Files-App.

- Endpoint: `/api/v3/forms/{formId}/submissions/export`
- Method: `POST`
- Url-Parameters:
  | Parameter | Type | Description |
  |--------------|---------|-------------|
  | _formId_ | Integer | ID of the form to get the submissions for |
- Parameters:
  | Parameter | Type | Description |
  |--------------|---------|-------------|
  | _path_ | String | Path within User-Dir, to store the file to |
  | _fileFormat_ | String | csv|ods|xlsx |
- Response: Stores the file to the given path and returns the fileName.

```
"data": "Form 2 (responses).csv"
```

### Delete Submissions

Delete all Submissions to a form

- Endpoint: `/api/v3/forms/{formId}/submissions`
- Method: `DELETE`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form to delete the submissions for |
- Response: **Status-Code OK**, as well as the id of the corresponding form.

```
"data": 3
```

### Upload a file

Upload a file to a file question before form submission. Each uploaded file is stored temporarily and bound to the given `formId` and `questionId`. The response includes an `uploadToken` that must be sent back when submitting the form, together with `uploadedFileId`.

- Endpoint: `/api/v3/forms/{formId}/submissions/files/{questionId}`
- Method: `POST`
- Url-Parameters:
  | Parameter | Type | Description |
  |--------------|----------------|-------------|
  | _formId_ | Integer | ID of the form to upload the file to |
  | _questionId_ | Integer | ID of the file question to upload the file to |
- Parameters:
  | Parameter | Type | Description |
  |--------------|----------------|-------------|
  | _files_ | Array of files | Files to upload |
  | _shareHash_ | String | optional, only necessary for uploads on a public share link |
- Response: **Status-Code OK**, as well as a list of uploaded files.

```
"data": [
  {
    "uploadedFileId": 42,
    "fileName": "document.pdf",
    "uploadToken": "a1b2c3d4e5f6..."
  }
]
```

When submitting the form, each file answer must include both `uploadedFileId` and `uploadToken` from this response for the same `questionId`. Uploads from other forms, questions, or sessions are rejected.

### Get a specific submission

Get a specific submission of a form. Viewing another user's submission requires the `results` permission; otherwise only the submission owner may view it.

- Endpoint: `/api/v3/forms/{formId}/submissions/{submissionId}`
- Method: `GET`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the submission |
  | _submissionId_ | Integer | ID of the submission to get |
- Response: The submission (the controller appends `userDisplayName`).

```
"data": {
  "id": 6,
  "formId": 3,
  "userId": "jonas",
  "timestamp": 1611274453,
  "answers": [
    {
      "id": 8,
      "submissionId": 6,
      "questionId": 1,
      "text": "Option 3"
    },
    {
      "id": 9,
      "submissionId": 6,
      "questionId": 2,
      "text": "One more."
    }
  ],
  "userDisplayName": "jonas"
}
```

### Insert a Submission

Store Submission to Database

- Endpoint: `/api/v3/forms/{formId}/submissions`
- Method: `POST`
- Url-Parameters:
  | Parameter | Type | Description |
  |--------------|----------------|-------------|
  | _formId_ | Integer | ID of the form to upload the file to |
- Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _answers_ | Array | Array of answers |
  | _shareHash_ | String | optional, only neccessary for submissions to a public share link |
- Restrictions: The array of answers has the following structure:
    - QuestionID as key
    - An **array** of values as value --> Even for short Text Answers, wrapped into Array.
    - For Question-Types with pre-defined answers (`multiple`, `multiple_unique`, `dropdown`), the array contains the corresponding option-IDs.
    - For File-Uploads, the array contains objects with `uploadedFileId` and `uploadToken` (both from the [Upload a file] endpoint for the same `questionId`).
    - Answers to questions that the [jumps](DataStructure.md#jumps) of the answers skip are ignored, and a skipped required question needs no answer.
    - An answer "Other" is `system-other-answer:` followed by its text, and the text must not be empty.

```
  {
    "1":[27,32],              // dropdown or multiple
    "2":["ShortTextAnswer"],  // All Text-Based Question-Types
    "3":[                     // File-Upload
      {"uploadedFileId": 42, "uploadToken": "a1b2c3d4e5f6..."},
      {"uploadedFileId": 43, "uploadToken": "f6e5d4c3b2a1..."}
  ],
}
```

- Response: **Status-Code OK**.

### Update a Submission

Update an existing submission (only allowed when `allowEditSubmissions` is set on the form).

- Endpoint: `/api/v3/forms/{formId}/submissions/{submissionId}`
- Method: `PUT`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the submission |
  | _submissionId_ | Integer | ID of the submission to update |
- Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _answers_ | Array | Array of answers (same structure as insert submission POST) |
- Restrictions:
    - The form must have `allowEditSubmissions` enabled.
    - Only the user who created the submission may update it.
    - Answers are validated the same way as on submit.
    - The answers replace all stored answers of the submission; answers to questions the [jumps](DataStructure.md#jumps) skip are not stored.
- Response: **Status-Code OK**, as well as the id of the updated submission.

```
"data": 5
```

### Delete a single Submission

- Endpoint: `/api/v3/forms/{formId}/submissions/{submissionId}`
- Method: `DELETE`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the submission |
  | _submissionId_ | Integer | ID of the submission to delete |
- Response: **Status-Code OK**, as well as the id of the deleted submission.

```
"data": 5
```

## Error Responses

All Endpoints return one of the following Error-Responses, if the request is not properly raised. This also results in a different `ocs:meta` object.

### 400 - Bad Request

This returns in case the Request is not properly set. This can e.g. include:

- The corresponding form can not be found
- Request Parameters are wrong (including formatting or type of parameters)

```
"ocs": {
"meta": {
"status": "failure",
"statuscode": 400,
"message": ""
},
"data": []
}
```

### 403 - Forbidden

This returns in case the authenticated user is not allowed to access this resource/endpoint. This can e.g. include:

- The user has no write access to the form (only form owner is allowed to edit)
- The user is not allowed to submit to the form (access-settings, form not yet open or expired, already submitted)

```
"ocs": {
"meta": {
"status": "failure",
"statuscode": 403,
"message": ""
},
"data": []
}
```

### 412 - Precondition Failed

This Error is not produed by the Forms-API, but comes from Nextclouds OCS API. Typically this is the result when missing the Request-Header `OCS-APIRequest: true`.

```
{
"message": "CSRF check failed"
}
```
