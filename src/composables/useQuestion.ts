/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Ref } from 'vue'
import type { FormsOption, FormsQuestion } from '../types/Entities.d.ts'
import type { Jumps } from './useJumps.ts'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit as emitEvent } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { computed, nextTick, ref, toRaw } from 'vue'
import { INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'
import { useJumps } from './useJumps.ts'

export const QUESTION_EMITS = [
	'update:text',
	'update:description',
	'update:isRequired',
	'update:extraSettings',
	'update:name',
	'update:values',
	'delete',
	'clone',
	'moveDown',
	'moveUp',
]

export const QUESTION_PROPS = {
	id: {
		type: Number,
		required: true,
	},
	formId: {
		type: Number,
		default: null,
	},
	text: {
		type: String,
		required: true,
	},
	description: {
		type: String,
		required: true,
	},
	isRequired: {
		type: Boolean,
		required: true,
	},
	index: {
		type: Number,
		required: true,
	},
	name: {
		type: String,
		default: '',
	},
	values: {
		type: [Array, Object],
		default() {
			return []
		},
	},
	options: {
		type: Array as () => FormsOption[],
		required: true,
	},
	order: {
		type: Number,
		default: -1,
	},
	type: {
		type: String,
		default: null,
	},
	answerType: {
		type: Object,
		required: true,
	},
	readOnly: {
		type: Boolean,
		default: false,
	},
	maxStringLengths: {
		type: Object,
		required: true,
	},
	extraSettings: {
		default: () => {
			return {}
		},
	},
	accept: {
		type: Array,
		default() {
			return []
		},
	},
	canMoveUp: {
		type: Boolean,
		default: false,
	},
	canMoveDown: {
		type: Boolean,
		default: false,
	},
}

const questionRequests = new Map<number, Promise<unknown>>()

/**
 * Send a request once the earlier requests of the questions have finished; later requests of these questions wait for it.
 *
 * @param questionIds the ids of the questions the request changes
 * @param request sends the request
 */
export function queueQuestionRequest<T>(
	questionIds: number[],
	request: () => Promise<T>,
): Promise<T> {
	const result = Promise.allSettled(
		questionIds.map((id) => questionRequests.get(id)),
	).then(request)
	const done = result.catch(() => undefined)
	questionIds.forEach((id) => {
		questionRequests.set(id, done)
		done.then(() => {
			if (questionRequests.get(id) === done) {
				questionRequests.delete(id)
			}
		})
	})
	return result
}

interface RootElementLike {
	$el?: HTMLElement
}

interface UseQuestionOptions {
	emit: (event: string, ...args: unknown[]) => void
	infoMessage?: Ref<string | null>
	rootElement?: Ref<RootElementLike | HTMLElement | null>
}

interface QuestionPropsLike {
	id: number
	formId: number | null
	index: number
	text: string
	description: string
	isRequired: boolean
	readOnly: boolean
	name: string
	maxStringLengths: Record<string, number>
	canMoveUp: boolean
	canMoveDown: boolean
	extraSettings: Record<string, unknown>
	options: FormsOption[]
	[key: string]: unknown
}

interface QuestionForwardedProps {
	index: number
	text: string
	description: string
	isRequired: boolean
	readOnly: boolean
	maxStringLengths: Record<string, number>
	name: string
	canMoveUp: boolean
	canMoveDown: boolean
	jumps: Jumps
}

/**
 * Shared question helpers previously provided via QuestionMixin.
 *
 * @param props Question component props used by shared question logic.
 * @param options Emits bridge for forwarding events to parent components.
 */
export function useQuestion(props: QuestionPropsLike, options: UseQuestionOptions) {
	const errorMessage = ref<string | null>(null)

	let pendingExtraSettings: Record<string, unknown> = {}
	const saveExtraSettings = debounce(function () {
		const newExtraSettings = { ...props.extraSettings, ...pendingExtraSettings }
		pendingExtraSettings = {}
		saveQuestionProperty('extraSettings', newExtraSettings)
	}, INPUT_DEBOUNCE_MS)

	/**
	 * Show changed extra settings at once and save them; changes within the debounce time are saved together
	 *
	 * @param newSettings the changed extra settings
	 */
	const onExtraSettingsChange = (newSettings: Record<string, unknown>): void => {
		pendingExtraSettings = { ...pendingExtraSettings, ...newSettings }
		options.emit('update:extraSettings', {
			...props.extraSettings,
			...pendingExtraSettings,
		})
		saveExtraSettings()
	}

	const jumps = useJumps(props, (newSettings: Record<string, unknown>): void => {
		onExtraSettingsChange(newSettings)
		saveExtraSettings.flush()
	})

	const questionProps = computed<QuestionForwardedProps>(() => ({
		index: props.index,
		text: props.text,
		description: props.description,
		isRequired: props.isRequired,
		readOnly: props.readOnly,
		maxStringLengths: props.maxStringLengths,
		name: props.name,
		canMoveUp: props.canMoveUp,
		canMoveDown: props.canMoveDown,
		jumps,
	}))

	const titleId = computed(() => {
		return 'q' + props.index + '_title'
	})

	const descriptionId = computed(() => {
		return 'q' + props.index + '_desc'
	})

	const hasError = computed(() => {
		return !!errorMessage.value
	})

	const hasInfo = computed(() => {
		return !!options.infoMessage?.value
	})

	const errorId = computed(() => {
		return `q${props.index}_error`
	})

	const infoId = computed(() => {
		return `q${props.index}_info`
	})

	const onTitleChange = debounce(function (text: string) {
		options.emit('update:text', text)
		saveQuestionProperty('text', text)
	}, INPUT_DEBOUNCE_MS)

	const onDescriptionChange = debounce(function (description: string) {
		options.emit('update:description', description)
		saveQuestionProperty('description', description)
	}, INPUT_DEBOUNCE_MS)

	const onRequiredChange = debounce(function (isRequiredValue: boolean) {
		options.emit('update:isRequired', isRequiredValue)
		saveQuestionProperty('isRequired', isRequiredValue)
	}, INPUT_DEBOUNCE_MS)

	const onNameChange = debounce(function (name: string) {
		options.emit('update:name', name)
		saveQuestionProperty('name', name)
	}, INPUT_DEBOUNCE_MS)

	const onShuffleOptionsChange = (shuffle: boolean): void => {
		onExtraSettingsChange({ shuffleOptions: shuffle })
	}

	const onValuesChange = (
		values:
			| string
			| number
			| boolean
			| Array<string | number | boolean | Record<string, unknown>>
			| Record<string, unknown>
			| null
			| unknown,
	): void => {
		options.emit('update:values', values)
	}

	const onDelete = (): void => {
		options.emit('delete')
	}

	const onClone = (): void => {
		options.emit('clone')
	}

	const focus = (): void => {
		const raw = options.rootElement?.value
		const element = (raw && '$el' in raw ? raw.$el : raw) as
			HTMLElement | null | undefined
		element?.scrollIntoView({ behavior: 'smooth' })
		nextTick(() => {
			const title = element?.querySelector(
				'.question__header__title__text__input',
			) as HTMLInputElement | null
			if (title) {
				title.focus()
			}
		})
	}

	const shuffleArray = <T>(input: T[]): T[] => {
		const shuffled = [...input]
		let idx = shuffled.length
		while (--idx > 0) {
			const rndIdx = Math.floor(Math.random() * (idx + 1))
			;[shuffled[rndIdx], shuffled[idx]] = [shuffled[idx], shuffled[rndIdx]]
		}
		return shuffled
	}

	const queuedValues = new Map<string, unknown>()
	const savingKeys = new Set<string>()

	/**
	 * Persist a single question field to the backend once its earlier change and the earlier requests of the question are done; of the values queued meanwhile only the latest is saved.
	 *
	 * @param key Question property name.
	 * @param value Question property value.
	 */
	async function saveQuestionProperty(key: string, value: unknown): Promise<void> {
		queuedValues.set(key, value)
		if (savingKeys.has(key)) {
			return
		}
		savingKeys.add(key)
		try {
			while (queuedValues.has(key)) {
				const queuedValue = queuedValues.get(key)
				queuedValues.delete(key)
				await queueQuestionRequest([props.id], () =>
					patchQuestionProperty(key, queuedValue),
				)
			}
		} finally {
			savingKeys.delete(key)
		}
	}

	/**
	 * Persist a single question field to the backend.
	 * Extra settings the server adjusted replace the local ones, unless these changed in the meantime.
	 *
	 * @param key Question property name.
	 * @param value Question property value.
	 */
	async function patchQuestionProperty(
		key: string,
		value:
			| string
			| number
			| boolean
			| Array<unknown>
			| Record<string, unknown>
			| null
			| unknown,
	): Promise<void> {
		const url = generateOcsUrl(
			'apps/forms/api/v3/forms/{id}/questions/{questionId}',
			{
				id: props.formId,
				questionId: props.id,
			},
		)
		try {
			const response = await axios.patch(url, {
				keyValuePairs: {
					[key]: value,
				},
			})
			emitEvent('forms:last-updated:set', props.formId)
			if (
				String(response.headers['x-forms-adjusted'] ?? '').includes(
					'removed-jumps',
				)
			) {
				const question = await axios
					.get(url)
					.then((reply) => OcsResponse2Data<FormsQuestion>(reply))
					.catch((error) => {
						logger.error('Error while loading the adjusted question', {
							error,
						})
						return null
					})
				if (question && toRaw(props.extraSettings) === value) {
					options.emit('update:extraSettings', question.extraSettings)
				}
			}
		} catch (error) {
			logger.error('Error while saving question', { error })
			showError(t('forms', 'Error while saving question'))
		}
	}

	const commonListeners = computed(() => {
		return {
			clone: onClone,
			delete: onDelete,
			'update:text': onTitleChange,
			'update:description': onDescriptionChange,
			'update:isRequired': onRequiredChange,
			'update:name': onNameChange,
			moveDown: (...args: unknown[]) => options.emit('moveDown', ...args),
			moveUp: (...args: unknown[]) => options.emit('moveUp', ...args),
		}
	})

	return {
		errorMessage,
		jumps,
		questionProps,
		titleId,
		descriptionId,
		hasError,
		hasInfo,
		errorId,
		infoId,
		commonListeners,
		onTitleChange,
		onDescriptionChange,
		onRequiredChange,
		onExtraSettingsChange,
		onNameChange,
		onShuffleOptionsChange,
		onValuesChange,
		onDelete,
		onClone,
		focus,
		shuffleArray,
		saveQuestionProperty,
	}
}
