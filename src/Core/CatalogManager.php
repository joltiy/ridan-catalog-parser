<?php

namespace Joltiy\RidanCatalogParser\Core;

use Joltiy\RidanCatalogParser\Core\FileDownloader;
use Joltiy\RidanCatalogParser\Core\LargeExcelProcessor;
use Joltiy\RidanCatalogParser\Repositories\ProductRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CatalogManager
{
    private string $baseDomain;
    private string $downloadPath;   // путь, куда сохраняется архив/файл
    private string $databasePath;   // путь к SQLite
    private array $categories;

    private LoggerInterface $logger;

    private FileDownloader $downloader;
    private LargeExcelProcessor $processor;
    private ProductRepository $repository;

    /**
     * @param array{
     *     baseDomain: string,
     *     downloadPath: string,
     *     databasePath: string,
     *     categories?: array<int>
     * } $config
     */
    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        $this->baseDomain   = $config['baseDomain']   ?? 'https://ridan.ru';
        $this->downloadPath = $config['downloadPath'] ?? '';
        $this->databasePath = $config['databasePath'] ?? '';
        $this->categories   = $config['categories']   ?? [];

        $this->logger = $logger ?? new NullLogger();

        if ($this->downloadPath === '' || $this->databasePath === '') {
            throw new \InvalidArgumentException('downloadPath и databasePath обязательны');
        }

        // Инициализация компонентов
        $this->downloader = new FileDownloader(
            $this->baseDomain,
            $this->downloadPath,
            $this->categories,
            $this->logger
        );

        $this->processor = new LargeExcelProcessor($this->databasePath, $this->logger);
        $this->repository = new ProductRepository($this->databasePath);
    }

    /**
     * Полный цикл: скачать (если нужно/или принудительно), распаковать при необходимости и обработать
     *
     * @param bool $forceDownload Принудительно скачать (игнорировать кэш)
     * @param bool $resetDatabase Очистить/инициализировать базу перед импортом
     * @return array Статистика БД после обработки
     */
    public function updateAndImport(bool $forceDownload = false, bool $resetDatabase = false): array
    {
        // Скачиваем файл (если нужно)
        if ($forceDownload) {
            // Удалим старый файл и скачаем заново
            $this->downloader->deleteOldFile();
            $ok = $this->downloader->download();
        } else {
            $ok = $this->downloader->checkAndDownload();
        }

        if (!$ok) {
            throw new \RuntimeException('Не удалось скачать файл каталога');
        }

        // Определяем тип скачанного файла
        $ext = strtolower(pathinfo($this->downloadPath, PATHINFO_EXTENSION));

        // Инициализация БД при необходимости
        if ($resetDatabase) {
            $this->processor->init();
        }

        // Если zip — распаковываем и обрабатываем все файлы в папке
        if ($ext === 'zip') {
            $this->downloader->unzip($this->processor);
        } else {
            // Иначе считаем, что это готовый Excel — обрабатываем напрямую
            $this->processor->processFile($this->downloadPath);
        }

        return $this->processor->getDatabaseStats();
    }

    /**
     * Только скачать (с учетом кэша)
     */
    public function downloadIfNeeded(): bool
    {
        return $this->downloader->checkAndDownload();
    }

    /**
     * Принудительно скачать
     */
    public function forceDownload(): bool
    {
        $this->downloader->deleteOldFile();
        return $this->downloader->download();
    }

    /**
     * Распаковать и обработать zip (инициализация БД внутри unzip уже вызывается)
     * Если нужен «чистый» импорт — передайте $resetDatabase = true в updateAndImport
     *
     * @return array Статистика после обработки
     */
    public function unzipAndProcess(): array
    {
        $this->downloader->unzip($this->processor);
        return $this->processor->getDatabaseStats();
    }

    /**
     * Обработать конкретный файл (например, локальный Excel)
     */
    public function processFile(string $filePath, bool $resetDatabase = false): array
    {
        if ($resetDatabase) {
            $this->processor->init();
        }
        $this->processor->processFile($filePath);
        return $this->processor->getDatabaseStats();
    }

    /**
     * Инфо о скачанном файле
     */
    public function getDownloadFileInfo(): array
    {
        return $this->downloader->getFileInfo();
    }

    /**
     * Товар по артикулу
     */
    public function findProduct(string $material)
    {
        return $this->repository->findByMaterial($material);
    }

    /**
     * Получить товары, исключая заданные артикула, с постраничной навигацией
     *
     * @return array{items: array, page: int, perPage: int, total: int, totalPages: int}
     */
    public function findProductsExcluding(array $excludeMaterials, int $page = 1, int $perPage = 20): array
    {
        $items = $this->repository->findExcludingMaterials($excludeMaterials, $page, $perPage);
        $total = $this->repository->countExcludingMaterials($excludeMaterials);
        $totalPages = (int)max(1, ceil($total / max(1, $perPage)));

        return [
            'items' => $items,
            'page' => max(1, $page),
            'perPage' => max(1, $perPage),
            'total' => $total,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Получить все уникальные серии
     */
    public function getUniqueSeries(): array
    {
        return $this->repository->getUniqueSeries();
    }

    /**
     * Получить все уникальные подкатегории
     */
    public function getUniqueSubcategories(): array
    {
        return $this->repository->getUniqueSubcategories();
    }
}
