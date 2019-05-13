<?php
	/**
	 * @copyright Copyright (c) 2019 Inigo Jiron <ijiron@terpmail.umd.edu>
	 *
	 * @author Inigo Jiron <ijiron@terpmail.umd.edu>
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
	Util::addStyle('forms', 'main');
?>
<div id="emptycontent" class="">
	<div class="icon-forms"></div>
	<h1>
		<?php p($l->t('Form Expired')); ?>
	</h1>
	<h2>
		<?php p($l->t('This Form has expired and is no longer taking answers.')); ?>
	</h2>
</div>
