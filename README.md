# ridan-catalog-parser
Библиотека для автоматизированной работы с каталогом Ридан: скачивание архива каталога, распаковка, импорт данных из Excel в SQLite и удобный поиск товаров.
Основной сценарий использования — через единый менеджер CatalogManager: одна точка входа для скачивания, распаковки и обработки файлов, а также работы с товарами.
## Возможности
- Загрузка каталога с сайта (с кэшированием по времени).
- Распаковка ZIP-архива в директорию.
- Импорт Excel-файлов в SQLite с обработкой большими партиями (batch/stream).
- Хранение товаров и их характеристик.
- Поиск товаров по артикулу.
- Получение списка товаров с исключением заданных артикулов (с пагинацией).
- Получение уникальных серий и подкатегорий.
- Логирование (через PSR-3, совместимо с Monolog).
- Готовые конфигурации для:
    - PHPUnit (тесты),
    - PHPStan и Psalm (статический анализ),
    - GitHub Actions (CI).

## Требования
- PHP 8.0+
- SQLite 3
- Composer

## Установка
1. Установите зависимости:
``` bash
composer install
```
1. Убедитесь, что PHP расширение pdo_sqlite включено.

## Быстрый старт
``` php
<?php
require 'vendor/autoload.php';

use Joltiy\RidanCatalogParser\Core\CatalogManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Необязательный логгер (можно не передавать)
$logger = new Logger('ridan');
$logger->pushHandler(new StreamHandler(__DIR__ . '/ridan.log', Logger::DEBUG));

$manager = new CatalogManager([
    'baseDomain'   => 'https://ridan.ru',
    'downloadPath' => __DIR__ . '/ridan-catalog.zip',        // куда сохранить архив/файл
    'databasePath' => __DIR__ . '/products_database.sqlite', // куда писать SQLite
    'categories'   => [475, 786, 825],                       // при необходимости ограничить категории
], $logger);

// Полный цикл: скачать (если нужно), распаковать и импортировать в SQLite
$stats = $manager->updateAndImport(forceDownload: false, resetDatabase: true);
print_r($stats);

// Найти товар по артикулу (material)
$product = $manager->findProduct('009D0005R');

// Получить товары, исключая артикулы, с пагинацией
$result = $manager->findProductsExcluding(['009D0005R', '009D0006R'], page: 1, perPage: 20);
// $result содержит: items, page, perPage, total, totalPages
```
## Основные сценарии
- Скачать файл только при необходимости (по возрасту файла):
``` php
$manager->downloadIfNeeded();
```
- Принудительно скачать заново:
``` php
$manager->forceDownload();
```
- Распаковать ZIP и обработать все файлы из папки:
``` php
$stats = $manager->unzipAndProcess();
```
- Обработать конкретный локальный файл Excel:
``` php
$stats = $manager->processFile(__DIR__ . '/local.xlsx', resetDatabase: true);
```
- Информация о скачанном файле:
``` php
$info = $manager->getDownloadFileInfo();
// ['exists' => bool, 'path' => string, 'size' => int, 'size_human' => string, 'modified' => string, 'age_days' => int|null, 'needs_update' => bool]
```
## API менеджера
Класс CatalogManager предоставляет высокоуровневые методы:
- updateAndImport(bool forceDownload = false, boolresetDatabase = false): array
  Полный цикл: загрузка → распаковка/обработка → статистика.
- downloadIfNeeded(): bool
  Скачать файл, если он устарел/отсутствует.
- forceDownload(): bool
  Принудительная загрузка.
- unzipAndProcess(): array
  Распаковка zip и обработка всех файлов в папке.
- processFile(string filePath, boolresetDatabase = false): array
  Обработка конкретного файла Excel.
- getDownloadFileInfo(): array
  Информация о загруженном файле.
- findProduct(string $material)
  Поиск товара по артикулу.
- findProductsExcluding(array excludeMaterials, intpage = 1, int $perPage = 20): array
  Товары, исключая указанные артикула, с пагинацией. Возвращает:
    - items — массив сущностей товаров,
    - page, perPage — текущая страница и размер страницы,
    - total — всего записей,
    - totalPages — всего страниц.

- getUniqueSeries(): array
  Уникальные серии.
- getUniqueSubcategories(): array
  Уникальные подкатегории.

## Структура данных
- Таблица products:
    - material (уникальный код/артикул),
    - description, catalog_name,
    - price, currency,
    - series, subcategory, category, direction.

- Таблица specifications:
    - product_material → ссылка на material,
    - characteristic_name, characteristic_value.

Индексы создаются автоматически для ускорения выборок.
## Логирование
Менеджер принимает любой PSR-3 логгер. Если не передан, используется NullLogger. Рекомендуется Monolog:
``` php
$logger = new Monolog\Logger('ridan');
$logger->pushHandler(new Monolog\Handler\StreamHandler('ridan.log'));
```
## Тестирование и качество кода
- Запуск тестов:
``` bash
vendor/bin/phpunit
```
- PHPStan:
``` bash
vendor/bin/phpstan analyse
```
- Psalm:
``` bash
vendor/bin/psalm
```
CI (GitHub Actions) запускает тесты и статический анализ при каждом пуше/PR.
## Рекомендации по производительности
- Импорт больших файлов выполняется пакетно (batch), что снижает нагрузку на память.
- Для поиска по материалам и характеристикам используются индексы.
- Для больших выборок используйте пагинацию.
