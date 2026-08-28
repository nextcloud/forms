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
	id?: number
	formId?: number
	shareType: number
	permissions?: string[]
	shareWith?: string
	displayName?: string
}

export interface FormsAnswer {
	id: number
	submissionId?: number
	questionId: number
	questionName?: string
	text: string
	fileId?: number
}

export interface FormsSubmission {
	id: number
	formId?: number
	userId: string
	timestamp: number | string
	answers: FormsAnswer[]
	userDisplayName: string
}

export interface FormsQuestionExtraSettings {
	allowOtherAnswer?: boolean
	shuffleOptions?: boolean
	optionsLimitMax?: number
	optionsLimitMin?: number
	questionType?: string
	optionsLabelLowest?: string
	optionsLabelHighest?: string
	optionsLowest?: number
	optionsHighest?: number
	inputType?: string
	inputValidation?: string
	isRange?: boolean
	dateMinLimit?: string
	dateMaxLimit?: string
	dateMinLimitType?: string
	dateMaxLimitType?: string
	dateMinLimitTypeAmount?: number
	dateMaxLimitTypeAmount?: number
	fileFormats?: string[]
	fileMaxFiles?: number
	fileMaxSize?: number
	[key: string]: unknown
}

export interface FormsQuestion {
	id: number
	formId?: number | null
	text: string
	type: string
	order?: number | null
	options?: FormsOption[]
	answers?: FormsAnswer[]
	description?: string
	isRequired?: boolean
	name?: string
	extraSettings?: FormsQuestionExtraSettings | null
	[key: string]: unknown
}

export interface FormsAccess {
	permitAllUsers?: boolean
	showToAllUsers?: boolean
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
	access: FormsAccess | Record<string, unknown>
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
	submissions?: FormsSubmission[]
}

export interface MaxStringLengths {
	questionText: number
	questionDescription: number
	optionText: number
	answerText: number
	submissionMessage: number
	formTitle: number
	formDescription: number
	optionsLabelLowest: number
	optionsLabelHighest: number
	fileFormats: number
	[key: string]: number
}

export type GridQuestionValues = Record<number, number | number[]>

/** Statistics for a single option in a countable question (e.g., multiple choice, checkboxes) */
export interface OptionStats extends FormsOption {
	count: number
	percentage: number
	best?: boolean
}

/** Statistics for a single option in a ranking question (Borda count) */
export interface BordaRankStats {
	id: number | string
	text: string
	bordaTotal: number
	rankSum: number
	count: number
	avgRank: string | number
	best?: boolean
}

/** Statistics for a single cell in a grid question response matrix */
export interface GridMatrixCell {
	answersCount: number
	percentage: number
	totalValue: number
	averageValue: string | number
}
