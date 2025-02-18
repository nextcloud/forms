<?php

/**
 * @copyright Copyright (c] 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author Christian Hartmann <chris-hartmann@gmx.de>
 * @author Ferdinand Thiessen <opensource@fthiessen.de>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

$apiBase = '/api/{apiVersion}/';
$requirements_v3 = [
	'apiVersion' => 'v3',
	'formId' => '\d+',
	'questionId' => '\d+',
	'optionId' => '\d+',
	'shareId' => '\d+',
	'submissionId' => '\d+'
];

return [
	'routes' => [
		// Internal AppConfig routes
		['name' => 'config#getAppConfig', 'url' => '/config', 'verb' => 'GET'],
		['name' => 'config#updateAppConfig', 'url' => '/config/update', 'verb' => 'PATCH'],

		// Public Share Link
		['name' => 'page#public_link_view', 'url' => '/s/{hash}', 'verb' => 'GET'],

		// Embedded View
		['name' => 'page#embedded_form_view', 'url' => '/embed/{hash}', 'verb' => 'GET'],

		// Internal views
		['name' => 'page#views', 'url' => '/{hash}/{view}', 'verb' => 'GET'],
		// Internal Form Link
		['name' => 'page#internal_link_view', 'url' => '/{hash}', 'verb' => 'GET'],
		// App Root
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
	],

	'ocs' => [
		// CORS Preflight
		['name' => 'api#preflightedCors', 'url' => $apiBase . '{path}', 'verb' => 'OPTIONS', 'requirements' => [
			'path' => '.+',
			'apiVersion' => 'v2(\.[1-5])?|v3'
		]],

		// API routes v3
		// Forms
		['name' => 'api#getForms', 'url' => $apiBase . 'forms', 'verb' => 'GET', 'requirements' => $requirements_v3],
		['name' => 'api#newForm', 'url' => $apiBase . 'forms', 'verb' => 'POST', 'requirements' => $requirements_v3],
		['name' => 'api#getForm', 'url' => $apiBase . 'forms/{formId}', 'verb' => 'GET', 'requirements' => $requirements_v3],
		['name' => 'api#updateForm', 'url' => $apiBase . 'forms/{formId}', 'verb' => 'PATCH', 'requirements' => $requirements_v3],
		['name' => 'api#deleteForm', 'url' => $apiBase . 'forms/{formId}', 'verb' => 'DELETE', 'requirements' => $requirements_v3],

		// Questions
		['name' => 'api#getQuestions', 'url' => $apiBase . 'forms/{formId}/questions', 'verb' => 'GET', 'requirements' => $requirements_v3],
		['name' => 'api#newQuestion', 'url' => $apiBase . 'forms/{formId}/questions', 'verb' => 'POST', 'requirements' => $requirements_v3],
		['name' => 'api#getQuestion', 'url' => $apiBase . 'forms/{formId}/questions/{questionId}', 'verb' => 'GET', 'requirements' => $requirements_v3],
		['name' => 'api#updateQuestion', 'url' => $apiBase . 'forms/{formId}/questions/{questionId}', 'verb' => 'PATCH', 'requirements' => $requirements_v3],
		['name' => 'api#deleteQuestion', 'url' => $apiBase . 'forms/{formId}/questions/{questionId}', 'verb' => 'DELETE', 'requirements' => $requirements_v3],
		['name' => 'api#reorderQuestions', 'url' => $apiBase . 'forms/{formId}/questions', 'verb' => 'PATCH', 'requirements' => $requirements_v3],

		// Options
		// ['name' => 'api#getOptions', 'url' => $apiBase . 'forms/{formId}/questions/{questionId}/options', 'verb' => 'GET', 'requirements' => $requirements_v3],
		['name' => 'api#newOption', 'url' => $apiBase . 'forms/{formId}/questions/{questionId}/options', 'verb' => 'POST', 'requirements' => $requirements_v3],
		// ['name' => 'api#getOption', 'url' => $apiBase . 'forms/{formId}/questions/{questionId}/options/{optionId}', 'verb' => 'GET', 'requirements' => $requirements_v3],
		['name' => 'api#updateOption', 'url' => $apiBase . 'forms/{formId}/questions/{questionId}/options/{optionId}', 'verb' => 'PATCH', 'requirements' => $requirements_v3],
		['name' => 'api#deleteOption', 'url' => $apiBase . 'forms/{formId}/questions/{questionId}/options/{optionId}', 'verb' => 'DELETE', 'requirements' => $requirements_v3],
		['name' => 'api#reorderOptions', 'url' => $apiBase . 'forms/{formId}/questions/{questionId}/options', 'verb' => 'PATCH', 'requirements' => $requirements_v3],

		// Shares
		// ['name' => 'shareApi#getUserShares', 'url' => $apiBase . 'shares', 'verb' => 'GET', 'requirements' => $requirements_v3],
		// ['name' => 'shareApi#getShares', 'url' => $apiBase . 'forms/{formId}/shares', 'verb' => 'GET', 'requirements' => $requirements_v3],
		['name' => 'shareApi#newShare', 'url' => $apiBase . 'forms/{formId}/shares', 'verb' => 'POST', 'requirements' => $requirements_v3],
		// ['name' => 'shareApi#getShare', 'url' => $apiBase . 'forms/{formId}/shares/{shareId}', 'verb' => 'GET', 'requirements' => $requirements_v3],
		['name' => 'shareApi#updateShare', 'url' => $apiBase . 'forms/{formId}/shares/{shareId}', 'verb' => 'PATCH', 'requirements' => $requirements_v3],
		['name' => 'shareApi#deleteShare', 'url' => $apiBase . 'forms/{formId}/shares/{shareId}', 'verb' => 'DELETE', 'requirements' => $requirements_v3],

		// Submissions
		['name' => 'api#getSubmissions', 'url' => $apiBase . 'forms/{formId}/submissions', 'verb' => 'GET', 'requirements' => $requirements_v3],
		['name' => 'api#newSubmission', 'url' => $apiBase . 'forms/{formId}/submissions', 'verb' => 'POST', 'requirements' => $requirements_v3],
		['name' => 'api#deleteAllSubmissions', 'url' => $apiBase . 'forms/{formId}/submissions', 'verb' => 'DELETE', 'requirements' => $requirements_v3],
		//['name' => 'api#getSubmission', 'url' => $apiBase . 'forms/{formId}/submissions/{submissionId}', 'verb' => 'GET', 'requirements' => $requirements_v3],
		//['name' => 'api#updateSubmission', 'url' => $apiBase . 'forms/{formId}/submissions/{submissionId}', 'verb' => 'PATCH', 'requirements' => $requirements_v3],
		['name' => 'api#deleteSubmission', 'url' => $apiBase . 'forms/{formId}/submissions/{submissionId}', 'verb' => 'DELETE', 'requirements' => $requirements_v3],
		['name' => 'api#exportSubmissionsToCloud', 'url' => $apiBase . 'forms/{formId}/submissions/export', 'verb' => 'POST', 'requirements' => $requirements_v3],
		['name' => 'api#uploadFiles', 'url' => $apiBase . 'forms/{formId}/submissions/files/{questionId}', 'verb' => 'POST', 'requirements' => $requirements_v3],

		// Legacy v2 routes (TODO: remove with Forms v5)
		// Forms
		['name' => 'api#getFormsLegacy', 'url' => $apiBase . 'forms', 'verb' => 'GET', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		['name' => 'api#newFormLegacy', 'url' => $apiBase . 'form', 'verb' => 'POST', 'requirements' => [
			'apiVersion_path' => 'v2(\.[1-5])?'
		]],
		['name' => 'api#getFormLegacy', 'url' => $apiBase . 'form/{id}', 'verb' => 'GET', 'requirements' => [
			'apiVersion_path' => 'v2(\.[1-5])?',
			'id' => '\d+'
		]],
		['name' => 'api#cloneFormLegacy', 'url' => $apiBase . 'form/clone/{id}', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'id' => '\d+'
		]],
		['name' => 'api#updateFormLegacy', 'url' => $apiBase . 'form/update', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		['name' => 'api#updateFormLegacy', 'url' => $apiBase . 'form/update', 'verb' => 'PATCH', 'requirements' => [
			'apiVersion' => 'v2\.[2-5]'
		]],
		['name' => 'api#transferOwnerLegacy', 'url' => $apiBase . 'form/transfer', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2\.[2-5]'
		]],
		['name' => 'api#deleteFormLegacy', 'url' => $apiBase . 'form/{id}', 'verb' => 'DELETE', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'id' => '\d+'
		]],
		['name' => 'api#getPartialFormLegacy', 'url' => $apiBase . 'partial_form/{hash}', 'verb' => 'GET', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'hash' => '[a-zA-Z0-9]{16}'
		]],
		['name' => 'api#getSharedFormsLegacy', 'url' => $apiBase . 'shared_forms', 'verb' => 'GET', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],

		// Questions
		['name' => 'api#newQuestionLegacy', 'url' => $apiBase . 'question', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		// TODO: Remove POST in next API release
		['name' => 'api#updateQuestionLegacy', 'url' => $apiBase . 'question/update', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		['name' => 'api#updateQuestionLegacy', 'url' => $apiBase . 'question/update', 'verb' => 'PATCH', 'requirements' => [
			'apiVersion' => 'v2\.[2-5]'
		]],
		// TODO: Remove POST in next API release
		['name' => 'api#reorderQuestionsLegacy', 'url' => $apiBase . 'question/reorder', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		['name' => 'api#reorderQuestionsLegacy', 'url' => $apiBase . 'question/reorder', 'verb' => 'PUT', 'requirements' => [
			'apiVersion' => 'v2\.[2-5]'
		]],
		['name' => 'api#deleteQuestionLegacy', 'url' => $apiBase . 'question/{id}', 'verb' => 'DELETE', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'id' => '\d+'
		]],
		['name' => 'api#cloneQuestionLegacy', 'url' => $apiBase . 'question/clone/{id}', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2\.[3-5]',
			'id' => '\d+'
		]],

		// Options
		['name' => 'api#newOptionLegacy', 'url' => $apiBase . 'option', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		// TODO: Remove POST in next API release
		['name' => 'api#updateOptionLegacy', 'url' => $apiBase . 'option/update', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		['name' => 'api#updateOptionLegacy', 'url' => $apiBase . 'option/update', 'verb' => 'PATCH', 'requirements' => [
			'apiVersion' => 'v2\.[2-5]'
		]],
		['name' => 'api#deleteOptionLegacy', 'url' => $apiBase . 'option/{id}', 'verb' => 'DELETE', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'id' => '\d+'
		]],

		// Shares
		['name' => 'shareApi#newShareLegacy', 'url' => $apiBase . 'share', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		['name' => 'shareApi#deleteShareLegacy', 'url' => $apiBase . 'share/{id}', 'verb' => 'DELETE', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'id' => '\d+'
		]],
		// TODO: Remove POST in next API release
		['name' => 'shareApi#updateShareLegacy', 'url' => $apiBase . 'share/update', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2\.[1-5]'
		]],
		['name' => 'shareApi#updateShareLegacy', 'url' => $apiBase . 'share/update', 'verb' => 'PATCH', 'requirements' => [
			'apiVersion' => 'v2\.[2-5]'
		]],

		// Submissions
		['name' => 'api#getSubmissionsLegacy', 'url' => $apiBase . 'submissions/{hash}', 'verb' => 'GET', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'hash' => '[a-zA-Z0-9]{16}'
		]],
		['name' => 'api#exportSubmissionsLegacy', 'url' => $apiBase . 'submissions/export/{hash}', 'verb' => 'GET', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'hash' => '[a-zA-Z0-9]{16}'
		]],
		['name' => 'api#exportSubmissionsToCloudLegacy', 'url' => $apiBase . 'submissions/export', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		['name' => 'api#deleteAllSubmissionsLegacy', 'url' => $apiBase . 'submissions/{formId}', 'verb' => 'DELETE', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'formId' => '\d+'
		]],
		['name' => 'api#uploadFilesLegacy', 'url' => $apiBase . 'uploadFiles/{formId}/{questionId}', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2.5',
			'formId' => '\d+',
			'questionId' => '\d+'
		]],
		['name' => 'api#insertSubmissionLegacy', 'url' => $apiBase . 'submission/insert', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?'
		]],
		['name' => 'api#deleteSubmissionLegacy', 'url' => $apiBase . 'submission/{id}', 'verb' => 'DELETE', 'requirements' => [
			'apiVersion' => 'v2(\.[1-5])?',
			'id' => '\d+'
		]],
		// Submissions linking with file in cloud
		['name' => 'api#linkFileLegacy', 'url' => $apiBase . 'form/link/{fileFormat}', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2.[4-5]',
			'fileFormat' => 'csv|ods|xlsx'
		]],
		['name' => 'api#unlinkFileLegacy', 'url' => $apiBase . 'form/unlink', 'verb' => 'POST', 'requirements' => [
			'apiVersion' => 'v2.[4-5]',
		]]
	]
];
