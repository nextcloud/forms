<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
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
  -
  - UPDATE: Adds Quiz option and takes the input:
  - is yet to store input of quizzes and cannot represtent them
  - requires quizFormItem.vue (should be added to svn)
  -->

<template>
	<div id="app-content">
		<Controls :intitle="title">
			<template slot="after">
				<button :disabled="writingForm" class="button btn primary" @click="writeForm(form.mode)">
					<span>{{ saveButtonTitle }}</span>
					<span v-if="writingForm" class="icon-loading-small" />
				</button>
				<button class="button symbol icon-settings" @click="switchSidebar" />
			</template>
		</Controls>

		<div class="workbench">
			<div>
				<h2>{{ t('forms', 'Form description') }}</h2>

				<label>{{ t('forms', 'Title') }}</label>
				<input id="formTitle"
					v-model="form.event.title"
					:class="{ error: titleEmpty }"
					type="text">

				<label>{{ t('forms', 'Description') }}</label>
				<textarea id="formDesc" v-model="form.event.description" style="resize: vertical; width: 100%;" />
			</div>

			<div>
				<h2>{{ t('forms', 'Make a Form') }}</h2>
				<div v-show="form.event.type === 'quizForm'" id="quiz-form-selector-text">
					<!--shows inputs for question types: drop down box to select the type, text box for question, and button to add-->
					<label for="ans-type">Answer Type: </label>
					<select v-model="selected">
						<option value="" disabled>
							Select
						</option>
						<option v-for="option in options" :key="option.value" :value="option.value">
							{{ option.text }}
						</option>
					</select>
					<input v-model="newQuizQuestion" :placeholder=" t('forms', 'Add Question') " @keyup.enter="addQuestion()">
					<button id="questButton"
						@click="addQuestion()">
						{{ t('forms', 'Add Question') }}
					</button>
				</div>
				<!--Transition group to list the already added questions (in the form of quizFormItems)-->
				<transitionGroup
					v-show="form.mode == 'create'"
					id="form-list"
					name="list"
					tag="ul"
					class="form-table">
					<li
						is="quiz-form-item"
						v-for="(question, index) in form.options.formQuizQuestions"
						:key="question.id"
						:question="question"
						:type="question.type"
						@add-answer="addAnswer"
						@remove-answer="removeAnswer"
						@remove="form.options.formQuizQuestions.splice(index, 1)" />
				</transitionGroup>
			</div>
		</div>

		<SideBar v-if="sidebar">
			<div v-if="adminMode" class="warning">
				{{ t('forms', 'You are editing in admin mode') }}
			</div>
			<UserDiv :user-id="form.event.owner" :description="t('forms', 'Owner')" />

			<ul class="tabHeaders">
				<li class="tabHeader selected" data-tabid="configurationsTabView" data-tabindex="0">
					<a href="#">
						{{ t('forms', 'Configuration') }}
					</a>
				</li>
			</ul>

			<div v-if="protect">
				<span>{{ t('forms', 'Configuration is locked. Changing options may result in unwanted behaviour, but you can unlock it anyway.') }}</span>
				<button @click="protect=false">
					{{ t('forms', 'Unlock configuration ') }}
				</button>
			</div>
			<div id="configurationsTabView" class="tab">
				<div class="configBox ">
					<label class="title icon-settings">
						{{ t('forms', 'Form configurations') }}
					</label>

					<input id="anonymous"
						v-model="form.event.isAnonymous"
						:disabled="protect"
						type="checkbox"
						class="checkbox">
					<label for="anonymous" class="title">
						{{ t('forms', 'Anonymous form') }}
					</label>

					<input id="unique"
						v-model="form.event.unique"
						:disabled="form.event.access !== 'registered' || form.event.isAnonymous"
						type="checkbox"
						class="checkbox">
					<label for="unique" class="title">
						<span>{{ t('forms', 'Only allow one submission per user') }}</span>
					</label>

					<input v-show="form.event.isAnonymous"
						id="trueAnonymous"
						v-model="form.event.fullAnonymous"
						:disabled="protect"
						type="checkbox"
						class="checkbox">
					<input id="expiration"
						v-model="form.event.expiration"
						:disabled="protect"
						type="checkbox"
						class="checkbox">
					<label class="title" for="expiration">
						{{ t('forms', 'Expires') }}
					</label>

					<DatetimePicker v-show="form.event.expiration"
						v-model="form.event.expirationDate"
						v-bind="expirationDatePicker"
						:disabled="protect"
						:time-picker-options="{ start: '00:00', step: '00:05', end: '23:55' }"
						style="width:170px" />
				</div>

				<div class="configBox">
					<label class="title icon-user">
						{{ t('forms', 'Access') }}
					</label>
					<input id="private"
						v-model="form.event.access"
						:disabled="protect"
						type="radio"
						value="registered"
						class="radio">
					<label for="private" class="title">
						<div class="title icon-group" />
						<span>{{ t('forms', 'Registered users only') }}</span>
					</label>
					<input id="public"
						v-model="form.event.access"
						:disabled="protect"
						type="radio"
						value="public"
						class="radio">
					<label for="public" class="title">
						<div class="title icon-link" />
						<span>{{ t('forms', 'Public access') }}</span>
					</label>
					<input id="select"
						v-model="form.event.access"
						:disabled="protect"
						type="radio"
						value="select"
						class="radio">
					<label for="select" class="title">
						<div class="title icon-shared" />
						<span>{{ t('forms', 'Only shared') }}</span>
					</label>
				</div>
			</div>

			<ShareDiv v-show="form.event.access === 'select'"
				:active-shares="form.shares"
				:placeholder="t('forms', 'Name of user or group')"
				:hide-names="true"
				@update-shares="updateShares"
				@remove-share="removeShare" />
		</SideBar>
		<LoadingOverlay v-if="loadingForm" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import moment from '@nextcloud/moment'

import Controls from '../components/_base-Controls'
import LoadingOverlay from '../components/_base-LoadingOverlay'
import QuizFormItem from '../components/quizFormItem'
import ShareDiv from '../components/shareDiv'
import SideBar from '../components/_base-SideBar'
import UserDiv from '../components/_base-UserDiv'

export default {
	name: 'Create',
	components: {
		Controls,
		DatetimePicker,
		LoadingOverlay,
		QuizFormItem,
		ShareDiv,
		SideBar,
		UserDiv,
	},

	data() {
		return {
			move: {
				step: 1,
				unit: 'week',
				units: ['minute', 'hour', 'day', 'week', 'month', 'year'],
			},
			form: {
				mode: 'create',
				votes: [],
				shares: [],
				grantedAs: 'owner',
				id: 0,
				result: 'new',
				event: {
					id: 0,
					hash: '',
					type: 'quizForm',
					title: '',
					description: '',
					created: '',
					access: 'public',
					unique: false,
					expiration: false,
					expirationDate: '',
					expired: false,
					isAnonymous: false,
					fullAnonymous: false,
					owner: undefined,
				},
				options: {
					formQuizQuestions: [],
				},
			},
			lang: '',
			locale: '',
			placeholder: '',
			newQuizAnswer: '',
			newQuizQuestion: '',
			nextQuizAnswerId: 1,
			nextQuizQuestionId: 1,
			protect: false,
			writingForm: false,
			loadingForm: true,
			sidebar: false,
			titleEmpty: false,
			indexPage: '',
			longDateFormat: '',
			dateTimeFormat: '',
			selected: '',
			uniqueName: false,
			uniqueAns: false,
			haveAns: false,
			options: [
				{ text: 'Radio Buttons', value: 'radiogroup' },
				{ text: 'Checkboxes', value: 'checkbox' },
				{ text: 'Short Response', value: 'text' },
				{ text: 'Long Response', value: 'comment' },
				{ text: 'Drop Down', value: 'dropdown' },
			],
		}
	},

	computed: {
		adminMode() {
			return (this.form.event.owner !== OC.getCurrentUser().uid && OC.isUserAdmin())
		},

		langShort() {
			return this.lang.split('-')[0]
		},

		title() {
			if (this.form.event.title === '') {
				return t('forms', 'Create new form')
			} else {
				return this.form.event.title

			}
		},

		saveButtonTitle() {
			if (this.writingForm) {
				return t('forms', 'Writing form')
			} else if (this.form.mode === 'edit') {
				return t('forms', 'Update form')
			} else {
				return t('forms', 'Done')
			}
		},

		localeData() {
			return moment.localeData(moment.locale(this.locale))
		},

		expirationDatePicker() {
			return {
				editable: true,
				minuteStep: 1,
				type: 'datetime',
				format: moment.localeData().longDateFormat('L') + ' ' + moment.localeData().longDateFormat('LT'),
				lang: this.lang.split('-')[0],
				placeholder: t('forms', 'Expiration date'),
				timePickerOptions: {
					start: '00:00',
					step: '00:30',
					end: '23:30',
				},
			}
		},

		optionDatePicker() {
			return {
				editable: false,
				minuteStep: 1,
				type: 'datetime',
				format: moment.localeData().longDateFormat('L') + ' ' + moment.localeData().longDateFormat('LT'),
				lang: this.lang.split('-')[0],
				placeholder: t('forms', 'Click to add a date'),
				timePickerOptions: {
					start: '00:00',
					step: '00:30',
					end: '23:30',
				},
			}
		},

	},

	watch: {
		title() {
			// only used when the title changes after page load
			document.title = t('forms', 'Forms') + ' - ' + this.title
		},
	},

	created() {
		this.indexPage = OC.generateUrl('apps/forms/')
		this.lang = OC.getLanguage()
		try {
			this.locale = OC.getLocale()
		} catch (e) {
			if (e instanceof TypeError) {
				this.locale = this.lang
			} else {
				/* eslint-disable-next-line no-console */
				console.log(e)
			}
		}
		moment.locale(this.locale)
		this.longDateFormat = moment.localeData().longDateFormat('L')
		this.dateTimeFormat = moment.localeData().longDateFormat('L') + ' ' + moment.localeData().longDateFormat('LT')

		if (this.$route.name === 'create') {
			this.form.event.owner = OC.getCurrentUser().uid
			this.loadingForm = false
		} else if (this.$route.name === 'edit') {
			this.loadForm(this.$route.params.hash)
			this.protect = true
			this.form.mode = 'edit'
		} else if (this.$route.name === 'clone') {
			this.loadForm(this.$route.params.hash)
		}
		if (window.innerWidth > 1024) {
			this.sidebar = true
		}
	},

	methods: {

		switchSidebar() {
			this.sidebar = !this.sidebar
		},

		addShare(item) {
			this.form.shares.push(item)
		},

		updateShares(share) {
			this.form.shares = share.slice(0)
		},

		removeShare(item) {
			this.form.shares.splice(this.form.shares.indexOf(item), 1)
		},

		checkNames() {
			this.uniqueName = true
			this.form.options.formQuizQuestions.forEach(q => {
				if (q.text === this.newQuizQuestion) {
					this.uniqueName = false
				}
			})
		},

		addQuestion() {
			this.checkNames()
			if (this.selected === '') {
				OC.Notification.showTemporary(t('forms', 'Select a question type!'))
			} else if (!this.uniqueName) {
				OC.Notification.showTemporary(t('forms', 'Cannot have the same question!'))
			} else {
				if (this.newQuizQuestion !== null & this.newQuizQuestion !== '' & (/\S/.test(this.newQuizQuestion))) {
					this.form.options.formQuizQuestions.push({
						id: this.nextQuizQuestionId++,
						text: this.newQuizQuestion,
						type: this.selected,
						answers: [],
					})
				}
				this.newQuizQuestion = ''
			}
		},

		checkAnsNames(item, question) {
			this.uniqueAnsName = true
			question.answers.forEach(q => {
				if (q.text === item.newQuizAnswer) {
					this.uniqueAnsName = false
				}
			})
		},

		removeAnswer(item, question, index) {
			item.formQuizAnswers.splice(index, 1)
			question.answers.splice(index, 1)
		},

		addAnswer(item, question) {
			this.checkAnsNames(item, question)
			if (!this.uniqueAnsName) {
				OC.Notification.showTemporary(t('forms', 'Two answers cannot be the same!'))
			} else {
				if (item.newQuizAnswer !== null & item.newQuizAnswer !== '' & (/\S/.test(item.newQuizAnswer))) {
					item.formQuizAnswers.push({
						id: item.nextQuizAnswerId,
						text: item.newQuizAnswer,
					})
					question.answers.push({
						id: item.nextQuizAnswerId,
						text: item.newQuizAnswer,
					})
					item.nextQuizAnswerId++
				}
				item.newQuizAnswer = ''
			}
		},

		allHaveAns() {
			this.haveAns = true
			this.form.options.formQuizQuestions.forEach(q => {
				if (q.type !== 'text' && q.type !== 'comment' && q.answers.length === 0) {
					this.haveAns = false
				}
			})
		},

		writeForm(mode) {
			this.allHaveAns()
			if (mode !== '') {
				this.form.mode = mode
			}
			if (this.form.event.title.length === 0 | !(/\S/.test(this.form.event.title))) {
				this.titleEmpty = true
				OC.Notification.showTemporary(t('forms', 'Title must not be empty!'))
			} else if (this.form.options.formQuizQuestions.length === 0) {
				OC.Notification.showTemporary(t('forms', 'Must have at least one question!'))
			} else if (!this.haveAns) {
				OC.Notification.showTemporary(t('forms', 'All questions need answers!'))
			} else if (this.form.event.expiration & this.form.event.expirationDate === '') {
				OC.Notification.showTemporary(t('forms', 'Need to pick an expiration date!'))
			} else {
				this.writingForm = true
				this.titleEmpty = false
				// this.form.event.expirationDate = moment(this.form.event.expirationDate).utc()

				axios.post(OC.generateUrl('apps/forms/write/form'), this.form)
					.then((response) => {
						this.form.mode = 'edit'
						this.form.event.hash = response.data.hash
						this.form.event.id = response.data.id
						this.writingForm = false
						OC.Notification.showTemporary(t('forms', '%n successfully saved', 1, this.form.event.title))
						// window.location.href = OC.generateUrl('apps/forms/edit/' + this.form.event.hash)
						this.$router.push('/apps/forms/')
					}, (error) => {
						this.form.event.hash = ''
						this.writingForm = false
						OC.Notification.showTemporary(t('forms', 'Error on saving form, see console'))
						/* eslint-disable-next-line no-console */
						console.log(error.response)
					})
			}
		},

		loadForm(hash) {
			this.loadingForm = true
			axios.get(OC.generateUrl('apps/forms/get/form/' + hash))
				.then((response) => {
					this.form = response.data
					if (this.form.event.expirationDate !== null) {
						this.form.event.expirationDate = new Date(moment.utc(this.form.event.expirationDate))
					} else {
						this.form.event.expirationDate = ''
					}

					if (this.$route.name === 'clone') {
						this.form.event.owner = OC.getCurrentUser().uid
						this.form.event.title = t('forms', 'Clone of %n', 1, this.form.event.title)
						this.form.event.id = 0
						this.form.id = 0
						this.form.event.hash = ''
						this.form.grantedAs = 'owner'
						this.form.result = 'new'
						this.form.mode = 'create'
						this.form.votes = []
					}

					this.loadingForm = false
					this.newQuizAnswer = ''
					this.newQuizQuestion = ''

				}, (error) => {
					/* eslint-disable-next-line no-console */
					console.log(error.response)
					this.form.event.hash = ''
					this.loadingForm = false
				})
		},
	},
}
</script>

<style lang="scss">
#app-content {
    input.hasTimepicker {
        width: 75px;
    }
}

.warning {
    color: var(--color-error);
    font-weight: bold;
}

.forms-content {
    display: flex;
    padding-top: 45px;
    flex-grow: 1;
}

input[type="text"] {
    display: block;
    width: 100%;
}

.workbench {
    margin-top: 45px;
    display: flex;
    flex-grow: 1;
    flex-wrap: wrap;
    overflow-x: hidden;

    > div {
        min-width: 245px;
        max-width: 540px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        padding: 8px;
    }
}

.forms-sidebar {
    margin-top: 45px;
    width: 25%;

    .configBox {
        display: flex;
        flex-direction: column;
        padding: 8px;
        & > * {
            padding-left: 21px;
        }
        & > .title {
			display: flex;
            background-position: 0 2px;
            padding-left: 24px;
            opacity: 0.7;
            font-weight: bold;
            margin-bottom: 4px;
			& > span {
				padding-left: 4px;
			}
        }
    }
}

input,
textarea {
    &.error {
        border: 2px solid var(--color-error);
        box-shadow: 1px 0 var(--border-radius) var(--color-box-shadow);
    }
}

/* Transitions for inserting and removing list items */
.list-enter-active,
.list-leave-active {
    transition: all 0.5s ease;
}

.list-enter,
.list-leave-to {
    opacity: 0;
}

.list-move {
    transition: transform 0.5s;
}
/*  */

#form-item-selector-text {
    > input {
        width: 100%;
    }
}

.form-table {
    > li {
        display: flex;
        align-items: baseline;
        padding-left: 8px;
        padding-right: 8px;
        line-height: 24px;
        min-height: 24px;
        border-bottom: 1px solid var(--color-border);
        overflow: hidden;
        white-space: nowrap;

        &:active,
        &:hover {
            transition: var(--background-dark) 0.3s ease;
            background-color: var(--color-background-dark); //$hover-color;

        }

        > div {
            display: flex;
            flex-grow: 1;
            font-size: 1.2em;
            opacity: 0.7;
            white-space: normal;
            padding-right: 4px;
            &.avatar {
                flex-grow: 0;
            }
        }

        > div:nth-last-child(1) {
            justify-content: center;
            flex-grow: 0;
            flex-shrink: 0;
        }
    }
}

button {
    &.button-inline {
        border: 0;
        background-color: transparent;
    }
}

.tab {
    display: flex;
    flex-wrap: wrap;
}
.selectUnit {
	display: flex;
	align-items: center;
	flex-wrap: nowrap;
	> label {
		padding-right: 4px;
	}
}

#shiftDates {
	background-repeat: no-repeat;
	background-position: 10px center;
	min-width: 16px;
	min-height: 16px;
	padding: 10px;
	padding-left: 34px;
	text-align: left;
	margin: 0;
}
</style>
