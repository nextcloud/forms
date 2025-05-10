<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="pagination-block">
		<div class="pagination-items">
			<NcButton
				type="tertiary"
				:disabled="totalPages === 1 || pageNumber <= 1"
				:aria-label="t('forms', 'Go to first page')"
				@click="pageNumber = 1">
				<template #icon>
					<PageFirstIcon :size="20" />
				</template>
			</NcButton>
			<NcButton
				type="tertiary"
				:disabled="totalPages === 1 || pageNumber <= 1"
				:aria-label="t('forms', 'Go to previous page')"
				@click="pageNumber--">
				<template #icon>
					<IconChevronLeft :size="20" />
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
				type="tertiary"
				:disabled="totalPages === 1 || pageNumber >= totalPages"
				:aria-label="t('forms', 'Go to next page')"
				@click="pageNumber++">
				<template #icon>
					<IconChevronRight :size="20" />
				</template>
			</NcButton>
			<NcButton
				type="tertiary"
				:disabled="totalPages === 1 || pageNumber >= totalPages"
				:aria-label="t('forms', 'Go to last page')"
				@click="pageNumber = totalPages">
				<template #icon>
					<PageLastIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import IconChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import IconChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PageFirstIcon from 'vue-material-design-icons/PageFirst.vue'
import PageLastIcon from 'vue-material-design-icons/PageLast.vue'

export default {
	name: 'PaginationToolbar',
	components: {
		IconChevronLeft,
		IconChevronRight,
		NcButton,
		NcSelect,
		PageFirstIcon,
		PageLastIcon,
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

	computed: {
		allPageNumbersArray() {
			return Array.from(
				{ length: this.totalPages },
				(value, index) => 1 + index,
			)
		},
		totalPages() {
			return Math.max(1, Math.ceil(this.totalItemsCount / this.limit))
		},
		pageNumber: {
			get() {
				return Math.floor(this.offset / this.limit) + 1
			},
			set(pageNumber) {
				this.$emit('update:offset', (pageNumber - 1) * this.limit)
			},
		},
	},
}
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
	padding-left: 5px;

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
