<!--
  - @copyright Copyright (c) 2020 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @license GNU AGPL version 3 or any later version
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
	<div class="table submission section">
		<div class="submission-head">
			<h3 class="submission-title">
				Response by {{ userDisplayName }}
			</h3>
			<Actions class="submission-menu" :force-menu="true">
				<ActionButton icon="icon-delete" @click="onDelete">
					{{ t('forms', 'Delete submission') }}
				</ActionButton>
			</Actions>
		</div>
		<p class="submission-date">
			{{ submissionDateTime }}
		</p>
		<table class="answer">
			<Answer
				v-for="answer in submission.answers"
				:key="answer.id"
				:answer="answer"
				:question="questionToAnswer(answer.questionId)" />
		</table>
	</div>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import moment from '@nextcloud/moment'

import Answer from './Answer'

export default {
	name: 'Submission',

	components: {
		Actions,
		ActionButton,
		Answer,
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
	},

	computed: {
		userDisplayName() {
			return this.submission.userId
		},
		submissionDateTime() {
			return moment(this.submission.timestamp, 'X').format('LLLL')
		},
	},

	methods: {
		questionToAnswer(questionId) {
			return this.questions.find(question => question.id === questionId)
		},

		onDelete() {
			this.$emit('delete')
		},
	},
}
</script>

<style lang="scss" scoped>
.section {
	padding-left: 16px;
	padding-right: 16px;

	h3 {
		font-weight: bold;
	}

	.submission-date {
		color: var(--color-text-lighter);
		margin-bottom: 12px;
	}

	.answer {
		width: 100%;
	}
}
</style>
