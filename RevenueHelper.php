<?php

namespace App\Services;

use App\Entity\Purchase;
use App\Repository\SupplyProductRepository;

class RevenueHelper
{
    private $revenue;
    private $profit;

    private $supplyProductRepository;

    public function __construct(SupplyProductRepository $supplyProductRepository)
    {
        $this->supplyProductRepository = $supplyProductRepository;
    }

    /**
     * @param Purchase $purchase
     * @return RevenueHelper
     * @throws
     */
    public function forPurchase(Purchase $purchase): self
    {
        $this->revenue = 0;
        $this->profit = 0;
        foreach ($purchase->getAct()->getActInvoices() as $actInvoice) {
            $price = $actInvoice->getProduct()->getPrice();
            $costPrice = ($actInvoice->getProduct()->getCostPrice() ?: $actInvoice->getCostPrice());
            $this->revenue += $price * $actInvoice->getQty();
            $this->profit += ($price-$costPrice) * $actInvoice->getQty();
        }

        return $this;
    }

    public function getProfit(): ?float
    {
        return $this->profit;
    }

    public function getRevenue(): ?float
    {
        return $this->revenue;
    }
}
