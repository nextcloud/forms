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
		<CheckboxRadioSwitch :checked="form.isAnonymous"
			type="switch"
			@update:checked="onAnonChange">
			<!-- TRANSLATORS Checkbox to select whether responses will be stored anonymously or not -->
			{{ t('forms', 'Store responses anonymously') }}
		</CheckboxRadioSwitch>
		<CheckboxRadioSwitch v-tooltip="disableSubmitMultipleExplanation"
			:checked="submitMultiple"
			:disabled="disableSubmitMultiple"
			type="switch"
			@update:checked="onSubmitMultipleChange">
			{{ t('forms', 'Allow multiple responses per person') }}
		</CheckboxRadioSwitch>
		<div>
			<CheckboxRadioSwitch :checked="formExpires"
				type="switch"
				@update:checked="onFormExpiresChange">
				{{ t('forms', 'Set expiration date') }}
			</CheckboxRadioSwitch>
			<DatetimePicker v-show="formExpires"
				id="expiresDatetimePicker"
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
		</div>
	</div>
</template>

<script>
import { CheckboxRadioSwitch, DatetimePicker } from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import ShareTypes from '../../mixins/ShareTypes'

export default {
	components: {
		CheckboxRadioSwitch,
		DatetimePicker,
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
				stringify: this.stringify,
				parse: this.parse,
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
			return this.form.shares.slice()
				.filter(share => share.shareType === this.SHARE_TYPES.SHARE_TYPE_LINK)
				.length !== 0
		},

		// Inverting submitOnce for UI here. Adapt downto Db for V3, if this imposes for longterm.
		submitMultiple() {
			if (this.disableSubmitMultiple) {
				return true
			}
			return !this.form.submitOnce
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
			// DB still stores submitOnce = !submitMultiple -> To be changed for Forms v3
			this.$emit('update:formProp', 'submitOnce', !checked)
		},
		onFormExpiresChange(checked) {
			if (checked) {
				this.$emit('update:formProp', 'expires', moment().add(1, 'hour').unix()) // Expires in one hour.
			} else {
				this.$emit('update:formProp', 'expires', 0)
			}
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
		stringify(datetime) {
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
		parse(value) {
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
	left: 36px;
	width: calc(100% - 44px);
}
</style>
