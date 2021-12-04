<!--
 - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 -
 - @author John Molakvoæ <skjnldsv@protonmail.com>
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
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<AppSidebar
		v-show="opened"
		:title="t('forms', 'Share form')"
		@close="onClose">
		<button class="copyShareLink" @click="copyShareLink">
			<span class="icon-clippy" role="img" />
			{{ t('forms', 'Share link') }}
		</button>

		<ul>
			<li>
				<input id="public"
					v-model="form.access.type"
					type="radio"
					value="public"
					class="radio"
					@change="onAccessChange">
				<label for="public">
					<span class="icon-public">
						{{ t('forms', 'Share via link') }}
					</span>
				</label>
			</li>
			<li>
				<input id="registered"
					v-model="form.access.type"
					type="radio"
					value="registered"
					class="radio"
					@change="onAccessChange">
				<label for="registered">
					<span class="icon-group">
						{{ t('forms', 'Show to all users of this instance') }}
					</span>
				</label>
			</li>
			<li>
				<input id="selected"
					v-model="form.access.type"
					type="radio"
					value="selected"
					class="radio"
					@change="onAccessChange">
				<label for="selected">
					<span class="icon-shared">
						{{ t('forms', 'Choose users to share with') }}
					</span>
				</label>
				<ShareDiv v-show="form.access.type === 'selected'"
					:user-shares="userShares"
					:group-shares="groupShares"
					@update:shares="onSharingChange" />
			</li>
		</ul>

		<h3>{{ t('forms', 'Settings') }}</h3>
		<ul>
			<li>
				<input id="isAnonymous"
					v-model="form.isAnonymous"
					type="checkbox"
					class="checkbox"
					@change="onAnonChange">
				<label for="isAnonymous">
					<!-- TRANSLATORS Checkbox to select whether responses will be stored anonymously or not -->
					{{ t('forms', 'Anonymous responses') }}
				</label>
			</li>
			<li>
				<input id="submitOnce"
					v-model="submitMultiple"
					:disabled="isPublic || form.isAnonymous"
					type="checkbox"
					class="checkbox"
					@change="onSubmitOnceChange">
				<label for="submitOnce">
					{{ t('forms', 'Allow multiple responses per person') }}
				</label>
			</li>
			<li>
				<input id="expires"
					v-model="formExpires"
					type="checkbox"
					class="checkbox">
				<label for="expires">
					{{ t('forms', 'Set expiration date') }}
				</label>
				<DatetimePicker v-show="formExpires"
					id="expiresDatetimePicker"
					:clearable="false"
					:disabled-date="notBeforeToday"
					:disabled-time="notBeforeNow"
					:editable="false"
					:formatter="formatter"
					:minute-step="5"
					:placeholder="t('forms', 'Expiration date')"
					:show-second="false"
					:value="expirationDate"
					type="datetime"
					@change="onExpiresChange" />
			</li>
		</ul>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import moment from '@nextcloud/moment'

import ShareDiv from '../components/ShareDiv'
import ViewsMixin from '../mixins/ViewsMixin'

export default {
	name: 'Sidebar',

	components: {
		AppSidebar,
		DatetimePicker,
		ShareDiv,
	},
	mixins: [ViewsMixin],

	props: {
		opened: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			lang: {
				placeholder: {
					date: t('forms', 'Select expiration date'),
				},
			},
			formatter: {
				stringify: this.stringify,
				parse: this.parse,
			},
		}
	},

	computed: {
		// Inverting submitOnce for UI here. Adapt downto Db for V3, if this imposes for longterm.
		submitMultiple: {
			get() {
				if (this.form.access.type === 'public' || this.form.isAnonymous) {
					return true
				}
				return !this.form.submitOnce
			},
			set(submitMultiple) {
				this.form.submitOnce = !submitMultiple
			},
		},

		formExpires: {
			get() {
				return this.form.expires !== 0
			},
			set(checked) {
				if (checked) {
					this.form.expires = moment().unix() + 3600 // Expires in one hour.
				} else {
					this.form.expires = 0
				}
				this.saveFormProperty('expires')
			},
		},
		isPublic() {
			return this.form?.access?.type === 'public'
		},

		expirationDate() {
			return moment(this.form.expires, 'X').toDate()
		},
		isExpired() {
			return this.form.expires && moment().unix() > this.form.expires
		},

		userShares() {
			return [...this.form?.access?.users || []]
		},
		groupShares() {
			return [...this.form?.access?.groups || []]
		},
	},

	methods: {
		/**
		 * Sidebar state methods
		 */
		onClose() {
			this.$emit('update:opened', false)
		},
		onToggle() {
			this.$emit('update:opened', !this.opened)
		},

		/**
		 * Save Form-Properties
		 */
		onAnonChange() {
			this.saveFormProperty('isAnonymous')
		},
		onSubmitOnceChange() {
			this.saveFormProperty('submitOnce')
		},
		onAccessChange() {
			this.saveFormProperty('access')
		},
		onSharingChange({ groups, users }) {
			this.$set(this.form.access, 'groups', groups)
			this.$set(this.form.access, 'users', users)
			this.onAccessChange()
		},

		/**
		 * On date picker change
		 *
		 * @param {Date} datetime the expiration Date
		 */
		onExpiresChange(datetime) {
			this.form.expires = parseInt(moment(datetime).format('X'))
			this.saveFormProperty('expires')
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
		 * Form expires timestamp to Date form the datepicker
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
		 * Prevent selecting a time before the current one + 1hour
		 *
		 * @param {Date} datetime the datepicker Date
		 * @return {boolean}
		 */
		notBeforeNow(datetime) {
			return datetime < moment().add(1, 'hour').toDate()
		},
	},
}
</script>

<style lang="scss" scoped>
.copyShareLink {
	margin: 8px;
}

h3 {
	font-weight: bold;
	margin-left: 8px;
	margin-bottom: 8px;
}

ul {
	margin-bottom: 24px;

	label {
		padding: 8px;
		display: block;

		span[class^='icon-'],
		span[class*=' icon-'] {
			background-position: 4px;
			padding-left: 24px;
		}
	}
}

input,
textarea {
	&.error {
		border: 2px solid var(--color-error);
		box-shadow: 1px 0 var(--border-radius) var(--color-box-shadow);
	}
}

#expiresDatetimePicker {
	left: 36px;
	width: calc(100% - 44px);
}
</style>
