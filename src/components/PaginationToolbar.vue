<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="pagination-block">
		<div class="pagination-items">
			<NcButton
				variant="tertiary"
				:disabled="totalPages === 1 || pageNumber <= 1"
				:aria-label="t('forms', 'Go to first page')"
				@click="pageNumber = 1">
				<template #icon>
					<NcIconSvgWrapper :svg="PageFirstIcon" />
				</template>
			</NcButton>
			<NcButton
				variant="tertiary"
				:disabled="totalPages === 1 || pageNumber <= 1"
				:aria-label="t('forms', 'Go to previous page')"
				@click="pageNumber--">
				<template #icon>
					<NcIconSvgWrapper :svg="IconChevronLeft" />
				</template>
			</NcButton>
			<div class="page-number">
				<NcSelect
					v-model="pageNumber"
					:options="allPageNumbersArray"
					:aria-label-combobox="t('forms', 'Page number')">
					<template #selected-option-container="{ option }">
						<span class="selected-page">
							{{
								t('forms', '{page} of {totalPages}', {
									page: option.label,
									totalPages,
								})
							}}
						</span>
					</template>
				</NcSelect>
			</div>
			<NcButton
				variant="tertiary"
				:disabled="totalPages === 1 || pageNumber >= totalPages"
				:aria-label="t('forms', 'Go to next page')"
				@click="pageNumber++">
				<template #icon>
					<NcIconSvgWrapper :svg="IconChevronRight" />
				</template>
			</NcButton>
			<NcButton
				variant="tertiary"
				:disabled="totalPages === 1 || pageNumber >= totalPages"
				:aria-label="t('forms', 'Go to last page')"
				@click="pageNumber = totalPages">
				<template #icon>
					<NcIconSvgWrapper :svg="PageLastIcon" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script lang="ts">
import IconChevronLeft from '@material-symbols/svg-400/outlined/chevron_left.svg?raw'
import IconChevronRight from '@material-symbols/svg-400/outlined/chevron_right.svg?raw'
import PageFirstIcon from '@material-symbols/svg-400/outlined/first_page.svg?raw'
import PageLastIcon from '@material-symbols/svg-400/outlined/last_page.svg?raw'
import { translate as t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSelect from '@nextcloud/vue/components/NcSelect'

export default defineComponent({
	name: 'PaginationToolbar',
	components: {
		NcIconSvgWrapper,
		NcButton,
		NcSelect,
	},

	props: {
		totalItemsCount: {
			type: Number,
			required: true,
		},

		limit: {
			type: Number,
			default: 20,
		},

		offset: {
			type: Number,
			default: 0,
		},
	},

	emits: ['update:offset'],

	setup() {
		return {
			IconChevronLeft,
			IconChevronRight,
			PageFirstIcon,
			PageLastIcon,
			t,
		}
	},

	computed: {
		allPageNumbersArray(): number[] {
			return Array.from(
				{ length: this.totalPages },
				(value, index) => 1 + index,
			)
		},

		totalPages(): number {
			return Math.max(1, Math.ceil(this.totalItemsCount / this.limit))
		},

		pageNumber: {
			get(): number {
				return Math.floor(this.offset / this.limit) + 1
			},

			set(pageNumber: number) {
				this.$emit('update:offset', (Number(pageNumber) - 1) * this.limit)
			},
		},
	},
})
</script>

<style lang="scss" scoped>
:deep(.vs__clear) {
	display: none;
}

:deep(.v-select) {
	min-width: 95px !important;
	.vs__dropdown-toggle {
		background: none;
	}
}

.selected-page {
	padding-inline-start: 5px;

	display: inline-flex;
	align-items: center;
}

.page-number {
	padding-inline: 5px;
	padding-top: 5px;
	padding-bottom: 1px;
}

.pagination-items {
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-large);
	pointer-events: all;

	display: flex;
	align-items: center;
}

.pagination-block {
	width: 100%;
	pointer-events: none;

	display: flex;
	justify-content: center;
	align-items: center;
}
</style>
