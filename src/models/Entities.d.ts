/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export interface FormsOption {
	local?: boolean
	id: number
	text: string
	order?: number
	questionId: number
	optionType: string
}
