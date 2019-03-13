<?php

namespace App\Services;

use App\Entity\CategoryInterface;

/**
 * Class CategoryHelper
 * @package App\Services
 *
 * @property array nested
 */
class CategoryHelper
{
    private $nested;

    private function recursive(CategoryInterface $category): void
    {
        foreach ($category->getChildren() as $child) {
            $this->nested[] = $child;
            $this->recursive($child);
        }
    }

    /**
     * @param CategoryInterface|null $category
     * @return CategoryInterface[]
     */
    public function getNested(CategoryInterface $category): array
    {
        $this->nested = [$category];
        $this->recursive($category);

        return $this->nested;
    }

    /**
     * @param CategoryInterface[] $categories
     * @return CategoryInterface[]
     */
    public function getNestedMultiple(array $categories): array
    {
        $result = [];
        foreach ($categories as $category) {
            $result = array_merge($this->getNested($category), $result);
        }

        return $result;
    }
}
