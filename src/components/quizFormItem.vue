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
	<li id="questionList">
		<div id="questionListData">
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
					name="transitionList"
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
		</div>
		<div class="delete-icon">
			<a class="icon icon-delete svg"
				tabindex="0"
				@click="$emit('remove'), $emit('delete')"
				@keyup.enter="$emit('remove'), $emit('delete')" />
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
$wrap-width: 1100px;

#questionList {
	min-height: 40px;

	#questionListData{
		display: flex;
	}

	@media all and (max-width: $wrap-width) {
		#questionListData {
			flex-direction: column;
			#text {
				width: 100%;
			}
			#add-option {
				width: 100%;
			}
			#optionList {
				width: 100%;
			}
		}
	}

	#text {
		margin: 8px;
		margin-right: 15px;
		width: 35%;
		flex-grow: 1;
	}

	#add-option {
		margin-right: 5px;
		width: 30%;
		max-width: 300px;
		display: flex;
		align-items: flex-start;

		> input {
			height:30px;
			width: 200px;
			margin-top: 5px;
			flex-grow: 1;
		}
		> button {
			height: 30px;
			width: 30px;
			margin-top: 7px;
		}
	}

	#optionList {
		width: 35%;
		display: block;

		> ul {

			> li {
				border-bottom: 0px;
				margin-top: 8px;
				margin-bottom: 8px;
				min-height: 25px;

				.delete-icon  {
					width: 16px;
					padding-right: 0px;
					display: block;
					> a {
						display: block;
					}
				}
			}
		}
	}

	.delete-icon {
		display: block;
		width: 30px;
		> a {
			display:block;
		}
	}
}

// Show Recyclebin, usable for mouse and keyboard
#questionList .delete-icon {
	> a {
		background-image: none;
	}
}
#questionList:focus-within, #questionList:hover {
	.delete-icon {
		> a {
			background-image: var(--icon-delete-000);
		}
		> a:focus, a:hover {
			background-image: var(--icon-delete-e9322d);
		}
	}
	#optionList >ul>li .delete-icon {
		> a {
			background-image: none;
		}
		> a:focus, a:hover {
			background-image: var(--icon-delete-e9322d);
		}
	}
	#optionList >ul>li:hover .delete-icon {
		> a {
			background-image: var(--icon-delete-000);
		}
		> a:focus, a:hover {
			background-image: var(--icon-delete-e9322d);
		}
	}
}

</style>
