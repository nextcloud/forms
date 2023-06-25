<!--
  - @copyright Copyright (c) 2020 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="section submission">
		<div class="submission-head">
			<h3>{{ submission.userDisplayName }}</h3>
			<NcActions class="submission-menu" :force-menu="true">
				<NcActionButton v-if="canDeleteSubmission" @click="onDelete">
					<template #icon>
						<IconDelete :size="20" />
					</template>
					{{ t('forms', 'Delete this response') }}
				</NcActionButton>
			</NcActions>
		</div>
		<p class="submission-date">
			{{ submissionDateTime }}
		</p>

		<Answer v-for="question in answeredQuestions"
			:key="question.id"
			:answer-text="question.squashedAnswers"
			:question-text="question.text" />
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import moment from '@nextcloud/moment'
import IconDelete from 'vue-material-design-icons/Delete.vue'

import Answer from './Answer.vue'

export default {
	name: 'Submission',

	components: {
		Answer,
		IconDelete,
		NcActions,
		NcActionButton,
	},

	props: {
		submission: {
			type: Object,
			required: true,
		},
		questions: {
			type: Array,
			required: true,
		},
		canDeleteSubmission: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		// Format submission-timestamp to DateTime
		submissionDateTime() {
			return moment(this.submission.timestamp, 'X').format('LLLL')
		},

		/**
		 * Join answered Questions with corresponding answers.
		 * Multiple answers to a question are squashed into one string.
		 *
		 * @return {Array}
		 */
		answeredQuestions() {
			const answeredQuestionsArray = []

			this.questions.forEach(question => {
				const answers = this.submission.answers.filter(answer => answer.questionId === question.id)
				if (!answers.length) {
					return // no answers, go to next question
				}
				const squashedAnswers = answers.map(answer => answer.text).join('; ')

				answeredQuestionsArray.push({
					id: question.id,
					text: question.text,
					squashedAnswers,
				})
			})
			return answeredQuestionsArray
		},
	},

	methods: {
		onDelete() {
			this.$emit('delete')
		},
	},
}
</script>

<style lang="scss" scoped>
.submission {
	padding-inline: 44px 16px;

	&-head {
		display: flex;
		align-items: flex-end;

		h3 {
			font-weight: bold;
		}

		&-menu {
			display: inline-block;
		}
	}

	&-date {
		color: var(--color-text-lighter);
		margin-block-start: -8px;
	}
}
</style>
