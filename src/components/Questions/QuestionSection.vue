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
	</Question>
</template>

<script>
import Question from './Question.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'

export default {
	name: 'QuestionSection',
	components: {
		Question,
	},

	mixins: [QuestionMixin],
}
</script>
