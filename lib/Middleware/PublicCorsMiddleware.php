<?php
/**
 * @copyright Copyright (c) 2023 Jonas Rittershofer <jotoeri@users.noreply.github.com>
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

namespace OCA\Forms\Middleware;

use OC\AppFramework\Middleware\Security\Exceptions\SecurityException;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IRequest;
use OCP\IUserSession;

class PublicCorsMiddleware extends Middleware {
	/** @var IRequest  */
	private $request;
	/** @var IControllerMethodReflector */
	private $reflector;
	/** @var IUserSession */
	private $userSession;

	/**
	 * @param IRequest $request
	 * @param IControllerMethodReflector $reflector
	 * @param IUserSession $session
	 */
	public function __construct(IRequest $request,
		IControllerMethodReflector $reflector,
		IUserSession $userSession) {
		$this->request = $request;
		$this->reflector = $reflector;
		$this->userSession = $userSession;
	}

	/**
	 * Copied and modified version of the CORSMiddleware beforeController.
	 * Most significantly it also works with the PublicPage annotation.
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @throws SecurityException
	 */
	public function beforeController($controller, $methodName) {
		// ensure that @CORS annotated API routes are not used in conjunction
		// with session authentication since this enables CSRF attack vectors
		if ($this->reflector->hasAnnotation('PublicCORSFix')) {
			$user = array_key_exists('PHP_AUTH_USER', $this->request->server) ? $this->request->server['PHP_AUTH_USER'] : null;
			$pass = array_key_exists('PHP_AUTH_PW', $this->request->server) ? $this->request->server['PHP_AUTH_PW'] : null;

			// Allow to use the current session if a CSRF token is provided
			if ($this->request->passesCSRFCheck()) {
				return;
			}
			$this->userSession->logout();
			if ($user === null || $pass === null || !$this->userSession->login($user, $pass)) {
				throw new SecurityException('CORS requires basic auth', Http::STATUS_UNAUTHORIZED);
			}
		}
	}

	/**
	 * If an SecurityException is being caught return a JSON error response
	 *
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param \Exception $exception the thrown exception
	 * @throws \Exception the passed in exception if it can't handle it
	 * @return Response a Response object or null in case that the exception could not be handled
	 */
	public function afterException($controller, $methodName, \Exception $exception) {
		if ($exception instanceof SecurityException) {
			$response = new JSONResponse(['message' => $exception->getMessage()]);
			if ($exception->getCode() !== 0) {
				$response->setStatus($exception->getCode());
			} else {
				$response->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			return $response;
		}

		throw $exception;
	}
}
