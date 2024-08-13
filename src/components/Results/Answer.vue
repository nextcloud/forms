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
	<div class="answer">
		<h4 class="answer__question-text" dir="auto">
			{{ questionText }}
		</h4>
		<!-- Do not wrap the following line between tags! `white-space:pre-line` respects `\n` but would produce additional empty first line -->
		<!-- eslint-disable-next-line -->
		<template v-if="answers.length">
			<p
				v-for="answer of answers"
				:key="answer.id"
				class="answer__text"
				dir="auto">
				<a :href="answer.url" target="_blank"
					><IconFile :size="20" class="answer__text-icon" />
					{{ answer.text }}</a
				>
			</p>
		</template>
		<p v-else class="answer__text" dir="auto">
			{{ answerText }}
		</p>
	</div>
</template>

<script>
import IconFile from 'vue-material-design-icons/File.vue'

export default {
	name: 'Answer',
	components: {
		IconFile,
	},

	props: {
		answers: {
			type: Array,
			required: false,
			default: () => [],
		},
		answerText: {
			type: String,
			required: false,
			default: '',
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
	margin-block-start: 12px;
	width: 100%;

	&__question-text {
		font-weight: bold;
	}

	&__text {
		white-space: pre-line;

		&-icon {
			display: inline-flex;
			position: relative;
			top: 4px;
		}
	}
}
</style>
