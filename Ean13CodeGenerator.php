<?php

namespace App\Services;

/**
 * Class Ean13Generator
 * @package App\Services
 *
 * @property array $numbers
 */
class Ean13CodeGenerator
{
    private $numbers;

    /**
     * @param int $value
     * @return int
     */
    public function getNextValue($value)
    {
        $firstPart = substr($value, 0, 12);
        $firstPart++;
        $this->numbers = str_split($firstPart);
        $lastNumber = $this->calculateLastNumber();
        $result = $firstPart.$lastNumber;

        return $result;
    }

    /**
     * @return int
     */
    private function calculateLastNumber()
    {
        $result = $this->sumHalfNumbers(true);
        $result *= 3;
        $result += $this->sumHalfNumbers(false);
        $result %= 10;
        if ($result) {
            $result = 10 - $result;
        }

        return $result;
    }

    /**
     * @param bool $even
     * @return int
     */
    private function sumHalfNumbers($even)
    {
        $sum = 0;
        for ($i = (int) $even; $i < 12; $i += 2) {
            $sum += $this->numbers[$i];
        }

        return $sum;
    }
}
