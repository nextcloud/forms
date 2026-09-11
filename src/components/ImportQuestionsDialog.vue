<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:open="open"
		:name="t('forms', 'Import questions')"
		size="normal"
		@update:open="$emit('update:open', $event)">
		<div class="import">
			<NcLoadingIcon v-if="loadingForms" :size="32" />

			<NcEmptyContent
				v-else-if="otherForms.length === 0"
				:name="t('forms', 'No other forms to import from')">
				<template #description>
					{{
						t(
							'forms',
							'Questions can be imported from any form you are able to edit.',
						)
					}}
				</template>
			</NcEmptyContent>

			<template v-else>
				<label class="import__row">
					<span>{{ t('forms', 'Copy from') }}</span>
					<select
						:value="selectedFormId ?? ''"
						:aria-label="t('forms', 'Form to copy questions from')"
						@change="onSelectForm">
						<option disabled value="">
							{{ t('forms', 'Choose a form') }}
						</option>
						<option
							v-for="candidate in otherForms"
							:key="candidate.id"
							:value="candidate.id">
							{{ candidate.title || t('forms', 'Untitled form') }}
						</option>
					</select>
				</label>

				<NcLoadingIcon v-if="loadingQuestions" :size="32" />

				<template v-else-if="selectedFormId">
					<p v-if="questions.length === 0" class="import__empty">
						{{ t('forms', 'That form has no questions to copy.') }}
					</p>
					<template v-else>
						<NcCheckboxRadioSwitch
							:modelValue="allChosen"
							@update:modelValue="onToggleAll">
							{{ t('forms', 'Select all') }}
						</NcCheckboxRadioSwitch>
						<ul class="import__list">
							<li v-for="question in questions" :key="question.id">
								<NcCheckboxRadioSwitch
									:modelValue="chosen.includes(question.id)"
									@update:modelValue="
										onToggle(question.id, $event)
									">
									{{
										question.text
										|| t('forms', 'Untitled question')
									}}
									<span class="import__type">
										{{ typeLabel(question.type) }}
									</span>
								</NcCheckboxRadioSwitch>
							</li>
						</ul>
					</template>
				</template>
			</template>
		</div>

		<template #actions>
			<NcButton
				:disabled="chosen.length === 0 || importing"
				variant="primary"
				@click="onImport">
				<template v-if="importing" #icon>
					<NcLoadingIcon :size="20" />
				</template>
				{{ t('forms', 'Copy') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { FormsForm, FormsQuestion } from '../types/Entities.d.ts'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, defineComponent, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import answerTypes from '../models/AnswerTypes.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'

export default defineComponent({
	name: 'ImportQuestionsDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},

		formId: {
			type: Number as PropType<number>,
			required: true,
		},
	},

	emits: ['update:open', 'imported'],

	setup(props, { emit }) {
		const forms = ref<FormsForm[]>([])
		const questions = ref<FormsQuestion[]>([])
		const chosen = ref<number[]>([])
		const selectedFormId = ref<number | null>(null)
		const loadingForms = ref(false)
		const loadingQuestions = ref(false)
		const importing = ref(false)

		const otherForms = computed(() =>
			forms.value.filter((form) => form.id !== props.formId),
		)

		const allChosen = computed(
			() =>
				questions.value.length > 0
				&& chosen.value.length === questions.value.length,
		)

		/**
		 * @param type the answer type
		 */
		function typeLabel(type: string): string {
			return answerTypes[type]?.label ?? type
		}

		/**
		 *
		 */
		async function loadForms(): Promise<void> {
			loadingForms.value = true
			try {
				const response = await axios.get(
					generateOcsUrl('apps/forms/api/v3/forms'),
				)
				forms.value = OcsResponse2Data(response) ?? []
			} catch (error) {
				logger.error('Could not load forms to import from', { error })
				showError(t('forms', 'Could not load your forms'))
			} finally {
				loadingForms.value = false
			}
		}

		/**
		 * @param event the select change
		 */
		async function onSelectForm(event: Event): Promise<void> {
			selectedFormId.value = Number((event.target as HTMLSelectElement).value)
			chosen.value = []
			loadingQuestions.value = true
			try {
				const response = await axios.get(
					generateOcsUrl('apps/forms/api/v3/forms/{id}', {
						id: selectedFormId.value,
					}),
				)
				questions.value = OcsResponse2Data(response)?.questions ?? []
			} catch (error) {
				logger.error('Could not load questions to import', { error })
				showError(t('forms', 'Could not load the questions of that form'))
				questions.value = []
			} finally {
				loadingQuestions.value = false
			}
		}

		/**
		 * @param questionId the question toggled
		 * @param selected its new state
		 */
		function onToggle(questionId: number, selected: boolean): void {
			chosen.value = selected
				? [...chosen.value, questionId]
				: chosen.value.filter((id) => id !== questionId)
		}

		/**
		 * @param selected whether to select every question
		 */
		function onToggleAll(selected: boolean): void {
			chosen.value = selected ? questions.value.map((q) => q.id) : []
		}

		/**
		 *
		 */
		async function onImport(): Promise<void> {
			importing.value = true
			const created = []
			try {
				// Sequential rather than parallel: each copy is appended at the end of the
				// form, so firing them at once would give an unpredictable order.
				for (const question of questions.value) {
					if (!chosen.value.includes(question.id)) {
						continue
					}
					const response = await axios.post(
						generateOcsUrl('apps/forms/api/v3/forms/{id}/questions', {
							id: props.formId,
						}),
						{ fromId: question.id },
					)
					created.push(OcsResponse2Data(response))
				}
				emit('imported', created)
				emit('update:open', false)
			} catch (error) {
				logger.error('Could not import questions', { error })
				// Some may already have been copied, so do not imply that none were.
				showError(
					created.length
						? t('forms', 'Only some questions could be copied')
						: t('forms', 'Could not copy the questions'),
				)
				if (created.length) {
					emit('imported', created)
				}
			} finally {
				importing.value = false
			}
		}

		watch(
			() => props.open,
			(isOpen) => {
				if (isOpen) {
					loadForms()
				}
			},
			{ immediate: true },
		)

		return {
			allChosen,
			chosen,
			importing,
			loadingForms,
			loadingQuestions,
			onImport,
			onSelectForm,
			onToggle,
			onToggleAll,
			otherForms,
			questions,
			selectedFormId,
			t,
			typeLabel,
		}
	},
})
</script>

<style lang="scss" scoped>
.import {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-height: 120px;

	&__row {
		align-items: center;
		display: flex;
		flex-wrap: wrap;
		gap: 8px;

		select {
			flex: 1 1 12ch;
			min-height: var(--default-clickable-area);
			min-width: 0;
		}
	}

	&__list {
		max-height: 320px;
		overflow-y: auto;
	}

	&__type {
		color: var(--color-text-maxcontrast);
		margin-inline-start: 8px;
	}

	&__empty {
		color: var(--color-text-maxcontrast);
	}

	@media (max-width: 512px) {
		&__row {
			align-items: stretch;
			flex-direction: column;
		}
	}
}
</style>
