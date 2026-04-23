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
				v-model="maxSubmissionsValue"
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
				<p class="confirmation-email__hint">
					{{ t('forms', 'Set up confirmation emails in three steps:') }}
				</p>
				<ol class="confirmation-email__steps">
					<li>{{ t('forms', 'Add an email field to the form.') }}</li>
					<li>
						{{
							t(
								'forms',
								'Select which email field is used for confirmation emails.',
							)
						}}
					</li>
					<li>{{ t('forms', 'Customize the subject and message.') }}</li>
				</ol>
				<NcNoteCard
					v-if="confirmationEmailErrorText"
					type="error"
					:text="confirmationEmailErrorText" />
				<div
					v-if="emailQuestionCount > 0"
					class="confirmation-email__recipient">
					<label class="confirmation-email__label">
						{{ t('forms', 'Recipient field') }}
					</label>
					<p
						v-if="emailQuestionCount === 1"
						class="confirmation-email__recipient-summary">
						<strong>{{ selectedConfirmationEmailQuestionLabel }}</strong>
						<br />
						{{
							t(
								'forms',
								'Selected automatically because this is the only email field in the form.',
							)
						}}
					</p>
					<template v-else>
						<NcSelect
							:modelValue="selectedConfirmationEmailQuestionOption"
							:disabled="locked || isSavingConfirmationEmailRecipient"
							:options="confirmationEmailQuestionOptions"
							:placeholder="t('forms', 'Select an email field')"
							class="confirmation-email__select"
							label="label"
							:searchable="false"
							:clearable="false"
							trackBy="id"
							@update:modelValue="
								onConfirmationEmailRecipientSelectionChange
							" />
						<p
							v-if="selectedConfirmationEmailQuestionLabel"
							class="confirmation-email__recipient-summary">
							{{
								t('forms', 'Current recipient field: {question}', {
									question: selectedConfirmationEmailQuestionLabel,
								})
							}}
						</p>
					</template>
				</div>
				<p class="confirmation-email__placeholder-hint">
					{{
						t(
							'forms',
							'Available placeholders: {formTitle}, {formDescription}, and field names like {name}.',
						)
					}}
				</p>
				<label class="confirmation-email__label">
					{{ t('forms', 'Email subject') }}
				</label>
				<input
					v-model="confirmationEmailSubject"
					:disabled="locked || isConfirmationEmailConfigurationBlocked"
					:maxlength="255"
					:placeholder="t('forms', 'Thank you for your submission')"
					class="confirmation-email__input"
					type="text"
					@blur="onConfirmationEmailSubjectChange" />
				<label class="confirmation-email__label">
					{{ t('forms', 'Email body') }}
				</label>
				<textarea
					:value="confirmationEmailBody"
					:disabled="locked || isConfirmationEmailConfigurationBlocked"
					:placeholder="emailBodyPlaceholder"
					class="confirmation-email__textarea"
					@input="onConfirmationEmailBodyInput"
					@blur="onConfirmationEmailBodyChange"></textarea>
			</div>
		</template>
		<NcNoteCard
			v-else
			type="info"
			:text="
				t('forms', 'Confirmation emails are disabled by your administrator.')
			" />

		<TransferOwnership
			:locked="locked"
			:isOwner="isCurrentUserOwner"
			:form="form" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { vOnClickOutside as ClickOutside } from '@vueuse/components'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import TransferOwnership from './TransferOwnership.vue'
import svgLockOpen from '../../../img/lock_open.svg?raw'
import ShareTypes from '../../mixins/ShareTypes.js'
import { FormState } from '../../models/Constants.ts'

export default {
	components: {
		NcButton,
		NcInputField,
		NcCheckboxRadioSwitch,
		NcDateTimePicker,
		NcIconSvgWrapper,
		NcNoteCard,
		NcSelect,
		TransferOwnership,
	},

	directives: {
		ClickOutside,
	},

	mixins: [ShareTypes],

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

	data() {
		return {
			formatter: {
				stringify: this.stringifyDate,
				parse: this.parseTimestampToDate,
			},

			appConfig: loadState('forms', 'appConfig'),
			maxStringLengths: loadState('forms', 'maxStringLengths'),
			/** If custom submission message is shown as input or rendered markdown */
			editMessage: false,
			svgLockOpen,
			confirmationEmailSubject: this.form?.confirmationEmailSubject || '',
			confirmationEmailBody: this.form?.confirmationEmailBody || '',
			isSavingConfirmationEmailRecipient: false,
		}
	},

	computed: {
		isCurrentUserOwner() {
			return getCurrentUser().uid === this.form.ownerId
		},

		isFormLockedPermanently() {
			return this.locked && this.form.lockedUntil === 0
		},

		/**
		 * If the form has a custom submission message or the user wants to add one (settings switch)
		 */
		hasCustomSubmissionMessage() {
			return (
				this.form?.submissionMessage !== undefined
				&& this.form?.submissionMessage !== null
			)
		},

		/**
		 * Submit Multiple is disabled, if it cannot be controlled.
		 */
		disableSubmitMultiple() {
			return this.hasPublicLink || this.form.isAnonymous
		},

		disableSubmitMultipleExplanation() {
			if (this.disableSubmitMultiple) {
				return t(
					'forms',
					'This can not be controlled, if the form has a public link or stores responses anonymously.',
				)
			}
			return ''
		},

		hasPublicLink() {
			return (
				this.form.shares.filter(
					(share) => share.shareType === this.SHARE_TYPES.SHARE_TYPE_LINK,
				).length !== 0
			)
		},

		// If disabled, submitMultiple will be casted to true
		submitMultiple() {
			return this.disableSubmitMultiple || this.form.submitMultiple
		},

		formExpires() {
			return this.form.expires !== 0
		},

		formArchived() {
			return this.form.state === FormState.FormArchived
		},

		formClosed() {
			return this.form.state !== FormState.FormActive
		},

		hasMaxSubmissions() {
			return (
				this.form.maxSubmissions !== null
				&& this.form.maxSubmissions !== undefined
			)
		},

		maxSubmissionsValue: {
			get() {
				return this.form.maxSubmissions ?? 1
			},

			set(value) {
				this.$emit('update:formProp', 'maxSubmissions', value)
			},
		},

		isExpired() {
			return this.form.expires && moment().unix() > this.form.expires
		},

		expirationDate() {
			return moment(this.form.expires, 'X').toDate()
		},

		/**
		 * The submission message rendered as HTML
		 */
		submissionMessageHTML() {
			return this.$markdownit.render(this.form.submissionMessage || '')
		},

		/**
		 * Placeholder text for email body
		 */
		emailBodyPlaceholder() {
			return this.t(
				'forms',
				'Thank you for submitting the form "{formTitle}".',
				{ formTitle: this.form.title || '' },
			)
		},

		emailQuestionCount() {
			return this.confirmationEmailQuestions.length
		},

		confirmationEmailQuestions() {
			const questions = this.form?.questions || []
			return questions.filter(
				(question) =>
					question.type === 'short'
					&& question.extraSettings?.validationType === 'email',
			)
		},

		confirmationEmailRecipientCount() {
			return this.confirmationEmailQuestions.filter(
				(question) => question.extraSettings?.confirmationEmailRecipient,
			).length
		},

		selectedConfirmationEmailQuestion() {
			const selectedQuestion = this.confirmationEmailQuestions.find(
				(question) => question.extraSettings?.confirmationEmailRecipient,
			)
			if (selectedQuestion) {
				return selectedQuestion
			}

			if (this.emailQuestionCount === 1) {
				return this.confirmationEmailQuestions[0]
			}

			return null
		},

		selectedConfirmationEmailQuestionId() {
			return this.selectedConfirmationEmailQuestion?.id ?? ''
		},

		confirmationEmailQuestionOptions() {
			return this.confirmationEmailQuestions.map((question) => ({
				id: question.id,
				label: this.confirmationEmailQuestionLabel(question),
			}))
		},

		selectedConfirmationEmailQuestionOption() {
			return (
				this.confirmationEmailQuestionOptions.find(
					(question) =>
						question.id === this.selectedConfirmationEmailQuestionId,
				) || null
			)
		},

		selectedConfirmationEmailQuestionLabel() {
			if (!this.selectedConfirmationEmailQuestion) {
				return ''
			}

			return this.confirmationEmailQuestionLabel(
				this.selectedConfirmationEmailQuestion,
			)
		},

		hasConfirmationEmailRecipientConflict() {
			return this.confirmationEmailRecipientCount > 1
		},

		confirmationEmailErrorText() {
			if (this.hasConfirmationEmailRecipientConflict) {
				return t(
					'forms',
					'Only one email field can be used for confirmation emails. Select the recipient field below to fix this.',
				)
			}

			if (this.emailQuestionCount === 0) {
				return t(
					'forms',
					'Add at least one email field before confirmation emails can be used.',
				)
			}

			if (this.requiresConfirmationEmailRecipientSelection) {
				return t(
					'forms',
					'Select which email field should receive confirmation emails before finishing this setup.',
				)
			}

			return ''
		},

		requiresConfirmationEmailRecipientSelection() {
			return (
				this.emailQuestionCount > 1
				&& this.confirmationEmailRecipientCount !== 1
			)
		},

		isConfirmationEmailConfigurationBlocked() {
			return (
				this.form.confirmationEmailEnabled
				&& (this.emailQuestionCount === 0
					|| this.hasConfirmationEmailRecipientConflict
					|| this.requiresConfirmationEmailRecipientSelection)
			)
		},
	},

	watch: {
		form: {
			handler(newForm) {
				this.confirmationEmailSubject =
					newForm?.confirmationEmailSubject || ''
				this.confirmationEmailBody = newForm?.confirmationEmailBody || ''
			},

			deep: true,
		},

		confirmationEmailQuestions: {
			async handler() {
				if (
					this.form.confirmationEmailEnabled
					&& this.emailQuestionCount === 1
					&& this.confirmationEmailRecipientCount !== 1
				) {
					await this.saveConfirmationEmailRecipient(
						this.confirmationEmailQuestions[0].id,
					)
				}
			},

			deep: true,
		},
	},

	methods: {
		confirmationEmailQuestionLabel(question) {
			return question.text || t('forms', 'Untitled question')
		},

		/**
		 * Save Form-Properties
		 *
		 * @param {boolean} checked New Checkbox/Switch Value to use
		 */
		onAnonChange(checked) {
			this.$emit('update:formProp', 'isAnonymous', checked)
		},

		onSubmitMultipleChange(checked) {
			this.$emit('update:formProp', 'submitMultiple', checked)
		},

		onAllowEditSubmissionsChange(checked) {
			this.$emit('update:formProp', 'allowEditSubmissions', checked)
		},

		onFormExpiresChange(checked) {
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

		onShowExpirationChange(checked) {
			this.$emit('update:formProp', 'showExpiration', checked)
		},

		/**
		 * On date picker change
		 *
		 * @param {Date} datetime the expiration Date
		 */
		onExpirationDateChange(datetime) {
			this.$emit(
				'update:formProp',
				'expires',
				parseInt(moment(datetime).format('X')),
			)
		},

		onMaxSubmissionsChange(checked) {
			this.$emit('update:formProp', 'maxSubmissions', checked ? 1 : null)
		},

		onMaxSubmissionsValueChange(value) {
			if (value > 0) {
				this.$emit('update:formProp', 'maxSubmissions', value)
			}
		},

		onFormClosedChange(isClosed) {
			this.$emit(
				'update:formProp',
				'state',
				isClosed ? FormState.FormClosed : FormState.FormActive,
			)
		},

		onFormLockChange(locked) {
			this.$emit('update:formProp', 'lockedUntil', locked ? 0 : null)
		},

		onFormArchivedChange(isArchived) {
			this.$emit(
				'update:formProp',
				'state',
				isArchived ? FormState.FormArchived : FormState.FormClosed,
			)
		},

		onSubmissionMessageChange({ target }) {
			this.$emit('update:formProp', 'submissionMessage', target.value)
		},

		/**
		 * Enable or disable the whole custom submission message
		 * Disabled means the value is set to null.
		 */
		onUpdateHasCustomSubmissionMessage() {
			if (this.hasCustomSubmissionMessage) {
				this.$emit('update:formProp', 'submissionMessage', null)
			} else {
				this.$emit('update:formProp', 'submissionMessage', '')
			}
		},

		onConfirmationEmailEnabledChange(checked) {
			this.$emit('update:formProp', 'confirmationEmailEnabled', checked)
		},

		onConfirmationEmailSubjectChange() {
			this.$emit(
				'update:formProp',
				'confirmationEmailSubject',
				this.confirmationEmailSubject,
			)
		},

		onConfirmationEmailBodyInput(event) {
			this.confirmationEmailBody = event.target.value
		},

		onConfirmationEmailBodyChange({ target }) {
			this.$emit('update:formProp', 'confirmationEmailBody', target.value)
		},

		async onConfirmationEmailRecipientSelectionChange(option) {
			const questionId = option?.id
			if (!questionId) {
				return
			}

			await this.saveConfirmationEmailRecipient(questionId)
		},

		async saveConfirmationEmailRecipient(selectedQuestionId) {
			if (!this.form?.id) {
				return
			}

			const emailQuestions = this.confirmationEmailQuestions
			const pendingUpdates = emailQuestions
				.map((question) => {
					const nextExtraSettings = { ...(question.extraSettings || {}) }
					if (selectedQuestionId === question.id) {
						nextExtraSettings.confirmationEmailRecipient = true
					} else {
						delete nextExtraSettings.confirmationEmailRecipient
					}

					const hasRecipientFlag =
						!!question.extraSettings?.confirmationEmailRecipient
					const shouldHaveRecipientFlag =
						selectedQuestionId === question.id

					if (hasRecipientFlag === shouldHaveRecipientFlag) {
						return null
					}

					return {
						question,
						nextExtraSettings,
					}
				})
				.filter((update) => update !== null)

			if (pendingUpdates.length === 0) {
				return
			}

			const previousExtraSettings = new Map(
				pendingUpdates.map(({ question }) => [
					question.id,
					{ ...(question.extraSettings || {}) },
				]),
			)

			pendingUpdates.forEach(({ question, nextExtraSettings }) => {
				const localQuestion = this.form.questions.find(
					(searchQuestion) => searchQuestion.id === question.id,
				)
				if (localQuestion) {
					localQuestion.extraSettings = nextExtraSettings
				}
			})

			this.isSavingConfirmationEmailRecipient = true

			try {
				await Promise.all(
					pendingUpdates.map(({ question, nextExtraSettings }) =>
						axios.patch(
							generateOcsUrl(
								'apps/forms/api/v3/forms/{id}/questions/{questionId}',
								{
									id: this.form.id,
									questionId: question.id,
								},
							),
							{
								keyValuePairs: {
									extraSettings: nextExtraSettings,
								},
							},
						),
					),
				)
				emit('forms:last-updated:set', this.form.id)
			} catch {
				pendingUpdates.forEach(({ question }) => {
					const localQuestion = this.form.questions.find(
						(searchQuestion) => searchQuestion.id === question.id,
					)
					if (localQuestion) {
						localQuestion.extraSettings =
							previousExtraSettings.get(question.id) || {}
					}
				})
				showError(t('forms', 'Error while saving question'))
			} finally {
				this.isSavingConfirmationEmailRecipient = false
			}
		},

		/**
		 * Datepicker timestamp to string
		 *
		 * @param {Date} datetime the datepicker Date
		 * @return {string}
		 */
		stringifyDate(datetime) {
			const date = moment(datetime).format('LLL')

			if (this.isExpired) {
				return t('forms', 'Expired on {date}', { date })
			}
			return t('forms', 'Expires on {date}', { date })
		},

		/**
		 * Form expires timestamp to Date of the datepicker
		 *
		 * @param {number} value the expires timestamp
		 * @return {Date}
		 */
		parseTimestampToDate(value) {
			return moment(value, 'X').toDate()
		},

		/**
		 * Prevent selecting a day before today
		 *
		 * @param {Date} datetime the datepicker Date
		 * @return {boolean}
		 */
		notBeforeToday(datetime) {
			return datetime < moment().add(-1, 'day').toDate()
		},

		/**
		 * Prevent selecting a time before the current one
		 *
		 * @param {Date} datetime the datepicker Date
		 * @return {boolean}
		 */
		notBeforeNow(datetime) {
			return datetime < moment().toDate()
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../../scssmixins/markdownOutput' as *;

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
	&__hint {
		color: var(--color-text-maxcontrast);
		font-size: 13px;
		margin-bottom: 8px;
	}

	&__steps {
		margin: 0 0 12px;
		padding-inline-start: 20px;
		color: var(--color-text-maxcontrast);
		font-size: 13px;
	}

	&__recipient {
		margin-bottom: 12px;
	}

	&__recipient-summary,
	&__placeholder-hint {
		color: var(--color-text-maxcontrast);
		font-size: 13px;
		margin-top: 8px;
	}

	&__label {
		display: block;
		margin-top: 12px;
		margin-bottom: 4px;
		font-weight: 600;
	}

	&__input {
		width: 100%;
		padding: 8px;
		margin-bottom: 12px;
		border: 2px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius-large);
		font-size: 14px;

		&:focus {
			outline: none;
			border-color: var(--color-primary-element);
		}
	}

	&__select {
		width: 100%;
	}

	&__textarea {
		width: 100%;
		min-height: 120px;
		padding: 8px;
		border: 2px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius-large);
		font-size: 14px;
		line-height: 1.5;
		resize: vertical;

		&:focus {
			outline: none;
			border-color: var(--color-primary-element);
		}
	}
}
</style>
