<!--
  - @copyright Copyright (c) 2022 Jan C. Borchardt https://jancborchardt.net
  -
  - @author Jan C. Borchardt https://jancborchardt.net
  - @author Ferdinand Thiessen <rpm@fthiessen.de>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div class="pill-menu">
		<NcCheckboxRadioSwitch
			v-for="option of options"
			:key="option.id"
			:aria-label="isMobile ? option.ariaLabel : null"
			:checked="active.id"
			:disabled="disabled"
			class="pill-menu__toggle"
			:class="{ 'pill-menu__toggle--icon-only': isMobile && option.icon }"
			button-variant
			button-variant-grouped="horizontal"
			type="radio"
			:value="option.id"
			@update:checked="$emit('update:active', option)">
			<template v-if="option.icon" #icon>
				<NcIconSvgWrapper :path="option.icon" />
			</template>
			{{ !isMobile || !option.icon ? option.title : null }}
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script>
import { useIsSmallMobile } from '@nextcloud/vue/dist/Composables/useIsMobile.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'

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
