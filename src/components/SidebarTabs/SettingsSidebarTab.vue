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
			:checked="form.isAnonymous"
			:disabled="formArchived || locked"
			type="switch"
			@update:checked="onAnonChange">
			<!-- TRANSLATORS Checkbox to select whether responses will be stored anonymously or not -->
			{{ t('forms', 'Store responses anonymously') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			v-tooltip="disableSubmitMultipleExplanation"
			:checked="submitMultiple"
			:disabled="disableSubmitMultiple || formArchived || locked"
			type="switch"
			@update:checked="onSubmitMultipleChange">
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
			:checked="formExpires"
			:disabled="formArchived || locked"
			type="switch"
			@update:checked="onFormExpiresChange">
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
				:formatter="formatter"
				:minute-step="5"
				:show-second="false"
				:value="expirationDate"
				type="datetime"
				@change="onExpirationDateChange" />
			<NcCheckboxRadioSwitch
				:checked="form.showExpiration"
				:disabled="locked"
				type="switch"
				@update:checked="onShowExpirationChange">
				{{ t('forms', 'Show expiration date on form') }}
			</NcCheckboxRadioSwitch>
		</div>
		<NcCheckboxRadioSwitch
			:checked="formClosed"
			:disabled="formArchived || locked"
			aria-describedby="forms-settings__close-form"
			type="switch"
			@update:checked="onFormClosedChange">
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
			:checked="formArchived"
			aria-describedby="forms-settings__archive-form"
			:disabled="locked"
			type="switch"
			@update:checked="onFormArchivedChange">
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
			:checked="hasCustomSubmissionMessage"
			:disabled="formArchived || locked"
			type="switch"
			@update:checked="onUpdateHasCustomSubmissionMessage">
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

		<TransferOwnership :locked="locked" :form="form" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import moment from '@nextcloud/moment'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import ShareTypes from '../../mixins/ShareTypes.js'
import TransferOwnership from './TransferOwnership.vue'

import { directive as ClickOutside } from 'v-click-outside'
import { loadState } from '@nextcloud/initial-state'
import { FormState } from '../../models/Constants.ts'
import svgLockOpen from '../../../img/lock_open.svg?raw'

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
	},

	methods: {
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
</style>
