<!--
  - @copyright Copyright (c) 2023 Ferdinand Thiessen <rpm@fthiessen.de>
  -
  - @author Ferdinand Thiessen <rpm@fthiessen.de>
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
	<NcListItem :title="submissionDateTime"
		:bold="false"
		:details="submissionAge"
		:link-aria-label="t('forms', 'Click to expand submission')"
		@click="onExpand">
		<template #icon>
			<NcAvatar v-if="!submission.userId.startsWith('anon-user-')"
				:size="44"
				:user="submission.userId"
				:display-name="submission.userDisplayName" />
			<IconAccountOff v-else :size="44" />
		</template>
		<template #subtitle>
			{{ submission.userDisplayName }}
		</template>
		<template #extra>
			<div v-if="expanded" class="submission">
				<Answer v-for="question in answeredQuestions"
					:key="question.id"
					:answer-text="question.squashedAnswers"
					:question-text="question.text" />
			</div>
		</template>
		<template v-if="!viewed" #indicator>
			<IconCheckboxBlankCircle :size="14" fill-color="var(--color-primary)" />
		</template>
		<template #actions>
			<NcActionButton v-if="canDeleteSubmission" @click="onDelete">
				<template #icon>
					<IconDelete :size="20" />
				</template>
				{{ t('forms', 'Delete this response') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import Answer from './Answer.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import moment from '@nextcloud/moment'
import IconAccountOff from 'vue-material-design-icons/AccountOff.vue'
import IconCheckboxBlankCircle from 'vue-material-design-icons/CheckboxBlankCircle.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'SubmissionItem',

	components: {
		Answer,
		IconAccountOff,
		IconCheckboxBlankCircle,
		IconDelete,
		NcActionButton,
		NcAvatar,
		NcListItem,
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

	data() {
		return {
			expanded: false,
			viewed: false,
		}
	},

	computed: {
		// Format submission-timestamp to DateTime
		submissionDateTime() {
			return moment(this.submission.timestamp, 'X').format('LLLL')
		},

		/**
		 * Age of the submission, e.g. '11 hours' or '1 year'
		 */
		submissionAge() {
			return moment(this.submission.timestamp, 'X').fromNow(true)
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

		onExpand() {
			this.expanded = !this.expanded
			this.viewed = true
		},
	},
}
</script>

<style scoped lang="scss">
.session {
	padding-left: 1em;
}
</style>
