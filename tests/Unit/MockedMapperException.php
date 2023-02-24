<?php

namespace OCA\Forms\Tests\Unit;

use OCP\AppFramework\Db\IMapperException;

/**
 * Simple wrapper over IMapperException to implement Throwable, needed for throwing a mocked exception
 */
interface MockedMapperException extends IMapperException, \Throwable {
}
