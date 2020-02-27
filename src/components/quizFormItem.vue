<!--
  -
  -
  - @author Nick Gallo
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
	<li>
		<div>{{ question.text }}</div>
		<div>
			<input v-show="(question.type != 'text') && (question.type != 'comment')"
				v-model="newQuizAnswer"
				style="height:30px;"
				:placeholder=" t('forms', 'Add Answer')"
				@keyup.enter="emitNewAnswer(question)">
			<transitionGroup
				id="form-list"
				name="list"
				tag="ul"
				class="form-table">
				<li
					is="text-form-item"
					v-for="(ans, index) in formQuizAnswers"
					:key="ans.id"
					:option="ans"
					@remove="emitRemoveAnswer(question, index)"
					@delete="question.answers.splice(index, 1)" />
			</transitionGroup>
		</div>
		<div>
			<a class="icon icon-delete svg delete-form" @click="$emit('remove'), $emit('delete')" />
		</div>
	</li>
</template>

<script>
import TextFormItem from './textFormItem'
export default {
	components: {
		TextFormItem,
	},
	props: {
		question: {
			type: Object,
			default: undefined,
			answers: [],
		},
	},
	data() {
		return {
			formQuizAnswers: [],
			nextQuizAnswerId: 1,
			newQuizAnswer: '',
			type: '',
		}
	},

	methods: {
		emitNewAnswer(question) {
			this.$emit('add-answer', this, question)
		},

		emitRemoveAnswer(question, id) {
			this.$emit('remove-answer', this, question, id)
		},
	},
}

</script>
