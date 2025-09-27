<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		:content-valid="contentValid"
		:shift-drag-handle="shiftDragHandle"
		v-on="commonListeners">
		<template #actions>

      <NcActionInput
          :model-value.sync="columnsTitle"
          :label-outside="false"
          :show-trailing-button="false"
          :label="t('forms', 'Columns Title')">
        <template #icon>
          <IconArrowRight :size="20" />
        </template>
        {{ t('forms', 'Columns Title') }}
      </NcActionInput>

      <NcActionInput
          :modelValue.sync="rowsTitle"
          :label-outside="false"
          :show-trailing-button="false"
          :label="t('forms', 'Rows Title')">
        <template #icon>
          <IconArrowDown :size="20" />
        </template>
        {{ t('forms', 'Rows Title') }}
      </NcActionInput>

      <NcActionInput
          type="multiselect"
          :value="questionType"
          track-by="id"
          :options="questionTypes">
<!--        <template #icon>-->
<!--          <Pencil :size="20" />-->
<!--        </template>-->
        {{ t('forms', 'Question type') }}
      </NcActionInput>

    </template>
		<template v-if="readOnly">
			<fieldset :name="name || undefined" :aria-labelledby="titleId">
				<NcNoteCard v-if="hasError" :id="errorId" type="error">
					{{ errorMessage }}
				</NcNoteCard>

        <!-- fixme: render grid based on a question type -->
        <!-- fixme: render titles https://stackoverflow.com/questions/45506550/how-can-a-split-diagonally-a-table-header-cell -->

        <table class="answer-grid">
          <thead>
            <tr>
              <th class="legend">
                <div>
                  <span class="columns-title">{{ columnsTitle }}</span>
                  <span class="rows-title">{{ rowsTitle }}</span>
                  <div class="line"></div>
                </div>

              </th>
              <th v-for="column of columns" :key="column.local ? 'option-local' : column.id">{{ column.text }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row of rows" :key="row.local ? 'option-local' : row.id">
              <td>{{ row.text }}</td>
              <td v-for="column of columns" :key="column.local ? 'option-local' : column.id"> O </td>
            </tr>
          </tbody>
        </table>

<!--				<NcCheckboxRadioSwitch-->
<!--					v-for="answer in sortedOptions"-->
<!--					:key="answer.id"-->
<!--					:aria-errormessage="hasError ? errorId : undefined"-->
<!--					:aria-invalid="hasError ? 'true' : undefined"-->
<!--					:model-value="questionValues"-->
<!--					:value="answer.id.toString()"-->
<!--					:name="`${id}-answer`"-->
<!--					:type="isUnique ? 'radio' : 'checkbox'"-->
<!--					:required="checkRequired(answer.id)"-->
<!--					@update:modelValue="onChange"-->
<!--					@keydown.enter.exact.prevent="onKeydownEnter">-->
<!--					{{ answer.text }}-->
<!--				</NcCheckboxRadioSwitch>-->

			</fieldset>
		</template>

		<template v-else>
			<div v-if="isLoading">
				<NcLoadingIcon :size="64" />
			</div>

      <template v-else>

        <div>{{ t('forms', 'Grid columns') }}</div>
        <Draggable
				    v-model="columns"
            class="question__content"
            animation="200"
            direction="vertical"
            handle=".option__drag-handle"
            invert-swap
            tag="ul"
            @change="saveOptionsOrder('column')"
            @start="isDragging = true"
            @end="isDragging = false">
          <TransitionGroup
              :name="
						isDragging
							? 'no-external-transition-on-drag'
							: 'options-list-transition'
					">
            <!-- Column input edit -->
            <AnswerInput
                v-for="(answer, index) in columns"
                :key="answer.local ? 'option-local' : answer.id"
                ref="input"
                :answer="answer"
                :form-id="formId"
                :index="index"
                :is-unique="isUnique"
                :max-index="columns.length - 2"
                :max-option-length="maxStringLengths.optionText"
                option-type="column"
                @create-answer="onCreateAnswer"
                @update:answer="updateAnswer"
                @delete="deleteOption"
                @focus-next="focusNextInput"
                @move-up="onOptionMoveUp(index, 'column')"
                @move-down="onOptionMoveDown(index, 'column')"
                @tabbed-out="checkValidOption('column')" />
          </TransitionGroup>
        </Draggable>

        <div>{{ t('forms', 'Grid rows') }}</div>
        <Draggable
				v-model="rows"
				class="question__content"
				animation="200"
				direction="vertical"
				handle=".option__drag-handle"
				invert-swap
				tag="ul"
				@change="saveOptionsOrder('row')"
				@start="isDragging = true"
				@end="isDragging = false">
				<TransitionGroup
					:name="
						isDragging
							? 'no-external-transition-on-drag'
							: 'options-list-transition'
					">
					<!-- Row input edit -->
					<AnswerInput
						v-for="(answer, index) in rows"
						:key="answer.local ? 'option-local' : answer.id"
						ref="input"
						:answer="answer"
						:form-id="formId"
						:index="index"
						:is-unique="isUnique"
						:max-index="rows.length - 2"
						:max-option-length="maxStringLengths.optionText"
            option-type="row"
						@create-answer="onCreateAnswer"
						@update:answer="updateAnswer"
						@delete="deleteOption"
						@focus-next="focusNextInput"
						@move-up="onOptionMoveUp(index, 'row')"
						@move-down="onOptionMoveDown(index, 'row')"
						@tabbed-out="checkValidOption('row')" />
				</TransitionGroup>
			</Draggable>
      </template>
		</template>

	</Question>
</template>

<script>
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import Draggable from 'vuedraggable'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import IconArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconCheckboxBlankOutline from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import IconContentPaste from 'vue-material-design-icons/ContentPaste.vue'
import IconRadioboxBlank from 'vue-material-design-icons/RadioboxBlank.vue'

import AnswerInput from './AnswerInput.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import QuestionMultipleMixin from '../../mixins/QuestionMultipleMixin.ts'

export default {
	name: 'QuestionGrid',

	components: {
    IconArrowRight,
    IconArrowDown,
		AnswerInput,
		Draggable,
		IconCheckboxBlankOutline,
		IconContentPaste,
		IconRadioboxBlank,
		NcActionButton,
		NcActionCheckbox,
		NcActionInput,
		NcCheckboxRadioSwitch,
		NcInputField,
		NcLoadingIcon,
		NcNoteCard,
	},

	mixins: [QuestionMixin, QuestionMultipleMixin],

	data() {
		return {
			/**
			 * The shown error message
			 */
			errorMessage: null,

			isDragging: false,
			isLoading: false,
      questionTypes: [
        { label: t('forms', 'Radio'), id: 'radio' },
        { label: t('forms', 'Checkbox'), id: 'checkbox' },
        { label: t('forms', 'Number'), id: 'number' },
        { label: t('forms', 'Text'), id: 'text' },
      ]
		}
	},

	computed: {
		isUnique() {
			return this.answerType.unique === true
		},

		hasError() {
			return !!this.errorMessage
		},

		shiftDragHandle() {
			return !this.readOnly && this.options.length !== 0 && !this.isLastEmpty
		},

		questionValues() {
			return this.isUnique ? this.values?.[0] : this.values
		},

		titleId() {
			return `q${this.index}_title`
		},

		errorId() {
			return `q${this.index}_error`
		},

		questionType() {
			return this.extraSettings?.questionType ?? 'radio'
		},

    columns: {
      get() {
        return this.sortOptionsOfType(this.options, 'column')
      },
      set(value) {
        this.updateOptionsOrder(value, 'column')
      }
    },

    rows: {
      get() {
        return this.sortOptionsOfType(this.options, 'row')
      },
      set(value) {
        this.updateOptionsOrder(value, 'row')
      }
    },

    columnsTitle: {
      get() {
        return this.extraSettings?.columnsTitle ?? ''
      },
      set(value) {
        this.onExtraSettingsChange({ columnsTitle : value })
      }
    },

    rowsTitle: {
      get() {
        return this.extraSettings?.rowsTitle ?? ''
      },
      set(value) {
        this.onExtraSettingsChange({ rowsTitle : value })
      }
    },
	},

	methods: {
		async validate() {
			if (!this.isUnique) {
				// Validate limits
				const max = this.extraSettings.optionsLimitMax ?? 0
				const min = this.extraSettings.optionsLimitMin ?? 0
				if (max && this.values.length > max) {
					this.errorMessage = n(
						'forms',
						'You must choose at most one option',
						'You must choose a maximum of %n options',
						max,
					)
					return false
				}
				if (min && this.values.length < min) {
					this.errorMessage = n(
						'forms',
						'You must choose at least one option',
						'You must choose at least %n options',
						min,
					)
					return false
				}
			}

			this.errorMessage = null
			return true
		},

		onChange(value) {
			this.$emit('update:values', this.isUnique ? [value].flat() : value)
		},

		/**
		 * Is the provided answer required ?
		 * This is needed for checkboxes as html5
		 * doesn't allow to require at least ONE checked.
		 * So we require the one that are checked or all
		 * if none are checked yet.
		 *
		 * @return {boolean}
		 */
		checkRequired() {
			// false, if question not required
			if (!this.isRequired) {
				return false
			}

			// true for Radiobuttons
			if (this.isUnique) {
				return true
			}

			// For checkboxes, only required if no other is checked
			return this.areNoneChecked
		},
  },
}
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}

.question__item {
	position: relative;
	display: inline-flex;
	min-height: var(--default-clickable-area);

	&__pseudoInput {
		color: var(--color-primary-element);
		margin-inline-start: -2px;
		z-index: 1;
	}

	.question__input {
		width: calc(100% - var(--default-clickable-area));
		position: relative;
		inset-inline-start: -34px;
		inset-block-start: 1px;
		margin-inline-end: 10px !important;
		padding-inline-start: 36px !important;
	}

	.question__label {
		flex: 1 1 100%;
		// Overwrite guest page core styles
		text-align: start !important;
		// Some rounding issues lead to this strange number, so label and answerInput show up a the same position, working on different browsers.
		padding-block: 6.5px 0;
		padding-inline: 30px 0;
		line-height: 22px;
		min-height: 34px;
		height: min-content;
		position: relative;

		&::before {
			box-sizing: border-box;
			// Adjust position manually for proper position to text
			position: absolute;
			inset-block-start: 10px;
			width: 16px;
			height: 16px;
			margin-inline: -30px 14px !important;
			margin-block-end: 0;
		}
	}
}

.question__other-answer {
	display: flex;
	gap: 4px 16px;
	flex-wrap: wrap;

	.question__label {
		flex-basis: content;
	}

	.question__input {
		flex: 1;
		min-width: 260px;
	}

	.input-field__input {
		min-height: var(--default-clickable-area);
	}
}

.question__other-answer:deep() .input-field__input {
	min-height: var(--default-clickable-area);
}

.options-list-transition-move,
.options-list-transition-enter-active,
.options-list-transition-leave-active {
	transition: all var(--animation-slow) ease;
}

.options-list-transition-enter-from,
.options-list-transition-leave-to {
	opacity: 0;
	transform: translateX(44px);
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.options-list-transition-leave-active {
	position: absolute;
}

.answer-grid {
  border-collapse: collapse;
  width: 100%;

  .legend {
    width: 200px;
    height: 80px;
    padding: 0;
    margin: 0;

    .line {
      width: 200px;
      height: 80px;
      border-bottom: 1px solid red;
      transform: translateY(-40px) translateX(0px) rotate(20deg);
      position: absolute;
    }

    .columns-title {
      position: absolute;
      bottom: 1px;
      left: 1px;
    }

    .rows-title {
      position: absolute;
      top: 1px;
      right: 1px;
    }
  }

  .legend>div {
    position: relative;
    height: 100%;
    width: 100%;
    top: 0;
    left: 0;
  }

}
</style>
