<?php

namespace App\Services\Report;

use App\Entity\CategoryArray;
use App\Repository\CategoryRepository;
use App\Repository\SaleRepository;
use App\Services\CategoryHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class SalesForCategoriesService
{
    private $saleRepository;
    private $categoryRepository;
    private $categoryHelper;
    private $em;
    private $request;

    public function __construct(
        SaleRepository $saleRepository,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $em,
        CategoryHelper $categoryHelper
    )
    {
        $this->saleRepository = $saleRepository;
        $this->categoryRepository = $categoryRepository;
        $this->request = Request::createFromGlobals();
        $this->categoryHelper = $categoryHelper;
        $this->em = $em;
    }

    public function getReport(): array
    {
        $minDate = $this->getMinDate();
        $maxDate = $this->getMaxDate();
        $category = new CategoryArray($this->getCategory() ? $this->getCategory()->getId() : 1, $this->em);
        $report = [];
        foreach ($category->getChildren() as $child) {
            $categories = $this->categoryHelper->getNested($child);
            $report[$child->getName()] = $this->saleRepository->salesForCategory($categories, $minDate, $maxDate);
        }

        return $report;
    }

    private function getCategory()
    {
        $categoryId = $this->request->get('category');

        return $categoryId ? $this->categoryRepository->find($categoryId) : null;
    }

    private function getMinDate(): ?\DateTime
    {
        $minDateStr = $this->request->get('minDate');

        return $minDateStr ? \DateTime::createFromFormat('Y-m-d H:i', $minDateStr) : null;
    }

    private function getMaxDate(): ?\DateTime
    {
        $maxDateStr = $this->request->get('maxDate');

        return $maxDateStr ? \DateTime::createFromFormat('Y-m-d H:i', $maxDateStr) : null;
    }
}
