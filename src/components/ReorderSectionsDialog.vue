<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('forms', 'Reorder sections')"
		:open="open"
		:buttons="buttons"
		size="normal"
		@update:open="$emit('update:open', $event)">
		<p class="reorder-sections__hint">
			{{ t('forms', 'Each section moves with its questions.') }}
		</p>
		<VueDraggable
			v-model="order"
			tag="ol"
			class="reorder-sections"
			handle=".reorder-sections__handle"
			:animation="150"
			@end="onDragEnd">
			<li
				v-for="(section, index) in order"
				:key="section.id"
				class="reorder-sections__item">
				<NcIconSvgWrapper
					class="reorder-sections__handle"
					:svg="IconDragIndicator" />
				<div class="reorder-sections__text">
					<span class="reorder-sections__title" dir="auto">
						{{ titleOf(section) }}
					</span>
					<span class="reorder-sections__position">
						{{
							t('forms', 'Section {index} of {count}', {
								index: index + 1,
								count: order.length,
							})
						}}
					</span>
				</div>
				<NcButton
					:id="`reorder-section-${section.id}-up`"
					:aria-label="
						t(
							'forms',
							'Move section {title} up',
							{ title: titleOf(section) },
							undefined,
							{ escape: false, sanitize: false },
						)
					"
					:disabled="index === 0"
					variant="tertiary"
					@click="move(index, -1)">
					<template #icon>
						<NcIconSvgWrapper :svg="IconArrowUp" />
					</template>
				</NcButton>
				<NcButton
					:id="`reorder-section-${section.id}-down`"
					:aria-label="
						t(
							'forms',
							'Move section {title} down',
							{ title: titleOf(section) },
							undefined,
							{ escape: false, sanitize: false },
						)
					"
					:disabled="index === order.length - 1"
					variant="tertiary"
					@click="move(index, 1)">
					<template #icon>
						<NcIconSvgWrapper :svg="IconArrowDown" />
					</template>
				</NcButton>
			</li>
		</VueDraggable>
		<p class="hidden-visually" aria-live="polite">
			{{ announcement }}
		</p>
	</NcDialog>
</template>

<script lang="ts">
import type { PropType } from 'vue'

import IconDragIndicator from '@material-symbols/svg-400/outlined/drag_indicator.svg?raw'
import IconArrowDown from '@material-symbols/svg-400/outlined/keyboard_arrow_down.svg?raw'
import IconArrowUp from '@material-symbols/svg-400/outlined/keyboard_arrow_up.svg?raw'
import { t } from '@nextcloud/l10n'
import { computed, defineComponent, nextTick, ref, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

interface Section {
	id: number
	text: string
}

export default defineComponent({
	name: 'ReorderSectionsDialog',

	components: {
		NcButton,
		NcDialog,
		NcIconSvgWrapper,
		VueDraggable,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		sections: {
			type: Array as PropType<Section[]>,
			required: true,
		},
	},

	emits: ['update:open', 'save'],

	setup(props, { emit }) {
		const order = ref<Section[]>([])
		const announcement = ref('')
		const movedIds = new Set<number>()

		watch(
			() => props.open,
			(open) => {
				if (open) {
					order.value = [...props.sections]
					announcement.value = ''
					movedIds.clear()
				}
			},
			{ immediate: true },
		)

		const changed = computed(() =>
			order.value.some(({ id }, index) => props.sections[index]?.id !== id),
		)

		/**
		 * The title of a section, or a placeholder for a section without title
		 *
		 * @param section the section
		 */
		const titleOf = (section: Section): string =>
			section.text || t('forms', 'Untitled section')

		/**
		 * Remember a section as moved by the user and announce its new position
		 *
		 * @param section the moved section
		 * @param position its new position
		 */
		const announceMove = (section: Section, position: number): void => {
			movedIds.add(section.id)
			announcement.value = t(
				'forms',
				'{title} moved to position {position} of {count}',
				{
					title: titleOf(section),
					position: position + 1,
					count: order.value.length,
				},
				undefined,
				{ escape: false, sanitize: false },
			)
		}

		/**
		 * Remember and announce a section dropped after dragging
		 *
		 * @param event the drop event
		 * @param event.newIndex the new position of the section
		 */
		const onDragEnd = (event: { newIndex?: number }): void => {
			const section = order.value[event.newIndex ?? -1]
			if (section) {
				announceMove(section, event.newIndex as number)
			}
		}

		/**
		 * Swap a section with its neighbour and keep the focus on the moved section's button, or on its other button at the first or last position
		 *
		 * @param index the position of the section
		 * @param step -1 to move the section up, 1 to move it down
		 */
		const move = (index: number, step: -1 | 1): void => {
			const sections = [...order.value]
			const section = sections[index]
			;[sections[index], sections[index + step]] = [
				sections[index + step],
				section,
			]
			order.value = sections
			const position = index + step
			announceMove(section, position)
			const atEnd =
				step === -1 ? position === 0 : position === sections.length - 1
			const direction = (step === -1) !== atEnd ? 'up' : 'down'
			nextTick(() => {
				document
					.getElementById(`reorder-section-${section.id}-${direction}`)
					?.focus()
			})
		}

		const buttons = computed(() => [
			{
				label: t('forms', 'Cancel'),
			},
			{
				label: t('forms', 'Save'),
				variant: 'primary' as const,
				disabled: !changed.value,
				callback: () => {
					emit(
						'save',
						order.value.map(({ id }) => id),
						order.value
							.filter(
								({ id }, index) =>
									movedIds.has(id)
									&& props.sections[index]?.id !== id,
							)
							.map(({ id }) => id),
					)
				},
			},
		])

		return {
			announcement,
			buttons,
			IconArrowDown,
			IconArrowUp,
			IconDragIndicator,
			move,
			onDragEnd,
			order,
			t,
			titleOf,
		}
	},
})
</script>

<style scoped lang="scss">
.reorder-sections {
	margin-block-end: calc(2 * var(--default-grid-baseline));

	&__hint {
		color: var(--color-text-maxcontrast);
		margin-block-end: calc(2 * var(--default-grid-baseline));
	}

	&__item {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		padding-block: calc(2 * var(--default-grid-baseline));
		border-block-end: 1px solid var(--color-border);

		&:first-child {
			border-block-start: 1px solid var(--color-border);
		}
	}

	&__handle {
		color: var(--color-text-maxcontrast);
		cursor: grab;

		&:active {
			cursor: grabbing;
		}
	}

	&__text {
		display: flex;
		flex: 1;
		flex-direction: column;
		min-width: 0;
	}

	&__title {
		font-weight: bold;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__position {
		color: var(--color-text-maxcontrast);
	}
}
</style>
