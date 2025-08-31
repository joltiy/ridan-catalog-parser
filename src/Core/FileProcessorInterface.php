<?php

namespace Joltiy\RidanCatalogParser\Core;

use Exception;

interface FileProcessorInterface
{
    /**
     * Инициализация базы данных.
     * Может использоваться для создания структуры базы данных
     * или очистки перед новым импортом.
     *
     * @return void
     */
    public function init(): void;

    /**
     * Обрабатывает переданный файл и записывает данные в базу данных.
     *
     * @param string $filePath Полный путь к файлу.
     * @return void
     * @throws Exception Если произошла ошибка при чтении или обработке файла.
     */
    public function processFile(string $filePath): void;

    /**
     * Получает статистику текущей базы данных.
     *
     * @return array Массив статистики базы данных:
     */
    public function getDatabaseStats(): array;
}
