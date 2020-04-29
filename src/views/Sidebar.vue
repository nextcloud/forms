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
	<AppSidebar v-show="opened" :title="form.title" @close="onClose">
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
					v-model="form.submitOnce"
					:disabled="form.access.type === 'public' || form.isAnonymous"
					type="checkbox"
					class="checkbox"
					@change="onSubmOnceChange">
				<label for="submitOnce">
					{{ t('forms', 'Only allow one response per user') }}
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
					v-model="form.expires"
					v-bind="expirationDatePicker"
					@change="onExpiresChange" />
			</li>
		</ul>

		<h3>{{ t('forms', 'Sharing') }}</h3>
		<ul>
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
				<input id="public"
					v-model="form.access.type"
					type="radio"
					value="public"
					class="radio"
					@change="onAccessChange">
				<label for="public">
					<span class="icon-link">
						{{ t('forms', 'Share link') }}
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
					:active-shares="form.shares"
					:placeholder="t('forms', 'Name of user or group')"
					:hide-names="true"
					@update-shares="updateShares"
					@remove-share="removeShare" />
			</li>
		</ul>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import moment from '@nextcloud/moment'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import ShareDiv from '../components/shareDiv'
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
			lang: '',
			locale: '',
			longDateFormat: '',
			dateTimeFormat: '',
			formExpires: false,
		}
	},

	computed: {
		expirationDatePicker() {
			return {
				editable: true,
				minuteStep: 1,
				type: 'datetime',
				valueType: 'X', // unix-timestamp
				format: moment.localeData().longDateFormat('L') + ' ' + moment.localeData().longDateFormat('LT'),
				lang: this.lang.split('-')[0],
				placeholder: t('forms', 'Expiration date'),
				timePickerOptions: {
					start: '00:00',
					step: '00:15',
					end: '23:45',
				},
			}
		},
	},

	watch: {
		formExpires: {
			handler: function() {
				if (!this.formExpires) {
					this.form.expires = 0
					this.onExpiresChange()
				} else {
					this.form.expires = moment().unix() + 3600 // Expires in one hour.
				}
			},
		},
	},

	created() {
		this.lang = OC.getLanguage()
		try {
			this.locale = OC.getLocale()
		} catch (e) {
			if (e instanceof TypeError) {
				this.locale = this.lang
			} else {
				/* eslint-disable-next-line no-console */
				console.log(e)
			}
		}
		moment.locale(this.locale)
		this.longDateFormat = moment.localeData().longDateFormat('L')
		this.dateTimeFormat = moment.localeData().longDateFormat('L') + ' ' + moment.localeData().longDateFormat('LT')

		// Compute current formExpires for checkbox
		if (this.form.expires) {
			this.formExpires = true
		} else {
			this.formExpires = false
		}

		// Watch for Sidebar toggle
		subscribe('toggleSidebar', this.onToggle)
	},

	beforeDestroy() {
		unsubscribe('toggleSidebar')
	},

	methods: {
		addShare(item) {
			this.form.shares.push(item)
		},

		updateShares(share) {
			this.form.shares = share.slice(0)
		},

		removeShare(item) {
			this.form.shares.splice(this.form.shares.indexOf(item), 1)
		},

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
		onSubmOnceChange() {
			this.saveFormProperty('submitOnce')
		},
		onAccessChange() {
			this.saveFormProperty('access')
		},
		onExpiresChange() {
			this.saveFormProperty('expires')
		},
	},
}
</script>

<style lang="scss" scoped>
h3 {
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
