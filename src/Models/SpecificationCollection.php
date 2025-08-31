<?php

namespace Joltiy\RidanCatalogParser\Models;

class SpecificationCollection implements \IteratorAggregate, \Countable
{
    private array $specifications = [];

    public function add(Specification $specification): void
    {
        $this->specifications[$specification->name] = $specification;
    }

    public function get(string $name): ?Specification
    {
        return $this->specifications[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->specifications[$name]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->specifications);
    }

    public function count(): int
    {
        return count($this->specifications);
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->specifications as $specification) {
            $result[$specification->name] = $specification->value;
        }
        return $result;
    }

    public function getValues(): array
    {
        return array_map(fn($spec) => $spec->value, $this->specifications);
    }

    public function getNames(): array
    {
        return array_keys($this->specifications);
    }

    public function filter(callable $callback): SpecificationCollection
    {
        $filtered = new SpecificationCollection();
        foreach ($this->specifications as $specification) {
            if ($callback($specification)) {
                $filtered->add($specification);
            }
        }
        return $filtered;
    }
}
