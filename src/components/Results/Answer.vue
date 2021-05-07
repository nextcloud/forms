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
	<div class="answer">
		<h4 class="answer__question-text">
			{{ questionText }}
		</h4>
		<!-- Do not wrap the following line between tags! `white-space:pre-line` respects `\n` but would produce additional empty first line -->
		<!-- eslint-disable-next-line -->
		<p class="answer__text">{{ getAnswerText }}</p>
	</div>
</template>

<script>
import moment from '@nextcloud/moment'

export default {
	name: 'Answer',

	props: {
		answerText: {
			type: String,
			required: true,
		},
		questionText: {
			type: String,
			required: true,
		},
		questionType: {
			type: String,
			required: true,
		},
	},

	computed: {
		// Format answerText for date/datetime answers
		getAnswerText() {
			if (this.questionType === 'date') {
				return moment(this.answerText, 'x').format('LL')
			} else if (this.questionType === 'datetime') {
				return moment(this.answerText, 'x').format('LLL')
			} else {
				return this.answerText
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.answer {
	margin-top: 12px;
	width: 100%;

	&__question-text {
		font-weight: bold;
	}

	&__text {
		white-space: pre-line;
	}
}

</style>
