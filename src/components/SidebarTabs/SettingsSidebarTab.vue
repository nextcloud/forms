<!--
  - @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  - @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="sidebar-tabs__content">
		<NcCheckboxRadioSwitch :checked="form.isAnonymous"
			type="switch"
			@update:checked="onAnonChange">
			<!-- TRANSLATORS Checkbox to select whether responses will be stored anonymously or not -->
			{{ t('forms', 'Store responses anonymously') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch v-tooltip="disableSubmitMultipleExplanation"
			:checked="submitMultiple"
			:disabled="disableSubmitMultiple"
			type="switch"
			@update:checked="onSubmitMultipleChange">
			{{ t('forms', 'Allow multiple responses per person') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch :checked="formExpires"
			type="switch"
			@update:checked="onFormExpiresChange">
			{{ t('forms', 'Set expiration date') }}
		</NcCheckboxRadioSwitch>
		<div v-show="formExpires" class="settings-div--indent">
			<NcDatetimePicker id="expiresDatetimePicker"
				:clearable="false"
				:disabled-date="notBeforeToday"
				:disabled-time="notBeforeNow"
				:editable="false"
				:formatter="formatter"
				:minute-step="5"
				:show-second="false"
				:value="expirationDate"
				type="datetime"
				@change="onExpirationDateChange" />
			<NcCheckboxRadioSwitch :checked="form.showExpiration"
				type="switch"
				@update:checked="onShowExpirationChange">
				{{ t('forms', 'Show expiration date on form') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import moment from '@nextcloud/moment'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcDatetimePicker from '@nextcloud/vue/dist/Components/NcDatetimePicker.js'
import ShareTypes from '../../mixins/ShareTypes.js'

export default {
	components: {
		NcCheckboxRadioSwitch,
		NcDatetimePicker,
	},

	mixins: [ShareTypes],

	props: {
		form: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			formatter: {
				stringify: this.stringifyDate,
				parse: this.parseTimestampToDate,
			},
		}
	},

	computed: {
		/**
		 * Submit Multiple is disabled, if it cannot be controlled.
		 */
		disableSubmitMultiple() {
			return this.hasPublicLink || this.form.access.legacyLink || this.form.isAnonymous
		},
		disableSubmitMultipleExplanation() {
			if (this.disableSubmitMultiple) {
				return t('forms', 'This can not be controlled, if the form has a public link or stores responses anonymously.')
			}
			return ''
		},
		hasPublicLink() {
			return this.form.shares
				.filter(share => share.shareType === this.SHARE_TYPES.SHARE_TYPE_LINK)
				.length !== 0
		},

		// If disabled, submitMultiple will be casted to true
		submitMultiple() {
			return this.disableSubmitMultiple || this.form.submitMultiple
		},

		formExpires() {
			return this.form.expires !== 0
		},
		isExpired() {
			return this.form.expires && moment().unix() > this.form.expires
		},
		expirationDate() {
			return moment(this.form.expires, 'X').toDate()
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
		onFormExpiresChange(checked) {
			if (checked) {
				this.$emit('update:formProp', 'expires', moment().add(1, 'hour').unix()) // Expires in one hour.
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
			this.$emit('update:formProp', 'expires', parseInt(moment(datetime).format('X')))
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
	width: calc(100% - 44px);
}

.settings-div--indent {
	margin-left: 40px;
}
</style>
