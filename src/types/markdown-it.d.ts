/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Options } from 'markdown-it'
import type Renderer from 'markdown-it/lib/renderer'
import type Token from 'markdown-it/lib/token'

declare module 'markdown-it' {
	class MarkdownIt {
		constructor(options?: { breaks?: boolean })
		render(input: string): string
		renderer: {
			rules: {
				link_open?: (
					tokens: Token[],
					idx: number,
					options: Options,
					env: unknown,
					self: Renderer,
				) => string
			}
		}
	}

	export default MarkdownIt
}
