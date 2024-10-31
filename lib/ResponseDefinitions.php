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
 * @psalm-type FormsOption = array{
 *   id: int,
 *   questionId: int|float,
 *   text: string,
 *   order: ?int
 * }
 *
 * @psalm-type FormsOrder = array{
 *   order: int
 * }
 *
 * @psalm-type FormsQuestionExtraSettings = array{
 *   allowOtherAnswer?: ?bool,
 *   allowedFileExtensions?: ?list<string>,
 *   allowedFileTypes?: ?list<string>,
 *   maxAllowedFilesCount?: ?int,
 *   maxFileSize?: ?int,
 *   optionsLimitMax?: ?int,
 *   optionsLimitMin?: ?int,
 *   shuffleOptions?: ?bool,
 *   validationRegex?: ?string,
 *   validationType?: ?string
 * }
 *
 * @psalm-type FormsQuestionType = "dropdown"|"multiple"|"multiple_unique"|"date"|"time"|"short"|"long"|"file"|"datetime"
 *
 * @psalm-type FormsQuestion = array{
 *   id: int,
 *   formId: int,
 *   order: int,
 *   type: FormsQuestionType,
 *   isRequired: bool,
 *   text: string,
 *   name: string,
 *   description: string,
 *   extraSettings: FormsQuestionExtraSettings|\stdClass,
 *   options: list<FormsOption>,
 *   accept: list<string>,
 * }
 *
 * @psalm-type FormsAnswer = array{
 *   id: int,
 *   submissionId: int,
 *   fileId: ?int,
 *   questionId: int,
 *   text: string
 * }
 *
 * @psalm-type FormsSubmission = array{
 *   id: int,
 *   formId: int,
 *   userId: string,
 *   timestamp: int,
 *   answers: list<FormsAnswer>,
 *   userDisplayName: string
 * }
 *
 * @psalm-type FormsSubmissions = array{
 *   submissions: list<FormsSubmission>,
 *   questions: list<FormsQuestion>
 * }
 *
 * @psalm-type FormsAccess = array{
 *   permitAllUsers?: bool,
 *   showToAllUsers?: bool
 * }
 *
 * @psalm-type FormsPermission = "edit"|"results"|"results_delete"|"submit"|"embed"
 *
 * @psalm-type FormsShare = array{
 *   id: int,
 *   formId: int,
 *   shareType: int,
 *   shareWith: string,
 *   permissions: list<FormsPermission>,
 *   displayName: string
 * }
 *
 * @psalm-type FormsPartialForm = array{
 *   id: int,
 *   hash: string,
 *   title: string,
 *   expires: int,
 *   permissions: list<FormsPermission>,
 *   partial: true,
 *   state: int
 * }
 *
 * @psalm-type FormsForm = array{
 *   id: int,
 *   hash: string,
 *   title: string,
 *   description: string,
 *   ownerId: string,
 *   created: int,
 *   access: FormsAccess,
 *   expires: int,
 *   fileFormat: ?string,
 *   fileId: ?int,
 *   filePath?: ?string,
 *   isAnonymous: bool,
 *   lastUpdated: int,
 *   submitMultiple: bool,
 *   showExpiration: bool,
 *   canSubmit: bool,
 *   permissions: list<FormsPermission>,
 *   questions: list<FormsQuestion>,
 *   state: 0|1|2,
 *   shares: list<FormsShare>,
 *   submissionCount?: int,
 *   submissionMessage: ?string,
 * }
 *
 * @psalm-type FormsUploadedFile = array{
 *   uploadedFileId: int,
 *   fileName: string
 * }
 */
class ResponseDefinitions {
}
