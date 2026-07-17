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

export interface FormsShare {
	shareType: number
	permissions?: string[]
	shareWith?: string
}

export interface FormsQuestion {
	id: number
	text: string
	type: string
	order?: number | null
	options?: unknown[]
	answers?: unknown[]
	description?: string
	isRequired?: boolean
	name?: string
	extraSettings?: Record<string, unknown> | null
	[key: string]: unknown
}

export interface FormsForm {
	id: number
	hash: string
	title: string
	description: string
	partial?: boolean
	ownerId: string
	created: number
	access: unknown
	expires: number
	fileFormat?: string | null
	fileId?: number | null
	filePath?: string | null
	isAnonymous: boolean
	isMaxSubmissionsReached: boolean
	lastUpdated: number
	submitMultiple: boolean
	allowEditSubmissions: boolean
	showExpiration: boolean
	canSubmit: boolean
	permissions: string[]
	questions: FormsQuestion[]
	state: 0 | 1 | 2
	lockedBy?: string | null
	lockedUntil?: number | null
	maxSubmissions?: number | null
	shares: FormsShare[]
	submissionCount?: number
	submissionMessage?: string | null
	confirmationEmailEnabled: boolean
	confirmationEmailSubject?: string | null
	confirmationEmailBody?: string | null
	confirmationEmailQuestionId?: number | null
	allowComments: boolean
}
