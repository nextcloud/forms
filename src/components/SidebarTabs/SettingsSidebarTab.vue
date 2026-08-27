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
import { computed, defineComponent, inject, ref, watch } from 'vue'
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

	setup(props, { emit }) {
		const { SHARE_TYPES } = useShareTypes()
		const appConfig = ref<SettingsAppConfig>(
			loadState(formsAppName, 'appConfig') as SettingsAppConfig,
		)
		const maxStringLengths = ref<Record<string, number>>(
			loadState(formsAppName, 'maxStringLengths'),
		)
		const editMessage = ref(false)
		const confirmationEmailSubject = ref('')
		const confirmationEmailBody = ref('')
		const isCurrentUserOwner = computed(
			() => getCurrentUser()?.uid === props.form.ownerId,
		)
		const isFormLockedPermanently = computed(
			() => props.locked && props.form.lockedUntil === 0,
		)

		/**
		 * If the form has a custom submission message or the user wants to add one (settings switch)
		 */
		const hasCustomSubmissionMessage = computed(
			() =>
				props.form?.submissionMessage !== undefined
				&& props.form?.submissionMessage !== null,
		)
		const hasPublicLink = computed(
			() =>
				props.form.shares.filter(
					(share) => share.shareType === SHARE_TYPES.SHARE_TYPE_LINK,
				).length !== 0,
		)

		/**
		 * Submit Multiple is disabled, if it cannot be controlled.
		 */
		const disableSubmitMultiple = computed(
			() => hasPublicLink.value || props.form.isAnonymous,
		)
		const disableSubmitMultipleExplanation = computed(() => {
			if (disableSubmitMultiple.value) {
				return t(
					'forms',
					'This can not be controlled, if the form has a public link or stores responses anonymously.',
				)
			}
			return ''
		})

		// If disabled, submitMultiple will be casted to true
		const submitMultiple = computed(
			() => disableSubmitMultiple.value || props.form.submitMultiple,
		)
		const formExpires = computed(() => props.form.expires !== 0)
		const formArchived = computed(
			() => props.form.state === FormState.FormArchived,
		)
		const formClosed = computed(() => props.form.state !== FormState.FormActive)
		const hasMaxSubmissions = computed(
			() =>
				props.form.maxSubmissions !== null
				&& props.form.maxSubmissions !== undefined,
		)
		const maxSubmissionsValue = computed(() => props.form.maxSubmissions ?? 1)
		const isExpired = computed(
			() => props.form.expires && moment().unix() > props.form.expires,
		)
		const expirationDate = computed(() =>
			moment(props.form.expires, 'X').toDate(),
		)
		const injectMarkdownit = (): MarkdownRenderer => {
			return (
				(inject('$markdownit', {
					render: (input: string) => input,
				}) as MarkdownRenderer) ?? {
					render: (input: string) => input,
				}
			)
		}

		/**
		 * The submission message rendered as HTML
		 */
		const submissionMessageHTML = computed(() => {
			const markdownit = injectMarkdownit()
			return markdownit.render(props.form.submissionMessage || '')
		})
		const emailBodyPlaceholder = computed(() =>
			t(
				'forms',
				'Hello,\n\nThank you for submitting the form "{formTitle}".\n\nBest regards',
			),
		)
		const confirmationEmailQuestions = computed(() => {
			const questions = props.form?.questions || []
			return questions.filter(
				(question: FormsQuestion) =>
					question.type === 'short'
					&& question.extraSettings?.validationType === 'email',
			)
		})
		const emailQuestionCount = computed(
			() => confirmationEmailQuestions.value.length,
		)
		const selectedConfirmationEmailQuestion = computed(() => {
			const selectedQuestion = confirmationEmailQuestions.value.find(
				(question: FormsQuestion) =>
					question.id === props.form.confirmationEmailQuestionId,
			)
			if (selectedQuestion) {
				return selectedQuestion
			}

			if (
				props.form.confirmationEmailQuestionId === null
				&& emailQuestionCount.value === 1
			) {
				return confirmationEmailQuestions.value[0]
			}
			return null
		})
		const selectedConfirmationEmailQuestionId = computed(
			() =>
				props.form.confirmationEmailQuestionId
				?? selectedConfirmationEmailQuestion.value?.id
				?? '',
		)
		const confirmationEmailQuestionLabel = (question: FormsQuestion): string =>
			question.text || t('forms', 'Untitled question')
		const confirmationEmailQuestionOptions = computed(() =>
			confirmationEmailQuestions.value.map((question) => ({
				id: question.id,
				label: confirmationEmailQuestionLabel(question),
			})),
		)
		const selectedConfirmationEmailQuestionOption = computed(
			() =>
				confirmationEmailQuestionOptions.value.find(
					(question) =>
						question.id === selectedConfirmationEmailQuestionId.value,
				) || null,
		)
		const requiresConfirmationEmailQuestionIdSelection = computed(
			() =>
				emailQuestionCount.value > 1
				&& !selectedConfirmationEmailQuestion.value,
		)
		const confirmationEmailErrorText = computed(() => {
			if (emailQuestionCount.value === 0) {
				return t(
					'forms',
					'Add at least one email field before confirmation emails can be used.',
				)
			}

			if (requiresConfirmationEmailQuestionIdSelection.value) {
				return t(
					'forms',
					'Select which email field should receive confirmation emails before finishing this setup.',
				)
			}

			return ''
		})
		const confirmationEmailNoteCardType = computed<'warning' | 'info'>(() => {
			if (requiresConfirmationEmailQuestionIdSelection.value) {
				return 'warning'
			}
			return 'info'
		})
		const isConfirmationEmailConfigurationBlocked = computed(
			() =>
				props.form.confirmationEmailEnabled
				&& (emailQuestionCount.value === 0
					|| requiresConfirmationEmailQuestionIdSelection.value),
		)

		/**
		 * Datepicker timestamp to string
		 *
		 * @param datetime the datepicker Date
		 * @return
		 */
		const stringifyDate = (datetime: Date): string => {
			const date = moment(datetime).format('LLL')
			if (isExpired.value) {
				return t('forms', 'Expired on {date}', { date })
			}
			return t('forms', 'Expires on {date}', { date })
		}

		/**
		 * Form expires timestamp to Date of the datepicker
		 *
		 * @param value the expires timestamp
		 * @return
		 */
		const parseTimestampToDate = (value: number): Date =>
			moment(value, 'X').toDate()

		/**
		 * Prevent selecting a day before today
		 *
		 * @param datetime the datepicker Date
		 * @return
		 */
		const notBeforeToday = (datetime: Date): boolean =>
			datetime < moment().add(-1, 'day').toDate()

		/**
		 * Prevent selecting a time before the current one
		 *
		 * @param datetime the datepicker Date
		 * @return
		 */
		const notBeforeNow = (datetime: Date): boolean =>
			datetime < moment().toDate()

		const saveConfirmationEmailQuestionId = (
			selectedQuestionId: number | null,
		): void => {
			if (props.form.confirmationEmailQuestionId === selectedQuestionId) {
				return
			}
			emit(
				'update:formProp',
				'confirmationEmailQuestionId',
				selectedQuestionId,
			)
		}
		watch(
			() => props.form.confirmationEmailSubject,
			(val) => {
				confirmationEmailSubject.value = val || ''
			},
			{ immediate: true },
		)
		watch(
			() => props.form.confirmationEmailBody,
			(val) => {
				confirmationEmailBody.value = val || ''
			},
			{ immediate: true },
		)
		watch(
			confirmationEmailQuestions,
			() => {
				const selectedRecipientId = props.form.confirmationEmailQuestionId
				const hasValidSelectedRecipient =
					selectedRecipientId !== null
					&& confirmationEmailQuestions.value.some(
						(question) => question.id === selectedRecipientId,
					)

				if (selectedRecipientId !== null && !hasValidSelectedRecipient) {
					if (emailQuestionCount.value === 1) {
						saveConfirmationEmailQuestionId(
							confirmationEmailQuestions.value[0].id,
						)
					} else {
						saveConfirmationEmailQuestionId(null)
					}
					return
				}

				if (
					props.form.confirmationEmailEnabled
					&& emailQuestionCount.value === 1
					&& props.form.confirmationEmailQuestionId === null
				) {
					saveConfirmationEmailQuestionId(
						confirmationEmailQuestions.value[0].id,
					)
				}
			},
			{ deep: true },
		)

		/**
		 * Save Form-Properties
		 *
		 * @param checked New Checkbox/Switch Value to use
		 */
		const onAnonChange = (checked: boolean): void => {
			emit('update:formProp', 'isAnonymous', checked)
		}
		const onSubmitMultipleChange = (checked: boolean): void => {
			emit('update:formProp', 'submitMultiple', checked)
		}
		const onAllowEditSubmissionsChange = (checked: boolean): void => {
			emit('update:formProp', 'allowEditSubmissions', checked)
		}
		const onAllowCommentsChange = (checked: boolean): void => {
			emit('update:formProp', 'allowComments', checked)
		}
		const onFormExpiresChange = (checked: boolean): void => {
			if (checked) {
				emit('update:formProp', 'expires', moment().add(1, 'hour').unix())
			} else {
				emit('update:formProp', 'expires', 0)
			}
		}
		const onShowExpirationChange = (checked: boolean): void => {
			emit('update:formProp', 'showExpiration', checked)
		}

		/**
		 * On date picker change
		 *
		 * @param datetime the expiration Date
		 */
		const onExpirationDateChange = (
			datetime: Date | [Date, Date] | null,
		): void => {
			if (!(datetime instanceof Date)) {
				return
			}
			emit(
				'update:formProp',
				'expires',
				parseInt(moment(datetime).format('X')),
			)
		}
		const onMaxSubmissionsChange = (checked: boolean): void => {
			emit('update:formProp', 'maxSubmissions', checked ? 1 : null)
		}
		const onMaxSubmissionsValueChange = (value: string | number): void => {
			const parsedValue = Number(value)
			if (parsedValue > 0) {
				emit('update:formProp', 'maxSubmissions', parsedValue)
			}
		}
		const onFormClosedChange = (isClosed: boolean): void => {
			emit(
				'update:formProp',
				'state',
				isClosed ? FormState.FormClosed : FormState.FormActive,
			)
		}
		const onFormLockChange = (locked: boolean): void => {
			emit('update:formProp', 'lockedUntil', locked ? 0 : null)
		}
		const onFormArchivedChange = (isArchived: boolean): void => {
			emit(
				'update:formProp',
				'state',
				isArchived ? FormState.FormArchived : FormState.FormClosed,
			)
		}
		const onSubmissionMessageChange = (event: Event): void => {
			emit(
				'update:formProp',
				'submissionMessage',
				(event.target as HTMLTextAreaElement).value,
			)
		}

		/**
		 * Enable or disable the whole custom submission message
		 * Disabled means the value is set to null.
		 */
		const onUpdateHasCustomSubmissionMessage = (): void => {
			if (hasCustomSubmissionMessage.value) {
				emit('update:formProp', 'submissionMessage', null)
			} else {
				emit('update:formProp', 'submissionMessage', '')
			}
		}
		const onConfirmationEmailEnabledChange = (checked: boolean): void => {
			if (
				checked
				&& props.form.confirmationEmailQuestionId === null
				&& emailQuestionCount.value === 1
			) {
				saveConfirmationEmailQuestionId(
					confirmationEmailQuestions.value[0].id,
				)
			}
			emit('update:formProp', 'confirmationEmailEnabled', checked)
		}
		const onConfirmationEmailSubjectChange = (): void => {
			emit(
				'update:formProp',
				'confirmationEmailSubject',
				confirmationEmailSubject.value,
			)
		}
		const onConfirmationEmailBodyChange = (): void => {
			emit(
				'update:formProp',
				'confirmationEmailBody',
				confirmationEmailBody.value,
			)
		}
		const onConfirmationEmailQuestionIdSelectionChange = (
			option: ConfirmationEmailQuestionOption | null,
		): void => {
			const questionId = option?.id ?? null
			if (questionId === null) {
				return
			}
			saveConfirmationEmailQuestionId(questionId)
		}

		return {
			t,
			SHARE_TYPES,
			appConfig,
			maxStringLengths,
			editMessage,
			svgLockOpen,
			confirmationEmailSubject,
			confirmationEmailBody,
			isCurrentUserOwner,
			isFormLockedPermanently,
			hasCustomSubmissionMessage,
			disableSubmitMultiple,
			disableSubmitMultipleExplanation,
			hasPublicLink,
			submitMultiple,
			formExpires,
			formArchived,
			formClosed,
			hasMaxSubmissions,
			maxSubmissionsValue,
			isExpired,
			expirationDate,
			submissionMessageHTML,
			emailBodyPlaceholder,
			emailQuestionCount,
			confirmationEmailQuestions,
			selectedConfirmationEmailQuestion,
			selectedConfirmationEmailQuestionId,
			confirmationEmailQuestionOptions,
			selectedConfirmationEmailQuestionOption,
			confirmationEmailErrorText,
			confirmationEmailNoteCardType,
			requiresConfirmationEmailQuestionIdSelection,
			isConfirmationEmailConfigurationBlocked,
			confirmationEmailQuestionLabel,
			stringifyDate,
			parseTimestampToDate,
			notBeforeToday,
			notBeforeNow,
			onAnonChange,
			onSubmitMultipleChange,
			onAllowEditSubmissionsChange,
			onAllowCommentsChange,
			onFormExpiresChange,
			onShowExpirationChange,
			onExpirationDateChange,
			onMaxSubmissionsChange,
			onMaxSubmissionsValueChange,
			onFormClosedChange,
			onFormLockChange,
			onFormArchivedChange,
			onSubmissionMessageChange,
			onUpdateHasCustomSubmissionMessage,
			onConfirmationEmailEnabledChange,
			onConfirmationEmailSubjectChange,
			onConfirmationEmailBodyChange,
			onConfirmationEmailQuestionIdSelectionChange,
			saveConfirmationEmailQuestionId,
		}
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
