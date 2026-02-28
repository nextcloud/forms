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
			:model-value="form.isAnonymous"
			:disabled="formArchived || locked"
			type="switch"
			@update:model-value="onAnonChange">
			<!-- TRANSLATORS Checkbox to select whether responses will be stored anonymously or not -->
			{{ t('forms', 'Store responses anonymously') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:title="disableSubmitMultipleExplanation"
			:model-value="submitMultiple"
			:disabled="disableSubmitMultiple || formArchived || locked"
			type="switch"
			@update:model-value="onSubmitMultipleChange">
			{{ t('forms', 'Allow multiple responses per person') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:model-value="form.allowEditSubmissions"
			:disabled="formArchived || locked"
			type="switch"
			@update:model-value="onAllowEditSubmissionsChange">
			{{ t('forms', 'Allow editing own responses') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:model-value="formExpires"
			:disabled="formArchived || locked"
			type="switch"
			@update:model-value="onFormExpiresChange">
			{{ t('forms', 'Set expiration date') }}
		</NcCheckboxRadioSwitch>
		<div v-show="formExpires && !formArchived" class="settings-div--indent">
			<NcDateTimePicker
				id="expiresDatetimePicker"
				:clearable="false"
				:disabled="locked"
				:disabled-date="notBeforeToday"
				:disabled-time="notBeforeNow"
				:editable="false"
				:format="stringifyDate"
				:minute-step="5"
				:show-second="false"
				:model-value="expirationDate"
				type="datetime"
				@change="onExpirationDateChange" />
			<NcCheckboxRadioSwitch
				:model-value="form.showExpiration"
				:disabled="locked"
				type="switch"
				@update:model-value="onShowExpirationChange">
				{{ t('forms', 'Show expiration date on form') }}
			</NcCheckboxRadioSwitch>
		</div>
		<NcCheckboxRadioSwitch
			:model-value="formClosed"
			:disabled="formArchived || locked"
			aria-describedby="forms-settings__close-form"
			type="switch"
			@update:model-value="onFormClosedChange">
			{{ t('forms', 'Close form') }}
		</NcCheckboxRadioSwitch>
		<p id="forms-settings__close-form" class="settings-hint">
			{{ t('forms', 'Closed forms do not accept new submissions.') }}
		</p>
		<NcCheckboxRadioSwitch
			:model-value="isFormLockedPermanently"
			:disabled="
				formArchived
				|| (locked && form.lockedUntil !== 0)
				|| !isCurrentUserOwner
			"
			type="switch"
			@update:model-value="onFormLockChange">
			{{ t('forms', 'Lock form permanently') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			:model-value="formArchived"
			aria-describedby="forms-settings__archive-form"
			:disabled="locked || !isCurrentUserOwner"
			type="switch"
			@update:model-value="onFormArchivedChange">
			{{ t('forms', 'Archive form') }}
		</NcCheckboxRadioSwitch>
		<p id="forms-settings__archive-form" class="settings-hint">
			{{
				t(
					'forms',
					'Archived forms do not accept new submissions and can not be modified.',
				)
			}}
		</p>
		<NcCheckboxRadioSwitch
			:model-value="hasCustomSubmissionMessage"
			:disabled="formArchived || locked"
			type="switch"
			@update:model-value="onUpdateHasCustomSubmissionMessage">
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

		<NcCheckboxRadioSwitch
			:model-value="form.confirmationEmailEnabled"
			:disabled="formArchived || locked"
			type="switch"
			@update:model-value="onConfirmationEmailEnabledChange">
			{{ t('forms', 'Send confirmation email to respondents') }}
		</NcCheckboxRadioSwitch>
		<div
			v-show="form.confirmationEmailEnabled && !formArchived"
			class="settings-div--indent confirmation-email">
			<p class="confirmation-email__hint">
				{{
					t(
						'forms',
						'Requires an email field in the form. Available placeholders: {formTitle}, {formDescription}, and field names like {name}.',
					)
				}}
			</p>
			<NcNoteCard
				v-if="form.confirmationEmailEnabled && emailQuestionCount === 0"
				type="error"
				:text="
					t(
						'forms',
						'Add at least one email field to send confirmation emails.',
					)
				" />
			<NcNoteCard
				v-else-if="form.confirmationEmailEnabled && emailQuestionCount > 1"
				type="info"
				:text="
					t(
						'forms',
						'Multiple email fields found. The first email field in the form order will be used.',
					)
				" />
			<label class="confirmation-email__label">
				{{ t('forms', 'Email subject') }}
			</label>
			<input
				v-model="confirmationEmailSubject"
				:disabled="locked"
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
				:disabled="locked"
				:placeholder="t('forms', 'Thank you for submitting the form.')"
				class="confirmation-email__textarea"
				@input="onConfirmationEmailBodyInput"
				@blur="onConfirmationEmailBodyChange"></textarea>
		</div>

		<TransferOwnership
			:locked="locked"
			:is-owner="isCurrentUserOwner"
			:form="form" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'
import { directive as ClickOutside } from 'v-click-outside'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import TransferOwnership from './TransferOwnership.vue'
import svgLockOpen from '../../../img/lock_open.svg?raw'
import ShareTypes from '../../mixins/ShareTypes.js'
import { FormState } from '../../models/Constants.ts'

export default {
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDateTimePicker,
		NcIconSvgWrapper,
		NcNoteCard,
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

	emits: ['update:form-prop'],

	data() {
		return {
			formatter: {
				stringify: this.stringifyDate,
				parse: this.parseTimestampToDate,
			},

			maxStringLengths: loadState('forms', 'maxStringLengths'),
			/** If custom submission message is shown as input or rendered markdown */
			editMessage: false,
			svgLockOpen,
			confirmationEmailSubject: this.form?.confirmationEmailSubject || '',
			confirmationEmailBody: this.form?.confirmationEmailBody || '',
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
			const questions = this.form?.questions || []
			return questions.filter(
				(question) =>
					question.type === 'short'
					&& question.extraSettings?.validationType === 'email',
			).length
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
	},

	methods: {
		/**
		 * Save Form-Properties
		 *
		 * @param {boolean} checked New Checkbox/Switch Value to use
		 */
		onAnonChange(checked) {
			this.$emit('update:form-prop', 'isAnonymous', checked)
		},

		onSubmitMultipleChange(checked) {
			this.$emit('update:form-prop', 'submitMultiple', checked)
		},

		onAllowEditSubmissionsChange(checked) {
			this.$emit('update:form-prop', 'allowEditSubmissions', checked)
		},

		onFormExpiresChange(checked) {
			if (checked) {
				this.$emit(
					'update:form-prop',
					'expires',
					moment().add(1, 'hour').unix(),
				) // Expires in one hour.
			} else {
				this.$emit('update:form-prop', 'expires', 0)
			}
		},

		onShowExpirationChange(checked) {
			this.$emit('update:form-prop', 'showExpiration', checked)
		},

		/**
		 * On date picker change
		 *
		 * @param {Date} datetime the expiration Date
		 */
		onExpirationDateChange(datetime) {
			this.$emit(
				'update:form-prop',
				'expires',
				parseInt(moment(datetime).format('X')),
			)
		},

		onFormClosedChange(isClosed) {
			this.$emit(
				'update:form-prop',
				'state',
				isClosed ? FormState.FormClosed : FormState.FormActive,
			)
		},

		onFormLockChange(locked) {
			this.$emit('update:form-prop', 'lockedUntil', locked ? 0 : null)
		},

		onFormArchivedChange(isArchived) {
			this.$emit(
				'update:form-prop',
				'state',
				isArchived ? FormState.FormArchived : FormState.FormClosed,
			)
		},

		onSubmissionMessageChange({ target }) {
			this.$emit('update:form-prop', 'submissionMessage', target.value)
		},

		/**
		 * Enable or disable the whole custom submission message
		 * Disabled means the value is set to null.
		 */
		onUpdateHasCustomSubmissionMessage() {
			if (this.hasCustomSubmissionMessage) {
				this.$emit('update:form-prop', 'submissionMessage', null)
			} else {
				this.$emit('update:form-prop', 'submissionMessage', '')
			}
		},

		onConfirmationEmailEnabledChange(checked) {
			this.$emit('update:form-prop', 'confirmationEmailEnabled', checked)
		},

		onConfirmationEmailSubjectChange() {
			this.$emit(
				'update:form-prop',
				'confirmationEmailSubject',
				this.confirmationEmailSubject,
			)
		},

		onConfirmationEmailBodyInput(event) {
			this.confirmationEmailBody = event.target.value
		},

		onConfirmationEmailBodyChange({ target }) {
			this.$emit('update:form-prop', 'confirmationEmailBody', target.value)
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
		@import '../../scssmixins/markdownOutput';

		padding: 12px;
		margin-block: 3px;
		border: 2px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius-large);

		&:hover {
			border-color: var(--color-primary-element);
		}

		@include markdown-output;
	}
}

.confirmation-email {
	&__hint {
		color: var(--color-text-maxcontrast);
		font-size: 13px;
		margin-bottom: 12px;
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
