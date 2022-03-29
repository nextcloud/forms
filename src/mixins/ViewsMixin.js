/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import Clipboard from 'v-clipboard'
import Vue from 'vue'

import CancelableRequest from '../utils/CancelableRequest'
import OcsResponse2Data from '../utils/OcsResponse2Data'

Vue.use(Clipboard)

export default {
	props: {
		hash: {
			type: String,
			default: '',
		},
		form: {
			type: Object,
			required: true,
		},
		publicView: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			// State-Variable
			isLoadingForm: true,

			// storage for axios cancel function
			cancelFetchFullForm: () => {},
		}
	},

	methods: {
		/**
		 * Focus title after form load
		 */
		focusTitle() {
			this.$nextTick(() => {
				this.$refs.title.focus()
			})
		},

		/**
		 * Fetch the full form data and update parent
		 *
		 * @param {number} id the unique form hash
		 */
		async fetchFullForm(id) {
			this.isLoadingForm = true

			// Cancel previous request
			this.cancelFetchFullForm('New request pending.')

			// Output after cancelling previous request for logical order.
			console.debug('Loading form', id)

			// Create new cancelable get request
			const { request, cancel } = CancelableRequest(async function(url, requestOptions) {
				return axios.get(url, requestOptions)
			})
			// Store cancel-function
			this.cancelFetchFullForm = cancel

			try {
				const response = await request(generateOcsUrl('apps/forms/api/v2/form/{id}', { id }))
				this.$emit('update:form', OcsResponse2Data(response))
				this.isLoadingForm = false
			} catch (error) {
				if (axios.isCancel(error)) {
					console.debug('The request for form', id, 'has been canceled.', error)
				} else {
					console.error(error)
					this.isLoadingForm = false
				}
			} finally {
				if (this.form.title === '') {
					this.focusTitle()
				}
			}
		},

		async saveFormProperty(key) {
			try {
				// TODO: add loading status feedback ?
				await axios.post(generateOcsUrl('apps/forms/api/v2/form/update'), {
					id: this.form.id,
					keyValuePairs: {
						[key]: this.form[key],
					},
				})
			} catch (error) {
				showError(t('forms', 'Error while saving form'))
				console.error(error)
			}
		},
	},
}
