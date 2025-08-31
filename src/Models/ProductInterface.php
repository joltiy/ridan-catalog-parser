<?php

namespace Joltiy\RidanCatalogParser\Models;

interface ProductInterface
{
    public function getMaterial(): string;
    public function getDescription(): string;
    public function getCatalogName(): string;
    public function getPrice(): float;
    public function getCurrency(): string;
    public function getSeries(): string;
    public function getSubcategory(): string;
    public function getCategory(): string;
    public function getDirection(): string;
    public function getCharacteristics(): SpecificationCollection;
}
