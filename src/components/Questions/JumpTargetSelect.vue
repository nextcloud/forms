<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSelect
		class="jump-target-select"
		:modelValue="selected"
		:options="options"
		:inputLabel="inputLabel"
		:clearable="false"
		label="label"
		@update:modelValue="onSelect" />
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { JumpTargetOption } from '../../composables/useJumps.ts'
import type { FormsJumpTarget } from '../../types/Entities.d.ts'

import { t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'

/**
 * Select where to go next: the next question, a later question or the end of the form.
 * A target that is not offered any more is shown as going to the next question.
 */
export default defineComponent({
	name: 'JumpTargetSelect',

	components: {
		NcSelect,
	},

	props: {
		/**
		 * The target, `null` for the next question
		 */
		modelValue: {
			type: [Number, String] as PropType<FormsJumpTarget | null>,
			default: null,
		},

		options: {
			type: Array as PropType<JumpTargetOption[]>,
			required: true,
		},

		/**
		 * Visible label
		 */
		inputLabel: {
			type: String,
			required: true,
		},
	},

	emits: ['update:modelValue'],

	computed: {
		selected(): JumpTargetOption {
			return (
				this.options.find((option) => option.id === this.modelValue) ?? {
					id: this.modelValue,
					label: t(
						'forms',
						'Next question (the chosen question was deleted or moved up)',
					),
				}
			)
		},
	},

	methods: {
		onSelect(option: JumpTargetOption | null): void {
			this.$emit('update:modelValue', option?.id ?? null)
		},
	},
})
</script>

<style lang="scss" scoped>
.jump-target-select {
	flex: 0 0 220px;
}

.jump-target-select.nc-select.v-select {
	min-width: 0;
}

@container (max-width: 480px) {
	.jump-target-select {
		flex: 1 1 50%;
	}

	.jump-target-select.nc-select.v-select {
		margin-inline-start: -12px;
	}
}
</style>
