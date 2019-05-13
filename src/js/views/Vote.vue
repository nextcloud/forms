<!--
	- @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
	-
	- @author Natalie Gilbert
	- @author Nick Gallo
	- @author Affan Hussain
	-
	- @license GNU AGPL version 3 or any later version
	-
	- This program is free software: you can redistribute it and/or modify
	- it under the terms of the GNU Affero General Public License as
	- published by the Free Software Foundation, either version 3 of the
	- License, or (at your option) any later version.
	-
	- This program is distributed in the hope that it will be useful,
	- but WITHOUT ANY WARRANTY without even the implied warranty of
	- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	- GNU Affero General Public License for more details.
	-
	- You should have received a copy of the GNU Affero General Public License
	- along with this program.  If not, see <http://www.gnu.org/licenses/>.
	-
	-->
<template>
	<div id="app-votes">
		<survey :survey="survey"></survey>
	</div>
</template>

<script>
import * as SurveyVue from 'survey-vue'
import * as widgets from 'surveyjs-widgets'
// import 'bootstrap/dist/css/bootstrap.css'
var Survey = SurveyVue.Survey
Survey.cssType = 'bootstrap'

// import "inputmask/dist/inputmask/phone-codes/phone.js"

widgets.icheck(SurveyVue)
widgets.select2(SurveyVue)
widgets.inputmask(SurveyVue)
widgets.jquerybarrating(SurveyVue)
widgets.jqueryuidatepicker(SurveyVue)
widgets.nouislider(SurveyVue)
widgets.select2tagbox(SurveyVue)
widgets.signaturepad(SurveyVue)
widgets.sortablejs(SurveyVue)
widgets.ckeditor(SurveyVue)
widgets.autocomplete(SurveyVue)
widgets.bootstrapslider(SurveyVue)

export default {
	name: 'AppVote',
	components: {
		Survey
	},
	data() {
		var json = {
			title: '',
			questions: []
		}
		var model = new SurveyVue.Model(json)
		return {
			loadingForm: false,
			writingForm: false,
			form: [],
			myTitle: '',
			quests: [],
			survey: model,
			ans: []
		}
	},
	created() {
		this.indexPage = OC.generateUrl('apps/forms/')
		this.loadForm(this.$route.params.hash)
	},

	methods: {
		loadForm(hash) {
			this.loadingForm = true
			this.$http.get(OC.generateUrl('apps/forms/get/form/' + hash))
				.then((response) => {
					this.form = response.data
					this.myTitle = response.data.event.title
					this.quests = response.data.options.formQuizQuestions
					this.loadingForm = false
					this.setSurvey()
				}, (error) => {
					/* eslint-disable-next-line no-console */
					console.log(error.response)
					this.form.event.hash = ''
					this.loadingForm = false
				})
		},
		setSurvey() {
			this.quests.forEach(q => {
				q.answers.forEach(a => {
					this.ans.push(a.text)
				})
				this.survey.pages[0].addNewQuestion(q.type, q.text).choices = this.ans
				this.ans = []
				this.survey.pages[0].questions.forEach(i => {
					i.isRequired = true
				})
			})
			this.survey
				.onUpdateQuestionCssClasses
				.add(function(survey, options) {
					var classes = options.cssClasses
					classes.root = 'sq-root'
					classes.title = 'sq-title'
					classes.item = 'sq-item'
					classes.label = 'sq-label'

					if (options.question.isRequired) {
						classes.title = 'sq-title sq-title-required'
						classes.root = 'sq-root sq-root-required'
					}
				})
			this.survey
				.onComplete
				.add(function(result) {
					this.writingForm = true
					this.form.answers = result.data
					this.form.userId = OC.getCurrentUser().uid
					this.form.questions = this.quests
					this.$http.post(OC.generateUrl('apps/forms/insert/vote'), this.form)
						.then((response) => {
							this.writingForm = false
						}, (error) => {
							/* eslint-disable-next-line no-console */
							console.log(error.response)
							this.writingForm = false
						})
				}.bind(this))
		}

	}
}

</script>

<style lang="scss">

.app-forms {
	margin: auto;
	width: 50%;
	margin-top: 20px;
}

.sv_qstn .sq-root {
    border: 1px solid gray;
    border-left: 4px solid #18a689;
    border-radius: 5px;
    padding: 20px;
    margin-bottom: 30px;
		font-size: 18px;
}

.sq-title {
    font-size: 22px;
    margin-left: 20px;
}

.sq-title-required {
    color: black;
}

.sq-label {
    margin-left: 30px;
}
.sq-item:nth-child(1) {
    margin-bottom: 5px;
}

</style>
