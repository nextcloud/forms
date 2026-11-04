<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="branch-condition-editor">
		<!-- Option-based conditions (radio, dropdown) -->
		<template v-if="isOptionBasedTrigger">
			<div class="condition-options">
				<NcSelect
					:inputLabel="conditionLabel"
					:modelValue="selectedOptions"
					:options="optionsList"
					:placeholder="t('forms', 'Select an option')"
					:multiple="false"
					label="text"
					@update:modelValue="onSingleOptionSelect" />
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
		 * Check if trigger is option-based (radio, dropdown)
		 */
		isOptionBasedTrigger() {
			return ['multiple_unique', 'dropdown'].includes(this.triggerType)
		},

		/**
		 * Label for option-based conditions
		 */
		conditionLabel() {
			return t('forms', 'Show when selected:')
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
				return null
			}

			const selectedIds = this.branch.conditions.map((c) => c.optionId)
			const selected = this.optionsList.filter((opt) =>
				selectedIds.includes(opt.id),
			)

			return selected[0] || null
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
