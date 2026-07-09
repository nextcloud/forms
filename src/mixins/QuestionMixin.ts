/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable @typescript-eslint/no-explicit-any */

import type { FormsOption } from '../models/Entities.d.ts'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { defineComponent } from 'vue'
import Question from '../components/Questions/Question.vue'
import { INPUT_DEBOUNCE_MS, OptionType } from '../models/Constants.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'

/** QuestionMixin data interface */
interface QuestionMixinData {
	errorMessage: string | null
}

export default defineComponent({
	name: 'QuestionMixin',

	inheritAttrs: false,

	emits: [
		'update:text',
		'update:description',
		'update:isRequired',
		'update:extraSettings',
		'update:name',
		'update:values',
		'delete',
		'clone',
		'keydown',
		'moveDown',
		'moveUp',
	],

	props: {
		/** Question-Id */
		id: {
			type: Number,
			required: true,
		},

		/** ID of the form */
		formId: {
			type: Number,
			default: null,
		},

		/** The question title */
		text: {
			type: String,
			required: true,
		},

		/** Question Description */
		description: {
			type: String,
			required: true,
		},

		/** Required-Setting */
		isRequired: {
			type: Boolean,
			required: true,
		},

		/** The index of the question */
		index: {
			type: Number,
			required: true,
		},

		/** Technical name */
		name: {
			type: String,
			default: '',
		},

		/** The user answers */
		values: {
			type: [Array, Object],
			default() {
				return []
			},
		},

		/** The question list of answers */
		options: {
			type: Array as () => FormsOption[],
			required: true,
		},

		/** Order of the question */
		order: {
			type: Number,
			default: -1,
		},

		/** Question type */
		type: {
			type: String,
			default: null,
		},

		/** Answer type model object */
		answerType: {
			type: Object,
			required: true,
		},

		/** Submission or Edit-Mode */
		readOnly: {
			type: Boolean,
			default: false,
		},

		/** Database-Restrictions */
		maxStringLengths: {
			type: Object,
			required: true,
		},

		/** Extra settings */
		extraSettings: {
			default: () => {
				return {}
			},
		},

		/** Mime-Types and file extensions that are allowed to be uploaded */
		accept: {
			type: Array,
			default() {
				return []
			},
		},

		/** Can question be moved up in order? */
		canMoveUp: {
			type: Boolean,
			default: false,
		},

		/** Can question be moved down in order? */
		canMoveDown: {
			type: Boolean,
			default: false,
		},
	},

	components: {
		Question,
	},

	data(): QuestionMixinData {
		return {
			/** The shown error message */
			errorMessage: null,
		}
	},

	computed: {
		questionProps(): any {
			const props = { ...this.$props }
			const allowedKeys = Object.keys(Question.props || {})
			Object.keys(props).forEach((key) => {
				if (!allowedKeys.includes(key)) {
					delete props[key]
				}
			})
			return props
		},

		titleId(): string {
			return 'q' + this.index + '_title'
		},

		descriptionId(): string {
			return 'q' + this.index + '_desc'
		},

		hasError(): boolean {
			return !!this.errorMessage
		},

		hasInfo(): boolean {
			return !!(this as any).infoMessage
		},

		errorId(): string {
			return `q${this.index}_error`
		},

		infoId(): string {
			return `q${this.index}_info`
		},

		/**
		 * Listeners for all questions to forward
		 */
		commonListeners(): any {
			return {
				clone: this.onClone,
				delete: this.onDelete,
				'update:text': this.onTitleChange,
				'update:description': this.onDescriptionChange,
				'update:isRequired': this.onRequiredChange,
				'update:name': this.onNameChange,
				moveDown: (...args: any[]) => this.$emit('moveDown', ...args),
				moveUp: (...args: any[]) => this.$emit('moveUp', ...args),
			}
		},
	},

	methods: {
		/**
		 * Override to allow custom validation
		 */
		async validate(): Promise<boolean> {
			return true
		},

		/**
		 * Forward the title change to the parent and store to db
		 */
		onTitleChange: debounce(function (this: any, text: string) {
			this.$emit('update:text', text)
			this.saveQuestionProperty('text', text)
		}, INPUT_DEBOUNCE_MS),

		/**
		 * Forward the description change to the parent and store to db
		 */
		onDescriptionChange: debounce(function (this: any, description: string) {
			this.$emit('update:description', description)
			this.saveQuestionProperty('description', description)
		}, INPUT_DEBOUNCE_MS),

		/**
		 * Forward the required change to the parent and store to db
		 */
		onRequiredChange: debounce(function (this: any, isRequiredValue: boolean) {
			this.$emit('update:isRequired', isRequiredValue)
			this.saveQuestionProperty('isRequired', isRequiredValue)
		}, INPUT_DEBOUNCE_MS),

		/**
		 * Create mapper to forward the required change to the parent and store to db
		 */
		onExtraSettingsChange: debounce(function (this: any, newSettings: any) {
			const newExtraSettings = { ...this.extraSettings, ...newSettings }
			this.$emit('update:extraSettings', newExtraSettings)
			this.saveQuestionProperty('extraSettings', newExtraSettings)
		}, INPUT_DEBOUNCE_MS),

		/**
		 * Forward the technical-name change to the parent and store to db
		 */
		onNameChange: debounce(function (this: any, name: string) {
			this.$emit('update:name', name)
			this.saveQuestionProperty('name', name)
		}, INPUT_DEBOUNCE_MS),

		/**
		 * Forward the required change to the parent and store to db
		 *
		 * @param shuffle
		 */
		onShuffleOptionsChange(shuffle: boolean): void {
			return (this.onExtraSettingsChange as any)({ shuffleOptions: shuffle })
		},

		/**
		 * Forward the answer(s) change to the parent
		 *
		 * @param values
		 */
		onValuesChange(values: any): void {
			this.$emit('update:values', values)
		},

		/**
		 * Delete this question
		 */
		onDelete(): void {
			this.$emit('delete')
		},

		/**
		 * Clone this question.
		 */
		onClone(): void {
			this.$emit('clone')
		},

		/**
		 * Don't automatically submit form on Enter, parent will handle that
		 * To be called with prevent: @keydown.enter.prevent="onKeydownEnter"
		 *
		 * @param event
		 */
		onKeydownEnter(event: KeyboardEvent): void {
			this.$emit('keydown', event)
		},

		/**
		 * Focus the first focusable element
		 */
		focus(): void {
			;(this.$el as HTMLElement).scrollIntoView({ behavior: 'smooth' })
			this.$nextTick(() => {
				const title = (this.$el as HTMLElement).querySelector(
					'.question__header__title__text__input',
				) as HTMLInputElement
				if (title) {
					title.focus()
				}
			})
		},

		/**
		 * Shuffle an array using Fisher-Yates
		 *
		 * @param input
		 */
		shuffleArray(input: any[]): any[] {
			const shuffled = [...input]
			let idx = shuffled.length
			while (--idx > 0) {
				const rndIdx = Math.floor(Math.random() * (idx + 1))
				;[shuffled[rndIdx], shuffled[idx]] = [
					shuffled[idx],
					shuffled[rndIdx],
				]
			}
			return shuffled
		},

		async saveQuestionProperty(key: string, value: any): Promise<void> {
			try {
				// TODO: add loading status feedback ?
				await axios.patch(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}',
						{
							id: this.formId,
							questionId: this.id,
						},
					),
					{
						keyValuePairs: {
							[key]: value,
						},
					},
				)
				emit('forms:last-updated:set', this.formId)
			} catch (error) {
				logger.error('Error while saving question', { error })
				showError(t('forms', 'Error while saving question'))
			}
		},

		/**
		 * Handles multiple options for a question.
		 *
		 * @param answers
		 */
		async handleMultipleOptions(answers: string[]): Promise<void> {
			const component = this as any
			if (component.isLoading !== undefined) {
				component.isLoading = true
			}
			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/options',
						{
							id: this.formId,
							questionId: this.id,
						},
					),
					{
						optionTexts: answers,
						optionType: OptionType.Choice,
					},
				)
				const newServerOptions = OcsResponse2Data(response)
				const options = this.options.slice()
				newServerOptions.forEach((option: FormsOption) => {
					options.push({
						id: option.id,
						questionId: this.id,
						text: option.text,
						optionType: option.optionType,
						local: false,
					})
				})
				if (component.updateOptions) {
					component.updateOptions(options)
				}
				this.$nextTick(() => {
					if (component.focusIndex) {
						component.focusIndex(options.length - 1, OptionType.Choice)
					}
				})
			} catch (error) {
				logger.error('Error while saving question options', { error })
				showError(t('forms', 'Error while saving question options'))
			}
			if (component.isLoading !== undefined) {
				component.isLoading = false
			}
		},
	},
})
