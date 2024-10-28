<?php
/**
 * @copyright Copyright (c) 2024 Christian Hartmann <chris-hartmann@gmx.de>
 *
 * @author Christian Hartmann <chris-hartmann@gmx.de>
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

namespace OCA\Forms;

/**
 * @psalm-type FormsPartialForm = array{
 *   id: int,
 *   hash: string,
 *   title: string,
 *   expires: int,
 *   permissions: string[],
 *   partial: bool,
 *   state: int
 * }
 *
 * @psalm-type FormsOption = array{
 *   id: int,
 *   questionId: int,
 *   text: string,
 *   order: ?int
 * }
 *
 * @psalm-type FormsQuestion = array{
 *   id: int,
 *   formId: int,
 *   order: int,
 *   type: string,
 *   isRequired: bool,
 *   text: string,
 *   name: string,
 *   options: array<FormsOption>,
 *   accept: string[],
 *   extraSettings: \stdClass
 * }
 *
 * @psalm-type FormsAnswer = array{
 *   id: int,
 *   submissionId: int,
 *   questionId: int,
 *   text: string
 * }
 *
 * @psalm-type FormsSubmission = array{
 *   id: int,
 *   formId: int,
 *   userId: string,
 *   timestamp: int,
 *   answers: array<FormsAnswer>,
 *   userDisplayName: string
 * }
 *
 * @psalm-type FormsSubmissions = array{
 *   submissions: array<FormsSubmission>,
 *   questions: array<FormsQuestion>
 * }
 *
 * @psalm-type FormsForm = array{
 *   id: int,
 *   hash: string,
 *   title: string,
 *   description: string,
 *   ownerId: string,
 *   created: int,
 *   access: \stdClass,
 *   expires: int,
 *   isAnonymous: bool,
 *   submitMultiple: bool,
 *   showExpiration: bool,
 *   canSubmit: bool,
 *   permissions: string[],
 *   questions: array<FormsQuestion>,
 *   state: int,
 *   shares: string[],
 *   submissions: array<FormsSubmission>,
 * }
 *
 * @psalm-type FormsUploadedFile = array{
 *   uploadedFileId: int,
 *   fileName: string
 * }
 *
 * @psalm-type FormsShare = array{
 *   id: int,
 *   formId: int,
 *   shareType: int,
 *   shareWith: string,
 *   permissions: string[],
 *   displayName: string
 * }
 */
class ResponseDefinitions {
}
