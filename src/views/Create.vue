<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent
		:pageHeading="
			form.title ? t('forms', 'Edit form') : t('forms', 'Create form')
		">
		<!-- Show results & sidebar button -->
		<TopBar
			:archived="isFormArchived"
			:locked="isFormLocked"
			:permissions="form?.permissions"
			:sidebarOpened="sidebarOpened"
			:submissionCount="form?.submissionCount"
			@shareForm="onShareForm" />

		<NcEmptyContent
			v-if="isLoadingForm"
			class="emtpycontent"
			:name="t('forms', 'Loading {title} …', { title: form.title })">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>

		<NcEmptyContent
			v-else-if="isFormArchived"
			class="emtpycontent"
			:name="t('forms', 'Form is archived')"
			:description="
				t('forms', 'Form \'{title}\' is archived and cannot be modified.', {
					title: form.title,
				})
			">
			<template #icon>
				<NcIconSvgWrapper :svg="IconLock" :size="64" />
			</template>
		</NcEmptyContent>

		<NcEmptyContent
			v-else-if="isFormLocked"
			class="emtpycontent"
			:name="t('forms', 'Form is locked')"
			:description="
				t(
					'forms',
					'Form \'{title}\' is locked by {lockedBy} and cannot be modified. The lock expires: {lockedUntil}',
					{
						title: form.title,
						lockedBy: form.lockedBy ?? '',
						lockedUntil:
							form.lockedUntil === 0
								? t('forms', 'never')
								: lockedUntilFormatted,
					},
				)
			">
			<template #icon>
				<NcIconSvgWrapper :svg="IconLock" :size="64" />
			</template>
		</NcEmptyContent>

		<template v-else>
			<!-- Forms title & description-->
			<header>
				<h2>
					<label class="hidden-visually" for="form-title">{{
						t('forms', 'Form title')
					}}</label>
					<textarea
						id="form-title"
						ref="title"
						:value="form.title"
						class="form-title"
						rows="1"
						dir="auto"
						:maxlength="maxStringLengths.formTitle"
						:placeholder="t('forms', 'Form title')"
						required
						autofocus
						@keydown.enter.prevent
						@input="onTitleInput" />
				</h2>
				<label class="hidden-visually" for="form-desc">
					{{ t('forms', 'Description') }}
				</label>
				<textarea
					id="form-desc"
					ref="description"
					class="form-desc"
					rows="1"
					dir="auto"
					:value="form.description"
					:placeholder="
						t(
							'forms',
							'Description (formatting using Markdown is supported)',
						)
					"
					:maxlength="maxStringLengths.formDescription"
					@input="updateDescription" />
				<!-- Show expiration message-->
				<p v-if="form.expires && form.showExpiration" class="info-message">
					{{ expirationMessage }}
				</p>
				<!-- Generate form information message-->
				<p v-if="infoMessage" class="info-message">
					{{ infoMessage }}
				</p>
			</header>

			<section>
				<!-- Questions list -->
				<QuestionList
					ref="questionList"
					:modelValue="form.questions"
					:getComponent="getQuestionComponent"
					:getAnswerType="getQuestionAnswerType"
					:maxStringLengths="maxStringLengths"
					:formId="form.id"
					:showInsert="true"
					:answerTypesFilter="answerTypesFilter"
					:hasSubtypes="hasSubtypes"
					:isLoadingQuestions="isLoadingQuestions"
					@update:modelValue="onUpdateQuestions"
					@updateProperty="updateQuestionProperty"
					@clone="cloneQuestion"
					@delete="(question) => deleteQuestion(question.id)"
					@moveDown="onMoveDown"
					@moveUp="onMoveUp"
					@addQuestion="addQuestion" />

				<!-- Add new questions menu -->
				<div class="question-menu">
					<AddQuestionMenu
						v-model:open="questionMenuOpened"
						:menuName="t('forms', 'Add a question')"
						:aria-label="t('forms', 'Add a question')"
						:isLoadingQuestions="isLoadingQuestions"
						:answerTypesFilter="answerTypesFilter"
						:hasSubtypes="hasSubtypes"
						primary
						@addQuestion="addQuestion" />
				</div>
			</section>
		</template>
	</NcAppContent>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { FormsForm, FormsQuestion } from '../types/Entities.d.ts'

import IconLock from '@material-symbols/svg-400/outlined/lock.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit as emitEvent } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { computed, defineComponent, nextTick, onMounted, ref, watch } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AddQuestionMenu from '../components/AddQuestionMenu.vue'
import QuestionList from '../components/Questions/QuestionList.vue'
import TopBar from '../components/TopBar.vue'
import { useViewForm } from '../composables/useViewForm.ts'
import answerTypes from '../models/AnswerTypes.ts'
import { FormState, INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'
import SetWindowTitle from '../utils/SetWindowTitle.ts'

const formsAppName = 'forms'

;(window as Window & { axios?: typeof axios }).axios = axios

export default defineComponent({
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Create',
	components: {
		NcIconSvgWrapper,
		AddQuestionMenu,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		QuestionList,
		TopBar,
	},

	props: {
		hash: {
			type: String,
			default: '',
		},

		form: {
			type: Object as PropType<FormsForm>,
			required: true,
		},

		sidebarOpened: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['update:form', 'open-sharing'],

	setup(props, { emit }) {
		const title = ref<HTMLTextAreaElement | null>(null)
		const description = ref<HTMLTextAreaElement | null>(null)
		const questionList = ref<InstanceType<typeof QuestionList> | null>(null)
		const viewForm = useViewForm({
			form: () => props.form,
			emit,
			titleRef: title,
		})

		// Various states
		const isLoadingQuestions = ref<boolean>(false)
		const maxStringLengths = loadState(
			formsAppName,
			'maxStringLengths',
		) as Record<string, number>
		const questionMenuOpened = ref<boolean>(false)

		/**
		 * Auto adjust the title height based its scroll height
		 */
		const resizeTitle = (): void => {
			nextTick(() => {
				const textarea = title.value
				if (textarea) {
					textarea.style.cssText = 'height: 0'
					// include 2px border
					textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px`
				}
			})
		}

		/**
		 * Auto adjust the description height based on its scroll height
		 */
		const resizeDescription = (): void => {
			// nextTick to ensure textarea is attached to DOM
			nextTick(() => {
				const textarea = description.value
				if (textarea) {
					textarea.style.cssText = 'height: 0'
					// include 2px border
					textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px`
				}
			})
		}

		/**
		 * Title & description save methods
		 */
		const saveTitle = debounce(async () => {
			await viewForm.saveFormProperty('title')
		}, INPUT_DEBOUNCE_MS)

		const saveDescription = debounce(async () => {
			await viewForm.saveFormProperty('description')
		}, INPUT_DEBOUNCE_MS)

		// Computed properties
		const hasQuestions = computed<boolean>(
			() => props.form.questions.length === 0,
		)

		const isRequiredUsed = computed<boolean>(() =>
			props.form.questions.reduce(
				(isUsed: boolean, question: FormsQuestion) =>
					isUsed || Boolean(question.isRequired),
				false,
			),
		)

		/**
		 * Check if form is expired
		 */
		const isExpired = computed<boolean>(
			() => props.form.expires > 0 && moment().unix() > props.form.expires,
		)

		/**
		 * Check if the form was archived
		 */
		const isFormArchived = computed<boolean>(
			() => props.form.state === FormState.FormArchived,
		)

		const infoMessage = computed<string>(() => {
			let message = ''
			if (props.form.isAnonymous) {
				message += t('forms', 'Responses are anonymous.')
			}

			// On Submit, this is dependent on `isLoggedIn`. Create-view is always logged in and the variable isLoggedIn does not exist.
			if (!props.form.isAnonymous && true) {
				message += t('forms', 'Responses are connected to your account.')
			}

			if (isRequiredUsed.value) {
				message +=
					' '
					+ t('forms', 'An asterisk (*) indicates mandatory questions.')
			}

			return message
		})

		const expirationMessage = computed<string>(() => {
			const relativeDate = moment(props.form.expires, 'X')
				.locale(window.OC?.getLanguage())
				.fromNow()
			if (isExpired.value) {
				return t('forms', 'Expired {relativeDate}.', { relativeDate })
			}
			return t('forms', 'Expires {relativeDate}.', { relativeDate })
		})

		// Remove properties from answerTypes for create button
		const answerTypesFilter = computed<Omit<typeof answerTypes, 'datetime'>>(
			() => {
				// Remove 'datetime' from answerTypes for create button
				// eslint-disable-next-line @typescript-eslint/no-unused-vars
				const { datetime, ...filteredAnswerTypes } = answerTypes
				return filteredAnswerTypes
			},
		)

		const hasSubtypes = computed<
			(
				answer: { subtypes?: Record<string, unknown> } | null | undefined,
			) => boolean
		>(() => {
			return (answer) =>
				Boolean(answer?.subtypes)
				&& Object.keys(answer?.subtypes ?? {}).length > 0
		})

		const getQuestionComponent = (question: FormsQuestion) =>
			answerTypes[question.type].component

		const getQuestionAnswerType = (question: FormsQuestion) =>
			answerTypes[question.type]

		const lockedUntilFormatted = computed<string>(() =>
			moment(props.form.lockedUntil, 'X').fromNow(),
		)

		// Event handlers
		const updateQuestionProperty = <K extends keyof FormsQuestion>(
			index: number,
			property: K,
			value: FormsQuestion[K],
		): void => {
			const questions = [...props.form.questions]
			questions[index] = { ...questions[index], [property]: value }
			emit('update:form', { ...props.form, questions })
		}

		/**
		 * Reorder questions on dragEnd
		 *
		 * @param questions the freshly reordered questions (avoids reading the not-yet-updated `form` prop)
		 */
		const onQuestionOrderChange = async (
			questions: FormsQuestion[],
		): Promise<void> => {
			isLoadingQuestions.value = true
			const newOrder = questions.map((question: FormsQuestion) => question.id)

			try {
				await axios.patch(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/questions', {
						id: props.form.id,
					}),
					{
						newOrder,
					},
				)
				emitEvent('forms:last-updated:set', props.form.id)
			} catch (error) {
				logger.error('Error while saving form', { error })
				showError(t('forms', 'Error while saving form'))
			} finally {
				isLoadingQuestions.value = false
			}
		}

		const onMoveUp = (index: number): void => {
			if (index > 0) {
				const questions = [...props.form.questions]
				;[questions[index - 1], questions[index]] = [
					questions[index],
					questions[index - 1],
				]
				emit('update:form', { ...props.form, questions })
				onQuestionOrderChange(questions)
			}
		}

		const onMoveDown = (index: number): void => {
			// only if not the last one
			if (index < props.form.questions.length - 1) {
				onMoveUp(index + 1)
			}
		}

		const insertQuestion = (
			questionData: FormsQuestion,
			defaultFields: Partial<FormsQuestion> = {},
			position: number | null = null,
		): void => {
			const newQuestionObj = {
				...defaultFields,
				...questionData,
			}

			let insertAt = null
			if (
				questionData
				&& questionData.order !== undefined
				&& questionData.order !== null
			) {
				insertAt = Number(questionData.order) - 1
			} else if (position !== null) {
				insertAt = position
			}

			const questions = [...props.form.questions]
			if (insertAt !== null && insertAt <= questions.length) {
				questions.splice(insertAt, 0, newQuestionObj)
			} else {
				questions.push(newQuestionObj)
			}
			emit('update:form', { ...props.form, questions })
			nextTick(() => {
				questionList.value?.focusQuestion(newQuestionObj.id)
			})

			emitEvent('forms:last-updated:set', props.form.id)
		}

		const onTitleChange = (): void => {
			resizeTitle()
			saveTitle()
		}

		const onTitleInput = (event: Event): void => {
			emit('update:form', {
				...props.form,
				title: (event.target as HTMLTextAreaElement).value,
			})
			onTitleChange()
		}

		/**
		 * Update the description
		 *
		 * @param event The input event of the textarea
		 */
		const updateDescription = (event: Event): void => {
			const target = event.target as HTMLTextAreaElement | null
			if (!target) {
				return
			}
			emit('update:form', { ...props.form, description: target.value })
			resizeDescription()
			saveDescription()
		}

		/**
		 * Add a new question to the current form
		 *
		 * @param type the question type, see AnswerTypes
		 * @param subtype the question subtype, see AnswerTypes.subtypes
		 * @param position where the new question should be added
		 */
		const addQuestion = async (
			type: string,
			subtype: string | null = null,
			position: number | null = null,
		): Promise<void> => {
			const text = ''
			isLoadingQuestions.value = true

			try {
				const body: {
					type: string
					text: string
					subtype: string | null
					position?: number
				} = { type, text, subtype }
				if (position !== null) {
					// position: current question position + 2 (0-based index: +1, next position: +1)
					body.position = position + 2
				}

				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/questions', {
						id: props.form.id,
					}),
					body,
				)
				const question = OcsResponse2Data<FormsQuestion>(response)

				// Delegate insertion & focus handling to helper
				insertQuestion(question, { text, type, answers: [] }, position)
			} catch (error) {
				logger.error('Error while adding new question', { error })
				showError(
					t('forms', 'There was an error while adding the new question'),
				)
			} finally {
				isLoadingQuestions.value = false
			}
		}

		/**
		 * Delete a question
		 *
		 * @param questionId the question id to delete
		 */
		const deleteQuestion = async (questionId: number): Promise<void> => {
			isLoadingQuestions.value = true

			try {
				await axios.delete(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}',
						{
							id: props.form.id,
							questionId,
						},
					),
				)
				const questions = props.form.questions.filter(
					(search: FormsQuestion) => search.id !== questionId,
				)
				emit('update:form', { ...props.form, questions })
				emitEvent('forms:last-updated:set', props.form.id)
			} catch (error) {
				logger.error(`Error while removing question ${questionId}`, {
					error,
				})
				showError(
					t('forms', 'There was an error while removing the question'),
				)
			} finally {
				isLoadingQuestions.value = false
			}
		}

		/**
		 * Clone a question
		 *
		 * @param question the question to clone in the current form
		 * @param question.id the question id to clone in the current form
		 * @param position where the cloned question should be added
		 */
		const cloneQuestion = async (
			question: Pick<FormsQuestion, 'id'>,
			position: number | null,
		): Promise<void> => {
			isLoadingQuestions.value = true

			try {
				const url = generateOcsUrl(
					'apps/forms/api/v3/forms/{id}/questions?fromId={questionId}',
					{
						id: props.form.id,
						questionId: question.id,
					},
				)

				const body: { position?: number } = {}
				if (position !== null) {
					// position: current question position + 2 (0-based index: +1, next position: +1)
					body.position = position + 2
				}

				const response = await axios.post(url, body)
				const clonedQuestion = OcsResponse2Data<FormsQuestion>(response)

				// Delegate insertion & focus handling to helper
				insertQuestion(clonedQuestion, { answers: [] })
			} catch (error) {
				logger.error(`Error while duplicating question ${question.id}`, {
					error,
				})
				showError('There was an error while duplicating the question')
			} finally {
				isLoadingQuestions.value = false
			}
		}

		const onUpdateQuestions = (questions: FormsQuestion[]): void => {
			emit('update:form', { ...props.form, questions })
			onQuestionOrderChange(questions)
		}

		// Watchers
		// Fetch full form on change
		watch(
			() => props.hash,
			() => {
				viewForm.fetchFullForm(props.form.id)
			},
		)

		// Update Window-Title on title change
		watch(
			() => props.form.title,
			() => {
				SetWindowTitle(viewForm.formTitle.value)
			},
		)

		// resize description if form is loaded
		watch(
			() => viewForm.isLoadingForm.value,
			(value: boolean) => {
				if (!value) {
					resizeTitle()
					resizeDescription()
				}
			},
		)

		// Lifecycle
		onMounted(() => {
			viewForm.fetchFullForm(props.form.id)
			SetWindowTitle(viewForm.formTitle.value)
		})

		return {
			...viewForm,
			title,
			description,
			questionList,
			isLoadingQuestions,
			maxStringLengths,
			questionMenuOpened,
			hasQuestions,
			isRequiredUsed,
			isExpired,
			isFormArchived,
			infoMessage,
			expirationMessage,
			answerTypesFilter,
			hasSubtypes,
			getQuestionComponent,
			getQuestionAnswerType,
			lockedUntilFormatted,
			updateQuestionProperty,
			onMoveUp,
			onMoveDown,
			onTitleInput,
			updateDescription,
			resizeTitle,
			resizeDescription,
			addQuestion,
			deleteQuestion,
			insertQuestion,
			cloneQuestion,
			onUpdateQuestions,
			onQuestionOrderChange,
			IconLock,
			t,
		}
	},
})
</script>

<style lang="scss" scoped>
.emptycontent {
	display: flex;
	height: 100%;
}

.app-content {
	display: flex;
	align-items: center;
	flex-direction: column;

	header,
	section {
		width: 100%;
		max-width: 750px;
	}

	// Title & description header
	header {
		display: flex;
		flex-direction: column;
		margin: 0;
		margin-block-end: 24px;
		padding-inline-start: 32px;
		margin-inline-end: -24px;

		.form-title {
			font-size: 28px;
			font-weight: bold;
			line-height: 34px;
			color: var(--color-main-text);
			min-height: 36px;
			// padding and margin should be aligned with the submit view (but keep the 2px border in mind)
			padding-block: 4px;
			padding-inline: 10px;
			margin-block: 22px 14px;
			margin-inline: 0;
			width: calc(
				100% - 58px
			); // margin of header, needed if screen is < 806px (max-width + margin-left)
			overflow: hidden;
			text-overflow: ellipsis;
			resize: none;

			&::placeholder {
				font-size: 28px;
			}
		}

		.form-desc,
		.info-message {
			font-size: 100%;
			min-height: unset;
			padding-block: 0px 20px;
			padding-inline: 12px;
			width: calc(100% - 58px);
		}

		.form-desc {
			color: var(--color-main-text);
			line-height: 22px;
			min-height: 47px; // one line (25px padding + 22px text height)
			margin: 0px;
			padding-block: 3px 18px; // 2px smaller because of border
			padding-inline: 10px;
			resize: none;
		}

		.info-message {
			margin-block-start: 4px;
			resize: none;
			color: var(--color-text-maxcontrast);
		}
	}

	// Questions container
	section {
		position: relative;
		display: flex;
		flex-direction: column;
		margin-block-end: 250px;

		.question-menu {
			position: sticky;
			inset-block-end: 0px;
			padding-block-end: 16px;
			// Above other menus
			z-index: 55;
			display: flex;
			align-items: center;
			align-self: flex-start;

			// To align with text
			margin-inline-start: var(--default-clickable-area);
		}
	}
}
</style>
