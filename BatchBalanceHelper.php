<?php

namespace App\Services;

use App\Entity\ActInvoice;
use App\Entity\Batch;
use App\Entity\BatchBalance;
use App\Entity\ProductStock;
use App\Repository\BatchBalanceRepository;
use App\Repository\ProductStockRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class BatchBalanceHelper
 * @package App\Services
 *
 * @property BatchBalanceRepository $repository
 * @property ProductStockRepository $productStockRepository
 * @property EntityManagerInterface $em
 */
class BatchBalanceHelper
{
    private $productStockRepository;
    private $repository;
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->productStockRepository = $em->getRepository(ProductStock::class);
        $this->repository = $em->getRepository(BatchBalance::class);
        $this->em = $em;
    }

    /**
     * @param ActInvoice $actInvoice
     * @throws
     */
    public function upBatchBalance(ActInvoice $actInvoice): void
    {
        $ps = $this->getProductStock($actInvoice);
        $qty = $actInvoice->getQty();
        while ($qty > 0) {
            $batchBalance = $ps ? $this->repository->findByProductStockForUp($ps) : null;
            if (!$batchBalance) {
                $batch = new Batch();
                $batch->setAct($actInvoice->getAct());
                $batchBalance = new BatchBalance();
                $batchBalance->setStock($actInvoice->getAct()->getStock());
                $batchBalance->setProduct($actInvoice->getProduct());
                $batchBalance->setQty(0);
                $batch->addBatchBalance($batchBalance);
                $this->em->persist($batch);
                $this->em->flush();
            }
            $maxQty = $this->repository->maxQty($batchBalance);
            $freeQty = $maxQty - $batchBalance->getQty();
            if ($freeQty >= $qty) {
                $newBbQty = $batchBalance->getQty() + $qty;
                $batchBalance->setQty($newBbQty);
                $qty = 0;
            } else {
                $batchBalance->setQty($maxQty);
                $qty -= $freeQty;
            }
            $this->em->flush();
        }
    }

    /**
     * @param ActInvoice $actInvoice
     * @throws
     */
    public function downBatchBalance(ActInvoice $actInvoice): void
    {
        $ps = $this->getProductStock($actInvoice);
        $qty = $actInvoice->getQty();

        while ($qty && $ps) {
            $batchBalance = $this->repository->findByProductStock($ps);
            if ($batchBalance) {
                $bbQty = $batchBalance->getQty();
                if ($bbQty > $qty) {
                    $newBbQty = $bbQty - $qty;
                    $batchBalance->setQty($newBbQty);
                    $qty = 0;
                } else {
                    $batchBalance->setQty(0);
                    $qty -= $bbQty;
                }
                $this->em->flush();
                $lastBb = $batchBalance;
            } elseif (isset($lastBb)) {
                $newBbQty = $lastBb->getQty() - $qty;
                $lastBb->setQty($newBbQty);
                $qty = false;
            } else {
                $qty = false;
            }
        }
    }

    /**
     * @param ActInvoice $actInvoice
     * @return ProductStock|null
     * @throws \Exception
     */
    private function getProductStock(ActInvoice $actInvoice): ?ProductStock
    {
        $productStock = $this->productStockRepository->findOneBy([
            'product' => $actInvoice->getProduct(),
            'stockUuid' => $actInvoice->getAct()->getStock()->getId(),
        ]);

        return $productStock;
    }
}
