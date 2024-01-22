/**
 * @copyright Copyright (c) 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 *
 * @author Ferdinand Thiessen <opensource@fthiessen.de>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
describe('Create empty form', () => {
	beforeEach(() => {
		cy.createRandomUser().then((user) => {
			cy.login(user)
		})
		cy.visit('/apps/forms')
	})

	it('shows the empty content', () => {
		cy.contains('[role="note"]', 'No forms created yet')
			.should('exist')
			.and('be.visible')

		cy.contains('button', 'Create a form')
			.should('be.visible')
	})

	it('can use button to create new form', () => {
		cy.contains('button', 'Create a form')
			.first()
			.click()

		cy.url().should('match', /apps\/forms\/.+/)

		// check we are in edit mode by default and the heading is focussed
		cy.get('h2 textarea').should('be.visible').and('have.focus')
	})

	it('can use app navigation to create new form', () => {
		cy.get('nav').contains('button', 'New form')
			.first()
			.click()

		cy.url().should('match', /apps\/forms\/.+/)

		// check we are in edit mode by default and the heading is focussed
		cy.get('h2 textarea').should('be.visible').and('have.focus')
	})

	it('Updates the form title in the navigation', () => {
		cy.get('nav').contains('button', 'New form')
			.first()
			.click()

		cy.url().then((url) => {
			expect(url).to.match(/apps\/forms\/.+/)
			const formId = url.match(/apps\/forms\/([^/?]+)/)[1]

			cy.get(`nav a[href*="${formId}"]`)
				.should('contain', 'New form')

			cy.get('h2 textarea')
				.should('have.focus')
				.type('Test form')

			cy.get(`nav a[href*="${formId}"]`)
				.should('contain', 'Test form')
		})
	})
})
