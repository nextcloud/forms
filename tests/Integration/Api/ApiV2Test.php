<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Forms\Tests\Integration\Api;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;

use OCP\DB\QueryBuilder\IQueryBuilder;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Test\TestCase;

class ApiV2Test extends TestCase {
	/** @var GuzzleHttp\Client */
	private $http;

	/** @var FormMapper */
	private $formMapper;

	/** @var Array */
	private $testForms = [
		[
			'hash' => 'abcdefg',
			'title' => 'Title of a Form',
			'description' => 'Just a simple form.',
			'owner_id' => 'test',
			'access_json' => [
				'permitAllUsers' => false,
				'showToAllUsers' => false
			],
			'created' => 12345,
			'expires' => 0,
			'is_anonymous' => false,
			'submit_once' => true,
			'questions' => [
				[
					'type' => 'short',
					'text' => 'First Question?',
					'isRequired' => true,
					'order' => 1,
					'options' => []
				],
				[
					'type' => 'multiple_unique',
					'text' => 'Second Question?',
					'isRequired' => false,
					'order' => 2,
					'options' => [
						[
							'text' => 'Option 1'
						],
						[
							'text' => 'Option 2'
						]
					]
				]
			],
			'shares' => [
				[
					'shareType' => 0,
					'shareWith' => 'user1',
				],
				[
					'shareType' => 3,
					'shareWith' => 'shareHash',
				],
			],
			'submissions' => [
				[
					'userId' => 'user1',
					'timestamp' => 123456,
					'answers' => [
						[
							'questionIndex' => 0,
							'text' => 'This is a short answer.'
						],
						[
							'questionIndex' => 1,
							'text' => 'Option 1'
						]
					]
				],
				[
					'userId' => 'user2',
					'timestamp' => 12345,
					'answers' => [
						[
							'questionIndex' => 0,
							'text' => 'This is another short answer.'
						],
						[
							'questionIndex' => 1,
							'text' => 'Option 2'
						]
					]
				]
			]
		],
		[
			'hash' => 'abcdefghij',
			'title' => 'Title of a second Form',
			'description' => '',
			'owner_id' => 'someUser',
			'access_json' => [
				'permitAllUsers' => true,
				'showToAllUsers' => true
			],
			'created' => 12345,
			'expires' => 0,
			'is_anonymous' => false,
			'submit_once' => true,
			'questions' => [
				[
					'type' => 'short',
					'text' => 'Third Question?',
					'isRequired' => false,
					'order' => 1,
					'options' => []
				],
			],
			'shares' => [
				[
					'shareType' => 0,
					'shareWith' => 'user2',
				],
			],
			'submissions' => []
		]
	];

	/**
	 * Set up test environment.
	 * Writing testforms into db, preparing http request
	 */
	public function setUp(): void {
		parent::setUp();

		$qb = TestCase::$realDatabase->getQueryBuilder();

		// Write our test forms into db
		foreach ($this->testForms as $index => $form) {
			$qb->insert('forms_v2_forms')
				->values([
					'hash' => $qb->createNamedParameter($form['hash'], IQueryBuilder::PARAM_STR),
					'title' => $qb->createNamedParameter($form['title'], IQueryBuilder::PARAM_STR),
					'description' => $qb->createNamedParameter($form['description'], IQueryBuilder::PARAM_STR),
					'owner_id' => $qb->createNamedParameter($form['owner_id'], IQueryBuilder::PARAM_STR),
					'access_json' => $qb->createNamedParameter(json_encode($form['access_json']), IQueryBuilder::PARAM_STR),
					'created' => $qb->createNamedParameter($form['created'], IQueryBuilder::PARAM_INT),
					'expires' => $qb->createNamedParameter($form['expires'], IQueryBuilder::PARAM_INT),
					'is_anonymous' => $qb->createNamedParameter($form['is_anonymous'], IQueryBuilder::PARAM_BOOL),
					'submit_once' => $qb->createNamedParameter($form['submit_once'], IQueryBuilder::PARAM_BOOL)
				]);
			$qb->execute();
			$formId = $qb->getLastInsertId();
			$this->testForms[$index]['id'] = $formId;

			// Insert Questions into DB
			foreach ($form['questions'] as $qIndex => $question) {
				$qb->insert('forms_v2_questions')
					->values([
						'form_id' => $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT),
						'order' => $qb->createNamedParameter($question['order'], IQueryBuilder::PARAM_INT),
						'type' => $qb->createNamedParameter($question['type'], IQueryBuilder::PARAM_STR),
						'is_required' => $qb->createNamedParameter($question['isRequired'], IQueryBuilder::PARAM_BOOL),
						'text' => $qb->createNamedParameter($question['text'], IQueryBuilder::PARAM_STR)
					]);
				$qb->execute();
				$questionId = $qb->getLastInsertId();
				$this->testForms[$index]['questions'][$qIndex]['id'] = $questionId;

				// Insert Options into DB
				foreach ($question['options'] as $oIndex => $option) {
					$qb->insert('forms_v2_options')
						->values([
							'question_id' => $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT),
							'text' => $qb->createNamedParameter($option['text'], IQueryBuilder::PARAM_STR)
						]);
					$qb->execute();
					$this->testForms[$index]['questions'][$qIndex]['options'][$oIndex]['id'] = $qb->getLastInsertId();
				}
			}

			// Insert Shares into DB
			foreach ($form['shares'] as $sIndex => $share) {
				$qb->insert('forms_v2_shares')
					->values([
						'form_id' => $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT),
						'share_type' => $qb->createNamedParameter($share['shareType'], IQueryBuilder::PARAM_STR),
						'share_with' => $qb->createNamedParameter($share['shareWith'], IQueryBuilder::PARAM_STR)
					]);
				$qb->execute();
				$this->testForms[$index]['shares'][$sIndex]['id'] = $qb->getLastInsertId();
			}

			// Insert Submissions into DB
			foreach ($form['submissions'] as $suIndex => $submission) {
				$qb->insert('forms_v2_submissions')
					->values([
						'form_id' => $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT),
						'user_id' => $qb->createNamedParameter($submission['userId'], IQueryBuilder::PARAM_STR),
						'timestamp' => $qb->createNamedParameter($submission['timestamp'], IQueryBuilder::PARAM_INT)
					]);
				$qb->execute();
				$submissionId = $qb->getLastInsertId();
				$this->testForms[$index]['submissions'][$suIndex]['id'] = $submissionId;

				foreach ($submission['answers'] as $aIndex => $answer) {
					$qb->insert('forms_v2_answers')
						->values([
							'submission_id' => $qb->createNamedParameter($submissionId, IQueryBuilder::PARAM_INT),
							'question_id' => $qb->createNamedParameter($this->testForms[$index]['questions'][$answer['questionIndex']]['id'], IQueryBuilder::PARAM_INT),
							'text' => $qb->createNamedParameter($answer['text'], IQueryBuilder::PARAM_STR)
						]);
					$qb->execute();
					$this->testForms[$index]['submissions'][$suIndex]['answers'][$aIndex]['id'] = $qb->getLastInsertId();
				}
			}
		}

		// Set up http Client
		$this->http = new Client([
			'base_uri' => 'http://localhost:8080/ocs/v2.php/apps/forms/',
			'auth' => ['test', 'test'],
			'headers' => [
				'OCS-ApiRequest' => 'true',
				'Accept' => 'application/json'
			],
		]);
	}

	/** Clean up database from testforms */
	public function tearDown(): void {
		$qb = TestCase::$realDatabase->getQueryBuilder();

		foreach ($this->testForms as $form) {
			$qb->delete('forms_v2_forms')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($form['id'], IQueryBuilder::PARAM_INT)));
			$qb->execute();

			foreach ($form['questions'] as $question) {
				$qb->delete('forms_v2_questions')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($question['id'], IQueryBuilder::PARAM_INT)));
				$qb->execute();

				foreach ($question['options'] as $option) {
					$qb->delete('forms_v2_options')
						->where($qb->expr()->eq('id', $qb->createNamedParameter($option['id'], IQueryBuilder::PARAM_INT)));
					$qb->execute();
				}
			}

			foreach ($form['shares'] as $share) {
				$qb->delete('forms_v2_shares')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($share['id'], IQueryBuilder::PARAM_INT)));
				$qb->execute();
			}

			if (isset($form['submissions'])) {
				foreach ($form['submissions'] as $submission) {
					$qb->delete('forms_v2_submissions')
						->where($qb->expr()->eq('id', $qb->createNamedParameter($submission['id'], IQueryBuilder::PARAM_INT)));
					$qb->execute();

					foreach ($submission['answers'] as $answer) {
						$qb->delete('forms_v2_answers')
							->where($qb->expr()->eq('id', $qb->createNamedParameter($answer['id'], IQueryBuilder::PARAM_INT)));
						$qb->execute();
					}
				}
			}
		}

		parent::tearDown();
	}

	// Small Wrapper for OCS-Response
	private function OcsResponse2Data($resp) {
		$arr = json_decode($resp->getBody()->getContents(), true);
		return $arr['ocs']['data'];
	}

	// Unset Id, as we can not control it on the tests.
	private function arrayUnsetId(array $arr): array {
		foreach ($arr as $index => $elem) {
			unset($arr[$index]['id']);
		}
		return $arr;
	}

	public function dataGetForms() {
		return [
			'getTestforms' => [
				'expected' => [[
					'hash' => 'abcdefg',
					'title' => 'Title of a Form',
					'expires' => 0,
					'permissions' => [
						'edit',
						'results',
						'submit'
					],
					'partial' => true
				]]
			]
		];
	}
	/**
	 * @dataProvider dataGetForms
	 *
	 * @param array $expected
	 */
	public function testGetForms(array $expected): void {
		$resp = $this->http->request('GET', 'api/v2/forms');

		$data = $this->OcsResponse2Data($resp);
		$data = $this->arrayUnsetId($data);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	public function dataGetSharedForms() {
		return [
			'getTestforms' => [
				'expected' => [[
					'hash' => 'abcdefghij',
					'title' => 'Title of a second Form',
					'expires' => 0,
					'permissions' => [
						'submit'
					],
					'partial' => true
				]]
			]
		];
	}
	/**
	 * @dataProvider dataGetSharedForms
	 *
	 * @param array $expected
	 */
	public function testGetSharedForms(array $expected): void {
		$resp = $this->http->request('GET', 'api/v2/shared_forms');

		$data = $this->OcsResponse2Data($resp);
		$data = $this->arrayUnsetId($data);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	public function dataGetPartialForm() {
		return [
			'getPartialForm' => [
				'expected' => [
					'hash' => 'abcdefghij',
					'title' => 'Title of a second Form',
					'expires' => 0,
					'permissions' => [
						'submit'
					],
					'partial' => true
				]
			]
		];
	}
	/**
	 * @dataProvider dataGetPartialForm
	 *
	 * @param array $expected
	 */
	public function testGetPartialForm(array $expected): void {
		$resp = $this->http->request('GET', "api/v2/partial_form/{$this->testForms[1]['hash']}");

		$data = $this->OcsResponse2Data($resp);
		unset($data['id']);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	public function dataGetNewForm() {
		return [
			'getNewForm' => [
				'expected' => [
					// 'hash' => Some random, cannot be checked.
					'title' => '',
					'description' => '',
					'ownerId' => 'test',
					// 'created' => Hard to check exactly.
					'access' => [
						'permitAllUsers' => false,
						'showToAllUsers' => false
					],
					'expires' => 0,
					'isAnonymous' => false,
					'submitOnce' => true,
					'canSubmit' => true,
					'permissions' => [
						'edit',
						'results',
						'submit'
					],
					'questions' => [],
					'shares' => [],
				]
			]
		];
	}
	/**
	 * @dataProvider dataGetNewForm
	 *
	 * @param array $expected
	 */
	public function testGetNewForm(array $expected): void {
		$resp = $this->http->request('POST', 'api/v2/form');
		$data = $this->OcsResponse2Data($resp);

		// Store for deletion on tearDown
		$this->testForms[] = $data;

		// Cannot control id
		unset($data['id']);
		// Check general behaviour of hash
		$this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{16}$/', $data['hash']);
		unset($data['hash']);
		// Check general behaviour of created (Created in the last 10 seconds)
		$this->assertTrue(time() - $data['created'] < 10);
		unset($data['created']);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	public function dataGetFullForm() {
		return [
			'getFullForm' => [
				'expected' => [
					'hash' => 'abcdefg',
					'title' => 'Title of a Form',
					'description' => 'Just a simple form.',
					'ownerId' => 'test',
					'created' => 12345,
					'access' => [
						'permitAllUsers' => false,
						'showToAllUsers' => false
					],
					'expires' => 0,
					'isAnonymous' => false,
					'submitOnce' => true,
					'canSubmit' => true,
					'permissions' => [
						'edit',
						'results',
						'submit'
					],
					'questions' => [
						[
							'type' => 'short',
							'text' => 'First Question?',
							'isRequired' => true,
							'order' => 1,
							'options' => []
						],
						[
							'type' => 'multiple_unique',
							'text' => 'Second Question?',
							'isRequired' => false,
							'order' => 2,
							'options' => [
								[
									'text' => 'Option 1'
								],
								[
									'text' => 'Option 2'
								]
							]
						]
					],
					'shares' => [
						[
							'shareType' => 0,
							'shareWith' => 'user1',
							'displayName' => ''
						],
						[
							'shareType' => 3,
							'shareWith' => 'shareHash',
							'displayName' => ''
						],
					],
				]
			]
		];
	}
	/**
	 * @dataProvider dataGetFullForm
	 *
	 * @param array $expected
	 */
	public function testGetFullForm(array $expected): void {
		$resp = $this->http->request('GET', "api/v2/form/{$this->testForms[0]['id']}");
		$data = $this->OcsResponse2Data($resp);

		// Cannot control ids, but check general consistency.
		foreach ($data['questions'] as $qIndex => $question) {
			$this->assertEquals($data['id'], $question['formId']);
			unset($data['questions'][$qIndex]['formId']);

			foreach ($question['options'] as $oIndex => $option) {
				$this->assertEquals($question['id'], $option['questionId']);
				unset($data['questions'][$qIndex]['options'][$oIndex]['questionId']);
				unset($data['questions'][$qIndex]['options'][$oIndex]['id']);
			}
			unset($data['questions'][$qIndex]['id']);
		}
		foreach ($data['shares'] as $sIndex => $share) {
			$this->assertEquals($data['id'], $share['formId']);
			unset($data['shares'][$sIndex]['formId']);
			unset($data['shares'][$sIndex]['id']);
		}
		unset($data['id']);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	public function dataCloneForm() {
		$fullFormExpected = $this->dataGetFullForm()['getFullForm']['expected'];
		// Compared to full form expected, update changed properties
		$fullFormExpected['title'] = 'Title of a Form - Copy';
		$fullFormExpected['shares'] = [];
		// Compared to full form expected, unset unpredictable properties. These will be checked logically.
		unset($fullFormExpected['id']);
		unset($fullFormExpected['hash']);
		unset($fullFormExpected['created']);
		foreach ($fullFormExpected['questions'] as $qIndex => $question) {
			unset($fullFormExpected['questions'][$qIndex]['formId']);
		}

		return [
			'updateFormProps' => [
				'expected' => $fullFormExpected
			]
		];
	}
	/**
	 * @dataProvider dataCloneForm
	 *
	 * @param array $expected
	 */
	public function testCloneForm(array $expected): void {
		$resp = $this->http->request('POST', "api/v2/form/clone/{$this->testForms[0]['id']}");
		$data = $this->OcsResponse2Data($resp);

		// Store for deletion on tearDown
		$this->testForms[] = $data;

		// Cannot control ids, but check general consistency.
		foreach ($data['questions'] as $qIndex => $question) {
			$this->assertEquals($data['id'], $question['formId']);
			unset($data['questions'][$qIndex]['formId']);

			foreach ($question['options'] as $oIndex => $option) {
				$this->assertEquals($question['id'], $option['questionId']);
				unset($data['questions'][$qIndex]['options'][$oIndex]['questionId']);
				unset($data['questions'][$qIndex]['options'][$oIndex]['id']);
			}
			unset($data['questions'][$qIndex]['id']);
		}
		foreach ($data['shares'] as $sIndex => $share) {
			$this->assertEquals($data['id'], $share['formId']);
			unset($data['shares'][$sIndex]['formId']);
			unset($data['shares'][$sIndex]['id']);
		}
		// Check not just returning source-form (id must differ).
		$this->assertGreaterThan($this->testForms[0]['id'], $data['id']);
		unset($data['id']);

		// Check general behaviour of hash
		$this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{16}$/', $data['hash']);
		unset($data['hash']);
		// Check general behaviour of created (Created in the last 10 seconds)
		$this->assertTrue(time() - $data['created'] < 10);
		unset($data['created']);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	public function dataUpdateFormProperties() {
		$fullFormExpected = $this->dataGetFullForm()['getFullForm']['expected'];
		$fullFormExpected['title'] = 'This is my NEW Title!';
		$fullFormExpected['access'] = [
			'permitAllUsers' => true,
			'showToAllUsers' => true
		];
		return [
			'updateFormProps' => [
				'expected' => $fullFormExpected
			]
		];
	}
	/**
	 * @dataProvider dataUpdateFormProperties
	 *
	 * @param array $expected
	 */
	public function testUpdateFormProperties(array $expected): void {
		$resp = $this->http->request('POST', 'api/v2/form/update', [
			'json' => [
				'id' => $this->testForms[0]['id'],
				'keyValuePairs' => [
					'title' => 'This is my NEW Title!',
					'access' => [
						'permitAllUsers' => true,
						'showToAllUsers' => true
					]
				]
			]
		]);
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['id'], $data);

		// Check if form equals updated form.
		$this->testGetFullForm($expected);
	}

	public function testDeleteForm() {
		$resp = $this->http->request('DELETE', "api/v2/form/{$this->testForms[0]['id']}");
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['id'], $data);

		// Check if not existent anymore.
		try {
			$this->http->request('GET', "api/v2/form/{$this->testForms[0]['id']}");
		} catch (ClientException $e) {
			$resp = $e->getResponse();
		}
		$this->assertEquals(400, $resp->getStatusCode());
	}

	public function dataCreateNewQuestion() {
		return [
			'newQuestion' => [
				'expected' => [
					// 'formId' => 3, // Checked during test
					// 'order' => 3, // Checked during test
					'type' => 'short',
					'isRequired' => false,
					'text' => 'Already some Question?',
					'options' => []
				]
			]
		];
	}
	/**
	 * @dataProvider dataCreateNewQuestion
	 *
	 * @param array $expected
	 */
	public function testCreateNewQuestion(array $expected): void {
		$resp = $this->http->request('POST', 'api/v2/question', [
			'json' => [
				'formId' => $this->testForms[0]['id'],
				'type' => 'short',
				'text' => 'Already some Question?'
			]
		]);
		$data = $this->OcsResponse2Data($resp);

		// Store for deletion on tearDown
		$this->testForms[0]['questions'][] = $data;

		// Check formId & order
		$this->assertEquals($this->testForms[0]['id'], $data['formId']);
		unset($data['formId']);
		$this->assertEquals(sizeof($this->testForms[0]['questions']), $data['order']);
		unset($data['order']);
		unset($data['id']);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	public function dataUpdateQuestionProperties() {
		$fullFormExpected = $this->dataGetFullForm()['getFullForm']['expected'];
		$fullFormExpected['questions'][0]['text'] = 'Still first Question!';
		$fullFormExpected['questions'][0]['isRequired'] = false;

		return [
			'updateQuestionProps' => [
				'fullFormExpected' => $fullFormExpected
			]
		];
	}
	/**
	 * @dataProvider dataUpdateQuestionProperties
	 *
	 * @param array $fullFormExpected
	 */
	public function testUpdateQuestionProperties(array $fullFormExpected): void {
		$resp = $this->http->request('POST', 'api/v2/question/update', [
			'json' => [
				'id' => $this->testForms[0]['questions'][0]['id'],
				'keyValuePairs' => [
					'isRequired' => false,
					'text' => 'Still first Question!'
				]
			]
		]);
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['questions'][0]['id'], $data);

		// Check if form equals updated form.
		$this->testGetFullForm($fullFormExpected);
	}

	public function dataReorderQuestions() {
		$fullFormExpected = $this->dataGetFullForm()['getFullForm']['expected'];
		$fullFormExpected['questions'][0]['order'] = 2;
		$fullFormExpected['questions'][1]['order'] = 1;

		// Exchange questions, as they will be returned in new order.
		$tmp = $fullFormExpected['questions'][0];
		$fullFormExpected['questions'][0] = $fullFormExpected['questions'][1];
		$fullFormExpected['questions'][1] = $tmp;

		return [
			'updateQuestionProps' => [
				'fullFormExpected' => $fullFormExpected
			]
		];
	}
	/**
	 * @dataProvider dataReorderQuestions
	 *
	 * @param array $fullFormExpected
	 */
	public function testReorderQuestions(array $fullFormExpected): void {
		$resp = $this->http->request('POST', 'api/v2/question/reorder', [
			'json' => [
				'formId' => $this->testForms[0]['id'],
				'newOrder' => [
					$this->testForms[0]['questions'][1]['id'],
					$this->testForms[0]['questions'][0]['id']
				]
			]
		]);
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals([
			$this->testForms[0]['questions'][0]['id'] => [ 'order' => 2 ],
			$this->testForms[0]['questions'][1]['id'] => [ 'order' => 1 ]
		], $data);

		// Check if form equals updated form.
		$this->testGetFullForm($fullFormExpected);
	}

	public function dataDeleteQuestion() {
		$fullFormExpected = $this->dataGetFullForm()['getFullForm']['expected'];
		array_splice($fullFormExpected['questions'], 0, 1);
		$fullFormExpected['questions'][0]['order'] = 1;

		return [
			'deleteQuestion' => [
				'fullFormExpected' => $fullFormExpected
			]
		];
	}
	/**
	 * @dataProvider dataDeleteQuestion
	 *
	 * @param array $fullFormExpected
	 */
	public function testDeleteQuestion(array $fullFormExpected) {
		$resp = $this->http->request('DELETE', "api/v2/question/{$this->testForms[0]['questions'][0]['id']}");
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['questions'][0]['id'], $data);

		$this->testGetFullForm($fullFormExpected);
	}

	public function dataCreateNewOption() {
		return [
			'newOption' => [
				'expected' => [
					// 'questionId' => Done dynamically below.
					'text' => 'A new Option.'
				]
			]
		];
	}
	/**
	 * @dataProvider dataCreateNewOption
	 *
	 * @param array $expected
	 */
	public function testCreateNewOption(array $expected): void {
		$resp = $this->http->request('POST', 'api/v2/option', [
			'json' => [
				'questionId' => $this->testForms[0]['questions'][1]['id'],
				'text' => 'A new Option.'
			]
		]);
		$data = $this->OcsResponse2Data($resp);

		// Store for deletion on tearDown
		$this->testForms[0]['questions'][1]['options'][] = $data;

		// Check questionId
		$this->assertEquals($this->testForms[0]['questions'][1]['id'], $data['questionId']);
		unset($data['questionId']);
		unset($data['id']);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	public function dataUpdateOptionProperties() {
		$fullFormExpected = $this->dataGetFullForm()['getFullForm']['expected'];
		$fullFormExpected['questions'][1]['options'][0]['text'] = 'New option Text.';

		return [
			'updateOptionProps' => [
				'fullFormExpected' => $fullFormExpected
			]
		];
	}
	/**
	 * @dataProvider dataUpdateOptionProperties
	 *
	 * @param array $fullFormExpected
	 */
	public function testUpdateOptionProperties(array $fullFormExpected): void {
		$resp = $this->http->request('POST', 'api/v2/option/update', [
			'json' => [
				'id' => $this->testForms[0]['questions'][1]['options'][0]['id'],
				'keyValuePairs' => [
					'text' => 'New option Text.'
				]
			]
		]);
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['questions'][1]['options'][0]['id'], $data);

		// Check if form equals updated form.
		$this->testGetFullForm($fullFormExpected);
	}

	public function dataDeleteOption() {
		$fullFormExpected = $this->dataGetFullForm()['getFullForm']['expected'];
		array_splice($fullFormExpected['questions'][1]['options'], 0, 1);

		return [
			'deleteOption' => [
				'fullFormExpected' => $fullFormExpected
			]
		];
	}
	/**
	 * @dataProvider dataDeleteOption
	 *
	 * @param array $fullFormExpected
	 */
	public function testDeleteOption(array $fullFormExpected) {
		$resp = $this->http->request('DELETE', "api/v2/option/{$this->testForms[0]['questions'][1]['options'][0]['id']}");
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['questions'][1]['options'][0]['id'], $data);

		$this->testGetFullForm($fullFormExpected);
	}

	public function dataAddShare() {
		return [
			'addAShare' => [
				'expected' => [
					// 'formId' => Checked dynamically
					'shareType' => 0,
					'shareWith' => 'test',
					'displayName' => 'Test Displayname'
				]
			]
		];
	}
	/**
	 * @dataProvider dataAddShare
	 *
	 * @param array $expected
	 */
	public function testAddShare(array $expected) {
		$resp = $this->http->request('POST', 'api/v2/share', [
			'json' => [
				'formId' => $this->testForms[0]['id'],
				'shareType' => 0,
				'shareWith' => 'test'
			]
		]);
		$data = $this->OcsResponse2Data($resp);

		// Store for cleanup
		$this->testForms[0]['shares'][] = $data;

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['id'], $data['formId']);
		unset($data['formId']);
		unset($data['id']);
		$this->assertEquals($expected, $data);
	}

	public function dataDeleteShare() {
		$fullFormExpected = $this->dataGetFullForm()['getFullForm']['expected'];
		array_splice($fullFormExpected['shares'], 0, 1);

		return [
			'deleteShare' => [
				'fullFormExpected' => $fullFormExpected
			]
		];
	}
	/**
	 * @dataProvider dataDeleteShare
	 *
	 * @param array $fullFormExpected
	 */
	public function testDeleteShare(array $fullFormExpected) {
		$resp = $this->http->request('DELETE', "api/v2/share/{$this->testForms[0]['shares'][0]['id']}");
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['shares'][0]['id'], $data);

		$this->testGetFullForm($fullFormExpected);
	}

	public function dataGetSubmissions() {
		return [
			'getSubmissions' => [
				'expected' => [
					'submissions' => [
						[
							// 'formId' => Checked dynamically
							'userId' => 'user1',
							'userDisplayName' => 'user1',
							'timestamp' => 123456,
							'answers' => [
								[
									// 'submissionId' => Checked dynamically
									// 'questionId' => Checked dynamically
									'text' => 'This is a short answer.'
								],
								[
									// 'submissionId' => Checked dynamically
									// 'questionId' => Checked dynamically
									'text' => 'Option 1'
								]
							]
						],
						[
							// 'formId' => Checked dynamically
							'userId' => 'user2',
							'userDisplayName' => 'user2',
							'timestamp' => 12345,
							'answers' => [
								[
									// 'submissionId' => Checked dynamically
									// 'questionId' => Checked dynamically
									'text' => 'This is another short answer.'
								],
								[
									// 'submissionId' => Checked dynamically
									// 'questionId' => Checked dynamically
									'text' => 'Option 2'
								]
							]
						]
					],
					'questions' => $this->dataGetFullForm()['getFullForm']['expected']['questions']
				]
			]
		];
	}
	/**
	 * @dataProvider dataGetSubmissions
	 *
	 * @param array $expected
	 */
	public function testGetSubmissions(array $expected) {
		$resp = $this->http->request('GET', "api/v2/submissions/{$this->testForms[0]['hash']}");
		$data = $this->OcsResponse2Data($resp);

		// Cannot control ids, but check general consistency.
		foreach ($data['submissions'] as $sIndex => $submission) {
			$this->assertEquals($this->testForms[0]['id'], $submission['formId']);
			unset($data['submissions'][$sIndex]['formId']);

			foreach ($submission['answers'] as $aIndex => $answer) {
				$this->assertEquals($submission['id'], $answer['submissionId']);
				$this->assertEquals($this->testForms[0]['questions'][
					$this->testForms[0]['submissions'][$sIndex]['answers'][$aIndex]['questionIndex']
				]['id'], $answer['questionId']);
				unset($data['submissions'][$sIndex]['answers'][$aIndex]['submissionId']);
				unset($data['submissions'][$sIndex]['answers'][$aIndex]['questionId']);
				unset($data['submissions'][$sIndex]['answers'][$aIndex]['id']);
			}
			unset($data['submissions'][$sIndex]['id']);
		}
		foreach ($data['questions'] as $qIndex => $question) {
			$this->assertEquals($this->testForms[0]['id'], $question['formId']);
			unset($data['questions'][$qIndex]['formId']);

			foreach ($question['options'] as $oIndex => $option) {
				$this->assertEquals($question['id'], $option['questionId']);
				unset($data['questions'][$qIndex]['options'][$oIndex]['questionId']);
				unset($data['questions'][$qIndex]['options'][$oIndex]['id']);
			}
			unset($data['questions'][$qIndex]['id']);
		}

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($expected, $data);
	}

	public function dataExportSubmissions() {
		return [
			'exportSubmissions' => [
				'expected' => '
					"User display name","Timestamp","First Question?","Second Question?"
					"user1","Friday, January 2, 1970 at 10:17:36 AM GMT+0:00","This is a short answer.","Option 1"
					"user2","Thursday, January 1, 1970 at 3:25:45 AM GMT+0:00","This is another short answer.","Option 2"'
			]
		];
	}
	/**
	 * @dataProvider dataExportSubmissions
	 *
	 * @param array $expected
	 */
	public function testExportSubmissions(string $expected) {
		$resp = $this->http->request('GET', "api/v2/submissions/export/{$this->testForms[0]['hash']}");
		$data = substr($resp->getBody()->getContents(), 3); // Some strange Character removed at the beginning

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals('attachment; filename="Title of a Form (responses).csv"', $resp->getHeaders()['Content-Disposition'][0]);
		$this->assertEquals('text/csv;charset=UTF-8', $resp->getHeaders()['Content-type'][0]);
		$arr_txt_expected = preg_split('/,/', str_replace(["\t", "\n"], '', $expected));
		$arr_txt_data = preg_split('/,/', str_replace(["\t", "\n"], '', $data));
		$this->assertEquals($arr_txt_expected, $arr_txt_data);
	}

	public function testExportToCloud() {
		$resp = $this->http->request('POST', 'api/v2/submissions/export', [
			'json' => [
				'hash' => $this->testForms[0]['hash'],
				'path' => ''
			]]
		);
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals('Title of a Form (responses).csv', $data);
	}

	public function dataDeleteSubmissions() {
		$submissionsExpected = $this->dataGetSubmissions()['getSubmissions']['expected'];
		$submissionsExpected['submissions'] = [];

		return [
			'deleteSubmissions' => [
				'submissionsExpected' => $submissionsExpected
			]
		];
	}
	/**
	 * @dataProvider dataDeleteSubmissions
	 *
	 * @param array $submissionsExpected
	 */
	public function testDeleteSubmissions(array $submissionsExpected) {
		$resp = $this->http->request('DELETE', "api/v2/submissions/{$this->testForms[0]['id']}");
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['id'], $data);

		$this->testGetSubmissions($submissionsExpected);
	}

	public function dataInsertSubmission() {
		$submissionsExpected = $this->dataGetSubmissions()['getSubmissions']['expected'];
		$submissionsExpected['submissions'][] = [
			'userId' => 'test'
		];

		return [
			'insertSubmission' => [
				'submissionsExpected' => $submissionsExpected
			]
		];
	}
	/**
	 * @dataProvider dataInsertSubmission
	 *
	 * @param array $submissionsExpected
	 */
	public function testInsertSubmission(array $submissionsExpected) {
		$resp = $this->http->request('POST', 'api/v2/submission/insert', [
			'json' => [
				'formId' => $this->testForms[0]['id'],
				'answers' => [
					$this->testForms[0]['questions'][0]['id'] => ['ShortAnswer!'],
					$this->testForms[0]['questions'][1]['id'] => [
						$this->testForms[0]['questions'][1]['options'][0]['id']
					]
				]
			]
		]);
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());

		// Check stored submissions
		$resp = $this->http->request('GET', "api/v2/submissions/{$this->testForms[0]['hash']}");
		$data = $this->OcsResponse2Data($resp);

		// Store for deletion
		$this->testForms[0]['submissions'][] = $data['submissions'][0];

		// Check Ids
		foreach ($data['submissions'][0]['answers'] as $aIndex => $answer) {
			$this->assertEquals($data['submissions'][0]['id'], $answer['submissionId']);
			unset($data['submissions'][0]['answers'][$aIndex]['id']);
			unset($data['submissions'][0]['answers'][$aIndex]['submissionId']);
		}
		unset($data['submissions'][0]['id']);
		// Check general behaviour of timestamp (Insert in the last 10 seconds)
		$this->assertTrue(time() - $data['submissions'][0]['timestamp'] < 10);
		unset($data['submissions'][0]['timestamp']);

		$this->assertEquals([
			'userId' => 'test',
			'userDisplayName' => 'Test Displayname',
			'formId' => $this->testForms[0]['id'],
			'answers' => [
				[
					'questionId' => $this->testForms[0]['questions'][0]['id'],
					'text' => 'ShortAnswer!'
				],
				[
					'questionId' => $this->testForms[0]['questions'][1]['id'],
					'text' => 'Option 1'
				]
			]
		], $data['submissions'][0]);
	}

	public function dataDeleteSingleSubmission() {
		$submissionsExpected = $this->dataGetSubmissions()['getSubmissions']['expected'];
		array_splice($submissionsExpected['submissions'], 0, 1);

		return [
			'deleteSingleSubmission' => [
				'submissionsExpected' => $submissionsExpected
			]
		];
	}
	/**
	 * @dataProvider dataDeleteSingleSubmission
	 *
	 * @param array $submissionsExpected
	 */
	public function testDeleteSingleSubmission(array $submissionsExpected) {
		$resp = $this->http->request('DELETE', "api/v2/submission/{$this->testForms[0]['submissions'][0]['id']}");
		$data = $this->OcsResponse2Data($resp);

		$this->assertEquals(200, $resp->getStatusCode());
		$this->assertEquals($this->testForms[0]['submissions'][0]['id'], $data);

		$this->testGetSubmissions($submissionsExpected);
	}
};
