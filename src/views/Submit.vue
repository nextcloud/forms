<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent
		:class="{ 'app-content--public': publicView }"
		:pageHeading="t('forms', 'Submit form')">
		<TopBar
			v-if="!publicView"
			:archived="isArchived"
			:locked="isFormLocked"
			:permissions="form?.permissions"
			:sidebarOpened="sidebarOpened"
			:submissionCount="form?.submissionCount"
			@shareForm="onShareForm" />

		<!-- Form is loading -->
		<NcEmptyContent
			v-if="isLoadingForm"
			class="forms-emptycontent"
			:name="t('forms', 'Loading {title} …', { title: form.title })">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>

		<template v-else>
			<!-- Forms title & description-->
			<header>
				<!-- eslint-disable-next-line vue/no-unused-refs -->
				<h2 ref="title" class="form-title" dir="auto">
					{{ formTitle }}
				</h2>
				<!-- eslint-disable vue/no-v-html -->
				<div
					v-if="!loading && !success && !!formDescription"
					class="form-desc"
					dir="auto"
					v-html="formDescription" />
				<!-- Show expiration message-->
				<p v-if="form.expires && form.showExpiration" class="info-message">
					{{ expirationMessage }}
				</p>
				<!-- Generate form information message-->
				<p v-if="infoMessage" class="info-message">
					{{ infoMessage }}
				</p>
			</header>

			<!-- Screen-reader-only live region for submission success announcement -->
			<div class="hidden-visually" aria-live="polite">
				{{ successAnnouncement }}
			</div>

			<NcEmptyContent
				v-if="loading"
				class="forms-emptycontent"
				:name="t('forms', 'Submitting form …')">
				<template #icon>
					<NcLoadingIcon :size="64" />
				</template>
			</NcEmptyContent>
			<NcEmptyContent
				v-else-if="
					success
					|| (!form.canSubmit && !isMaxSubmissionsReached && !submissionId)
				"
				class="forms-emptycontent"
				:name="
					form.submissionMessage
						? ''
						: t('forms', 'Thank you for completing the form!')
				"
				:description="form.submissionMessage ?? undefined">
				<template #icon>
					<NcIconSvgWrapper :svg="IconCheckSvg" :size="64" />
				</template>
				<template v-if="submissionMessageHTML" #description>
					<!-- eslint-disable-next-line vue/no-v-html -->
					<p class="submission-message" v-html="submissionMessageHTML" />
				</template>
			</NcEmptyContent>
			<NcEmptyContent
				v-else-if="isMaxSubmissionsReached && !submissionId"
				class="forms-emptycontent"
				:name="t('forms', 'Limit reached')"
				:description="
					t(
						'forms',
						'This form has reached the maximum number of responses',
					)
				">
				<template #icon>
					<NcIconSvgWrapper :svg="IconCheckSvg" :size="64" />
				</template>
			</NcEmptyContent>
			<NcEmptyContent
				v-else-if="isExpired"
				class="forms-emptycontent"
				:name="t('forms', 'Form expired')"
				:description="
					t(
						'forms',
						'This form has expired and is no longer taking responses',
					)
				">
				<template #icon>
					<NcIconSvgWrapper :svg="IconCheckSvg" :size="64" />
				</template>
			</NcEmptyContent>
			<NcEmptyContent
				v-else-if="isClosed || isArchived"
				class="forms-emptycontent"
				:name="t('forms', 'Form closed')"
				:description="
					t(
						'forms',
						'This form was closed and is no longer taking responses',
					)
				">
				<template #icon>
					<NcIconSvgWrapper :svg="IconCheckSvg" :size="64" />
				</template>
			</NcEmptyContent>

			<!-- Questions list -->
			<form v-else ref="formElement" @submit.prevent="onSubmit">
				<ul>
					<component
						:is="answerTypes[question.type].component"
						v-for="(question, index) in validQuestions"
						ref="questionRefs"
						:key="question.id"
						v-bind="question"
						readOnly
						:answerType="answerTypes[question.type]"
						:index="index + 1"
						:maxStringLengths="maxStringLengths"
						:values="answers[question.id]"
						@keydown.enter="onKeydownEnter"
						@keydown.ctrl.enter="onKeydownCtrlEnter"
						@update:values="
							(values: AnswerValue) => onUpdate(question, values)
						" />
				</ul>
				<div class="form-buttons">
					<NcButton
						alignment="center-reverse"
						class="submit-button"
						:disabled="!hasAnswers"
						type="reset"
						variant="tertiary-no-background"
						@click.prevent="showClearFormDialog = true">
						<template #icon>
							<NcIconSvgWrapper :svg="IconRefreshSvg" />
						</template>
						{{ t('forms', 'Clear form') }}
					</NcButton>
					<NcButton
						alignment="center-reverse"
						class="submit-button"
						:disabled="loading"
						type="submit"
						variant="primary">
						<template #icon>
							<NcIconSvgWrapper :svg="IconSendSvg" />
						</template>
						{{ t('forms', 'Submit') }}
					</NcButton>
				</div>
			</form>

			<!-- Confirmation dialog if form is empty submitted -->
			<NcDialog
				v-model:open="showConfirmEmptyModal"
				:name="t('forms', 'Confirm submit')"
				:message="
					t('forms', 'Are you sure you want to submit an empty form?')
				"
				:buttons="confirmEmptyModalButtons" />
			<!-- Confirmation dialog if form is left unsubmitted -->
			<NcDialog
				v-model:open="showConfirmLeaveDialog"
				:name="t('forms', 'Leave form')"
				:message="
					t(
						'forms',
						'You have unsaved changes! Do you still want to leave?',
					)
				"
				:buttons="confirmLeaveFormButtons"
				noClose
				:closeOnClickOutside="false" />
			<!-- Confirmation dialog for clear form -->
			<NcDialog
				v-model:open="showClearFormDialog"
				:name="t('forms', 'Clear form')"
				:message="t('forms', 'Do you want to clear all answers?')"
				:buttons="confirmClearFormButtons"
				noClose
				:closeOnClickOutside="false" />
			<!-- Confirmation dialog if form was changed -->
			<NcDialog
				v-model:open="showClearFormDueToChangeDialog"
				:name="t('forms', 'Clear form')"
				:message="
					t(
						'forms',
						'The form has changed since your last visit. Do you want to clear all answers?',
					)
				"
				:buttons="confirmClearFormButtons"
				noClose
				:closeOnClickOutside="false" />
		</template>
	</NcAppContent>
</template>

<script lang="ts">
import type { FormsOption, FormsQuestion } from '../types/Entities.d.ts'

import IconCancel from '@material-symbols/svg-400/outlined/block.svg?raw'
import IconCheck from '@material-symbols/svg-400/outlined/check.svg?raw'
import IconRefresh from '@material-symbols/svg-400/outlined/refresh.svg?raw'
import IconSend from '@material-symbols/svg-400/outlined/send.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit as emitEvent } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, onBeforeMount, onMounted, onUnmounted, ref, watch } from 'vue'
import { defineComponent } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute } from 'vue-router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import Question from '../components/Questions/Question.vue'
import QuestionLong from '../components/Questions/QuestionLong.vue'
import QuestionMultiple from '../components/Questions/QuestionMultiple.vue'
import QuestionShort from '../components/Questions/QuestionShort.vue'
import TopBar from '../components/TopBar.vue'
import { useViewForm } from '../composables/useViewForm.ts'
import answerTypes from '../models/AnswerTypes.ts'
import {
	FormState,
	QUESTION_EXTRASETTINGS_OTHER_PREFIX,
} from '../models/Constants.ts'
import { PERMISSION_TYPES } from '../models/Permissions.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'
import SetWindowTitle from '../utils/SetWindowTitle.ts'

const formsAppName = 'forms'

type AnswerValue = string[]
type AnswersMap = Record<number, AnswerValue>

interface StoredAnswerState {
	value: AnswerValue
	type: string
}

interface StoredAnswersMap {
	[key: string]: StoredAnswerState
}

interface SubmitQuestion extends FormsQuestion {
	options?: FormsOption[]
	extraSettings?: Record<string, unknown> & {
		allowOtherAnswer?: boolean
	}
	isRequired?: boolean
}

interface LoadedSubmissionAnswer {
	id: number
	questionId: number | string
	text: string
}

interface LoadedSubmissionResponse {
	answers: LoadedSubmissionAnswer[]
}

interface QuestionComponentRef {
	validate: () => Promise<boolean>
}

interface DialogButton {
	label: string
	icon: string
	variant?:
		| 'primary'
		| 'secondary'
		| 'tertiary'
		| 'tertiary-no-background'
		| 'tertiary-on-primary'
		| 'error'
		| 'warning'
		| 'success'
	callback: () => void
}

export default defineComponent({
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Submit',

	components: {
		NcAppContent,
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		NcIconSvgWrapper,
		NcNoteCard,
		Question,
		QuestionLong,
		QuestionShort,
		QuestionMultiple,
		TopBar,
	},

	props: {
		isLoggedIn: {
			type: Boolean,
			required: false,
			default: false,
		},

		shareHash: {
			type: String,
			default: '',
		},

		hash: {
			type: String,
			default: '',
		},

		form: {
			type: Object,
			required: true,
		},

		publicView: {
			type: Boolean,
			default: false,
		},

		sidebarOpened: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['update:form', 'open-sharing'],

	setup(props, { emit }) {
		const route = useRoute()
		const title = ref(null)
		const formElement = ref<HTMLFormElement | null>(null)
		const questionRefs = ref<
			QuestionComponentRef[] | QuestionComponentRef | null
		>(null)
		const viewForm = useViewForm({
			form: () => props.form,
			emit,
			titleRef: title,
		})

		const answers = ref<AnswersMap>({})
		const loading = ref(false)
		const success = ref(false)
		const successAnnouncement = ref('')
		const submitForm = ref(false)
		const showConfirmEmptyModal = ref(false)
		const showConfirmLeaveDialog = ref(false)
		const showClearFormDialog = ref(false)
		const showClearFormDueToChangeDialog = ref(false)
		const confirmButtonCallback = ref<(val: boolean) => void>(() => {})

		const validQuestions = computed<SubmitQuestion[]>(() => {
			return props.form.questions.filter((question: FormsQuestion) => {
				// All questions must have a valid title
				if (question.text?.trim() === '') {
					return false
				}

				// If specific conditions provided, test against them
				const answerType = answerTypes[question.type]
				if (typeof answerType.validate === 'function') {
					return answerType.validate(question)
				}
				return true
			}) as SubmitQuestion[]
		})

		const validQuestionsIds = computed<Set<number>>(() => {
			return new Set(validQuestions.value.map((question) => question.id))
		})

		const isRequiredUsed = computed<boolean>(() => {
			return props.form.questions.some((question: FormsQuestion) =>
				Boolean(question.isRequired),
			)
		})

		/**
		 * Check if form is expired
		 */
		const isExpired = computed<boolean>(() => {
			return props.form.expires > 0 && moment().unix() > props.form.expires
		})

		const isArchived = computed<boolean>(() => {
			return props.form.state === FormState.FormArchived
		})

		const isClosed = computed<boolean>(() => {
			return props.form.state === FormState.FormClosed
		})

		const isMaxSubmissionsReached = computed<boolean>(() => {
			return props.form.isMaxSubmissionsReached === true
		})

		/**
		 * Checks if the current state is active.
		 *
		 * @return - Returns true if active, otherwise false.
		 */
		const isActive = computed<boolean>(() => {
			return !isArchived.value && !isClosed.value && !isExpired.value
		})

		const infoMessage = computed<string>(() => {
			let message = ''
			if (props.form.isAnonymous) {
				message += t('forms', 'Responses are anonymous.')
			}
			if (!props.form.isAnonymous && props.isLoggedIn) {
				message += t('forms', 'Responses are connected to your account.')
			}
			if (isRequiredUsed.value) {
				message +=
					' '
					+ t('forms', 'An asterisk (*) indicates mandatory questions.')
			}

			return message
		})

		/**
		 * Rendered HTML of the custom submission message
		 */
		const submissionMessageHTML = computed<string>(() => {
			if (
				props.form.submissionMessage
				&& (success.value || !props.form.canSubmit)
			) {
				return viewForm.markdownit.render(props.form.submissionMessage)
			}
			return ''
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

		/**
		 * Buttons for the "confirm submit empty form" dialog
		 */
		const confirmEmptyModalButtons = computed<DialogButton[]>(() => {
			return [
				{
					label: t('forms', 'Abort'),
					icon: IconCancel,
					callback: () => {},
				},
				{
					label: t('forms', 'Submit'),
					icon: IconCheck,
					variant: 'primary',
					callback: () => {
						void onConfirmedSubmit()
					},
				},
			]
		})

		/**
		 * Buttons for the "confirm leave unsubmitted form" dialog
		 */
		const confirmLeaveFormButtons = computed<DialogButton[]>(() => {
			return [
				{
					label: t('forms', 'Abort'),
					icon: IconCancel,
					callback: () => confirmButtonCallback.value(false),
				},
				{
					label: t('forms', 'Leave'),
					icon: IconCheck,
					variant: 'primary',
					callback: () => confirmButtonCallback.value(true),
				},
			]
		})

		/**
		 * Buttons for the "confirm clear form" dialog
		 */
		const confirmClearFormButtons = computed<DialogButton[]>(() => {
			return [
				{
					label: t('forms', 'Abort'),
					icon: IconCancel,
					callback: () => {},
				},
				{
					label: t('forms', 'Clear'),
					icon: IconCheck,
					variant: 'primary',
					callback: () => onResetSubmission(),
				},
			]
		})

		const hasAnswers = computed<boolean>(() => {
			return Object.keys(answers.value).length > 0
		})

		const submissionId = computed<number | null>(() => {
			const routeSubmissionId = Array.isArray(route.params.submissionId)
				? route.params.submissionId[0]
				: route.params.submissionId
			const id =
				routeSubmissionId || loadState(formsAppName, 'submissionId', null)
			return id ? parseInt(String(id), 10) : null
		})

		/**
		 * Load saved values for current form from LocalStorage
		 *
		 * @return
		 */
		function getFormValuesFromLocalStorage(): StoredAnswersMap | null {
			const fromLocalStorage = localStorage.getItem(
				`nextcloud_forms_${props.publicView ? props.shareHash : props.hash}`,
			)
			if (fromLocalStorage) {
				return JSON.parse(fromLocalStorage)
			}
			return null
		}

		/**
		 * Initialize answers from saved state in LocalStorage
		 */
		function initFromLocalStorage(): void {
			const savedState = getFormValuesFromLocalStorage()
			if (!savedState) {
				return
			}

			const localAnswers: AnswersMap = {}
			for (const [questionId, answer] of Object.entries(savedState)) {
				// Clean up answers for questions that do not exist anymore
				if (!validQuestionsIds.value.has(parseInt(questionId, 10))) {
					showClearFormDueToChangeDialog.value = true
					logger.debug('Question does not exist anymore', {
						questionId,
					})
					continue
				}

				localAnswers[parseInt(questionId, 10)] = [
					'QuestionMultiple',
					'QuestionRanking',
				].includes(answer.type)
					? answer.value.map(String)
					: answer.value
			}
			answers.value = localAnswers
		}

		/**
		 * Save updated answers for question to LocalStorage in case of browser crash / closes / etc
		 *
		 * @param question Question to update
		 */
		function addFormFieldToLocalStorage(question: SubmitQuestion): void {
			if (!props.isLoggedIn) {
				return
			}
			// We make sure the values are updated by the `values.sync` handler
			const state = {
				...(getFormValuesFromLocalStorage() ?? {}),
				[`${question.id}`]: {
					value: answers.value[question.id],
					type: answerTypes[question.type].component.name,
				},
			}
			const stringified = JSON.stringify(state)
			localStorage.setItem(
				`nextcloud_forms_${props.publicView ? props.shareHash : props.hash}`,
				stringified,
			)
		}

		/**
		 * Deletes a non-existing field from local storage
		 */
		function deleteFormFieldFromLocalStorage(): void {
			if (!props.isLoggedIn) {
				return
			}
			localStorage.removeItem(
				`nextcloud_forms_${props.publicView ? props.shareHash : props.hash}`,
			)
		}

		/**
		 * Fetches the submission data for the given id from the server
		 */
		async function fetchSubmission(): Promise<void> {
			logger.debug(`Loading response ${submissionId.value}`)

			try {
				const response = await axios.get(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/submissions/{submissionId}',
						{
							id: props.form.id,
							submissionId: submissionId.value,
						},
					),
				)

				const loaded: AnswersMap = {}
				const loadedAnswers =
					OcsResponse2Data<LoadedSubmissionResponse>(response).answers
				for (const answer of loadedAnswers) {
					const questionId = Number(answer.questionId)
					const text = answer.text

					// Only initialize once, don't overwrite previous answers
					if (!loaded[questionId]) {
						loaded[questionId] = []
					}

					logger.debug(`questionId: ${questionId}, answerId: ${answer.id}`)
					// Clean up answers for questions that do not exist anymore
					if (!validQuestionsIds.value.has(questionId)) {
						showClearFormDueToChangeDialog.value = true
						logger.debug('Question does not exist anymore', {
							questionId,
						})
						continue
					}

					const question = props.form.questions.find(
						(question: FormsQuestion) => question.id === questionId,
					) as SubmitQuestion | undefined
					if (!question) {
						continue
					}
					if (question.type === 'ranking') {
						try {
							loaded[questionId].push(...JSON.parse(text).map(String))
						} catch (error) {
							logger.debug(
								`Could not parse ranking answer ${text} for question ${questionId}`,
								{ error },
							)
						}
					} else if (
						['multiple', 'multiple_unique', 'dropdown'].includes(
							question.type,
						)
					) {
						const option = (question.options ?? []).filter(
							(option) => option.text === text,
						)
						if (option.length > 0) {
							loaded[questionId].push(String(option[0].id))
						} else if (
							question.extraSettings?.allowOtherAnswer
							&& !loaded[questionId].some((localAnswer) =>
								String(localAnswer).startsWith(
									QUESTION_EXTRASETTINGS_OTHER_PREFIX,
								),
							)
						) {
							loaded[questionId].push(
								QUESTION_EXTRASETTINGS_OTHER_PREFIX + text,
							)
						} else {
							// error handling
							logger.debug(
								`option ${text} could not be mapped to an option for question ${questionId}`,
							)
						}
					} else if (question.type === 'file') {
						// File answers cannot be restored when editing a submission —
						// the uploaded file has already been moved to permanent storage
						// and the temporary uploadedFileId no longer exists.
						// The user must re-upload files if needed.
						logger.debug(
							`Skipping file answer for question ${questionId} — cannot restore uploaded files`,
						)
					} else {
						loaded[questionId].push(text)
					}
				}

				answers.value = loaded
			} catch (error) {
				logger.error('Error while loading response', { error })
				showError(
					t('forms', 'There was an error while loading the response'),
				)
			}
		}

		/**
		 * Update answers of a give value
		 *
		 * @param question The question to answer
		 * @param values The new values
		 */
		function onUpdate(question: SubmitQuestion, values: AnswerValue): void {
			answers.value = {
				...answers.value,
				[question.id]: values,
			}
			addFormFieldToLocalStorage(question)
		}

		/**
		 * Proxy for update events emitted by question components.
		 *
		 * @param question The question being updated.
		 * @param values The updated question values.
		 */
		function updateQuestionValues(
			question: SubmitQuestion,
			values: AnswerValue,
		): void {
			onUpdate(question, values)
		}

		/**
		 * On Enter, focus next form-element
		 * Last form element is the submit button, the form submits on enter then
		 *
		 * @param event The fired event.
		 */
		function onKeydownEnter(
			event: KeyboardEvent & { originalTarget?: EventTarget | null },
		): void {
			const formInputs = Array.from(
				formElement.value?.elements ?? [],
			) as HTMLElement[]
			const sourceInputIndex = formInputs.findIndex(
				(input) => input === (event.originalTarget ?? event.target),
			)

			// Focus next form element
			formInputs[sourceInputIndex + 1]?.focus()
		}

		/**
		 * Ctrl+Enter typically fires submit on forms.
		 * Some inputs do automatically, while some need explicit handling
		 */
		function onKeydownCtrlEnter(): void {
			formElement.value?.requestSubmit()
		}

		/*
		 * Methods for catching unwanted unload events
		 */
		/**
		 * Block closing or reloading while there are unsaved answers.
		 *
		 * @param e The beforeunload browser event.
		 */
		function beforeWindowUnload(e: BeforeUnloadEvent): void {
			if (
				isActive.value
				&& !submitForm.value
				&& Object.keys(answers.value).length !== 0
			) {
				// Cancel the window unload event
				e.preventDefault()
				e.returnValue = ''
			}
		}

		/**
		 * Checks if the user is attempting to leave the form under certain conditions
		 * and shows a confirmation dialog if necessary.
		 *
		 * Conditions to show the confirmation dialog:
		 * - The form is active.
		 * - The form is not currently submitted.
		 * - There are answers provided in the form.
		 *
		 * If the conditions are met, a confirmation dialog is shown and a promise is returned.
		 * The promise resolves with the value passed to the confirm button callback.
		 *
		 * @return - Returns a promise that resolves with the value
		 * passed to the confirm button callback if the dialog is shown, otherwise returns true.
		 */
		function confirmLeaveForm(): Promise<boolean> | boolean {
			if (
				isActive.value
				&& !submitForm.value
				&& Object.keys(answers.value).length !== 0
			) {
				showConfirmLeaveDialog.value = true
				return new Promise((resolve) => {
					confirmButtonCallback.value = (val: boolean) => {
						showConfirmLeaveDialog.value = false
						resolve(val)
					}
				})
			}

			return true
		}

		/**
		 * Submit the form after the browser validated it 🚀 or show confirmation modal if empty
		 */
		async function onSubmit(): Promise<void> {
			const rawQuestionRefs = Array.isArray(questionRefs.value)
				? questionRefs.value
				: [questionRefs.value].filter(Boolean)
			const validation = (rawQuestionRefs as QuestionComponentRef[]).map(
				async (question) => await question.validate(),
			)

			try {
				// wait for all to be validated
				const result = await Promise.all(validation)
				if (result.some((v) => !v)) {
					throw new Error('One question did not validate sucessfully')
				}

				// in case no answer is set or all are empty show the confirmation dialog
				if (
					Object.keys(answers.value).length === 0
					|| Object.values(answers.value).every(
						(localAnswers) => localAnswers.length === 0,
					)
				) {
					showConfirmEmptyModal.value = true
				} else {
					// otherwise do the real submit
					await onConfirmedSubmit()
				}
			} catch (error) {
				logger.debug('One question is not valid', { error })
				showError(t('forms', 'Some answers are not valid'))
			}
		}

		/**
		 * Handle the real submit of the form, this is only called if the form is not empty or user confirmed to submit
		 */
		async function onConfirmedSubmit(): Promise<void> {
			showConfirmEmptyModal.value = false
			loading.value = true

			try {
				if (submissionId.value) {
					await axios.put(
						generateOcsUrl(
							'apps/forms/api/v3/forms/{id}/submissions/{submissionId}',
							{
								id: props.form.id,
								submissionId: submissionId.value,
							},
						),
						{
							answers: answers.value,
						},
					)
				} else {
					await axios.post(
						generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
							id: props.form.id,
						}),
						{
							answers: answers.value,
							shareHash: props.shareHash,
						},
					)
				}
				submitForm.value = true
				success.value = true
				deleteFormFieldFromLocalStorage()
				emitEvent('forms:last-updated:set', props.form.id)
			} catch (error) {
				const errorMessage = (
					error as {
						response?: {
							data?: { ocs?: { meta?: { message?: string } } }
						}
					}
				).response?.data?.ocs?.meta?.message
				logger.error('Error while submitting the form', { error })
				if (errorMessage) {
					showError(
						t(
							'forms',
							'There was an error submitting the form: {message}',
							{
								message: errorMessage,
							},
						),
					)
				} else {
					showError(t('forms', 'There was an error submitting the form'))
				}
			} finally {
				loading.value = false
				if (!props.publicView) {
					await viewForm.fetchFullForm(props.form.id)
				}
			}
		}

		/**
		 *
		 */
		function onResetSubmission(): void {
			deleteFormFieldFromLocalStorage()
			resetData()
		}

		/**
		 * Reset View-Data
		 */
		function resetData(): void {
			answers.value = {}
			loading.value = false
			showConfirmLeaveDialog.value = false
			showClearFormDialog.value = false
			showClearFormDueToChangeDialog.value = false
			success.value = false
			submitForm.value = false
		}

		watch(success, (newVal: boolean): void => {
			if (newVal) {
				// Delay populating the live region to avoid the announcement being
				// swallowed by the simultaneous large DOM change (form replaced by
				// success view). Screen readers need a moment to process the new DOM
				// before a polite live region update registers.
				setTimeout(() => {
					successAnnouncement.value =
						props.form.submissionMessage
						|| t('forms', 'Thank you for completing the form!')
				}, 100)
			} else {
				successAnnouncement.value = ''
			}
		})

		watch(
			() => props.hash,
			(): void => {
				// If public view, abort. Should normally not occur.
				if (props.publicView) {
					logger.error('Hash changed on public view. Aborting.')
					return
				}
				resetData()
				// Fetch full form on change
				void viewForm.fetchFullForm(props.form.id)
				initFromLocalStorage()
				SetWindowTitle(viewForm.formTitle.value)
			},
		)

		onBeforeRouteUpdate(async () => {
			// This navigation guard is called when the route parameters changed (e.g. form hash)
			// continue with the navigation if there are no changes or the user confirms to leave the form
			if (await confirmLeaveForm()) {
				return
			} else {
				// Otherwise cancel the navigation
				return false
			}
		})

		onBeforeRouteLeave(async () => {
			// This navigation guard is called when the route changed and a new view should be shown
			// continue with the navigation if there are no changes or the user confirms to leave the form
			if (await confirmLeaveForm()) {
				return
			} else {
				// Otherwise cancel the navigation
				return false
			}
		})

		onMounted((): void => {
			window.addEventListener('beforeunload', beforeWindowUnload)
		})

		onUnmounted((): void => {
			window.removeEventListener('beforeunload', beforeWindowUnload)
		})

		onBeforeMount(async (): Promise<void> => {
			// Public Views get their form by initial-state from parent. No fetch necessary.
			if (props.publicView) {
				viewForm.isLoadingForm.value = false
			} else {
				await viewForm.fetchFullForm(props.form.id)
			}

			if (props.isLoggedIn) {
				if (
					submissionId.value
					&& (props.form.allowEditSubmissions
						|| props.form.permissions.includes(
							PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
						))
				) {
					await fetchSubmission()
				} else {
					initFromLocalStorage()
				}
			}

			SetWindowTitle(viewForm.formTitle.value)
		})

		// Non reactive properties
		return {
			...viewForm,
			answerTypes,
			answers,
			confirmClearFormButtons,
			confirmEmptyModalButtons,
			confirmLeaveFormButtons,
			expirationMessage,
			formElement,
			hasAnswers,
			infoMessage,
			isArchived,
			isClosed,
			isExpired,
			isMaxSubmissionsReached,
			loading,
			onKeydownCtrlEnter,
			onKeydownEnter,
			onSubmit,
			onUpdate,
			questionRefs,
			showClearFormDialog,
			showClearFormDueToChangeDialog,
			showConfirmEmptyModal,
			showConfirmLeaveDialog,
			submissionId,
			submissionMessageHTML,
			success,
			successAnnouncement,
			updateQuestionValues,
			validQuestions,
			title,
			IconCheckSvg: IconCheck,
			IconRefreshSvg: IconRefresh,
			IconSendSvg: IconSend,
			t,

			maxStringLengths: loadState(formsAppName, 'maxStringLengths') as Record<
				string,
				number
			>,
		}
	},
})
</script>

<style lang="scss" scoped>
@use '../scssmixins/markdownOutput.scss' as *;

.forms-emptycontent {
	height: 100%;
}

.app-content {
	display: flex;
	align-items: center;
	flex-direction: column;

	&--public:not(.app-forms-embedded *) {
		// Compensate top-padding for missing topbar
		padding-block-start: 50px;
	}

	header,
	form {
		width: 100%;
		max-width: 750px;
		display: flex;
		flex-direction: column;
	}

	// Title & description header
	header {
		margin-block-end: 24px;
		margin-inline-start: var(--default-clickable-area);

		.form-title,
		.form-desc,
		.info-message {
			width: calc(
				100% - 58px
			); // margin of header, needed if screen is < 806px (max-width + margin-left)
			font-size: 100%;
			padding-block: 0;
			padding-inline: 18px;
			border: none;
		}
		.form-title {
			font-size: 28px;
			font-weight: bold;
			color: var(--color-main-text);
			line-height: 34px;
			min-height: 36px;
			margin-block: 32px;
			margin-inline: 0;
			padding-block-end: 4px;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.form-desc {
			line-height: 22px;
			padding-block-end: 20px;
			resize: none;
			min-height: 42px;
			color: var(--color-main-text);

			@include markdown-output;
		}

		.info-message {
			padding-block-end: 20px;
			margin-block-start: 4px;
			resize: none;
			color: var(--color-text-maxcontrast);
		}
	}

	.submission-message {
		@include markdown-output;
		& {
			text-align: center;
		}
	}

	form {
		.question {
			// Less padding needed as submit view does not have drag handles
			padding-inline: var(--default-clickable-area);
		}

		.form-buttons {
			display: flex;
			justify-content: flex-end;
		}

		.submit-button {
			margin: 5px;
			margin-block-end: 160px;
			padding-inline-start: 20px;
		}
	}
}
</style>
