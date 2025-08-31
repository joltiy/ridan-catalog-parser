<?php

namespace Joltiy\RidanCatalogParser\Factory;

use Joltiy\RidanCatalogParser\Models\Product;
use Joltiy\RidanCatalogParser\Models\Specification;
use Joltiy\RidanCatalogParser\Models\SpecificationCollection;

class ProductFactory
{
    public static function createFromArray(array $data): Product
    {
        $characteristics = new SpecificationCollection();

        if (isset($data['characteristics']) && is_array($data['characteristics'])) {
            foreach ($data['characteristics'] as $name => $value) {
                $characteristics->add(new Specification($name, $value));
            }
        }

        return new Product(
            $data['material'] ?? '',
            $data['description'] ?? '',
            $data['catalog_name'] ?? '',
            (float)($data['price'] ?? 0),
            $data['currency'] ?? '',
            $data['series'] ?? '',
            $data['subcategory'] ?? '',
            $data['category'] ?? '',
            $data['direction'] ?? '',
            $characteristics
        );
    }
}
