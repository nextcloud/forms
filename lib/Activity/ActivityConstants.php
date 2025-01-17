<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Activity;

class ActivityConstants {
	/*****
	 * Types can have different Settings for Mail/Notifications.
	 */
	public const TYPE_NEWSHARE = 'forms_newshare';
	public const TYPE_NEWSUBMISSION = 'forms_newsubmission';
	public const TYPE_NEWSHAREDSUBMISSION = 'forms_newsharedsubmission';

	/*****
	 * Subjects are internal 'types', that get interpreted by our own Provider.
	 */

	/**
	 * Somebody shared a form to a selected user
	 * Needs Params:
	 * "user": The userId of the user who shared.
	 * "formTitle": The hash of the shared form.
	 * "formHash": The hash of the shared form
	 */
	public const SUBJECT_NEWSHARE = 'newshare';

	/**
	 * Somebody shared a form to a selected group
	 * Needs Params:
	 * "user": The userId of the user who shared.
	 * 'groupId': The groupId, that was shared to.
	 * "formTitle": The hash of the shared form.
	 * "formHash": The hash of the shared form
	 */
	public const SUBJECT_NEWGROUPSHARE = 'newgroupshare';

	/**
	 * Somebody shared a form to a selected circle
	 * Needs Params:
	 * "user": The userId of the user who shared.
	 * 'circleId': The circleId, that was shared to.
	 * "formTitle": The hash of the shared form.
	 * "formHash": The hash of the shared form
	 */
	public const SUBJECT_NEWCIRCLESHARE = 'newcircleshare';
	/**
	 * Somebody submitted an answer to a form
	 * Needs Params:
	 * "user": The userId of the user who submitted. Can also be our 'anon-user-', which will be handled separately.
	 * "formTitle": The hash of the form.
	 * "formHash": The hash of the form
	 */
	public const SUBJECT_NEWSUBMISSION = 'newsubmission';
}
