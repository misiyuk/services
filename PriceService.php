<?php

namespace App\Services;

use App\Controller\ProductsController;
use App\Entity\Product;
use App\Entity\PromotionProduct;
use App\Repository\ProductRepository;
use App\Repository\PromotionProductRepository;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PriceService
 * @package App\Services
 *
 * @property PromotionProductRepository $promoProductRepository
 * @property ProductRepository $productRepository
 * @property EntityManagerInterface $em
 * @property array $output
 */
class PriceService
{
    const NOT_PRODUCTS = 'Not products for update';

    private $em;
    private $promoProductRepository;
    private $productRepository;
    private $output = [];

    public function __construct(ObjectManager $em)
    {
        $this->em = $em;
        $this->promoProductRepository = $em->getRepository(PromotionProduct::class);
        $this->productRepository = $em->getRepository(Product::class);
    }

    /**
     * @param OutputInterface $output
     * @throws
     */
    public function synchronize(OutputInterface $output)
    {
        $this->em->transactional(function () use ($output) {
            $this->deactivationPromotionProduct();
            $this->activationPromotionProduct();
        });
        if (count($this->output)) {
            try {
                $controller = new ProductsController($this->em);
                $controller->syncEvotor();
            } catch (\Exception $e) {
                $this->output[] = 'Sync evotor failed with error';
            }
        }
        $output->writeln(
            $this->output()
        );
    }

    private function output(): array
    {
        $output = $this->output;
        $this->output = [];

        return empty($output) ? [self::NOT_PRODUCTS] : $output;
    }

    private function deactivationPromotionProduct(): void
    {
        $promotionProducts = $this->promoProductRepository->findByOutInterval(true);
        if (count($promotionProducts)) {
            $this->output[] = 'Deactivation promo for products:';
        }
        foreach ($promotionProducts as $promotionProduct) {
            $product = $promotionProduct->getProduct();
            $oldPrice = $product->getOldPrice();
            $product->setOldPrice(null);
            $product->setPrice($oldPrice);

            $promotionProduct->setSync(false);
            $this->em->persist($product);
            $this->em->persist($promotionProduct);
            $this->output[] = $product->getId();
        }
        $this->em->flush();
    }

    private function activationPromotionProduct(): void
    {
        $promotionProducts = $this->promoProductRepository->findByInterval(false);
        if (count($promotionProducts)) {
            $this->output[] = 'Activation promo for products:';
        }
        foreach ($promotionProducts as $promotionProduct) {
            $product = $promotionProduct->getProduct();
            if (
                !$this->promoProductRepository->findOneBy([
                    'product' => $product,
                    'sync' => true,
                ])
            ) {
                $newPrice = $promotionProduct->getPrice();
                $oldPrice = $product->getOldPrice() ?? $product->getPrice();
                $product->setOldPrice($oldPrice);
                $product->setPrice($newPrice);
                $promotionProduct->setSync(true);
                $this->em->persist($product);
                $this->em->persist($promotionProduct);
                $this->output[] = $product->getId();
            } else {
                $this->output[] = 'Error! Product promotion already exists';
            }
        }
        $this->em->flush();
    }
}
