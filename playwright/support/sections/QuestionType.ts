/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export enum QuestionType {
	Checkboxes = 'Checkboxes',
	Color = 'Color',
	Date = 'Date',
	Dropdown = 'Dropdown',
	File = 'File',
	Grid = 'Grid',
	LinearScale = 'Linear scale',
	LongAnswer = 'Long text',
	Ranking = 'Ranking',
	RadioButtons = 'Radio buttons',
	ShortAnswer = 'Short answer',
}

/**
 * Cell type subtypes available for grid questions.
 * Labels of the entries in the grid subtype menu.
 */
export enum GridSubtype {
	Checkboxes = 'Checkboxes',
	Number = 'Number',
	RadioButtons = 'Radio buttons',
}
