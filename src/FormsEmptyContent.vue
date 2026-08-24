<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent appName="forms">
		<NcAppContent class="forms-emptycontent">
			<NcEmptyContent
				:name="currentModel.title"
				:description="currentModel.description">
				<template #icon>
					<NcIconSvgWrapper :svg="currentModel.icon" :size="64" />
				</template>
			</NcEmptyContent>
		</NcAppContent>
	</NcContent>
</template>

<script lang="ts">
import IconCheck from '@material-symbols/svg-400/outlined/check.svg?raw'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, defineComponent } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import FormsIcon from '../img/forms-dark.svg?raw'

const formsAppName = 'forms'

/**
 * !! Keep Model-Names in sync with Constants EMTPY_... in lib/Constants.php !!
 * Models for each EmptyContent rendering taking resp. title and subtitle
 */
const renderModels: Record<
	string,
	{ title: string; description: string; icon: string }
> = {
	notfound: {
		title: t('forms', 'Form not found'),
		description: t('forms', 'This form does not exist'),
		icon: FormsIcon,
	},

	expired: {
		title: t('forms', 'Form expired'),
		description: t(
			'forms',
			'This form has expired and is no longer taking responses',
		),
		icon: IconCheck,
	},
}

export default defineComponent({
	name: 'FormsEmptyContent',

	components: {
		NcAppContent,
		NcContent,
		NcEmptyContent,
		NcIconSvgWrapper,
	},

	setup() {
		const renderAs = loadState(formsAppName, 'renderAs') as string
		const currentModel = computed(() => {
			return renderModels[renderAs]
		})

		return {
			currentModel,
		}
	},
})
</script>

<style lang="scss" scoped>
.forms-emptycontent {
	flex-basis: 100vw;
	flex-direction: column;
	height: 100%;
	display: flex;
}
</style>
