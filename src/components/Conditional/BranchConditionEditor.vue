<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="branch-condition-editor">
		<!-- Option-based conditions (radio, dropdown, checkbox) -->
		<template v-if="isOptionBasedTrigger">
			<div class="condition-options">
				<!-- Single select for radio/dropdown -->
				<NcSelect
					v-if="isSingleSelect"
					:inputLabel="conditionLabel"
					:modelValue="selectedOptions"
					:options="optionsList"
					:placeholder="t('forms', 'Select an option')"
					:multiple="false"
					label="text"
					@update:modelValue="onSingleOptionSelect" />

				<!-- Multi select for checkboxes -->
				<NcSelect
					v-else
					:inputLabel="conditionLabel"
					:modelValue="selectedOptions"
					:options="optionsList"
					:placeholder="t('forms', 'Select options combination')"
					:multiple="true"
					label="text"
					@update:modelValue="onMultipleOptionsSelect" />
			</div>
		</template>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'

export default {
	name: 'BranchConditionEditor',

	components: {
		NcSelect,
	},

	props: {
		/**
		 * The branch object containing conditions
		 */
		branch: {
			type: Object,
			required: true,
		},

		/**
		 * The trigger question type
		 */
		triggerType: {
			type: String,
			required: true,
		},

		/**
		 * Options for option-based triggers (radio, dropdown, checkbox)
		 */
		options: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['update:branch'],

	computed: {
		/**
		 * Check if trigger is option-based (radio, dropdown, checkbox)
		 */
		isOptionBasedTrigger() {
			return ['multiple_unique', 'dropdown', 'multiple'].includes(
				this.triggerType,
			)
		},

		/**
		 * Check if trigger is single-select (radio, dropdown)
		 */
		isSingleSelect() {
			return ['multiple_unique', 'dropdown'].includes(this.triggerType)
		},

		/**
		 * Label for option-based conditions
		 */
		conditionLabel() {
			if (this.isSingleSelect) {
				return t('forms', 'Show when selected:')
			}
			return t('forms', 'Show when all selected:')
		},

		/**
		 * Options list for NcSelect
		 */
		optionsList() {
			return this.options.map((opt) => ({
				id: opt.id,
				text: opt.text || t('forms', 'Option {id}', { id: opt.id }),
			}))
		},

		/**
		 * Currently selected options based on branch conditions
		 */
		selectedOptions() {
			if (!this.branch.conditions || this.branch.conditions.length === 0) {
				return this.isSingleSelect ? null : []
			}

			let selectedIds
			if (this.isSingleSelect) {
				// Single select uses optionId in each condition
				selectedIds = this.branch.conditions.map((c) => c.optionId)
			} else {
				// Multi select uses optionIds array in first condition
				selectedIds = this.branch.conditions[0]?.optionIds || []
			}
			const selected = this.optionsList.filter((opt) =>
				selectedIds.includes(opt.id),
			)

			return this.isSingleSelect ? selected[0] || null : selected
		},
	},

	methods: {
		/**
		 * Handle single option selection (radio/dropdown)
		 *
		 * @param {object|null} option The selected option or null
		 */
		onSingleOptionSelect(option) {
			const conditions = option ? [{ optionId: option.id }] : []
			this.emitUpdate({ conditions })
		},

		/**
		 * Handle multiple option selection (checkbox)
		 *
		 * @param {Array} options The selected options array
		 */
		onMultipleOptionsSelect(options) {
			const optionIds = options.map((opt) => opt.id)
			const conditions = optionIds.length > 0 ? [{ optionIds }] : []
			this.emitUpdate({ conditions })
		},

		/**
		 * Emit branch update
		 *
		 * @param {object} updates The branch properties to update
		 */
		emitUpdate(updates) {
			this.$emit('update:branch', {
				...this.branch,
				...updates,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.branch-condition-editor {
	padding: 8px 0;
}

.condition-options {
	max-width: 400px;
}
</style>
