<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="forms">
		<NcAppContent class="forms-emptycontent">
			<NcEmptyContent
				:name="currentModel.title"
				:description="currentModel.description">
				<template #icon>
					<component :is="currentModel.icon" :size="64" />
				</template>
			</NcEmptyContent>
		</NcAppContent>
	</NcContent>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import IconCheck from 'vue-material-design-icons/Check.vue'
import FormsIcon from './components/Icons/FormsIcon.vue'

export default {
	name: 'FormsEmptyContent',

	components: {
		FormsIcon,
		IconCheck,
		NcAppContent,
		NcContent,
		NcEmptyContent,
	},

	data() {
		return {
			/**
			 * !! Keep Model-Names in sync with Constants EMTPY_... in lib/Constants.php !!
			 * Models for each EmptyContent rendering taking resp. title and subtitle
			 */
			renderModels: {
				notfound: {
					title: t('forms', 'Form not found'),
					description: t('forms', 'This form does not exist'),
					icon: FormsIcon,
				},

				expired: {
					title: t('forms', 'Form expired'),
					description: t(
						'forms',
						'This form has expired and is no longer taking answers',
					),

					icon: IconCheck,
				},
			},

			renderAs: loadState(appName, 'renderAs'),
		}
	},

	computed: {
		currentModel() {
			return this.renderModels[this.renderAs]
		},
	},
}
</script>

<style lang="scss" scoped>
.forms-emptycontent {
	flex-basis: 100vw;
	flex-direction: column;
	height: 100%;
	display: flex;
}
</style>
