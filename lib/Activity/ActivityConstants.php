<?php

/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
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
