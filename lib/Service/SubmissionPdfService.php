<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\Submission;
use OCP\IL10N;

class SubmissionPdfService {
	private const MAX_LINES_PER_PAGE = 48;

	public function __construct(
		private IL10N $l10n,
	) {
	}

	/**
	 * @param array<int, array{question: string, answer: string}> $answerEntries
	 */
	public function createPdf(Form $form, Submission $submission, array $answerEntries = []): string {
		$submissionTimestamp = max(0, $submission->getTimestamp());
		$headerLines = [
			$this->l10n->t('Nextcloud Forms submission'),
			$this->l10n->t('Form: %s', [$form->getTitle()]),
			$this->l10n->t('Submission ID: %s', [(string)$submission->getId()]),
			$this->l10n->t('Submitted at (UTC): %s', [gmdate('Y-m-d H:i:s', $submissionTimestamp)]),
			'',
			$this->l10n->t('Responses:'),
		];

		$responseLines = [];
		if ($answerEntries === []) {
			$responseLines[] = '- ' . $this->l10n->t('No responses captured');
		} else {
			foreach ($answerEntries as $entry) {
				$responseLines[] = '- ' . $entry['question'] . ':';

				$normalizedAnswer = str_replace(["\r\n", "\r"], "\n", $entry['answer']);
				foreach (explode("\n", $normalizedAnswer) as $answerLine) {
					$responseLines[] = '  ' . ($answerLine === '' ? '[empty line]' : $answerLine);
				}
			}
		}

		$lines = array_merge($headerLines, $responseLines);
		$pdfLines = $this->normalizePdfLines($lines);
		$contentStreams = array_map(
			fn (array $pageLines): string => $this->createContentStream($pageLines),
			$this->paginatePdfLines($pdfLines),
		);

		return $this->assemblePdf($contentStreams);
	}

	public function createFilename(Form $form, Submission $submission): string {
		$title = trim($form->getTitle());
		$base = $title === '' ? 'form' : $title;
		$base = preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '_', $base) ?? 'form';
		$base = preg_replace('/\s+/', '_', trim($base)) ?? 'form';
		$base = trim($base, '._-');

		if ($base === '') {
			$base = 'form';
		}

		return sprintf('%s-submission-%d.pdf', $base, $submission->getId());
	}

	/**
	 * @param list<string> $lines
	 * @return list<string>
	 */
	private function normalizePdfLines(array $lines): array {
		$normalizedLines = [];
		foreach ($lines as $line) {
			$encodedLine = $this->encodeLine($line);
			foreach ($this->wrapLine($encodedLine, 96) as $wrappedLine) {
				$normalizedLines[] = $wrappedLine;
			}
		}

		return $normalizedLines;
	}

	private function encodeLine(string $line): string {
		$encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $line);
		if ($encoded === false) {
			return '';
		}

		return $encoded;
	}

	/**
	 * @return list<string>
	 */
	private function wrapLine(string $line, int $limit): array {
		if ($line === '') {
			return [''];
		}

		$words = preg_split('/\s+/', $line) ?: [];
		$current = '';
		$result = [];

		foreach ($words as $word) {
			if ($word === '') {
				continue;
			}

			$candidate = $current === '' ? $word : $current . ' ' . $word;
			if (strlen($candidate) <= $limit) {
				$current = $candidate;
				continue;
			}

			if ($current !== '') {
				$result[] = $current;
				$current = '';
			}

			while (strlen($word) > $limit) {
				$result[] = substr($word, 0, $limit);
				$word = substr($word, $limit);
			}

			$current = $word;
		}

		if ($current !== '') {
			$result[] = $current;
		}

		return $result === [] ? [''] : $result;
	}

	/**
	 * @param list<string> $pdfLines
	 * @return list<list<string>>
	 */
	private function paginatePdfLines(array $pdfLines): array {
		$pages = array_chunk($pdfLines, self::MAX_LINES_PER_PAGE);
		return $pages === [] ? [['']] : $pages;
	}

	/**
	 * @param list<string> $pdfLines
	 */
	private function createContentStream(array $pdfLines): string {
		$content = "BT\n/F1 11 Tf\n14 TL\n50 792 Td\n";
		foreach ($pdfLines as $line) {
			$escapedLine = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
			$content .= '(' . $escapedLine . ") Tj\nT*\n";
		}
		$content .= 'ET';

		return $content;
	}

	/**
	 * @param list<string> $contentStreams
	 */
	private function assemblePdf(array $contentStreams): string {
		$pageCount = count($contentStreams);
		$fontObjectId = 3 + ($pageCount * 2);
		$pageObjectIds = [];

		$objects = [
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
			2 => '',
		];
		foreach ($contentStreams as $index => $contentStream) {
			$pageObjectId = 3 + ($index * 2);
			$contentObjectId = $pageObjectId + 1;
			$pageObjectIds[] = $pageObjectId;

			$objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '
				. $fontObjectId . ' 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
			$objects[$contentObjectId] = '<< /Length ' . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream";
		}
		$objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(
			static fn (int $pageObjectId): string => $pageObjectId . ' 0 R',
			$pageObjectIds,
		)) . '] /Count ' . $pageCount . ' >>';
		$objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

		ksort($objects);

		$pdf = "%PDF-1.4\n";
		$offsets = [0 => 0];

		foreach ($objects as $id => $object) {
			$offsets[$id] = strlen($pdf);
			$pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
		}

		$startXref = strlen($pdf);
		$objectCount = max(array_keys($objects)) + 1;
		$pdf .= "xref\n0 " . $objectCount . "\n";
		$pdf .= sprintf("%010d 65535 f \n", 0);
		for ($i = 1; $i < $objectCount; $i++) {
			$pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
		}

		$pdf .= "trailer\n<< /Size " . $objectCount . " /Root 1 0 R >>\n";
		$pdf .= "startxref\n" . $startXref . "\n%%EOF";

		return $pdf;
	}
}
