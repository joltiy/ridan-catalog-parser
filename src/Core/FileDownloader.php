<?php

namespace Joltiy\RidanCatalogParser\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

//use Symfony\Component\DomCrawler\Crawler;

//require_once 'vendor/autoload.php';
//require_once 'FileDownloader.php';
//
//// Настройки
//$domain = 'https://example.com';
//$savePath = __DIR__ . '/downloads/ridan-catalog.xlsx';
//$categories = [475, 786, 825];
//
//// Создаем экземпляр загрузчика
//$downloader = new FileDownloader($domain, $savePath, $categories);
//
//// Проверяем и скачиваем если нужно
//if ($downloader->checkAndDownload()) {
//    $fileInfo = $downloader->getFileInfo();
//
//    if ($fileInfo['exists']) {
//        echo "Файл обновлен: " . $fileInfo['path'] . "\n";
//        echo "Размер: " . $fileInfo['size_human'] . "\n";
//        echo "Возраст: " . $fileInfo['age_days'] . " дней\n";
//    }
//} else {
//    echo "Файл актуален, обновление не требуется\n";
//}
//
//// Можно принудительно скачать
//// $downloader->download();
//
//// Можно проверить нужно ли обновление
//// if ($downloader->shouldDownload()) {
////     echo "Файл требует обновления\n";
//// }


class FileDownloader
{
    private const array CATEGORIES = [
        1 => 'Тепловая автоматика',
        475 => 'Холодильная техника',
        786 => 'Приводная техника',
        825 => 'Промышленная автоматика',
        859 => 'Теплый пол и снеготаяние',
        925 => 'Насосное оборудование',
        957 => 'Коттеджная автоматика'
    ];

    private string $domain;
    private string $savePath;
    private array $categories;

    private LoggerInterface $logger;
    private int $cacheTime = 7 * 24 * 60 * 60;

    public function __construct(string $domain, string $savePath, array $categories = [], ?LoggerInterface $logger = null)
    {
        $this->domain = rtrim($domain, '/');
        $this->savePath = $savePath;
        $this->categories = $categories ?: array_keys(self::CATEGORIES);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Проверяет нужно ли обновлять файл и скачивает если нужно
     */
    public function checkAndDownload(): bool
    {
        if ($this->shouldDownload()) {
            return $this->download();
        }

        return file_exists($this->savePath);
    }

    /**
     * @throws \Exception
     */
    public function unzip(FileProcessorInterface $processor): void
    {
        $path = $this->unzipToFolder($this->savePath);
        // Если нужно обнулить базу
        $processor->init();
        // Проверяем, существует ли директория
        if (!is_dir($path)) {
            throw new \Exception("Указанная директория $path не существует или недоступна.");
        }

        // Получаем список файлов Excel в указанной директории
//        $excelFiles = glob($path . DIRECTORY_SEPARATOR . '*.{xlsx,xls}', GLOB_BRACE);
        $files = glob($path . DIRECTORY_SEPARATOR . '*', GLOB_BRACE);

        // Если файлов не найдено, логируем или обрабатываем
        if (empty($files)) {
//            echo "В директории $path не найдено файлов Excel.\n";
            return;
        }

        // Обрабатываем каждый файл через метод processFile
        foreach ($files as $file) {
            try {
//                echo "Начало обработки файла: $file\n";
                $processor->processFile($file);
//                echo "Файл $file успешно обработан.\n";
            } catch (\Exception $e) {
//                echo "Ошибка при обработке файла $file: " . $e->getMessage() . "\n";
            }
        }

        // Обработка файла можно последовательно несколько файлов
//        $processor->processFile($excelFile);
    }

    /**
     * Разархивирует zip-файл в папку с таким же именем, как сам файл.
     *
     * @param string $zipFilePath Полный путь к zip-файлу.
     * @throws \Exception Если файл не существует или не является zip-файлом.
     */
    private function unzipToFolder(string $zipFilePath)
    {
        if (!file_exists($zipFilePath)) {
            throw new \Exception("Файл $zipFilePath не существует.");
        }

        $pathInfo = pathinfo($zipFilePath);
        if (!isset($pathInfo['filename'], $pathInfo['dirname']) || strtolower($pathInfo['extension']) !== 'zip') {
            throw new \Exception("Файл $zipFilePath не является zip-архивом.");
        }

        $destinationDir = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'];

        // Создаем директорию для распаковки, если её нет
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            throw new \Exception("Не удалось создать директорию $destinationDir.");
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath) === true) {
            if (!$zip->extractTo($destinationDir)) {
                $zip->close();
                throw new \Exception("Не удалось разархивировать файл $zipFilePath.");
            }
            $zip->close();
            return $destinationDir;
        }

        throw new \Exception("Не удалось открыть zip-файл $zipFilePath.");
    }

    /**
     * Проверяет нужно ли скачивать файл заново
     */
    public function shouldDownload(): bool
    {
        // Если файла нет - нужно скачать
        if (!file_exists($this->savePath)) {
            return true;
        }

        // Если файл старше 7 дней - нужно скачать
        $fileTime = filemtime($this->savePath);
        $sevenDaysAgo = time() - $this->cacheTime;

        return $fileTime < $sevenDaysAgo;
    }

    /**
     * Возвращает возраст файла в днях
     */
    public function getFileAgeInDays(): ?int
    {
        if (!file_exists($this->savePath)) {
            return null;
        }

        $fileTime = filemtime($this->savePath);
        $ageInSeconds = time() - $fileTime;

        return (int)floor($ageInSeconds / (24 * 60 * 60));
    }

    /**
     * Скачивает файл
     */
    public function download(): bool
    {
        $url = $this->domain . '/catalog-export';
        $url_post = $this->domain . '/catalog-export/catalog';

        // Создаём CookieJar для хранения сессионных куки
        $cookieJar = new CookieJar();
        $client = new Client([
            'cookies' => true,
            'timeout' => 300, // 5 минут таймаут
            'connect_timeout' => 30,
        ]);

        try {
            // Создаем директорию если её нет
            $dir = dirname($this->savePath);
            if (!is_dir($dir)) {
                $this->logger->debug('Создаем директорию: ' . $dir);
                mkdir($dir, 0755, true);
            }

            $this->logger->debug('Выполняем запрос GET: ' . $url);
            // 1. Выполняем запрос GET, чтобы получить содержимое страницы
            $response = $client->get($url, ['cookies' => $cookieJar]);
            if ($response->getStatusCode() !== 200) {
                $this->logger->error('Ошибка при загрузке страницы. Код: ' . $response->getStatusCode());
                throw new \Exception("Ошибка при загрузке страницы. Код: " . $response->getStatusCode());
            }

            $html = $response->getBody()->getContents();

            // 2. Извлекаем _token с помощью DomCrawler
//            $crawler = new Crawler($html);
////            $token = $crawler->filter('input[name="_token"]')->attr('value');
//            $tokenElement = $crawler->filterXpath('//input[@name="_token"]')->getNode(0);
//
//            if ($tokenElement === null || !$tokenElement->getAttribute('value')) {
//                $this->logger->error('_token не найден на странице');
//                throw new \Exception("_token не найден на странице");
//            }
//
//            $token = $tokenElement->getAttribute('value');
            $token = null;
            if (is_string($html) && $html !== '') {
                $internalErrors = libxml_use_internal_errors(true);
                $dom = new \DOMDocument();
                // Поддержка UTF-8 и возможных невалидных HTML
                $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
                libxml_use_internal_errors($internalErrors);

                if ($loaded) {
                    $xpath = new \DOMXPath($dom);
                    $nodes = $xpath->query('//input[@name="_token"]/@value');
                    if ($nodes !== false && $nodes->length > 0) {
                        $token = $nodes->item(0)->nodeValue;
                    }
                }
            }

            if (!$token) {
                $this->logger->error('_token не найден на странице');
                throw new \Exception("_token не найден на странице");
            }

            // 3. Формируем данные для отправки в формате multipart/form-data
            $multipartData = [
                [
                    'name' => '_token',
                    'contents' => $token,
                ]
            ];

            // Добавляем каждое значение `categories[]`
            foreach ($this->categories as $category) {
                if (!isset(self::CATEGORIES[$category])) {
                    $this->logger->error('Неизвестная категория: ' . $category);
                    throw new \InvalidArgumentException("Неизвестная категория: $category");
                }

                $multipartData[] = [
                    'name' => 'categories[]',
                    'contents' => $category,
                ];
            }

            // Добавляем оставшиеся параметры
            $multipartData[] = [
                'name' => 'extension',
                'contents' => 'xlsx',
            ];

            $this->logger->debug('Выполняем запрос POST: ' . $url_post);
            // 4. Отправляем POST-запрос
            $postResponse = $client->post($url_post, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
                    'Referer' => $url,
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept-Language' => 'ru',
                    'Cache-Control' => 'no-cache',
                ],
                'cookies' => $cookieJar,
                'multipart' => $multipartData,
            ]);

            if ($postResponse->getStatusCode() !== 200) {
                $this->logger->error('Сервер вернул ошибку. Код: ' . $postResponse->getStatusCode());
                throw new \Exception("Сервер вернул ошибку. Код: " . $postResponse->getStatusCode());
            }

            // 5. Проверяем заголовки ответа
            $contentType = $postResponse->getHeaderLine('Content-Type');
            $contentDisposition = $postResponse->getHeaderLine('Content-Disposition');

            if (
                !str_contains($contentType, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') &&
                !str_contains($contentType, 'application/zip')
            ) {
                $this->logger->error("Неверный Content-Type: $contentType. Ожидался Excel файл");
                throw new \Exception("Неверный Content-Type: $contentType. Ожидался Excel файл");
            }

            // 6. Сохраняем файл
            $this->logger->debug('Сохраняем файл: ' . $this->savePath);
            $responseBody = $postResponse->getBody()->getContents();
            $result = file_put_contents($this->savePath, $responseBody);

            if ($result === false) {
                $this->logger->error("Не удалось сохранить файл: " . $this->savePath);
                throw new \Exception("Не удалось сохранить файл: " . $this->savePath);
            }

            // 7. Проверяем сохранённый файл
            if (file_exists($this->savePath) && filesize($this->savePath) > 0) {
                // Обновляем время модификации файла
                $this->logger->debug('Обновляем время модификации файла: ' . $this->savePath);
                touch($this->savePath);
                return true;
            } else {
                $this->logger->error("Файл сохранился некорректно.");
                throw new \Exception("Файл сохранился некорректно.");
            }
        } catch (\Exception $e) {
//            error_log('FileDownloader Error: ' . $e->getMessage());
            $this->logger->error("FileDownloader Error: " . $e->getMessage());
            // Удаляем поврежденный файл если он создался
            if (file_exists($this->savePath)) {
                $this->logger->error("Удаляем поврежденный файл если он создался: " . $this->savePath);
                unlink($this->savePath);
            }

            return false;
        }
    }

    /**
     * Удаляет старый файл
     */
    public function deleteOldFile(): bool
    {
        if (file_exists($this->savePath)) {
            return unlink($this->savePath);
        }
        return true;
    }

    /**
     * Получает информацию о файле
     */
    public function getFileInfo(): array
    {
        if (!file_exists($this->savePath)) {
            return ['exists' => false];
        }

        return [
            'exists' => true,
            'path' => $this->savePath,
            'size' => filesize($this->savePath),
            'size_human' => $this->formatBytes(filesize($this->savePath)),
            'modified' => date('Y-m-d H:i:s', filemtime($this->savePath)),
            'age_days' => $this->getFileAgeInDays(),
            'needs_update' => $this->shouldDownload()
        ];
    }

    /**
     * Форматирует размер файла в читаемом виде
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
