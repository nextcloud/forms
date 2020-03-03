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
	<li class="questionList">
		<div id="text">
			{{ question.text }}
		</div>
		<div v-show="(question.type != 'text') && (question.type != 'comment')"
			id="add-option">
			<input
				v-model="newQuizAnswer"
				:placeholder=" t('forms', 'Add Answer')"
				@keyup.enter="emitNewAnswer(question)">
			<button class="symbol icon-add"
				@click="emitNewAnswer(question)" />
		</div>
		<div id="optionList">
			<transitionGroup
				name="optionList"
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
		<div class="delete-icon">
			<a class="icon icon-delete svg" @click="$emit('remove'), $emit('delete')" />
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

<style lang="scss">

.questionList {
	min-height: 40px;
	flex-grow: 0;

	#text {
		margin: 8px;
		width: 20%;
		max-width: 300px;
		display: block;
	}

	#add-option {
		width: 25%;
		max-width: 300px;
		margin-right: 15px;

		> input {
			height:30px;
			width: 150px;
			flex-grow: 1;
		}
		> button {
			height: 30px;
			width: 30px;
			margin-top: 6px;
		}
	}

	#optionList {
		width: 30%;

		> ul {
			width: 95%;

			> li {
				border-bottom: 0px;

				.delete-icon  {
					width: 16px;
					padding-right: 0px;

					> a {
						display:none;
					}
				}
			}

			> li:hover .delete-icon > a {
				display: block;
			}
		}
	}

	.delete-icon {
		display: block;
		width: 30px;

		> a {
			display: none;
		}
	}
}

.questionList:hover .delete-icon > a {
	display:block;
}

</style>
