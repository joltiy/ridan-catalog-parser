<?php

namespace Joltiy\RidanCatalogParser\Repositories;

use PDO;
use Joltiy\RidanCatalogParser\Models\Product;
use Joltiy\RidanCatalogParser\Models\Specification;
use Joltiy\RidanCatalogParser\Models\SpecificationCollection;

class ProductRepository
{
    private ?PDO $db;

    public function __construct(string $sqlitePath)
    {
        $this->db = new \PDO('sqlite:' . $sqlitePath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Получить все товары с характеристиками
     */
    public function findAll(): array
    {
        $products = [];

        // Получаем все товары
        $stmt = $this->db->query("SELECT * FROM products ORDER BY material");
        $productsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productsData as $productData) {
            $characteristics = $this->getProductSpecifications($productData['material']);
            $products[] = $this->createProductFromData($productData, $characteristics);
        }

        return $products;
    }
    /**
     * Получить товары, не входящие в указанный массив артикулов (с постраничным выводом)
     *
     * @param array $excludeMaterials Массив артикулов, которые нужно исключить
     * @param int $page Номер страницы (начиная с 1)
     * @param int $perPage Количество товаров на страницу
     * @return array Массив товаров
     */
    public function findExcludingMaterials(array $excludeMaterials, int $page, int $perPage): array
    {
        $products = [];
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        // Если нечего исключать — просто отдаем все с пагинацией
        if (count($excludeMaterials) === 0) {
            $stmt = $this->db->prepare("
                SELECT * FROM products
                ORDER BY material
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $productsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Создаем строку плейсхолдеров для запроса SQL
            $placeholders = implode(',', array_fill(0, count($excludeMaterials), '?'));

            // Готовим SQL-запрос
            $stmt = $this->db->prepare("
                SELECT * FROM products
                WHERE material NOT IN ($placeholders)
                ORDER BY material
                LIMIT :limit OFFSET :offset
            ");

            // Привязываем значения для материалов, которые исключаем
            foreach ($excludeMaterials as $index => $material) {
                $stmt->bindValue($index + 1, $material);
            }

            // Привязываем значения для пагинации
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            // Выполняем запрос
            $stmt->execute();

            // Получаем данные
            $productsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($productsData as $productData) {
            $characteristics = $this->getProductSpecifications($productData['material']);
            $products[] = $this->createProductFromData($productData, $characteristics);
        }

        return $products;
    }

    /**
     * Подсчитать количество товаров, не входящих в указанный массив артикулов
     *
     * @param array $excludeMaterials
     * @return int
     */
    public function countExcludingMaterials(array $excludeMaterials): int
    {
        if (count($excludeMaterials) === 0) {
            $stmt = $this->db->query("SELECT COUNT(*) AS cnt FROM products");
            return (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        }

        $placeholders = implode(',', array_fill(0, count($excludeMaterials), '?'));
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS cnt
            FROM products
            WHERE material NOT IN ($placeholders)
        ");

        foreach ($excludeMaterials as $index => $material) {
            $stmt->bindValue($index + 1, $material);
        }

        $stmt->execute();
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    }

    /**
     * Найти товар по материалу
     */
    public function findByMaterial(string $material): ?Product
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE material = ?");
        $stmt->execute([$material]);
        $productData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$productData) {
            return null;
        }

        $characteristics = $this->getProductSpecifications($material);
        return $this->createProductFromData($productData, $characteristics);
    }



    /**
     * Получить характеристики товара
     */
    private function getProductSpecifications(string $material): SpecificationCollection
    {
        $collection = new SpecificationCollection();

        $stmt = $this->db->prepare("
            SELECT characteristic_name, characteristic_value 
            FROM specifications 
            WHERE product_material = ? 
            ORDER BY characteristic_name
        ");
        $stmt->execute([$material]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $specification = new Specification($row['characteristic_name'], $row['characteristic_value']);
            $collection->add($specification);
        }

        return $collection;
    }

    /**
     * Создать объект Product из данных БД
     */
    private function createProductFromData(array $data, SpecificationCollection $characteristics): Product
    {
        return new Product(
            $data['material'],
            $data['description'],
            $data['catalog_name'],
            (float)$data['price'],
            $data['currency'],
            $data['series'],
            $data['subcategory'],
            $data['category'],
            $data['direction'],
            $characteristics
        );
    }

    /**
     * Получить все уникальные серии
     */
    public function getUniqueSeries(): array
    {
        $stmt = $this->db->query("SELECT DISTINCT series FROM products WHERE series IS NOT NULL ORDER BY series");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Получить все уникальные подкатегории
     */
    public function getUniqueSubcategories(): array
    {
        $stmt = $this->db->query("SELECT DISTINCT subcategory FROM products WHERE subcategory IS NOT NULL ORDER BY subcategory");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function __destruct()
    {
        $this->db = null;
    }
}
