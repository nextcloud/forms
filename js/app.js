function deleteForm($formEl) {
	var str = t('forms', 'Do you really want to delete this new form?') + '\n\n' + $($formEl).attr('data-value');
	if (confirm(str)) {
		var form = document.form_delete_form;
		var hiddenId = document.createElement("input");
		hiddenId.setAttribute("name", "formId");
		hiddenId.setAttribute("type", "hidden");
		form.appendChild(hiddenId);
		form.elements.formId.value = $formEl.id.split('_')[2];
		form.submit();
	}
}


