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
			<form v-else ref="form" @submit.prevent="onSubmit">
				<ul>
					<component
						:is="answerTypes[question.type].component"
						v-for="(question, index) in validQuestions"
						ref="questions"
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
							(values: unknown[]) => onUpdate(question, values)
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
import type { NavigationGuardNext } from 'vue-router'
import type { FormsQuestion } from '../models/Entities.d.ts'

import IconCancel from '@material-symbols/svg-400/outlined/block.svg?raw'
import IconCheck from '@material-symbols/svg-400/outlined/check.svg?raw'
import IconRefresh from '@material-symbols/svg-400/outlined/refresh.svg?raw'
import IconSend from '@material-symbols/svg-400/outlined/send.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { defineComponent } from 'vue'
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
import PermissionTypes from '../mixins/PermissionTypes.ts'
import ViewsMixin from '../mixins/ViewsMixin.ts'
import answerTypes from '../models/AnswerTypes.ts'
import {
	FormState,
	QUESTION_EXTRASETTINGS_OTHER_PREFIX,
} from '../models/Constants.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'
import SetWindowTitle from '../utils/SetWindowTitle.ts'

type AnswerValue = unknown[]
type AnswersMap = Record<number, AnswerValue>

interface StoredAnswerState {
	value: AnswerValue
	type: string
}

interface StoredAnswersMap {
	[key: string]: StoredAnswerState
}

interface QuestionOption {
	id: number
	text: string
	optionType?: string
}

interface SubmitQuestion extends FormsQuestion {
	options?: QuestionOption[]
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

interface SubmitViewData {
	answerTypes: typeof answerTypes
	answers: AnswersMap
	loading: boolean
	success: boolean
	successAnnouncement: string
	submitForm: boolean
	showConfirmEmptyModal: boolean
	showConfirmLeaveDialog: boolean
	showClearFormDialog: boolean
	showClearFormDueToChangeDialog: boolean
	confirmButtonCallback: (value: boolean) => void
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

	mixins: [PermissionTypes, ViewsMixin],

	/*
	 * This is used to confirm that the user wants to leave the page
	 * if the form is unsubmitted.
	 */
	async beforeRouteUpdate(
		_to: unknown,
		_from: unknown,
		next: NavigationGuardNext,
	): Promise<void> {
		// This navigation guard is called when the route parameters changed (e.g. form hash)
		// continue with the navigation if there are no changes or the user confirms to leave the form
		if (await this.confirmLeaveForm()) {
			next()
		} else {
			// Otherwise cancel the navigation
			next(false)
		}
	},

	async beforeRouteLeave(
		_to: unknown,
		_from: unknown,
		next: NavigationGuardNext,
	): Promise<void> {
		// This navigation guard is called when the route changed and a new view should be shown
		// continue with the navigation if there are no changes or the user confirms to leave the form
		if (await this.confirmLeaveForm()) {
			next()
		} else {
			// Otherwise cancel the navigation
			next(false)
		}
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
	},

	setup() {
		// Non reactive properties
		return {
			IconCheckSvg: IconCheck,
			IconRefreshSvg: IconRefresh,
			IconSendSvg: IconSend,
			t,

			maxStringLengths: loadState('forms', 'maxStringLengths') as Record<
				string,
				number
			>,
		}
	},

	data(): SubmitViewData {
		return {
			answerTypes,
			answers: {},
			loading: false,
			success: false,
			successAnnouncement: '',
			submitForm: false,
			showConfirmEmptyModal: false,
			showConfirmLeaveDialog: false,
			showClearFormDialog: false,
			showClearFormDueToChangeDialog: false,
			confirmButtonCallback: () => {},
		}
	},

	computed: {
		validQuestions(): SubmitQuestion[] {
			return this.form.questions.filter((question) => {
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
		},

		validQuestionsIds(): Set<number> {
			return new Set(this.validQuestions.map((question) => question.id))
		},

		isRequiredUsed(): boolean {
			return this.form.questions.some((question) =>
				Boolean(question.isRequired),
			)
		},

		/**
		 * Check if form is expired
		 */
		isExpired(): boolean {
			return this.form.expires > 0 && moment().unix() > this.form.expires
		},

		isArchived(): boolean {
			return this.form.state === FormState.FormArchived
		},

		isClosed(): boolean {
			return this.form.state === FormState.FormClosed
		},

		isMaxSubmissionsReached(): boolean {
			return this.form.isMaxSubmissionsReached === true
		},

		/**
		 * Checks if the current state is active.
		 *
		 * @return - Returns true if active, otherwise false.
		 */
		isActive(): boolean {
			return !this.isArchived && !this.isClosed && !this.isExpired
		},

		infoMessage(): string {
			let message = ''
			if (this.form.isAnonymous) {
				message += t('forms', 'Responses are anonymous.')
			}
			if (!this.form.isAnonymous && this.isLoggedIn) {
				message += t('forms', 'Responses are connected to your account.')
			}
			if (this.isRequiredUsed) {
				message +=
					' '
					+ t('forms', 'An asterisk (*) indicates mandatory questions.')
			}

			return message
		},

		/**
		 * Rendered HTML of the custom submission message
		 */
		submissionMessageHTML(): string {
			if (
				this.form.submissionMessage
				&& (this.success || !this.form.canSubmit)
			) {
				return this.markdownit.render(this.form.submissionMessage)
			}
			return ''
		},

		expirationMessage(): string {
			const relativeDate = moment(this.form.expires, 'X')
				.locale(window.OC.getLanguage())
				.fromNow()
			if (this.isExpired) {
				return t('forms', 'Expired {relativeDate}.', { relativeDate })
			}
			return t('forms', 'Expires {relativeDate}.', { relativeDate })
		},

		/**
		 * Buttons for the "confirm submit empty form" dialog
		 */
		confirmEmptyModalButtons(): DialogButton[] {
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
					callback: () => this.onConfirmedSubmit(),
				},
			]
		},

		/**
		 * Buttons for the "confirm leave unsubmitted form" dialog
		 */
		confirmLeaveFormButtons(): DialogButton[] {
			return [
				{
					label: t('forms', 'Abort'),
					icon: IconCancel,
					callback: () => this.confirmButtonCallback(false),
				},
				{
					label: t('forms', 'Leave'),
					icon: IconCheck,
					variant: 'primary',
					callback: () => this.confirmButtonCallback(true),
				},
			]
		},

		/**
		 * Buttons for the "confirm clear form" dialog
		 */
		confirmClearFormButtons(): DialogButton[] {
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
					callback: () => this.onResetSubmission(),
				},
			]
		},

		hasAnswers(): boolean {
			return Object.keys(this.answers).length > 0
		},

		submissionId(): number | null {
			const id =
				this.$route?.params.submissionId
				|| loadState('forms', 'submissionId', null)
			return id ? parseInt(String(id), 10) : null
		},
	},

	watch: {
		success(newVal: boolean): void {
			if (newVal) {
				// Delay populating the live region to avoid the announcement being
				// swallowed by the simultaneous large DOM change (form replaced by
				// success view). Screen readers need a moment to process the new DOM
				// before a polite live region update registers.
				setTimeout(() => {
					this.successAnnouncement =
						this.form.submissionMessage
						|| t('forms', 'Thank you for completing the form!')
				}, 100)
			} else {
				this.successAnnouncement = ''
			}
		},

		hash(): void {
			// If public view, abort. Should normally not occur.
			if (this.publicView) {
				logger.error('Hash changed on public view. Aborting.')
				return
			}
			this.resetData()
			// Fetch full form on change
			this.fetchFullForm(this.form.id)
			this.initFromLocalStorage()
			SetWindowTitle(this.formTitle)
		},
	},

	beforeUnmount(): void {
		window.removeEventListener('beforeunload', this.beforeWindowUnload)
	},

	created(): void {
		window.addEventListener('beforeunload', this.beforeWindowUnload)
	},

	async beforeMount(): Promise<void> {
		// Public Views get their form by initial-state from parent. No fetch necessary.
		if (this.publicView) {
			this.isLoadingForm = false
		} else {
			await this.fetchFullForm(this.form.id)
		}

		if (this.isLoggedIn) {
			if (
				this.submissionId
				&& (this.form.allowEditSubmissions
					|| this.form.permissions.includes(
						this.PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
					))
			) {
				this.fetchSubmission()
			} else {
				this.initFromLocalStorage()
			}
		}

		SetWindowTitle(this.formTitle)
	},

	methods: {
		/**
		 * Load saved values for current form from LocalStorage
		 *
		 * @return
		 */
		getFormValuesFromLocalStorage(): StoredAnswersMap | null {
			const fromLocalStorage = localStorage.getItem(
				`nextcloud_forms_${this.publicView ? this.shareHash : this.hash}`,
			)
			if (fromLocalStorage) {
				return JSON.parse(fromLocalStorage)
			}
			return null
		},

		/**
		 * Initialize answers from saved state in LocalStorage
		 */
		initFromLocalStorage(): void {
			const savedState = this.getFormValuesFromLocalStorage()
			if (!savedState) {
				return
			}

			const answers: AnswersMap = {}
			for (const [questionId, answer] of Object.entries(savedState)) {
				// Clean up answers for questions that do not exist anymore
				if (!this.validQuestionsIds.has(parseInt(questionId, 10))) {
					this.showClearFormDueToChangeDialog = true
					logger.debug('Question does not exist anymore', {
						questionId,
					})
					continue
				}

				answers[parseInt(questionId, 10)] = [
					'QuestionMultiple',
					'QuestionRanking',
				].includes(answer.type)
					? answer.value.map(String)
					: answer.value
			}
			this.answers = answers
		},

		/**
		 * Save updated answers for question to LocalStorage in case of browser crash / closes / etc
		 *
		 * @param question Question to update
		 */
		addFormFieldToLocalStorage(question: SubmitQuestion): void {
			if (!this.isLoggedIn) {
				return
			}
			// We make sure the values are updated by the `values.sync` handler
			const state = {
				...(this.getFormValuesFromLocalStorage() ?? {}),
				[`${question.id}`]: {
					value: this.answers[question.id],
					type: answerTypes[question.type].component.name,
				},
			}
			const stringified = JSON.stringify(state)
			localStorage.setItem(
				`nextcloud_forms_${this.publicView ? this.shareHash : this.hash}`,
				stringified,
			)
		},

		deleteFormFieldFromLocalStorage(): void {
			if (!this.isLoggedIn) {
				return
			}
			localStorage.removeItem(
				`nextcloud_forms_${this.publicView ? this.shareHash : this.hash}`,
			)
		},

		async fetchSubmission(): Promise<void> {
			logger.debug(`Loading response ${this.submissionId}`)

			try {
				const response = await axios.get(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/submissions/{submissionId}',
						{
							id: this.form.id,
							submissionId: this.submissionId,
						},
					),
				)

				const answers: AnswersMap = {}
				const loadedAnswers =
					OcsResponse2Data<LoadedSubmissionResponse>(response).answers
				for (const answer of loadedAnswers) {
					const questionId = Number(answer.questionId)
					const text = answer.text

					// Only initialize once, don't overwrite previous answers
					if (!answers[questionId]) {
						answers[questionId] = []
					}

					logger.debug(`questionId: ${questionId}, answerId: ${answer.id}`)
					// Clean up answers for questions that do not exist anymore
					if (!this.validQuestionsIds.has(questionId)) {
						this.showClearFormDueToChangeDialog = true
						logger.debug('Question does not exist anymore', {
							questionId,
						})
						continue
					}

					const question = this.form.questions.find(
						(question) => question.id === questionId,
					) as SubmitQuestion | undefined
					if (!question) {
						continue
					}
					if (question.type === 'ranking') {
						try {
							answers[questionId].push(...JSON.parse(text).map(String))
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
							answers[questionId].push(String(option[0].id))
						} else if (
							question.extraSettings?.allowOtherAnswer
							&& !answers[questionId].some((answer) =>
								String(answer).startsWith(
									QUESTION_EXTRASETTINGS_OTHER_PREFIX,
								),
							)
						) {
							answers[questionId].push(
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
						answers[questionId].push(text)
					}
				}

				this.answers = answers
			} catch (error) {
				logger.error('Error while loading response', { error })
				showError(
					t('forms', 'There was an error while loading the response'),
				)
			}
		},

		/**
		 * Update answers of a give value
		 *
		 * @param question The question to answer
		 * @param values The new values
		 */
		onUpdate(question: SubmitQuestion, values: AnswerValue): void {
			this.answers = { ...this.answers, [question.id]: values }
			this.addFormFieldToLocalStorage(question)
		},

		updateQuestionValues(question: SubmitQuestion, values: AnswerValue): void {
			this.onUpdate(question, values)
		},

		/**
		 * On Enter, focus next form-element
		 * Last form element is the submit button, the form submits on enter then
		 *
		 * @param event The fired event.
		 */
		onKeydownEnter(
			event: KeyboardEvent & { originalTarget?: EventTarget | null },
		): void {
			const formInputs = Array.from(
				(this.$refs.form as HTMLFormElement).elements,
			) as HTMLElement[]
			const sourceInputIndex = formInputs.findIndex(
				(input) => input === (event.originalTarget ?? event.target),
			)

			// Focus next form element
			formInputs[sourceInputIndex + 1]?.focus()
		},

		/**
		 * Ctrl+Enter typically fires submit on forms.
		 * Some inputs do automatically, while some need explicit handling
		 */
		onKeydownCtrlEnter(): void {
			;(this.$refs.form as HTMLFormElement | undefined)?.requestSubmit()
		},

		/*
		 * Methods for catching unwanted unload events
		 */
		beforeWindowUnload(e: BeforeUnloadEvent): void {
			if (
				this.isActive
				&& !this.submitForm
				&& Object.keys(this.answers).length !== 0
			) {
				// Cancel the window unload event
				e.preventDefault()
				e.returnValue = ''
			}
		},

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
		confirmLeaveForm(): Promise<boolean> | boolean {
			if (
				this.isActive
				&& !this.submitForm
				&& Object.keys(this.answers).length !== 0
			) {
				this.showConfirmLeaveDialog = true
				return new Promise((resolve) => {
					this.confirmButtonCallback = (val: boolean) => {
						this.showConfirmLeaveDialog = false
						resolve(val)
					}
				})
			}

			return true
		},

		/**
		 * Submit the form after the browser validated it 🚀 or show confirmation modal if empty
		 */
		async onSubmit(): Promise<void> {
			const questionRefs = (
				Array.isArray(this.$refs.questions)
					? this.$refs.questions
					: [this.$refs.questions].filter(Boolean)
			) as QuestionComponentRef[]
			const validation = questionRefs.map(
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
					Object.keys(this.answers).length === 0
					|| Object.values(this.answers).every(
						(answers) => answers.length === 0,
					)
				) {
					this.showConfirmEmptyModal = true
				} else {
					// otherwise do the real submit
					this.onConfirmedSubmit()
				}
			} catch (error) {
				logger.debug('One question is not valid', { error })
				showError(t('forms', 'Some answers are not valid'))
			}
		},

		/**
		 * Handle the real submit of the form, this is only called if the form is not empty or user confirmed to submit
		 */
		async onConfirmedSubmit(): Promise<void> {
			this.showConfirmEmptyModal = false
			this.loading = true

			try {
				if (this.submissionId) {
					await axios.put(
						generateOcsUrl(
							'apps/forms/api/v3/forms/{id}/submissions/{submissionId}',
							{
								id: this.form.id,
								submissionId: this.submissionId,
							},
						),
						{
							answers: this.answers,
						},
					)
				} else {
					await axios.post(
						generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
							id: this.form.id,
						}),
						{
							answers: this.answers,
							shareHash: this.shareHash,
						},
					)
				}
				this.submitForm = true
				this.success = true
				this.deleteFormFieldFromLocalStorage()
				emit('forms:last-updated:set', this.form.id)
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
				this.loading = false
				if (!this.publicView) {
					this.fetchFullForm(this.form.id)
				}
			}
		},

		onResetSubmission(): void {
			this.deleteFormFieldFromLocalStorage()
			this.resetData()
		},

		/**
		 * Reset View-Data
		 */
		resetData(): void {
			this.answers = {}
			this.loading = false
			this.showConfirmLeaveDialog = false
			this.showClearFormDialog = false
			this.showClearFormDueToChangeDialog = false
			this.success = false
			this.submitForm = false
		},
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
