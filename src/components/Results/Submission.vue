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
	<div class="table submission">
		<div id="submission-head">
			<div id="submission-title">
				Submission by {{ userDisplayName }}
			</div>
			<div id="submission-date">
				{{ submissionDateTime }}
			</div>
		</div>
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
import moment from '@nextcloud/moment'
import Answer from './Answer'

export default {
	name: 'Submission',

	components: {
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
	},
}
</script>

<style lang="scss" scoped>
.submission {
	margin: 15px 0px;
	width: 100%;

	border: 1px;
	border-color: var(--color-border);
	border-style: solid;

	line-break: normal;

	// div {
	// 	min-height: 30px;
	// }

	#submission-head {
		background-color: var(--color-background-dark);
		display: flex;

		#submission-title {
			font-size: 1.2em;
			display: flex;
			align-self: baseline;
			flex: 1 1 100%;
		}

		#submission-date {
			align-self: baseline;
			color: var(--color-text-lighter);
			display: block ruby;
			margin-left: 20px;
			margin-right: 10px;
			float: right;

		}
	}

	.answer {
		width: 100%;
	}
}
</style>
