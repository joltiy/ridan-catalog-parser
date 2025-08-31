<?php

namespace Joltiy\RidanCatalogParser\Core;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RidanCatalogParser
{
    private string $baseDomain;
    private string $storagePath;
    private string $databasePath;
    private array $targetCategories;
    private LoggerInterface $logger;

    /**
     * @param array{
     *     baseDomain: string,
     *     storagePath: string,
     *     databasePath: string,
     *     targetCategories: array
     * } $configuration Конфигурация парсера
     * @param LoggerInterface|null $logger Логгер для записи событий
     */
    public function __construct(array $configuration, ?LoggerInterface $logger = null)
    {
        $this->baseDomain = $configuration['baseDomain'] ?? 'https://ridan.ru';
        $this->storagePath = $configuration['storagePath'] ?? '';
        $this->databasePath = $configuration['databasePath'] ?? '';
        $this->targetCategories = $configuration['targetCategories'] ?? [];
        $this->logger = $logger ?? new NullLogger();
        $this->validateConfiguration();
    }

    /**
     * Валидация конфигурации парсера
     * @throws \InvalidArgumentException
     */
    private function validateConfiguration(): void
    {

        if (empty($this->storagePath)) {
            throw new \InvalidArgumentException('Storage path cannot be empty');
        }

        if (empty($this->databasePath)) {
            throw new \InvalidArgumentException('Database path cannot be empty');
        }

        if (!filter_var($this->baseDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new \InvalidArgumentException('Invalid domain format');
        }
    }
}
