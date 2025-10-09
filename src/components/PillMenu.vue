<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="pill-menu">
		<NcCheckboxRadioSwitch
			v-for="option of options"
			:key="option.id"
			:aria-label="isMobile ? option.ariaLabel : null"
			:model-value="active.id"
			:disabled="disabled || option.disabled"
			class="pill-menu__toggle"
			:class="{ 'pill-menu__toggle--icon-only': isMobile && option.icon }"
			button-variant
			button-variant-grouped="horizontal"
			type="radio"
			:value="option.id"
			@update:model-value="$emit('update:active', option)">
			<template v-if="option.icon" #icon>
				<NcIconSvgWrapper :path="option.icon" />
			</template>
			{{ !isMobile || !option.icon ? option.title : null }}
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script>
import { useIsSmallMobile } from '@nextcloud/vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

export default {
	name: 'PillMenu',

	components: {
		NcCheckboxRadioSwitch,
		NcIconSvgWrapper,
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
}
</script>

<style lang="scss" scoped>
.pill-menu {
	align-items: center;
	align-self: flex-end;
	display: flex;
	justify-content: flex-end;

	#{&} &__toggle {
		// Make it a bit more condensed
		:deep(.checkbox-radio-switch__content) {
			flex-direction: row;
			padding-block: 0;
		}

		// Make icon only toggle round intead of elipse
		&--icon-only :deep(.checkbox-radio-switch__content) {
			padding-inline: 0;
		}
	}
}
</style>
