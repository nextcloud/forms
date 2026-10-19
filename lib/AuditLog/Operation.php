<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\AuditLog;

/**
 * Operations Forms writes to the admin audit log, in the form `app.entity.action`
 *
 * The values are matched by log consumers (filters, alert rules, SIEM imports),
 * so changing one is a breaking change for them. Case names may be renamed.
 */
enum Operation: string {
	case FormCreated = 'forms.form.created';
	case FormDeleted = 'forms.form.deleted';
	case FormOwnerTransferred = 'forms.form.owner_transferred';
	case FormAccessUpdated = 'forms.form_access.updated';
	case FormStateChanged = 'forms.form_state.changed';
	case FormSettingsUpdated = 'forms.form_settings.updated';
	case ResultsFileLinked = 'forms.results_file.linked';
	case ShareCreated = 'forms.share.created';
	case ShareUpdated = 'forms.share.updated';
	case ShareDeleted = 'forms.share.deleted';
	case ResultsDownloaded = 'forms.results.downloaded';
	case ResultsExported = 'forms.results.exported';
	case SubmissionUpdated = 'forms.submission.updated';
	case SubmissionDeleted = 'forms.submission.deleted';
	case SubmissionsDeletedAll = 'forms.submissions.deleted_all';
	case ConfigUpdated = 'forms.config.updated';

	/**
	 * Short description of when the operation is logged, used for the documentation
	 */
	public function description(): string {
		return match ($this) {
			self::FormCreated => 'A form was created, either empty or as a copy of another form',
			self::FormDeleted => 'A form was deleted with its questions, shares, submissions and uploaded files',
			self::FormOwnerTransferred => 'The ownership of a form was transferred to another user',
			self::FormAccessUpdated => 'A form was opened to or closed for all users of the instance',
			self::FormStateChanged => 'A form was closed, archived or reopened',
			self::FormSettingsUpdated => 'The response settings of a form (anonymity, multiple or editable submissions, expiration, submission limit) were changed',
			self::ResultsFileLinked => 'The results of a form were linked to a spreadsheet file, which is then kept in sync with the submissions',
			self::ShareCreated => 'A form was shared with a user, group or team or by public link, with its permissions',
			self::ShareUpdated => 'The permissions or the custom public link token of a form share were changed',
			self::ShareDeleted => 'A form share was deleted, by someone managing the form or through the share review',
			self::ResultsDownloaded => 'All submissions of a form were downloaded as a CSV, ODS or XLSX file',
			self::ResultsExported => 'All submissions of a form were exported to a file in the cloud',
			self::SubmissionUpdated => 'The answers of a submission were replaced',
			self::SubmissionDeleted => 'A submission was deleted',
			self::SubmissionsDeletedAll => 'All submissions of a form were deleted',
			self::ConfigUpdated => 'An admin setting of Forms (sharing, form creation, confirmation emails, comments) was changed',
		};
	}
}
