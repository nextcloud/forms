/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { FormsOption } from '../models/Entities'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { defineComponent } from 'vue'

import { INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import logger from '../utils/Logger'

export default defineComponent({
	computed: {
		areNoneChecked() {
			return this.values.length === 0
		},

		contentValid() {
			return this.answerType.validate(this)
		},

		hasNoAnswer() {
			return this.options.length === 0
		},

		isLastEmpty() {
			const value = this.options[this.options.length - 1]
			return value?.text?.trim?.().length === 0
		},

        expectedOptionTypes() {
            return ['row', 'column']
        },

        sortedOptionsPerType(): { [key: string]: FormsOption[] } {
            let optionsPerType = Object.fromEntries(this.expectedOptionTypes.map(optionType => [optionType, []]))
            // console.log('sortedOptionsPerType', optionsPerType)

            this.options.forEach(option => {
                optionsPerType[option.optionType].push(option)
            })

            for (const optionType of Object.keys(optionsPerType)) {
                // Only shuffle options if not in editing mode (and shuffling is enabled)
                if (this.readOnly && this.extraSettings?.shuffleOptions) {
                    optionsPerType[optionType] = this.shuffleArray(optionsPerType[optionType])
                } else {
                    // Ensure order of options always is the same
                    optionsPerType[optionType] = [...optionsPerType[optionType]].sort((a, b) => {
                        if (a.order === b.order) {
                            return a.id - b.id
                        }
                        return (a.order ?? 0) - (b.order ?? 0)
                    })

                    if (!this.readOnly) {
                        // In edit mode append an empty option
                        optionsPerType[optionType].push({
                            local: true,
                            questionId: this.id,
                            text: '',
                            optionType: optionType,
                            order: optionsPerType[optionType].length,
                        })
                    }
                }
            }

            return optionsPerType
        },
    },

	methods: {
		/**
         * Set focus on next AnswerInput
         *
         * @param {number} index Index of current option
         * @param {string} optionType Type of current option
         */
		focusNextInput(index: number, optionType: string) {
			this.focusIndex(index + 1, optionType)
		},

		/**
		 * Focus the input matching the index
		 *
		 * @param {number} index the value index
         * @param {string} optionType the option type to focus
		 */
		focusIndex(index: number, optionType: string) {
			// refs are not guaranteed to be in correct order - we need to find the correct item
			const item = this.$refs.input.find(
				({ $vnode: vnode }) => {
                    const propsData = vnode?.componentOptions.propsData;

                    return propsData.optionType === optionType && propsData?.index === index
                }
			)
			if (item) {
				item.focus()
			} else {
				logger.warn('Could not find option to focus', {
					index,
					options: this.sortedOptionsPerType[optionType],
				})
			}
		},

        sortOptionsOfType(options: FormsOption[], optionType: string): FormsOption[] {
            // Only shuffle options if not in editing mode (and shuffling is enabled)
            options = options.filter(option => option.optionType === optionType);
            if (this.readOnly && this.extraSettings?.shuffleOptions) {
                return this.shuffleArray(options)
            }

            // Ensure order of options always is the same
            options = [...options].sort((a, b) => {
                if (a.order === b.order) {
                    return a.id - b.id
                }
                return (a.order ?? 0) - (b.order ?? 0)
            })

            if (!this.readOnly) {
                // In edit mode append an empty option
                return [
                    ...options,
                    {
                        local: true,
                        questionId: this.id,
                        text: '',
                        optionType: optionType,
                        order: options.length,
                    },
                ]
            }
            return options
        },

        updateOptionsOrder(newOptions: FormsOption[], optionType: string) {
            console.log('updateOptionsOrder', newOptions, optionType)

            this.replaceOptionsOfType(
                newOptions
                    .filter((option) => !option.local)
                    .map((option, index) => {
                        return ({
                            ...option,
                            order: index,
                        });
                    }),
                optionType,
            )
        },

        /**
		 * Handles the creation of a new answer option.
		 *
		 * @param index the index of the answer
		 * @param {FormsOption} answer - The new answer option to be added.
		 * @return {void}
		 */
		onCreateAnswer(index: number, answer: FormsOption): void {
			this.$nextTick(() => {
				this.$nextTick(() => this.focusIndex(index, answer.optionType))
			})
			this.updateOptions([...this.options, answer])
		},

        /**
         * Replace all options of a certain type
         *
         * @param {Array} options options to change
         * @param {string} optionType the type of options to update
         */
        replaceOptionsOfType(options: FormsOption[], optionType: string) {
            const updatedOptions = [
                ...this.options.filter(option => option.optionType !== optionType),
                ...options
            ]

            this.updateOptions(updatedOptions)
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
		updateAnswer(index: number, answer: FormsOption) {
            const options = [...this.sortedOptionsPerType[answer.optionType]]
			const [oldValue] = options.splice(index, 1, answer)

			// New value created - we need to set the correct focus
			if (oldValue.local && !answer.local) {
				this.$nextTick(() => {
					this.$nextTick(() => this.focusIndex(index, answer.optionType))
				})
			}

			this.replaceOptionsOfType(options.filter(({ local }) => !local), answer.optionType)
		},

		/**
		 * Remove any empty options when leaving an option
		 */
		checkValidOption(optionType: string) {
            console.log('checkValidOption', optionType, this.sortedOptionsPerType[optionType])
			// When leaving edit mode, filter and delete empty options
			this.sortedOptionsPerType[optionType].forEach((option) => {
				if (!option.text && !option.local) {
					this.deleteOption(option)
				}
			})
		},

		/**
         * Delete an option
         *
         * @param optionToDelete
         */
		deleteOption(optionToDelete: FormsOption) {
            const optionType = optionToDelete.optionType;
            const sortedOptions = this.sortedOptionsPerType[optionType];
            const index = sortedOptions.findIndex((option) => option.id === optionToDelete.id)
			const options = [...sortedOptions]
			const [option] = options.splice(index, 1)

			// delete from Db
			this.deleteOptionFromDatabase(option)

			// Update question - remove option and reorder other
			this.replaceOptionsOfType(
				options
					.filter(({ local }) => !local)
					.map((option, order) => ({ ...option, order })),
                optionType,
			)

			// Focus the previous option
			this.$nextTick(() => this.focusIndex(Math.max(index - 1, 0)), optionType)
		},

		/**
		 * Delete the option from Db in background.
		 * Restore option if delete not possible
		 *
		 * @param {object} option The option to delete
		 */
		deleteOptionFromDatabase(option: FormsOption & { local?: boolean }) {
			const optionIndex = this.options.findIndex(
				(opt: FormsOption) => opt.id === option.id,
			)

			if (!option.local) {
				// let's not await, deleting in background
				axios
					.delete(
						generateOcsUrl(
							'apps/forms/api/v3/forms/{id}/questions/{questionId}/options/{optionId}',
							{
								id: this.formId,
								questionId: this.id,
								optionId: option.id,
							},
						),
					)
					.catch((error) => {
						logger.error('Error while deleting an option', {
							error,
							option,
						})
						showError(
							t('forms', 'There was an issue deleting this option'),
						)
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
			this.focusIndex(index, option.optionType)
		},

		async saveOptionsOrder(optionType: string) {
            try {
				const newOrder = this.sortedOptionsPerType[optionType]
					.filter((option) => !option.local)
					.map((option) => option.id)

				await axios.patch(
					generateOcsUrl(
						`apps/forms/api/v3/forms/{id}/questions/{questionId}/options`,
						{
							id: this.formId,
							questionId: this.id,
						},
					),
					{
						newOrder,
                        // fixme: accept optionType on the server side
                        optionType,
					},
				)
				emit('forms:last-updated:set', this.formId)
			} catch (error) {
				logger.error('Could not reorder options', { error })
				showError(t('forms', 'Error while saving options order'))
			}
		},

		/**
		 * Reorder option by moving it upwards the list
		 * @param {number} index Option that should move up
         * @param {string} optionType Type of current option
		 */
		onOptionMoveUp(index: number, optionType: string) {
			if (index > 0) {
				this.onOptionMoveDown(index - 1, optionType)
			}
		},

		/**
         * Reorder option by moving it downwards the list
         * @param {number} index Option that should move down
         * @param {string} optionType Type of current option
         */
		onOptionMoveDown(index: number, optionType: string) {
            if (index === this.sortedOptionsPerType[optionType].length - 1) {
				return
			}

			// swap positions
			const first = this.sortedOptionsPerType[optionType][index]
			const second = this.sortedOptionsPerType[optionType][index + 1]
			second.order = index
			first.order = index + 1

            this.$nextTick(debounce(function() {
                // fixme: actually debounce not works
                this.saveOptionsOrder(optionType)
            }, INPUT_DEBOUNCE_MS))
        },
	},
})
