<?php

declare(strict_types=1);

/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Middleware;

use Exception;
use OCA\Forms\Exception\NoSuchFormException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Middleware;

/**
 * Simple middleware to throttle requests after invalid form access
 */
class ThrottleFormAccessMiddleware extends Middleware {

	public function afterException(Controller $controller, string $methodName, Exception $exception) {
		if (!($exception instanceof NoSuchFormException)) {
			throw $exception;
		}

		$response = new DataResponse(
			$exception->getMessage(),
			$exception->getCode(),
		);
		$response->throttle(['action' => 'form']);
		return $response;
	}
}
