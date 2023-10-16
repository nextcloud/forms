# Forms Public API
This file contains the API-Documentation. For more information on the returned Data-Structures, please refer to the [corresponding Documentation](DataStructure.md).

## Generals
- Base URL for all calls to the forms API is `<nextcloud_base_url>/ocs/v2.php/apps/forms`
- All Requests need to provide some authentication information.
- All Requests to OCS-Endpoints require the Header `OCS-APIRequest: true`
- Unless otherwise specified, all parameters are mandatory.

- By default, the API returns data formatted as _xml_. If formatting as _json_ is desired, the request should contain the header `Accept: application/json`. For simple representation, the output presented in this document is all formatted as _json_.
- The OCS-Endpoint *always returns* an object called `ocs`. This contains an object `meta` holding some meta-data, as well as an object `data` holding the actual data. In this document, the response-blocks only show the `data`, if not explicitely stated different.
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
### Breaking Changes on API v2
- The `mandatory` property of questions has been removed. It is replaced by `isRequired`.
- Completely new way of handling access & shares.

### Other API changes
- In API version 2.1 the endpoint `/api/v2.1/share/update` was added to update a Share

## Form Endpoints
### List owned Forms
Returns condensed objects of all Forms beeing owned by the authenticated user.
- Endpoint: `/api/v2.1/forms`
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
    "partial": true
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
    "partial": true
  }
]
```

### List shared Forms
Returns condensed objects of all Forms, that are shared & shown to the authenticated user and that have not expired yet.
- Endpoint: `/api/v2.1/shared_forms`
- Method: `GET`
- Parameters: None
- Response: Array of condensed Form Objects, sorted as newest first, similar to [List owned Forms](#list-owned-forms).
```
See above, 'List owned forms'
```

### Get a partial Form
Returns a single partial form object, corresponding to owned/shared form-listings.
- Endpoint: `/api/v2.1/partial_form/{hash}`
- Method: `GET`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _hash_    | String  | Hash of the form to request |
- Response: Partial form object, similar to form-list elements.
```
"data": {
  "id": 6,
  "hash": "yWeMwcwCwoqRs8T2",
  "title": "Form 2",
  "expires": 0,
  "permissions": [
    "submit"
  ],
  "partial": true
}
```

### Create a new Form
- Endpoint: `/api/v2.1/form`
- Method: `POST`
- Parameters: None
- Response: The new form object, similar to requesting an existing form.
```
See next section, 'Request full data of a form'
```

### Request full data of a form
Returns the full-depth object of the requested form (without submissions).
- Endpoint: `/api/v2.1/form/{id}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the form to request |
- Method: `GET`
- Response: A full object of the form, including access, questions and options in full depth.
```
"data": {
  "id": 3,
  "hash": "em4djk8B9BpXnkYG",
  "title": "Form 1",
  "description": "Description Text",
  "ownerId": "jonas",
  "created": 1611240961,
  "access": {
    "permitAllUsers": false,
    "showToAllUsers": false
  },
  "expires": 0,
  "isAnonymous": false,
  "submitMultiple": true,
  "showExpiration": false,
  "canSubmit": true,
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
          "text": "Option 1"
        },
        {
          "id": 2,
          "questionId": 1,
          "text": "Option 2"
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
      "name": "something_other",
      "options": [],
      "extraSettings": {}
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
  ]
}
```

### Clone a form
Creates a clone of a form (without submissions).
- Endpoint: `/api/v2.1/form/clone/{id}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the form to clone |
- Method: `POST`
- Response: Returns the full object of the new form. See [Request full data of a Form](#request-full-data-of-a-form)
```
See section 'Request full data of a form'.
```

### Update form properties
Update a single or multiple properties of a form-object. Concerns **only** the Form-Object, properties of Questions, Options and Submissions, as well as their creation or deletion, are handled separately.
- Endpoint: `/api/v2.1/form/update`
- Method: `POST`
- Parameters:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the form to update |
  | _keyValuePairs_ | Array | Array of key-value pairs to update |
- Restrictions: It is **not allowed** to update one of the following key-value pairs: _id, hash, ownerId, created_
- Response: **Status-Code OK**, as well as the id of the updated form.
```
"data": 3
```

### Delete a form
- Endpoint: `/api/v2.1/form/{id}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the form to delete |
- Method: `DELETE`
- Response: **Status-Code OK**, as well as the id of the deleted form.
```
"data": 3
```

## Question Endpoints
Contains only manipulative question-endpoints. To retrieve questions, request the full form data.

### Create a new question
- Endpoint: `/api/v2.1/question`
- Method: `POST`
- Parameters:
  | Parameter | Type    | Optional | Description |
  |-----------|---------|----------|-------------|
  | _formId_  | Integer |          | ID of the form, the new question will belong to |
  | _type_    | [QuestionType](DataStructure.md#question-types) |  | The question-type of the new question |
  | _text_    | String  | yes      | *Optional* The text of the new question. |
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

### Update question properties
Update a single or multiple properties of a question-object.
- Endpoint: `/api/v2.1/question/update`
- Method: `POST`
- Parameters:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the question to update |
  | _keyValuePairs_ | Array | Array of key-value pairs to update |
- Restrictions: It is **not allowed** to update one of the following key-value pairs: _id, formId, order_.
- Response: **Status-Code OK**, as well as the id of the updated question.
```
"data": 1
```

### Reorder questions
Reorders all Questions of a single form
- Endpoint: `/api/v2.1/question/reorder`
- Method: `POST`
- Parameters:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _formId_  | Integer | ID of the form, the questions belong to |
  | _newOrder_ | Array  | Array of **all** Question-IDs, ordered in the desired order |
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
- Endpoint: `/api/v2.1/question/{id}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the question to delete |
- Method: `DELETE`
- Response: **Status-Code OK**, as well as the id of the deleted question.
```
"data": 4
```

## Option Endpoints
Contains only manipulative question-endpoints. To retrieve options, request the full form data.

### Create a new Option
- Endpoint: `/api/v2.1/option`
- Method: `POST`
- Parameters:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _questionId_ | Integer | ID of the question, the new option will belong to |
  | _text_    | String  | The text of the new option |
- Response: The new option object
```
"data": {
  "id": 7,
  "questionId": 1,
  "text": "test"
}
```

### Update option properties
- Endpoint: `/api/v2.1/option/update`
- Method: `POST`
- Parameters:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the option to update |
  | _keyValuePairs_ | Array | Array of key-value pairs to update |
- Restrictions: It is **not allowed** to update one of the following key-value pairs: _id, questionId_.
- Response: **Status-Code OK**, as well as the id of the updated option.
```
"data": 7
```

### Delete an option
- Endpoint: `/api/v2.1/option/{id}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the option to delete |
- Method: `DELETE`
- Response: **Status-Code OK**, as well as the id of the deleted option.
```
"data": 7
```

## Sharing Endpoints
### Add a new Share
- Endpoint: `/api/v2.1/share`
- Method: `POST`
- Parameters:
  | Parameter    | Type     | Description |
  |--------------|----------|-------------|
  | _formId_     | Integer  | Id of the form to share |
  | _shareType_  | String   | NC-shareType, out of the used shareTypes. |
  | _shareWith_  | String   | User/Group for the share. Not used for link-shares. |
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

### Delete a Share
- Endpoint: `/api/v2.1/share/{id}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the share to delete |
- Method: `DELETE`
- Response: **Status-Code OK**, as well as the id of the deleted share.
```
"data": 5
```

### Update a Share
- Endpoint: `/api/v2.1/share/update`
- Url-Parameter:
  | Parameter        | Type     | Description |
  |------------------|----------|-------------|
  | _id_             | Integer  | ID of the share to update |
  | *keyValuePairs*¹ | Array    | Array of key-value pairs to update |
  
  ¹Currently only the _permissions_ can be updated.
- Method: `POST`
- Response: **Status-Code OK**, as well as the id of the share object.
```
"data": 5
```

## Submission Endpoints
### Get Form Submissions
Get all Submissions to a Form
- Endpoint: `/api/v2.1/submissions/{hash}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _hash_    | String  | Hash of the form to get the submissions for |
- Method: `GET`
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
          "text": "Option 1"
        },
        {
          "id": 27,
          "questionId": 1,
          "text": "Option 2"
        },
        {
          "id": 30,
          "questionId": 1,
          "text": "Option 3"
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
  ]
}
```

### Get Submissions as csv (Download)
Returns all submissions to the form in form of a csv-file.
- Endpoint: `/api/v2.1/submissions/export/{hash}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _hash_    | String  | Hash of the form to get the submissions for |
- Method: `GET`
- Response: A Data Download Response containg the headers `Content-Disposition: attachment; filename="Form 1 (responses).csv"` and `Content-Type: text/csv;charset=UTF-8`. The actual data contains all submissions to the referred form, formatted as comma separated and escaped csv.
```
"User display name","Timestamp","Question 1","Question 2"
"jonas","Friday, January 22, 2021 at 12:47:29 AM GMT+0:00","Option 2","Answer"
"jonas","Friday, January 22, 2021 at 12:45:57 AM GMT+0:00","Option 3","NextAnswer"
```

### Export Submissions to Cloud (Files-App)
Creates a csv file and stores it to the cloud, resp. Files-App.
- Endpoint: `/api/v2.1/submissions/export`
- Method: `POST`
- Parameters:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _hash_    | String  | Hash of the form to get the submissions for |
  | _path_    | String  | Path within User-Dir, to store the file to |
- Response: Stores the file to the given path and returns the fileName.
```
"data": "Form 2 (responses).csv"
```

### Delete Submissions
Delete all Submissions to a form
- Endpoint: `/api/v2.1/submissions/{formId}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _formId_  | Integer | ID of the form to delete the submissions for |
- Method: `DELETE`
- Response: **Status-Code OK**, as well as the id of the corresponding form.
```
"data": 3
```

### Insert a Submission
Store Submission to Database
- Endpoint: `/api/v2.1/submission/insert`
- Method: `POST`
- Parameters:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _formId_  | Integer | ID of the form to submit into |
  | _answers_ | Array   | Array of Answers |
  | _shareHash_ | String | optional, only neccessary for submissions to a public share link |
  
  The Array of Answers has the following structure:
  - QuestionID as key
  - An **array** of values as value --> Even for short Text Answers, wrapped into Array.
  - For Question-Types with pre-defined answers (`multiple`, `multiple_unique`, `dropdown`), the array contains the corresponding option-IDs.
  ```
  {
    "1":[27,32],              // dropdown or multiple
    "2":["ShortTextAnswer"],  // All Text-Based Question-Types
  }
  ```
- Response: **Status-Code OK**.

### Delete a single Submission
- Endpoint: `/api/v2.1/submission/{id}`
- Url-Parameter:
  | Parameter | Type    | Description |
  |-----------|---------|-------------|
  | _id_      | Integer | ID of the submission to delete |
- Method: `DELETE`
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
- The user is not allowed to submit to the form (access-settings, form expired, already submitted)

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
