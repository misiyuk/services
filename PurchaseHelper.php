<?php

namespace App\Services;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\ProductStockRepository;
use App\Repository\SaleRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PurchaseHelper
 * @package App\Services
 *
 * @property ProductRepository $productRepository
 * @property ProductStockRepository $productStockRepository
 * @property SaleRepository $saleRepository
 * @property Request $request
 * @property float $coefficient
 * @property float $coefficientStep
 * @property float[] $smoothSaleQuantity
 * @property float[] $saleQuantity
 * @property float[] $trend
 * @property float[] $extraColumn1
 * @property float[] $extraColumn2
 * @property float[] $extraColumn3
 * @property float $extraValue
 * @property float[] $prediction
 * @property int $iPrediction
 * @property float $recommendation
 * @property int $i
 */
class PurchaseHelper
{
    private const COEFFICIENT = 0.005;
    private const SALE_DAYS_COUNT = 60;
    private $productRepository;
    private $productStockRepository;
    private $saleRepository;
    private $request;
    private $coefficient = 0;
    private $coefficientStep = 0.125;
    private $smoothSaleQuantity;
    private $saleQuantity;
    private $extraColumn1;
    private $extraColumn2;
    private $extraColumn3;
    private $extraValue;
    private $recommendation;
    private $trend;
    private $prediction;
    private $iPrediction = 1;
    private $i = 0;

    public function __construct(
        ProductRepository $productRepository,
        ProductStockRepository $productStockRepository,
        SaleRepository $saleRepository
    )
    {
        $this->productRepository = $productRepository;
        $this->productStockRepository = $productStockRepository;
        $this->saleRepository = $saleRepository;
        $this->request = Request::createFromGlobals();
    }

    /**
     * @return array
     * @throws
     */
    public function getProducts(): array
    {
        $productEntities = $this->productRepository->findByPurchaseHelper();
        $products = [];
        foreach ($productEntities as $product) {
            $this->calcRecommendation($product);
            $values = [];
            $values['sales4week'] = $this->sales4Week($product);
            $values['monthGrowPercent'] = $this->monthTrend();
            $values['stocksQty'] = $this->productStockRepository->findByProductQty($product);
            $values['confirmedPurchase'] = $this->productRepository->countConfirmedPurchaseNotSupply($product);
            $values['notConfirmedPurchase'] = $this->productRepository->countNotConfirmedPurchase($product);
            $values['circle'][] = (
                $this->predictionWeek(0) + $this->predictionWeek(1) <
                $values['stocksQty']
            ); // 'a7+a8 < B'
            $values['condition'][] = "{$this->predictionWeek(0)} + {$this->predictionWeek(1)} <
                {$values['stocksQty']}";
            $values['circle'][] = (
                $this->predictionWeek(2) + $this->predictionWeek(3) <
                $values['stocksQty'] - $this->predictionWeek(0) -
                $this->predictionWeek(1) + $values['confirmedPurchase']
            ); //'a9+a10 < (B - a7 - a8) + C1';
            $values['condition'][] = "{$this->predictionWeek(2)} + {$this->predictionWeek(3)} <
                {$values['stocksQty']} - {$this->predictionWeek(0)} -
                {$this->predictionWeek(1)} + {$values['confirmedPurchase']}";
            $circle3left = $this->predictionWeek(4) + $this->predictionWeek(5);
            $circle3right = $values['stocksQty'] - $this->predictionWeek(0) - $this->predictionWeek(1) +
                $values['confirmedPurchase'] - $this->predictionWeek(2) -
                $this->predictionWeek(3) + $values['notConfirmedPurchase'];
            $values['circle'][] = ($circle3left < $circle3right); //'a11 + a12 < ((B - a7 - a8) + C1 - a9 - a10) + C2';
            $values['condition'][] = "{$this->predictionWeek(4)} + {$this->predictionWeek(5)} <
                {$values['stocksQty']} - {$this->predictionWeek(0)} - {$this->predictionWeek(1)} +
                {$values['confirmedPurchase']} - {$this->predictionWeek(2)} -
                {$this->predictionWeek(3)} + {$values['notConfirmedPurchase']}";
            if (!end($values['circle'])) {
                $values['recommend'] = round($this->recommendation) ?: null;
            }
            $products[$product->getId()]['values'] = $values;
            $products[$product->getId()]['product'] = $product;
            if (strtolower($this->request->get('debug') ?? '') == 'y') {
                $this->debug($product, $values);
            }
        }
        if (strtolower($this->request->get('debug') ?? '') == 'y') {
            exit();
        }

        return $products;
    }

    private function sales4Week(Product $product): array
    {
        $fourWeek = $this->saleRepository->dailySales($product, 28);
        for ($i = 0; $i < 4; $i++) {
            $week[] = array_sum(array_slice($fourWeek, $i * 7, 7));
        }

        return $week ?? [];
    }

    private function monthTrend(): string
    {
        $trend = end($this->trend) * 100;

        return round($trend, 2).'%';
    }

    private function predictionWeek(int $offset): float
    {
        return array_sum(array_slice($this->prediction, $offset*7, 7));
    }

    private function calcSmoothSaleQuantity(): void
    {
        if ($this->i) {
            if ($this->saleQuantity[$this->i] === null) {
                $this->saleQuantity[$this->i] = $this->smoothSaleQuantity[$this->i-1];
            }
            // ($D$1*A7)+(1-$D$1)*(B6+C6)
            $this->smoothSaleQuantity[$this->i] = ($this->coefficient * $this->saleQuantity[$this->i]) +
                (1 - $this->coefficient) *
                ($this->smoothSaleQuantity[$this->i-1] + $this->trend[$this->i-1]);
        } else {
            $this->smoothSaleQuantity[$this->i] = $this->saleQuantity[$this->i];
        }
    }

    private function calcTrend(): void
    {
        if ($this->i) {
            // (B6-B5)*$E$1+(1-$E$1)*C5
            $this->trend[$this->i] = ($this->smoothSaleQuantity[$this->i] - $this->smoothSaleQuantity[$this->i-1]) *
                self::COEFFICIENT +
                (1 - self::COEFFICIENT) *
                $this->trend[$this->i-1]
            ;
        } else {
            $this->trend[$this->i] = 0;
        }
    }

    private function calcExtraColumn1(): void
    {
        $this->extraColumn1[$this->i] = ($this->smoothSaleQuantity[$this->i-1] ?? 0) + ($this->trend[$this->i-2] ?? 0);
    }

    private function calcExtraColumn2(): void
    {
        if ($this->i) {
            $this->extraColumn2[$this->i] = $this->extraColumn1[$this->i] + $this->saleQuantity[$this->i];
        } else {
            $this->extraColumn2[$this->i] = 0;
        }
    }

    private function calcExtraColumn3(): void
    {
        $this->extraColumn3[$this->i] = $this->extraColumn2[$this->i] ** 2;
    }

    private function calcSumExtraColumn3(): void
    {
        $this->extraValue = array_sum($this->extraColumn3);
    }

    private function calcPrediction(): void
    {
        // $B$64+D68*$C$64
        $this->prediction[$this->iPrediction] = $this->smoothSaleQuantity[$this->i] +
            $this->iPrediction *
            $this->trend[$this->i]
        ;
    }

    private function searchBestCoefficient(): float
    {
        for ($best = $this->coefficient = $this->coefficientStep; $this->coefficient <= 1; $this->coefficient += $this->coefficientStep) {
            $this->calcAll();
            $minSumExtraColumn3 = !isset($minSumExtraColumn3) || $this->extraValue < $minSumExtraColumn3 ?
                $this->extraValue :
                $minSumExtraColumn3
            ;
            if ($minSumExtraColumn3 == $this->extraValue) {
                $best = $this->coefficient;
            }
        }

        return $best;
    }

    private function calcAll(): void
    {
        $this->resetValues();
        for ($this->i = 0; $this->i < self::SALE_DAYS_COUNT; $this->i++) {
            $this->calcSmoothSaleQuantity();
            $this->calcTrend();
            $this->calcExtraColumn1();
            $this->calcExtraColumn2();
            $this->calcExtraColumn3();
        }
        $this->i--;
        $this->calcSumExtraColumn3();
    }

    private function initSaleQuantity(Product $product): void
    {
        $saleQuantity = $this->saleRepository->dailySales($product, self::SALE_DAYS_COUNT);
        $stockQuantity = $this->productStockRepository->findByProductQty($product);
        for ($i = count($saleQuantity)-1; !$stockQuantity && $i >= 0; $i--) {
            if ($saleQuantity[$i] === 0) {
                $saleQuantity[$i] = null;
            } else {
                break;
            }
        }
        $this->saleQuantity = $saleQuantity;
    }

    private function calcRecommendation(Product $product): void
    {
        $this->initSaleQuantity($product);
        $this->coefficient = $this->searchBestCoefficient();
        $this->calcAll();

        for ($this->iPrediction = 1; $this->iPrediction <= 42; $this->iPrediction++) {
            $this->calcPrediction();
        }
        $predictions = array_slice($this->prediction, -14);
        $this->recommendation = array_sum($predictions);
    }

    private function resetValues(): void
    {
        $this->smoothSaleQuantity = [];
        $this->trend = [];
        $this->extraColumn1 = [];
        $this->extraColumn2 = [];
        $this->extraColumn3 = [];
        $this->prediction = [];
    }

    private function debug(Product $product, array $values): void
    {
        echo "<h2>Товар: {$product->getId()}</h2>";
        echo '<pre>';
        print_r($values);
        echo '</pre>';

        $refObj = new \ReflectionObject($this);
        $arr = [];
        $propertyArr = $refObj->getProperties();
        $pCount = count($propertyArr);
        for ($i = 0; $i < $pCount; $i++) {
            if (!preg_match('#(.*Repository)|(request)#', $propertyArr[$i]->getName())) {
                $arr[$propertyArr[$i]->getName()] = $this->{$propertyArr[$i]->getName()};
            }
        }
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
    }
}
