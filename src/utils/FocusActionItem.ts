/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Focus the `<button>` of an action item and mark the item as the only
 * active item of its actions menu.
 *
 * @param item The root element of an `NcActionButton`
 */
export default function focusActionItem(item: HTMLElement | null | undefined): void {
	if (!item) {
		return
	}

	item.parentElement
		?.querySelectorAll(':scope > li.active')
		.forEach((element) => element.classList.remove('active'))
	item.classList.add('active')
	;(item.querySelector('button') ?? item).focus()
}
