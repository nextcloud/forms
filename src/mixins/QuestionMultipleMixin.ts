/**
 * @copyright Copyright (c) 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 *
 * @author Ferdinand Thiessen <opensource@fthiessen.de>
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

import type { FormsOption } from '../models/Entities'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { defineComponent } from 'vue'

import axios from '@nextcloud/axios'
import debounce from 'debounce'
import logger from '../utils/Logger'

export default defineComponent({
	computed: {
		/**
		 * Options sorted by order or randomized if configured
		 */
		sortedOptions() {
			// Only shuffle options if not in editing mode (and shuffling is enabled)
			if (this.readOnly && this.extraSettings?.shuffleOptions) {
				return this.shuffleArray(this.options)
			}
			// Ensure order of options always is the same
			const options = [...this.options].sort((a, b) => {
				if (a.order === b.order) {
					return a.id - b.id
				}
				return (a.order ?? 0) - (b.order ?? 0)
			})

			if (!this.readOnly) {
				// In edit mode append an empty option
				return [...options, { local: true, questionId: this.id, text: '', order: options.length }]
			}
			return options
		},

		/**
		 * Debounced function to save options order
		 */
		onOptionsReordered() {
			return debounce(this.saveOptionsOrder, 750)
		},
	},

	methods: {
		/**
		 * Set focus on next AnswerInput
		 *
		 * @param {number} index Index of current option
		 */
		focusNextInput(index: number) {
			this.focusIndex(index + 1)
		},

		/**
		 * Focus the input matching the index
		 *
		 * @param {number} index the value index
		 */
		focusIndex(index: number) {
			// refs are not guaranteed to be in correct order - we need to find the correct item
			const item = this.$refs.input.find(({ $vnode: vnode }) => vnode?.componentOptions.propsData.index === index)
			if (item) {
				item.focus()
			} else {
				logger.warn('Could not find option to focus', { index, options: this.sortedOptions })
			}
		},

		/**
		 * Update the options
		 * This will handle updating the form (emitting the changes) and update last changed property
		 *
		 * @param {Array} options options to change
		 */
		updateOptions(options: FormsOption[]) {
			this.$emit('update:options', options)
			emit('forms:last-updated:set', this.formId)
		},

		/**
		 * Update an existing answer locally
		 *
		 * @param {string|number} index the current index to update
		 * @param {object} answer the new answer value
		 */
		updateAnswer(index: number, answer: FormsOption & { local?: boolean }) {
			const options = [...this.sortedOptions]
			const [oldValue] = options.splice(index, 1, answer)

			// New value created - we need to set the correct focus
			if (oldValue.local && !answer.local) {
				this.$nextTick(() => {
					this.$nextTick(() => this.focusIndex(index))
				})
			}

			this.updateOptions(options.filter(({ local }) => !local))
		},

		/**
		 * Remove any empty options when leaving an option
		 */
		checkValidOption() {
			// When leaving edit mode, filter and delete empty options
			this.options.forEach(option => {
				if (!option.text && !option.local) {
					this.deleteOption(option.id)
				}
			})
		},

		/**
		 * Delete an option
		 *
		 * @param {number} id the options id
		 */
		deleteOption(id: number) {
			const index = this.sortedOptions.findIndex((option) => option.id === id)
			const options = [...this.sortedOptions]
			const [option] = options.splice(index, 1)

			// delete from Db
			this.deleteOptionFromDatabase(option)

			// Update question - remove option and reorder other
			this.updateOptions(options.filter(({ local }) => !local).map((option, order) => ({ ...option, order })))

			// Focus the previous option
			this.$nextTick(() => this.focusIndex(Math.max(index - 1, 0)))
		},

		/**
		 * Delete the option from Db in background.
		 * Restore option if delete not possible
		 *
		 * @param {object} option The option to delete
		 */
		deleteOptionFromDatabase(option: FormsOption & { local?: boolean }) {
			const optionIndex = this.options.findIndex((opt: FormsOption) => opt.id === option.id)

			if (!option.local) {
				// let's not await, deleting in background
				axios.delete(generateOcsUrl('apps/forms/api/v2.4/option/{id}', { id: option.id }))
					.catch(error => {
						logger.error('Error while deleting an option', { error, option })
						showError(t('forms', 'There was an issue deleting this option'))
						// restore option
						this.restoreOption(option, optionIndex)
					})
			}
		},

		/**
		 * Restore an option locally
		 *
		 * @param {FormsOption} option the option
		 * @param {number} index the options index in this.options
		 */
		restoreOption(option: FormsOption, index: number) {
			const options = this.options.slice()
			options.splice(index, 0, option)

			this.updateOptions(options)
			this.focusIndex(index)
		},

		async saveOptionsOrder() {
			try {
				const order = this.sortedOptions
					.filter((option) => !option.local)
					.map(({ id }) => id)

				await axios.patch(generateOcsUrl(`apps/forms/api/v2.5/question/${this.id}/options`), {
					order,
				})
				emit('forms:last-updated:set', this.formId)
			} catch (error) {
				logger.error('Could not reorder options', { error })
				showError(t('forms', 'Error while saving options order'))
			}
		},

		/**
		 * Reorder option by moving it upwards the list
		 * @param {number} index Option that should move up
		 */
		onOptionMoveUp(index: number) {
			if (index > 0) {
				this.onOptionMoveDown(index - 1)
			}
		},

		/**
		 * Reorder option by moving it downwards the list
		 * @param {number} index Option that should move down
		 */
		onOptionMoveDown(index: number) {
			if (index === this.sortedOptions.length - 1) {
				return
			}

			// swap positions
			const first = this.sortedOptions[index]
			const second = this.sortedOptions[index + 1]
			second.order = index
			first.order = index + 1
			this.onOptionsReordered()
		},
	},
})
