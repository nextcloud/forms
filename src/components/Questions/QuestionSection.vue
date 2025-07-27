<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		v-on="commonListeners">
		<!-- Section divider line -->
		<div class="question-section__divider"></div>
	</Question>
</template>

<script>
import QuestionMixin from '../../mixins/QuestionMixin.js'
import Question from './Question.vue'

export default {
	name: 'QuestionSection',

	components: {
		Question,
	},

	mixins: [QuestionMixin],

	computed: {
		/**
		 * Section is always valid since it only needs a title
		 *
		 * @return {boolean} true if section valid
		 */
		sectionValid() {
			return this.text && this.text.trim() !== ''
		},
	},
}
</script>

<style lang="scss" scoped>
.question-section__divider {
	width: 100%;
	height: 1px;
	background-color: var(--color-border);
	margin: 1rem 0;
	border-radius: 0.5px;
}
</style> 