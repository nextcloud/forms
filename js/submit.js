var form = []
var questions = []

function sendDataToServer(survey) {
	form.answers = survey.data;
	form.userId = OC.getCurrentUser().uid;
	if(form.userId == ''){
		form.userId = 'anon_' + Date.now() + '_' + Math.floor(Math.random() * 10000)
	}
	form.questions = questions;
	$.post(OC.generateUrl('apps/forms/insert/submission'), form)
	.then((response) => {
	}, (error) => {
		/* eslint-disable-next-line no-console */
		console.log(error.response)
	});
}

function cssUpdate(survey, options){
	console.log(options.cssClasses)
	var classes = options.cssClasses
	classes.root = 'sq-root'
	classes.title = 'sq-title'
	classes.item = 'sq-item'
	classes.label = 'sq-label'
	classes.description = 'sv-q-description'

	if (options.question.isRequired) {
		classes.title = 'sq-title sq-title-required'
		classes.root = 'sq-root sq-root-required'
	}
}

$(document).ready(function () {
	var formJSON = $('#surveyContainer').attr('form')
	var questionJSON = $('#surveyContainer').attr('questions')

	form = JSON.parse(formJSON)
	questions = JSON.parse(questionJSON)

	var surveyJSON = { 
		title: form.title,
		description: form.description, 
		questions: []
	};

	questions.forEach(q => {
		var qChoices = []
		q.options.forEach(o => {
			qChoices.push(o.text);
		});
		surveyJSON.questions.push({type: q.type, name: q.text, choices: qChoices, isRequired: 'true'});
	});

	$('#surveyContainer').Survey({
		model: new Survey.Model(surveyJSON),
		onUpdateQuestionCssClasses: cssUpdate,
		onComplete: sendDataToServer,
	});
});
