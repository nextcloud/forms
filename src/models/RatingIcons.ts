/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import IconHeartFilled from '@material-symbols/svg-400/outlined/favorite-fill.svg?raw'
import IconHeart from '@material-symbols/svg-400/outlined/favorite.svg?raw'
import IconStarFilled from '@material-symbols/svg-400/outlined/star-fill.svg?raw'
import IconStar from '@material-symbols/svg-400/outlined/star.svg?raw'
import IconThumbFilled from '@material-symbols/svg-400/outlined/thumb_up-fill.svg?raw'
import IconThumb from '@material-symbols/svg-400/outlined/thumb_up.svg?raw'
import { t } from '@nextcloud/l10n'

export interface RatingIcon {
	id: 'star' | 'heart' | 'thumb'
	label: string
	outline: string
	filled: string
	color: string
}

/**
 * The icons a rating can be drawn with; the first one is the default
 */
export const RATING_ICONS: RatingIcon[] = [
	{
		id: 'star',
		label: t('forms', 'Stars'),
		outline: IconStar,
		filled: IconStarFilled,
		color: 'var(--color-element-warning)',
	},
	{
		id: 'heart',
		label: t('forms', 'Hearts'),
		outline: IconHeart,
		filled: IconHeartFilled,
		color: 'var(--color-element-error)',
	},
	{
		id: 'thumb',
		label: t('forms', 'Thumbs up'),
		outline: IconThumb,
		filled: IconThumbFilled,
		color: 'var(--color-element-info)',
	},
]

/**
 * The rating icon with the given id, or the default icon
 *
 * @param id the id of the icon as stored in the extra settings
 */
export function getRatingIcon(id: unknown): RatingIcon {
	return RATING_ICONS.find((icon) => icon.id === id) ?? RATING_ICONS[0]
}
