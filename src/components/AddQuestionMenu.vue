<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcActions
		v-model:open="openLocal"
		:container="container"
		:menuName="menuName"
		:aria-label="ariaLabel"
		:variant="variant"
		:primary="primary"
		:wide="wide">
		<template #icon>
			<NcLoadingIcon v-if="isLoadingQuestions" :size="20" />
			<NcIconSvgWrapper v-else :svg="IconPlus" />
		</template>

		<template v-if="!activeQuestionType">
			<NcActionButton
				v-for="(answer, type) in answerTypesFilter"
				:key="answer.label"
				:closeAfterClick="!hasSubtypes(answer)"
				:disabled="isLoadingQuestions"
				:isMenu="hasSubtypes(answer)"
				class="question-menu__question"
				@click="onPrimaryClick(answer, type, position)">
				<template #icon>
					<NcIconSvgWrapper :svg="answer.icon" />
				</template>
				{{ answer.label }}
			</NcActionButton>
		</template>

		<template v-else>
			<NcActionButton
				:disabled="isLoadingQuestions"
				class="question-menu__question"
				@click="activeQuestionType = null">
				<template #icon>
					<NcIconSvgWrapper :svg="IconChevronLeft" />
				</template>
				{{ t('forms', 'Grid') }}
			</NcActionButton>
			<NcActionSeparator />

			<NcActionButton
				v-for="(answer, type) in answerTypesFilter[activeQuestionType]
					.subtypes"
				:key="'subtype-' + answer.label"
				closeAfterClick
				:disabled="isLoadingQuestions"
				class="question-menu__question"
				@click="onSubtypeClick(activeQuestionType, type, position)">
				<template #icon>
					<NcIconSvgWrapper :svg="answer.icon" />
				</template>
				{{ answer.label }}
			</NcActionButton>
		</template>
	</NcActions>
</template>

<script lang="ts">
import IconPlus from '@material-symbols/svg-400/outlined/add.svg?raw'
import IconChevronLeft from '@material-symbols/svg-400/outlined/chevron_left.svg?raw'
import { t } from '@nextcloud/l10n'
import { defineComponent, ref, watch } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

export default defineComponent({
	name: 'AddQuestionMenu',

	components: {
		NcActions,
		NcActionButton,
		NcActionSeparator,
		NcIconSvgWrapper,
		NcLoadingIcon,
	},

	props: {
		open: { type: Boolean, default: false },
		container: { type: String, default: 'body' },
		menuName: { type: String, default: null },
		ariaLabel: { type: String, default: null },
		variant: { type: String, default: null },
		primary: { type: Boolean, default: false },
		position: { type: Number, default: null },
		isLoadingQuestions: { type: Boolean, default: false },
		answerTypesFilter: { type: Object, required: true },
		hasSubtypes: { type: Function, required: true },
		wide: { type: Boolean, default: false },
	},

	emits: ['update:open', 'addQuestion'],

	setup(props, { emit }) {
		const activeQuestionType = ref<string | null>(null)
		const openLocal = ref<boolean>(props.open)

		watch(
			() => props.open,
			(value: boolean) => {
				openLocal.value = value
			},
		)

		watch(openLocal, (value: boolean) => {
			emit('update:open', value)
			if (!value) {
				activeQuestionType.value = null
			}
		})

		const onPrimaryClick = (
			answer: unknown,
			type: string,
			position: number | null,
		) => {
			if (props.hasSubtypes(answer)) {
				activeQuestionType.value = type
				return
			}

			emit('addQuestion', type, null, position)
			openLocal.value = false
		}

		const onSubtypeClick = (
			type: string,
			subtype: string | number,
			position: number | null,
		) => {
			emit('addQuestion', type, subtype, position)
			openLocal.value = false
		}

		return {
			activeQuestionType,
			openLocal,
			onPrimaryClick,
			onSubtypeClick,
			t,
			IconChevronLeft,
			IconPlus,
		}
	},
})
</script>

<style scoped>
.question-menu__question {
	min-width: 200px;
}
</style>
