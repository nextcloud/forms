/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Represents the state of a form.
 *
 * Possible values:
 * - `FormActive` (0): The form is currently active and can be interacted with.
 * - `FormClosed` (1): The form is closed and no longer accepting input.
 * - `FormArchived` (2): The form is archived and stored for reference.
 *
 * Keep in sync with Constants.php
 */
export enum FormState {
	FormActive = 0,
	FormClosed = 1,
	FormArchived = 2,
}

/**
 * The debounce time in milliseconds for input events.
 *
 * This constant is used to limit the rate at which input-related
 * operations are triggered, improving performance and user experience.
 */
export const INPUT_DEBOUNCE_MS = 400

/**
 * A constant representing the prefix used for identifying "other" answers
 */
export const QUESTION_EXTRASETTINGS_OTHER_PREFIX = 'system-other-answer:'

export enum OptionType {
    Row = 'row',
    Column = 'column',
    Choice = 'choice',
}
