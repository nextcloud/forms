<?php

// reference for: use OCA\Analytics\Datasource\DatasourceEvent;
namespace OCA\Analytics\Datasource {
	class DatasourceEvent extends \OCP\EventDispatcher\Event {
		abstract public function registerDatasource(string $datasource): void {
		}
		abstract public function getDataSources(): array {
		}
	}

	interface IDatasource {
		public function getName(): string;
		public function getId(): int;
		public function getTemplate(): array;
		public function readData($option): array;
	}
}
