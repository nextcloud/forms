<?php
	/**
	 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
	 *
	 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
	 *
	 * @license GNU AGPL version 3 or any later version
	 *
	 *  This program is free software: you can redistribute it and/or modify
	 *  it under the terms of the GNU Affero General Public License as
	 *  published by the Free Software Foundation, either version 3 of the
	 *  License, or (at your option) any later version.
	 *
	 *  This program is distributed in the hope that it will be useful,
	 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
	 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 *  GNU Affero General Public License for more details.
	 *
	 *  You should have received a copy of the GNU Affero General Public License
	 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
	 *
	 */

	use OCP\Util;

	Util::addStyle('forms', 'submit');

	Util::addScript('forms', 'submit');
	Util::addScript('forms', 'survey.jquery.min');

	/** @var \OCA\Forms\Db\Form $form */
	$form = $_['form'];
	/** @var OCA\Forms\Db\Question[] $questions */
	$questions = $_['questions'];

?>

<?php if ($form->getIsAnonymous()):?>
*NOTE: This form is anonymous
<?php endif?>

<div id="surveyContainer"
	form="<?php echo htmlentities(json_encode($form->read()))?>"
	questions="<?php echo htmlentities(json_encode($questions))?>"
></div>
