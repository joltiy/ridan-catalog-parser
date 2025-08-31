<?php

namespace Joltiy\RidanCatalogParser\Models;

class Product implements ProductInterface
{
    /**
     * @var string Материал
     */
    private string $material;

    /**
     * @var string Описание
     */
    private string $description;

    /**
     * @var string Название из каталога
     */
    private string $catalogName;

    /**
     * @var float Цена
     */
    private float $price;

    /**
     * @var string Валюта
     */
    private string $currency;

    /**
     * @var string Серия
     */
    private string $series;

    /**
     * @var string Подкатегория
     */
    private string $subcategory;

    /**
     * @var string Категория
     */
    private string $category;

    /**
     * @var string Направление
     */
    private string $direction;

    /**
     * @var SpecificationCollection Характеристики
     */
    private SpecificationCollection $characteristics;

    public function __construct(
        string $material,
        string $description,
        string $catalogName,
        float $price,
        string $currency,
        string $series,
        string $subcategory,
        string $category,
        string $direction,
        SpecificationCollection $characteristics
    ) {
        $this->material = $material;
        $this->description = $description;
        $this->catalogName = $catalogName;
        $this->price = $price;
        $this->currency = $currency;
        $this->series = $series;
        $this->subcategory = $subcategory;
        $this->category = $category;
        $this->direction = $direction;
        $this->characteristics = $characteristics;
    }

    public function getMaterial(): string
    {
        return $this->material;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCatalogName(): string
    {
        return $this->catalogName;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function getSubcategory(): string
    {
        return $this->subcategory;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getCharacteristics(): SpecificationCollection
    {
        return $this->characteristics;
    }

    public function toArray(): array
    {
        return [
            'material' => $this->material,
            'description' => $this->description,
            'catalog_name' => $this->catalogName,
            'price' => $this->price,
            'currency' => $this->currency,
            'series' => $this->series,
            'subcategory' => $this->subcategory,
            'category' => $this->category,
            'direction' => $this->direction,
            'characteristics' => $this->characteristics->toArray()
        ];
    }
}
