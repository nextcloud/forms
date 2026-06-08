<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="pill-menu">
		<NcRadioGroup
			:label="groupLabel"
			:modelValue="active.id"
			hideLabel
			@update:modelValue="onUpdateActive">
			<NcRadioGroupButton
				v-for="option of options"
				:key="option.id"
				:value="option.id"
				:aria-label="isMobile && option.icon ? option.ariaLabel : undefined"
				:label="!isMobile || !option.icon ? option.title : undefined"
				:disabled="disabled || option.disabled">
				<template v-if="option.icon" #icon>
					<NcIconSvgWrapper :svg="option.icon" />
				</template>
			</NcRadioGroupButton>
		</NcRadioGroup>
	</div>
</template>

<script>
import { useIsSmallMobile } from '@nextcloud/vue'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'

export default {
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
			type: Object,
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
			type: Array,
			required: true,
		},
	},

	emits: ['update:active'],

	setup() {
		return {
			isMobile: useIsSmallMobile(),
		}
	},

	methods: {
		/**
		 * Emit the full selected option to keep PillMenu API stable
		 *
		 * @param {string} optionId The selected option id
		 */
		onUpdateActive(optionId) {
			const option = this.options.find((entry) => entry.id === optionId)
			if (option) this.$emit('update:active', option)
		},
	},
}
</script>
