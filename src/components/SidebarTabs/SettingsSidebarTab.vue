<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sidebar-tabs__content">
		<NcNoteCard
			v-if="locked"
			type="info"
			:heading="t('forms', 'Form is locked')"
			:text="
				t('forms', 'Lock by {lockedBy}, expires: {lockedUntil}', {
					lockedBy: form.lockedBy ? form.lockedBy : form.ownerId,
					lockedUntil:
						lockedUntil === '' ? t('forms', 'never') : lockedUntil,
				})
			" />
		<NcButton
			v-if="locked && isCurrentUserOwner"
			wide
			@click="onFormLockChange(false)">
			<template #icon>
				<NcIconSvgWrapper :svg="svgLockOpen" />
			</template>
			<!-- TRANSLATORS text for the action triggered by the button -->
			{{ t('forms', 'Unlock form') }}
		</NcButton>
		<NcCheckboxRadioSwitch
			:modelValue="form.isAnonymous"
			:disabled="formArchived || locked"
			type="switch"
			@update:modelValue="onAnonChange">
			<!-- TRANSLATORS Checkbox to select whether responses will be stored anonymously or not -->
			{{ t('forms', 'Store responses anonymously') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:title="disableSubmitMultipleExplanation"
			:modelValue="submitMultiple"
			:disabled="disableSubmitMultiple || formArchived || locked"
			type="switch"
			@update:modelValue="onSubmitMultipleChange">
			{{ t('forms', 'Allow multiple responses per person') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:modelValue="form.allowEditSubmissions"
			:disabled="formArchived || locked"
			type="switch"
			@update:modelValue="onAllowEditSubmissionsChange">
			{{ t('forms', 'Allow editing own responses') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			v-if="appConfig.allowComments"
			:modelValue="form.allowComments"
			:disabled="formArchived || locked"
			type="switch"
			@update:modelValue="onAllowCommentsChange">
			{{ t('forms', 'Allow comments') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:modelValue="formExpires"
			:disabled="formArchived || locked"
			type="switch"
			@update:modelValue="onFormExpiresChange">
			{{ t('forms', 'Set expiration date') }}
		</NcCheckboxRadioSwitch>
		<div v-show="formExpires && !formArchived" class="settings-div--indent">
			<NcDateTimePicker
				id="expiresDatetimePicker"
				:clearable="false"
				:disabled="locked"
				:disabledDate="notBeforeToday"
				:disabledTime="notBeforeNow"
				:editable="false"
				:format="stringifyDate"
				:minuteStep="5"
				:showSecond="false"
				:modelValue="expirationDate"
				type="datetime"
				@update:modelValue="onExpirationDateChange" />
			<NcCheckboxRadioSwitch
				:modelValue="form.showExpiration"
				:disabled="locked"
				type="switch"
				@update:modelValue="onShowExpirationChange">
				{{ t('forms', 'Show expiration date on form') }}
			</NcCheckboxRadioSwitch>
		</div>
		<NcCheckboxRadioSwitch
			:modelValue="hasMaxSubmissions"
			:disabled="formArchived || locked"
			type="switch"
			@update:modelValue="onMaxSubmissionsChange">
			{{ t('forms', 'Limit number of responses') }}
		</NcCheckboxRadioSwitch>
		<div
			v-show="hasMaxSubmissions && !formArchived"
			class="settings-div--indent">
			<NcInputField
				:modelValue="maxSubmissionsValue"
				type="number"
				:min="1"
				:disabled="locked"
				:label="t('forms', 'Maximum number of responses')"
				@update:modelValue="onMaxSubmissionsValueChange" />
			<p class="settings-hint">
				{{
					t(
						'forms',
						'Form will be closed automatically when the limit is reached.',
					)
				}}
			</p>
		</div>
		<NcCheckboxRadioSwitch
			:modelValue="formClosed"
			:disabled="formArchived || locked"
			aria-describedby="forms-settings__close-form"
			type="switch"
			@update:modelValue="onFormClosedChange">
			{{ t('forms', 'Close form') }}
		</NcCheckboxRadioSwitch>
		<p id="forms-settings__close-form" class="settings-hint">
			{{ t('forms', 'Closed forms do not accept new responses.') }}
		</p>
		<NcCheckboxRadioSwitch
			:modelValue="isFormLockedPermanently"
			:disabled="
				formArchived
				|| (locked && form.lockedUntil !== 0)
				|| !isCurrentUserOwner
			"
			type="switch"
			@update:modelValue="onFormLockChange">
			{{ t('forms', 'Lock form permanently') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:modelValue="formArchived"
			aria-describedby="forms-settings__archive-form"
			:disabled="locked || !isCurrentUserOwner"
			type="switch"
			@update:modelValue="onFormArchivedChange">
			{{ t('forms', 'Archive form') }}
		</NcCheckboxRadioSwitch>
		<p id="forms-settings__archive-form" class="settings-hint">
			{{
				t(
					'forms',
					'Archived forms do not accept new responses and cannot be modified.',
				)
			}}
		</p>
		<NcCheckboxRadioSwitch
			:modelValue="hasCustomSubmissionMessage"
			:disabled="formArchived || locked"
			type="switch"
			@update:modelValue="onUpdateHasCustomSubmissionMessage">
			{{ t('forms', 'Custom submission message') }}
		</NcCheckboxRadioSwitch>
		<div
			v-show="hasCustomSubmissionMessage"
			class="settings-div--indent submission-message"
			:tabindex="editMessage ? undefined : '0'"
			@focus="editMessage = true">
			<textarea
				v-if="!formArchived && (editMessage || !form.submissionMessage)"
				v-click-outside="
					() => {
						editMessage = false
					}
				"
				aria-describedby="forms-submission-message-description"
				:aria-label="t('forms', 'Custom submission message')"
				:value="form.submissionMessage"
				:disabled="locked"
				:maxlength="maxStringLengths.submissionMessage"
				:placeholder="
					t(
						'forms',
						'Message to show after a user submitted the form (formatting using Markdown is supported)',
					)
				"
				class="submission-message__input"
				@blur="editMessage = false"
				@change="onSubmissionMessageChange" />
			<!-- eslint-disable vue/no-v-html -->
			<div
				v-else
				:aria-label="t('forms', 'Custom submission message')"
				class="submission-message__output"
				v-html="submissionMessageHTML" />
			<!-- eslint-enable vue/no-v-html -->
			<div
				id="forms-submission-message-description"
				class="submission-message__description">
				{{
					t(
						'forms',
						'Message to show after a user submitted the form. Please note that the message will not be translated!',
					)
				}}
			</div>
		</div>

		<template v-if="appConfig.allowConfirmationEmail">
			<NcCheckboxRadioSwitch
				:modelValue="form.confirmationEmailEnabled"
				:disabled="formArchived || locked"
				type="switch"
				@update:modelValue="onConfirmationEmailEnabledChange">
				{{ t('forms', 'Send confirmation email to respondents') }}
			</NcCheckboxRadioSwitch>
			<div
				v-show="form.confirmationEmailEnabled && !formArchived"
				class="settings-div--indent confirmation-email">
				<NcNoteCard
					v-if="confirmationEmailErrorText"
					:type="confirmationEmailNoteCardType"
					:text="confirmationEmailErrorText" />
				<div
					v-if="emailQuestionCount > 0"
					class="confirmation-email__recipient">
					<label
						for="confirmation-email-recipient"
						class="confirmation-email__label">
						{{ t('forms', 'Recipient field') }}
					</label>
					<NcSelect
						inputId="confirmation-email-recipient"
						:modelValue="selectedConfirmationEmailQuestionOption"
						:disabled="locked || emailQuestionCount === 1"
						:options="confirmationEmailQuestionOptions"
						:placeholder="t('forms', 'Select an email field')"
						class="confirmation-email__select"
						label="label"
						:searchable="false"
						:clearable="false"
						trackBy="id"
						@update:modelValue="
							onConfirmationEmailQuestionIdSelectionChange
						" />
				</div>
				<p class="confirmation-email__placeholder-hint">
					{{ t('forms', 'Available placeholders:') }}
					<code>{formTitle}</code>, <code>{formDescription}</code>,
					{{ t('forms', 'and field labels.') }}
				</p>
				<NcInputField
					v-model="confirmationEmailSubject"
					:disabled="locked || isConfirmationEmailConfigurationBlocked"
					:maxlength="255"
					:placeholder="
						t('forms', 'Thank you for your {formTitle} submission')
					"
					:label="t('forms', 'Email subject')"
					class="confirmation-email__input"
					@blur="onConfirmationEmailSubjectChange" />
				<NcTextArea
					v-model="confirmationEmailBody"
					:disabled="locked || isConfirmationEmailConfigurationBlocked"
					:placeholder="emailBodyPlaceholder"
					:label="t('forms', 'Email body')"
					:maxlength="8192"
					class="confirmation-email__textarea"
					@blur="onConfirmationEmailBodyChange" />
			</div>
		</template>

		<TransferOwnership
			:locked="locked"
			:isOwner="isCurrentUserOwner"
			:form="form" />
	</div>
</template>

<script lang="ts">
import type { FormsQuestion } from '../../models/Entities.d.ts'

import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { vOnClickOutside as ClickOutside } from '@vueuse/components'
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import TransferOwnership from './TransferOwnership.vue'
import svgLockOpen from '../../../img/lock_open.svg?raw'
import { useShareTypes } from '../../composables/useShareTypes.ts'
import { FormState } from '../../models/Constants.ts'
import { getCurrentServerTime } from '../../utils/ServerTime.ts'

const formsAppName = 'forms'

type ConfirmationEmailQuestionOption = {
	id: number
	label: string
}

interface SettingsAppConfig {
	allowComments: boolean
	allowConfirmationEmail: boolean
	[key: string]: unknown
}

interface MarkdownRenderer {
	render: (input: string) => string
}

export default defineComponent({
	components: {
		NcButton,
		NcInputField,
		NcCheckboxRadioSwitch,
		NcDateTimePicker,
		NcIconSvgWrapper,
		NcNoteCard,
		NcSelect,
		NcTextArea,
		TransferOwnership,
	},

	directives: {
		ClickOutside,
	},

	inject: ['$markdownit'],

	props: {
		form: {
			type: Object,
			required: true,
		},

		locked: {
			type: Boolean,
			required: true,
		},

		lockedUntil: {
			type: String,
			default: '',
		},
	},

	emits: ['update:formProp'],

	setup() {
		const { SHARE_TYPES } = useShareTypes()

		return {
			t,
			SHARE_TYPES,
		}
	},

	data(): {
		formatter: {
			stringify: (datetime: Date | [Date, Date] | null) => string
			parse: (value: number) => Date
		}
		appConfig: SettingsAppConfig
		maxStringLengths: Record<string, number>
		editMessage: boolean
		svgLockOpen: string
		confirmationEmailSubject: string
		confirmationEmailBody: string
	} {
		return {
			formatter: {
				stringify: (datetime: Date | [Date, Date] | null) => {
					if (datetime instanceof Date) {
						return this.stringifyDate(datetime)
					}
					return this.stringifyDate(new Date())
				},

				parse: this.parseTimestampToDate,
			},

			appConfig: loadState(formsAppName, 'appConfig') as SettingsAppConfig,
			maxStringLengths: loadState(formsAppName, 'maxStringLengths'),
			/** If custom submission message is shown as input or rendered markdown */
			editMessage: false,
			svgLockOpen,
			confirmationEmailSubject: '',
			confirmationEmailBody: '',
		}
	},

	computed: {
		isCurrentUserOwner(): boolean {
			return getCurrentUser()?.uid === this.form.ownerId
		},

		isFormLockedPermanently(): boolean {
			return this.locked && this.form.lockedUntil === 0
		},

		/**
		 * If the form has a custom submission message or the user wants to add one (settings switch)
		 */
		hasCustomSubmissionMessage(): boolean {
			return (
				this.form?.submissionMessage !== undefined
				&& this.form?.submissionMessage !== null
			)
		},

		/**
		 * Submit Multiple is disabled, if it cannot be controlled.
		 */
		disableSubmitMultiple(): boolean {
			return this.hasPublicLink || this.form.isAnonymous
		},

		disableSubmitMultipleExplanation(): string {
			if (this.disableSubmitMultiple) {
				return t(
					'forms',
					'This can not be controlled, if the form has a public link or stores responses anonymously.',
				)
			}
			return ''
		},

		hasPublicLink(): boolean {
			return (
				this.form.shares.filter(
					(share) => share.shareType === this.SHARE_TYPES.SHARE_TYPE_LINK,
				).length !== 0
			)
		},

		// If disabled, submitMultiple will be casted to true
		submitMultiple(): boolean {
			return this.disableSubmitMultiple || this.form.submitMultiple
		},

		formExpires(): boolean {
			return this.form.expires !== 0
		},

		formArchived(): boolean {
			return this.form.state === FormState.FormArchived
		},

		formClosed(): boolean {
			return this.form.state !== FormState.FormActive
		},

		hasMaxSubmissions(): boolean {
			return (
				this.form.maxSubmissions !== null
				&& this.form.maxSubmissions !== undefined
			)
		},

		maxSubmissionsValue(): number {
			return this.form.maxSubmissions ?? 1
		},

		isExpired(): boolean {
			return this.form.expires && getCurrentServerTime() > this.form.expires
		},

		expirationDate(): Date {
			return moment(this.form.expires, 'X').toDate()
		},

		/**
		 * The submission message rendered as HTML
		 */
		submissionMessageHTML(): string {
			return (this.$markdownit as MarkdownRenderer).render(
				this.form.submissionMessage || '',
			)
		},

		emailBodyPlaceholder(): string {
			return t(
				'forms',
				'Hello,\n\nThank you for submitting the form "{formTitle}".\n\nBest regards',
			)
		},

		emailQuestionCount(): number {
			return this.confirmationEmailQuestions.length
		},

		confirmationEmailQuestions(): FormsQuestion[] {
			const questions = this.form?.questions || []
			return questions.filter(
				(question: FormsQuestion) =>
					question.type === 'short'
					&& question.extraSettings?.validationType === 'email',
			)
		},

		selectedConfirmationEmailQuestion(): FormsQuestion | null {
			const selectedQuestion = this.confirmationEmailQuestions.find(
				(question: FormsQuestion) =>
					question.id === this.form.confirmationEmailQuestionId,
			)
			if (selectedQuestion) {
				return selectedQuestion
			}

			if (
				this.form.confirmationEmailQuestionId === null
				&& this.emailQuestionCount === 1
			) {
				return this.confirmationEmailQuestions[0]
			}

			return null
		},

		selectedConfirmationEmailQuestionId(): number | string {
			return (
				this.form.confirmationEmailQuestionId
				?? this.selectedConfirmationEmailQuestion?.id
				?? ''
			)
		},

		confirmationEmailQuestionOptions(): ConfirmationEmailQuestionOption[] {
			return this.confirmationEmailQuestions.map((question) => ({
				id: question.id,
				label: this.confirmationEmailQuestionLabel(question),
			}))
		},

		selectedConfirmationEmailQuestionOption(): ConfirmationEmailQuestionOption | null {
			return (
				this.confirmationEmailQuestionOptions.find(
					(question) =>
						question.id === this.selectedConfirmationEmailQuestionId,
				) || null
			)
		},

		confirmationEmailErrorText(): string {
			if (this.emailQuestionCount === 0) {
				return t(
					'forms',
					'Add at least one email field before confirmation emails can be used.',
				)
			}

			if (this.requiresConfirmationEmailQuestionIdSelection) {
				return t(
					'forms',
					'Select which email field should receive confirmation emails before finishing this setup.',
				)
			}

			return ''
		},

		confirmationEmailNoteCardType(): 'warning' | 'info' {
			if (this.requiresConfirmationEmailQuestionIdSelection) {
				return 'warning'
			}
			return 'info'
		},

		requiresConfirmationEmailQuestionIdSelection(): boolean {
			return (
				this.emailQuestionCount > 1
				&& !this.selectedConfirmationEmailQuestion
			)
		},

		isConfirmationEmailConfigurationBlocked(): boolean {
			return (
				this.form.confirmationEmailEnabled
				&& (this.emailQuestionCount === 0
					|| this.requiresConfirmationEmailQuestionIdSelection)
			)
		},
	},

	watch: {
		'form.confirmationEmailSubject': {
			handler(val: string | null | undefined) {
				this.confirmationEmailSubject = val || ''
			},

			immediate: true,
		},

		'form.confirmationEmailBody': {
			handler(val: string | null | undefined) {
				this.confirmationEmailBody = val || ''
			},

			immediate: true,
		},

		confirmationEmailQuestions: {
			handler() {
				const selectedRecipientId = this.form.confirmationEmailQuestionId
				const hasValidSelectedRecipient =
					selectedRecipientId !== null
					&& this.confirmationEmailQuestions.some(
						(question) => question.id === selectedRecipientId,
					)

				if (selectedRecipientId !== null && !hasValidSelectedRecipient) {
					if (this.emailQuestionCount === 1) {
						this.saveConfirmationEmailQuestionId(
							this.confirmationEmailQuestions[0].id,
						)
					} else {
						this.saveConfirmationEmailQuestionId(null)
					}
					return
				}

				if (
					this.form.confirmationEmailEnabled
					&& this.emailQuestionCount === 1
					&& this.form.confirmationEmailQuestionId === null
				) {
					this.saveConfirmationEmailQuestionId(
						this.confirmationEmailQuestions[0].id,
					)
				}
			},

			deep: true,
		},
	},

	methods: {
		confirmationEmailQuestionLabel(question: FormsQuestion): string {
			return question.text || t('forms', 'Untitled question')
		},

		/**
		 * Save Form-Properties
		 *
		 * @param checked New Checkbox/Switch Value to use
		 */
		onAnonChange(checked: boolean): void {
			this.$emit('update:formProp', 'isAnonymous', checked)
		},

		onSubmitMultipleChange(checked: boolean): void {
			this.$emit('update:formProp', 'submitMultiple', checked)
		},

		onAllowEditSubmissionsChange(checked: boolean): void {
			this.$emit('update:formProp', 'allowEditSubmissions', checked)
		},

		onAllowCommentsChange(checked: boolean): void {
			this.$emit('update:formProp', 'allowComments', checked)
		},

		onFormExpiresChange(checked: boolean): void {
			if (checked) {
				this.$emit(
					'update:formProp',
					'expires',
					moment().add(1, 'hour').unix(),
				) // Expires in one hour.
			} else {
				this.$emit('update:formProp', 'expires', 0)
			}
		},

		onShowExpirationChange(checked: boolean): void {
			this.$emit('update:formProp', 'showExpiration', checked)
		},

		/**
		 * On date picker change
		 *
		 * @param datetime the expiration Date
		 */
		onExpirationDateChange(datetime: Date | [Date, Date] | null): void {
			if (!(datetime instanceof Date)) {
				return
			}
			this.$emit(
				'update:formProp',
				'expires',
				parseInt(moment(datetime).format('X')),
			)
		},

		onMaxSubmissionsChange(checked: boolean): void {
			this.$emit('update:formProp', 'maxSubmissions', checked ? 1 : null)
		},

		onMaxSubmissionsValueChange(value: string | number): void {
			const parsedValue = Number(value)
			if (parsedValue > 0) {
				this.$emit('update:formProp', 'maxSubmissions', parsedValue)
			}
		},

		onFormClosedChange(isClosed: boolean): void {
			this.$emit(
				'update:formProp',
				'state',
				isClosed ? FormState.FormClosed : FormState.FormActive,
			)
		},

		onFormLockChange(locked: boolean): void {
			this.$emit('update:formProp', 'lockedUntil', locked ? 0 : null)
		},

		onFormArchivedChange(isArchived: boolean): void {
			this.$emit(
				'update:formProp',
				'state',
				isArchived ? FormState.FormArchived : FormState.FormClosed,
			)
		},

		onSubmissionMessageChange(event: Event): void {
			this.$emit(
				'update:formProp',
				'submissionMessage',
				(event.target as HTMLTextAreaElement).value,
			)
		},

		/**
		 * Enable or disable the whole custom submission message
		 * Disabled means the value is set to null.
		 */
		onUpdateHasCustomSubmissionMessage(): void {
			if (this.hasCustomSubmissionMessage) {
				this.$emit('update:formProp', 'submissionMessage', null)
			} else {
				this.$emit('update:formProp', 'submissionMessage', '')
			}
		},

		onConfirmationEmailEnabledChange(checked: boolean): void {
			if (
				checked
				&& this.form.confirmationEmailQuestionId === null
				&& this.emailQuestionCount === 1
			) {
				this.saveConfirmationEmailQuestionId(
					this.confirmationEmailQuestions[0].id,
				)
			}

			this.$emit('update:formProp', 'confirmationEmailEnabled', checked)
		},

		onConfirmationEmailSubjectChange(): void {
			this.$emit(
				'update:formProp',
				'confirmationEmailSubject',
				this.confirmationEmailSubject,
			)
		},

		onConfirmationEmailBodyChange(): void {
			this.$emit(
				'update:formProp',
				'confirmationEmailBody',
				this.confirmationEmailBody,
			)
		},

		onConfirmationEmailQuestionIdSelectionChange(
			option: ConfirmationEmailQuestionOption | null,
		): void {
			const questionId = option?.id ?? null
			if (questionId === null) {
				return
			}

			this.saveConfirmationEmailQuestionId(questionId)
		},

		saveConfirmationEmailQuestionId(selectedQuestionId: number | null): void {
			if (this.form.confirmationEmailQuestionId === selectedQuestionId) {
				return
			}

			this.$emit(
				'update:formProp',
				'confirmationEmailQuestionId',
				selectedQuestionId,
			)
		},

		/**
		 * Datepicker timestamp to string
		 *
		 * @param datetime the datepicker Date
		 * @return
		 */
		stringifyDate(datetime: Date): string {
			const date = moment(datetime).format('LLL')

			if (this.isExpired) {
				return t('forms', 'Expired on {date}', { date })
			}
			return t('forms', 'Expires on {date}', { date })
		},

		/**
		 * Form expires timestamp to Date of the datepicker
		 *
		 * @param value the expires timestamp
		 * @return
		 */
		parseTimestampToDate(value: number): Date {
			return moment(value, 'X').toDate()
		},

		/**
		 * Prevent selecting a day before today
		 *
		 * @param datetime the datepicker Date
		 * @return
		 */
		notBeforeToday(datetime: Date): boolean {
			return datetime < moment().add(-1, 'day').toDate()
		},

		/**
		 * Prevent selecting a time before the current one
		 *
		 * @param datetime the datepicker Date
		 * @return
		 */
		notBeforeNow(datetime: Date): boolean {
			return datetime < moment().toDate()
		},
	},
})
</script>

<style lang="scss" scoped>
@use '../../scssmixins/markdownOutput.scss' as *;

#expiresDatetimePicker {
	width: calc(100% - var(--default-clickable-area));
}

.settings-div--indent {
	margin-inline-start: 40px;
}

.settings-hint {
	color: var(--color-text-maxcontrast);
	padding-inline-start: 16px;
}

.sidebar-tabs__content {
	display: flex;
	flex-direction: column;
}

.submission-message {
	&__description {
		color: var(--color-text-maxcontrast);
		font-size: 13px;
	}

	&__input,
	&__output {
		width: 100%;
		min-height: 100px;
		line-height: 24px;
	}

	&__output {
		@include markdown-output;
		padding: 12px;
		margin-block: 3px;
		border: 2px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius-large);

		&:hover {
			border-color: var(--color-primary-element);
		}
	}
}

.confirmation-email {
	&__recipient {
		margin-bottom: calc(var(--default-grid-baseline) * 3);
	}

	&__label {
		display: block;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		margin-bottom: var(--default-grid-baseline);
		font-weight: 600;
		color: var(--color-text-maxcontrast);
	}

	&__placeholder-hint {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small);
		margin-top: calc(var(--default-grid-baseline) * 2);
	}

	&__select {
		width: 100%;

		// NcSelect sets min-width: 260px with two-class specificity; double
		// our class to win the cascade without !important.
		&#{&} {
			min-width: 0;
		}
	}

	&__input,
	&__textarea {
		width: 100%;
		margin-top: calc(var(--default-grid-baseline) * 3);
	}
}
</style>
