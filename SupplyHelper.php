<?php

namespace App\Services;

use App\Entity\Act;
use App\Entity\ActInvoice;
use App\Entity\Batch;
use App\Repository\SaleRepository;

class SupplyHelper
{
    private $saleRepository;
    private $dh;
    private $productSales;

    public function __construct(SaleRepository $saleRepository, DateHelper $dh)
    {
        $this->saleRepository = $saleRepository;
        $this->dh = $dh;
    }

    /**
     * @param Batch $batch
     * @return float[]
     */
    public function getSaleStat(Batch $batch): array
    {
        $act = $batch->getAct();
        $foolQty = $this->foolQty($act);
        $percent = [];
        $this->productSales = [];
        for ($i = $this->dh->diff($act->getDateAct())->days; $i >= 0; $i--) {
            $currentDateTime = $this->dh->dayAgo($i);
            $salesOfDay = $this->salesQtyOfDay($currentDateTime, $act);
            $this->updateProductSales($act, $salesOfDay);
            $balance = $foolQty - array_sum($this->productSales);
            $percent[$currentDateTime->format('d.m.y')] = ($balance / $foolQty) * 100;
            if (end($percent) == 0) {
                break;
            }
        }

        return $percent;
    }

    private function updateProductSales(Act $act, array $saleQtyOfDay): void
    {
        foreach ($act->getActInvoices() as $ai) {
            $pId = $ai->getProduct()->getId();
            if (!isset($this->productSales[$pId])) {
                $this->productSales[$pId] = 0;
            }
            $this->productSales[$pId] += $saleQtyOfDay[$pId];
            if ($this->productSales[$pId] > $ai->getQty()) {
                $this->productSales[$pId] = $ai->getQty();
            }
        }
    }

    private function salesQtyOfDay(\DateTime $dateTime, Act $act): array
    {
        $qty = [];
        foreach ($act->getActInvoices() as $ai) {
            $qty[$ai->getProduct()->getId()] = ($this->saleRepository->countOfDay($ai->getProduct(), $dateTime) ?? 0);
        }

        return $qty;
    }

    private function foolQty(Act $act): int
    {
        return array_sum(array_map(function (ActInvoice $ai) {
            return $ai->getQty();
        }, $act->getActInvoices()->toArray()));
    }
}
