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

- In API version 3.0 the following endpoints were introduced/changed:
    - `GET /api/v3/forms/{formId}/questions` to get all questions of a form
    - `GET /api/v3/forms/{formId}/questions/{questionId}` to get a single question
    - `POST /api/v3/forms/{formId}/questions/{questionId}/options` does now accept more options at once
    - `PATCH /api/v3/forms/{formId}/questions/{questionId}/options/reorder` to reorder the options
    - `POST /api/v3/forms/{formId}/submissions/files/{questionId}` to upload a file to a file question before submitting the form
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
    "permissions": [
      "edit",
      "results",
      "submit"
    ],
    "partial": true,
    "state": 0
  },
  {
    "id": 3,
    "hash": "em4djk8B9BpXnkYG",
    "title": "Form 1",
    "expires": 0,
    "permissions": [
      "edit",
      "results",
      "submit"
    ],
    "partial": true,
    "state": 0
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
  "fileFormat": "csv",
  "fileId": 157,
  "filePath": "foo/bar",
  "isAnonymous": false,
  "submitMultiple": true,
  "allowEditSubmissions": false,
  "showExpiration": false,
  "canSubmit": true,
  "state": 0,
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
- Response: Returns the full object of the new form. See [Request full data of a Form](#request-full-data-of-a-form)

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
  | _questionId_ | Integer | Id of the
- Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _keyValuePairs_ | Array | Array of key-value pairs to update |
- Restrictions: It is **not allowed** to update one of the following key-value pairs: _id, formId, order_.
- Response: **Status-Code OK**, as well as the id of the updated question.

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
- Response: Array of questionIDs and their corresponding order.

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
- Response: Returns cloned question object with the new ID set.

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
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _text_ | Array | Array of strings containing the new options |
- Response: The new array of option objects

```
"data": {
  "id": 7,
  "questionId": 1,
  "text": "test"
}
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
- Response: **Status-Code OK**, as well as the id of the deleted option.

```
"data": 7
```

### Reorder options

- Endpoint: `/api/v3/forms/{formId}/questions/{questionId}/options/reorder`
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
- Restrictions: Currently only the _permissions_ can be updated.
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

Upload a file to an answer before form submission

- Endpoint: `/api/v3/forms/{formId}/submissions/files/{questionId}`
- Method: `POST`
- Url-Parameters:
  | Parameter | Type | Description |
  |--------------|----------------|-------------|
  | _formId_ | Integer | ID of the form to upload the file to |
  | _questionId_ | Integer | ID of the question to upload the file to |
- Parameters:
  | Parameter | Type | Description |
  |--------------|----------------|-------------|
  | _files_ | Array of files | Files to upload |
- Response: **Status-Code OK**, as well as the id of the uploaded file and it's name.

```
"data": {"uploadedFileId": integer, "fileName": "string"}
```

### Get a specific submission

Get all Submissions to a Form

- Endpoint: `/api/v3/forms/{formId}/submissions/{submissionId}`
- Method: `GET`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form to get the submissions for |
  | _submissionId_ | Integer | ID of the submission to get |
  | _query_ | Integer | Search string for full-text search. Can be a username |
  | _limit_ | Integer | How many submissions to fetch |
  | _offset_ | Integer | Offset for the pagination |
- Response: The submission

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
    },
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
    - For File-Uploads, the array contains the objects with key `uploadedFileId` (value from Upload a file endpoint).

```
  {
    "1":[27,32],              // dropdown or multiple
    "2":["ShortTextAnswer"],  // All Text-Based Question-Types
    "3":[                     // File-Upload
      {"uploadedFileId": integer},
      {"uploadedFileId": integer}
  ],
}
```

- Response: **Status-Code OK**.

### Delete a single Submission

- Endpoint: `/api/v3/forms/{formId}/submissions/{submissionId}`
- Method: `DELETE`
- Url-Parameters:
  | Parameter | Type | Description |
  |-----------|---------|-------------|
  | _formId_ | Integer | ID of the form containing the submission |
  | _submissionId_ | Integer | ID of the submission to delete |
- Response: **Status-Code OK**, as well as the id of the deleted submission.

````
"data": 5
```

## Error Responses

All Endpoints return one of the following Error-Responses, if the request is not properly raised. This also results in a different `ocs:meta` object.

### 400 - Bad Request

This returns in case the Request is not properly set. This can e.g. include:

-   The corresponding form can not be found
-   Request Parameters are wrong (including formatting or type of parameters)

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

-   The user has no write access to the form (only form owner is allowed to edit)
-   The user is not allowed to submit to the form (access-settings, form expired, already submitted)

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

```
````
