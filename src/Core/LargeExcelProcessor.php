<?php

namespace Joltiy\RidanCatalogParser\Core;

use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Common\Entity\Row;
use Exception;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LargeExcelProcessor implements FileProcessorInterface
{
    private $db;
    private $reader;
    private $chunkSize = 1000;
    private $sqlitePath;
    private LoggerInterface $logger;

    public function __construct(string $sqlitePath, ?LoggerInterface $logger = null)
    {
        $this->sqlitePath = $sqlitePath;
        $this->logger = $logger ?? new NullLogger();
        // Создаем/подключаем SQLite базу
        $this->db = new PDO('sqlite:' . $sqlitePath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


        $this->reader = new Reader();
    }

    public function init(): void
    {
        $this->createDatabaseSchema();
    }
    /**
     * Создание структуры базы данных
     */
    private function createDatabaseSchema(): void
    {
        // Таблица товаров
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                material TEXT UNIQUE,
                description TEXT,
                catalog_name TEXT,
                price REAL,
                currency TEXT,
                series TEXT,
                subcategory TEXT,
                category TEXT,
                direction TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Таблица характеристик
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS specifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_material TEXT,
                characteristic_name TEXT,
                characteristic_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_material) REFERENCES products(material)
            )
        ");

        // Индексы для ускорения поиска
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_products_material ON products(material)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_specs_material ON specifications(product_material)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_specs_name ON specifications(characteristic_name)");

        // Очищаем таблицы перед импортом
        $this->db->exec("DELETE FROM specifications");
        $this->db->exec("DELETE FROM products");
    }

    /**
     * Основной метод обработки Excel файла
     * @throws Exception
     */
    public function processFile(string $filePath): void
    {
//
//        // Создаем структуру базы
//        $this->createDatabaseSchema();

        $this->logger->debug('Начинаем обработку файла:', [$filePath]);
        try {
            $this->reader->open($filePath);

            foreach ($this->reader->getSheetIterator() as $sheet) {
                $sheetName = $sheet->getName();
                $this->logger->debug('Обрабатываем лист:', [$sheetName]);

                if ($sheetName === 'Каталог') {
                    $this->processCatalogSheet($sheet);
                } elseif ($sheetName === 'Тех.характеристики') {
                    $this->processSpecificationsSheet($sheet);
                } else {
                    echo "Пропускаем лист: $sheetName\n";
                }
            }

            $this->logger->debug('Обработка завершена успешно!');
        } catch (Exception $e) {
            $this->logger->error('Ошибка обработки файла:', [$e->getMessage()]);
            throw new Exception("Ошибка обработки файла: " . $e->getMessage());
        } finally {
            $this->reader->close();
        }
    }

    /**
     * Обработка листа "Каталог"
     */
    private function processCatalogSheet($sheet): void
    {
        $rowCount = 0;
        $headers = [];
        $batch = [];

        foreach ($sheet->getRowIterator() as $row) {
            $rowData = $this->rowToArray($row);

            if ($rowCount === 0) {
                $headers = $rowData;
            } else {
                if (!empty($rowData[0])) { // Проверяем что есть материал
                    $productData = array_combine($headers, $rowData);
                    $batch[] = $this->prepareProductData($productData);

                    if (count($batch) >= $this->chunkSize) {
                        $this->saveProductsBatch($batch);
                        $batch = [];
//                        echo ".";
                    }
                }
            }

            $rowCount++;
        }


        // Сохраняем последний батч
        if (!empty($batch)) {
            $this->saveProductsBatch($batch);
        }
        $this->logger->info('Обработано товаров:', [($rowCount - 1)]);
    }

    /**
     * Обработка листа "Тех.характеристики"
     */
    private function processSpecificationsSheet($sheet): void
    {
        $rowCount = 0;
        $headers = [];
        $currentMaterial = null;
        $batch = [];

        foreach ($sheet->getRowIterator() as $row) {
            $rowData = $this->rowToArray($row);

            if ($rowCount === 0) {
                $headers = $rowData;
            } else {
                if (!empty($rowData[0]) && $rowData[0] !== $currentMaterial) {
                    $currentMaterial = $rowData[0];
                }

                if ($currentMaterial) {
                    $specData = array_combine($headers, $rowData);
                    $batch[] = [
                        'product_material' => $currentMaterial,
                        'characteristic_name' => $specData['Название характеристики'] ?? '',
                        'characteristic_value' => $specData['Значение характеристики'] ?? ''
                    ];

                    if (count($batch) >= $this->chunkSize) {
                        $this->saveSpecificationsBatch($batch);
                        $batch = [];
                    }
                }
            }

            $rowCount++;
        }

        // Сохраняем последний батч
        if (!empty($batch)) {
            $this->saveSpecificationsBatch($batch);
        }
        $this->logger->debug('Обработано характеристик:', [($rowCount - 1)]);
    }

    /**
     * Подготовка данных товара
     */
    private function prepareProductData(array $data): array
    {
        return [
            'material' => $data['Материал'] ?? '',
            'description' => $data['Описание'] ?? '',
            'catalog_name' => $data['Название из каталога'] ?? '',
            'price' => is_numeric($data['Цена'] ?? '') ? (float)$data['Цена'] : 0,
            'currency' => $data['Валюта'] ?? '',
            'series' => $data['Серия'] ?? '',
            'subcategory' => $data['Подкатегория'] ?? '',
            'category' => $data['Категория'] ?? '',
            'direction' => $data['Направление'] ?? ''
        ];
    }

    /**
     * Сохранение батча товаров
     */
    private function saveProductsBatch(array $batch): void
    {
        $sql = "INSERT OR REPLACE INTO products 
                (material, description, catalog_name, price, currency, series, subcategory, category, direction) 
                VALUES (:material, :description, :catalog_name, :price, :currency, :series, :subcategory, :category, :direction)";

        $stmt = $this->db->prepare($sql);

        $this->db->beginTransaction();
        try {
            foreach ($batch as $product) {
                $stmt->execute($product);
            }
            $this->db->commit();

            $this->logger->debug('Сохранено товаров:', [count($batch)]);
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Ошибка сохранения товаров: ', [$e->getMessage()]);
            throw new Exception("Ошибка сохранения товаров: " . $e->getMessage());
        }
    }

    /**
     * Сохранение батча характеристик
     */
    private function saveSpecificationsBatch(array $batch): void
    {
        $sql = "INSERT OR REPLACE INTO specifications 
                (product_material, characteristic_name, characteristic_value) 
                VALUES (:product_material, :characteristic_name, :characteristic_value)";

        $stmt = $this->db->prepare($sql);

        $this->db->beginTransaction();
        try {
            foreach ($batch as $spec) {
                $stmt->execute($spec);
            }
            $this->db->commit();

            $this->logger->debug('Сохранено характеристик: ', [count($batch)]);
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Ошибка сохранения характеристик: ', [$e->getMessage()]);
            throw new Exception("Ошибка сохранения характеристик: " . $e->getMessage());
        }
    }

    /**
     * Преобразование строки в массив
     */
    private function rowToArray($row): array
    {
        $rowData = [];
        foreach ($row->getCells() as $cell) {
            $rowData[] = $cell->getValue();
        }
        return $rowData;
    }

    /**
     * Получение статистики базы данных
     */
    public function getDatabaseStats(): array
    {
        $productsCount = $this->db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $specsCount = $this->db->query("SELECT COUNT(*) FROM specifications")->fetchColumn();

        // Правильное получение размера файла базы данных
        $databaseSize = 0;
        if (file_exists($this->sqlitePath)) {
            $databaseSize = filesize($this->sqlitePath);
        }

        return [
            'products' => (int)$productsCount,
            'specifications' => (int)$specsCount,
            'database_size' => $databaseSize,
            'database_path' => $this->sqlitePath
        ];
    }



    public function __destruct()
    {
        $this->db = null;
        if ($this->reader) {
            $this->reader->close();
        }
    }
}
