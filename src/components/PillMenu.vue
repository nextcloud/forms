<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="pill-menu">
		<NcRadioGroup
			:label="groupLabel"
			:modelValue="activeId"
			hideLabel
			@update:modelValue="onUpdateActive">
			<NcRadioGroupButton
				v-for="pillOption of pillOptions"
				:key="pillOption.id"
				:value="String(pillOption.id)"
				:aria-label="
					isMobile && pillOption.icon ? pillOption.ariaLabel : undefined
				"
				:label="!isMobile || !pillOption.icon ? pillOption.title : undefined"
				:disabled="disabled || pillOption.disabled">
				<template v-if="pillOption.icon" #icon>
					<NcIconSvgWrapper :svg="pillOption.icon" />
				</template>
			</NcRadioGroupButton>
		</NcRadioGroup>
	</div>
</template>

<script lang="ts">
import type { PropType } from 'vue'

import { useIsSmallMobile } from '@nextcloud/vue'
import { defineComponent } from 'vue'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'

type PillOption = {
	id: string | number
	title: string
	ariaLabel?: string
	icon?: string
	disabled?: boolean
}

export default defineComponent({
	name: 'PillMenu',

	components: {
		NcIconSvgWrapper,
		NcRadioGroup,
		NcRadioGroupButton,
	},

	props: {
		/**
		 * The active option
		 */
		active: {
			type: Object as PropType<{
				id: string | number
				title?: string
				ariaLabel?: string
				icon?: string
				disabled?: boolean
			}>,

			required: true,
		},

		/**
		 * If the PillMenu is disabled
		 */
		disabled: {
			type: Boolean,
			default: false,
		},

		/**
		 * Accessible label for the radio group
		 */
		groupLabel: {
			type: String,
			required: true,
		},

		/**
		 * List of available options
		 * `option: {id: string, title: string, ariaLabel: string, icon?: string}`
		 */
		options: {
			type: Array as PropType<
				Array<{
					id: string | number
					title: string
					ariaLabel?: string
					icon?: string
					disabled?: boolean
				}>
			>,

			required: true,
		},
	},

	emits: ['update:active'],

	setup() {
		return {
			isMobile: useIsSmallMobile(),
		}
	},

	computed: {
		pillOptions(): PillOption[] {
			return this.options as PillOption[]
		},

		activeId(): string {
			return String(this.active.id)
		},
	},

	methods: {
		/**
		 * Emit the full selected option to keep PillMenu API stable
		 *
		 * @param optionId The selected option id
		 */
		onUpdateActive(optionId: string): void {
			const option = this.pillOptions.find(
				(entry) => String(entry.id) === optionId,
			)
			if (option) this.$emit('update:active', option)
		},
	},
})
</script>
