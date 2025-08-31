<?php

require 'vendor/autoload.php';

use Joltiy\RidanCatalogParser\Core\CatalogManager;
use Joltiy\RidanCatalogParser\Core\FileDownloader;

use Joltiy\RidanCatalogParser\Core\LargeExcelProcessor;
use Joltiy\RidanCatalogParser\Repositories\ProductRepository;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;





$logger = new Logger('name');
$logger->pushHandler(new StreamHandler('test.log', Logger::DEBUG));

// Конфигурация
$manager = new CatalogManager([
    'baseDomain'   => 'https://ridan.ru',
    'downloadPath' => __DIR__ . '/ridan-catalog.zip',
    'databasePath' => __DIR__ . '/products_database.sqlite',
    'categories'   => [475, 786, 825],
], $logger);

// 1) Полный цикл: скачать (если нужно), распаковать/обработать
//$stats = $manager->updateAndImport(forceDownload: false, resetDatabase: true);
//print_r($stats);

// 2) Поиск товара по артикулу
$product = $manager->findProduct('009D0005R');

// 3) Товары, исключая заданные артикула, с пагинацией
$result = $manager->findProductsExcluding(['009D0005R', '009D0006R'], page: 1, perPage: 1);
//
print_r($result);
