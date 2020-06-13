<!--
 - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 -
 - @author John Molakvoæ <skjnldsv@protonmail.com>
 -
 - @license GNU AGPL version 3 or any later version
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
			{{ t('forms', 'Copy share link') }}
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
					:format="format"
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
import { generateUrl } from '@nextcloud/router'
import { getLocale, getDayNamesShort, getMonthNamesShort } from '@nextcloud/l10n'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { showError, showSuccess } from '@nextcloud/dialogs'
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

	data() {
		return {
			opened: false,
			lang: {
				days: getDayNamesShort(),
				months: getMonthNamesShort(),
				placeholder: {
					date: t('forms', 'Select expiration date'),
				},
			},
			locale: 'en',
			format: {
				stringify: this.stringify,
				parse: this.parse,
			},
		}
	},

	computed: {
		shareLink() {
			return window.location.protocol + '//' + window.location.host + generateUrl(`/apps/forms/${this.form.hash}`)
		},

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

	async created() {
		// Load the locale
		// convert format like en_GB to en-gb for `moment.js`
		let locale = getLocale().replace('_', '-').toLowerCase()
		try {
			// default load e.g. fr-fr
			await import(/* webpackChunkName: 'moment' */'moment/locale/' + locale)
			this.locale = locale
		} catch (e) {
			try {
				// failure: fallback to fr
				locale = locale.split('-')[0]
				await import(/* webpackChunkName: 'moment' */'moment/locale/' + locale)
			} catch (e) {
				// failure, fallback to english
				console.debug('Fallback to locale', 'en')
				locale = 'en'
			}
		} finally {
			// force locale change to update
			// the component once done loading
			this.locale = locale
			console.debug('Locale used', locale)
		}
	},

	beforeMount() {
		// Watch for Sidebar toggle
		subscribe('toggleSidebar', this.onToggle)
	},

	beforeDestroy() {
		unsubscribe('toggleSidebar')
	},

	methods: {
		/**
		 * Sidebar state methods
		 */
		onClose() {
			this.opened = false
		},
		onToggle() {
			this.opened = !this.opened
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
		 * @param {Date} datetime the expiration Date
		 */
		onExpiresChange(datetime) {
			this.form.expires = parseInt(moment(datetime).format('X'))
			this.saveFormProperty('expires')
		},

		/**
		 * Datepicker timestamp to string
		 * @param {Date} datetime the datepicker Date
		 * @returns {string}
		 */
		stringify(datetime) {
			const date = moment(datetime).locale(this.locale).format('LLL')

			if (this.isExpired) {
				return t('forms', 'Expired on {date}', { date })
			}
			return t('forms', 'Expires on {date}', { date })
		},

		/**
		 * Form expires timestamp to Date form the datepicker
		 * @param {number} value the expires timestamp
		 * @returns {Date}
		 */
		parse(value) {
			return moment(value, 'X').toDate()
		},

		/**
		 * Prevent selecting a day before today
		 * @param {Date} datetime the datepicker Date
		 * @returns {boolean}
		 */
		notBeforeToday(datetime) {
			return datetime < moment().add(-1, 'day').toDate()
		},

		/**
		 * Prevent selecting a time before the current one + 1hour
		 * @param {Date} datetime the datepicker Date
		 * @returns {boolean}
		 */
		notBeforeNow(datetime) {
			return datetime < moment().add(1, 'hour').toDate()
		},

		copyShareLink(event) {
			if (this.$clipboard(this.shareLink)) {
				showSuccess(t('forms', 'Form link copied'))
			} else {
				showError(t('forms', 'Cannot copy, please copy the link manually'))
			}
			// Set back focus as clipboard removes focus
			event.target.focus()
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
