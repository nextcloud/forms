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
	<AppSidebar :title="form.form.title">
		<div class="configBox ">
			<label class="title icon-settings">
				{{ t('forms', 'Form configurations') }}
			</label>

			<input id="isAnonymous"
				v-model="form.form.isAnonymous"

				type="checkbox"
				class="checkbox">
			<label for="isAnonymous" class="title">
				{{ t('forms', 'Anonymous form') }}
			</label>

			<input id="submitOnce"
				v-model="form.form.submitOnce"
				:disabled="form.form.access !== 'registered' || form.form.isAnonymous"
				type="checkbox"
				class="checkbox">
			<label for="submitOnce" class="title">
				<span>{{ t('forms', 'Only allow one submission per user') }}</span>
			</label>

			<input id="expires"
				v-model="form.form.expires"

				type="checkbox"
				class="checkbox">
			<label class="title" for="expires">
				{{ t('forms', 'Expires') }}
			</label>

			<DatetimePicker v-show="form.form.expires"
				v-model="form.form.expirationDate"
				v-bind="expirationDatePicker"

				style="width:170px" />
		</div>

		<div class="configBox">
			<label class="title icon-user">
				{{ t('forms', 'Access') }}
			</label>
			<input id="private"
				v-model="form.form.access"

				type="radio"
				value="registered"
				class="radio">
			<label for="private" class="title">
				<div class="title icon-group" />
				<span>{{ t('forms', 'Registered users only') }}</span>
			</label>
			<input id="public"
				v-model="form.form.access"

				type="radio"
				value="public"
				class="radio">
			<label for="public" class="title">
				<div class="title icon-link" />
				<span>{{ t('forms', 'Public access') }}</span>
			</label>
			<input id="select"
				v-model="form.form.access"

				type="radio"
				value="select"
				class="radio">
			<label for="select" class="title">
				<div class="title icon-shared" />
				<span>{{ t('forms', 'Only shared') }}</span>
			</label>
		</div>

		<ShareDiv v-show="form.form.access === 'select'"
			:active-shares="form.shares"
			:placeholder="t('forms', 'Name of user or group')"
			:hide-names="true"
			@update-shares="updateShares"
			@remove-share="removeShare" />
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import moment from '@nextcloud/moment'

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
			lang: '',
			locale: '',
			longDateFormat: '',
			dateTimeFormat: '',
		}
	},

	computed: {
		expirationDatePicker() {
			return {
				editable: true,
				minuteStep: 1,
				type: 'datetime',
				valueType: 'YYYY-MM-DD HH:mm:ss',
				defaultValue: moment(),
				format: moment.localeData().longDateFormat('L') + ' ' + moment.localeData().longDateFormat('LT'),
				lang: moment.localeData()['_config'],
				placeholder: t('forms', 'Expiration date'),
				timePickerOptions: {
					start: '00:00',
					step: '00:15',
					end: '23:45',
				},
			}
		},

		optionDatePicker() {
			return {
				editable: false,
				minuteStep: 1,
				type: 'datetime',
				format: moment.localeData().longDateFormat('L') + ' ' + moment.localeData().longDateFormat('LT'),
				lang: this.lang.split('-')[0],
				placeholder: t('forms', 'Click to add a date'),
				timePickerOptions: {
					start: '00:00',
					step: '00:30',
					end: '23:30',
				},
			}
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
	},
}
</script>

<style lang="scss" scoped>

.configBox {
	display: flex;
	flex-direction: column;
	padding: 8px;
	& > * {
		padding-left: 21px;
	}
	& > .title {
		display: flex;
		background-position: 0 2px;
		padding-left: 24px;
		margin-bottom: 4px;
		& > span {
			padding-left: 4px;
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
</style>
