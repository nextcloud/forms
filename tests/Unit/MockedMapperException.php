<?php

/**
 * SPDX-FileCopyrightText: 2023 Ferdinand Thiessen <rpm@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit;

use OCP\AppFramework\Db\IMapperException;

/**
 * Simple wrapper over IMapperException to implement Throwable, needed for throwing a mocked exception
 */
interface MockedMapperException extends IMapperException, \Throwable {
}
