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
		<h4 class="question-text">
			{{ questionText }}
		</h4>
		<p>{{ answerText }}</p>
	</div>
</template>

<script>
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
	},
}
</script>

<style lang="scss" scoped>
.answer {
	margin-top: 12px;
	width: 100%;

	.question-text {
		font-weight: bold;
	}
}

</style>
