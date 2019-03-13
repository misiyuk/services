<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class XlsxReader
{
    private $sheetData;
    private $headRowKey;
    private $codeColumnKey;
    private $quantityColumnKey;
    private $data;
    private $pathName;

    const CODE_HEAD = 'код';
    const QUANTITY_HEAD = 'количество';

    public function __construct($pathName)
    {
        $this->pathName = $pathName;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function deserialize()
    {
        $reader = new Xlsx();
        $spreadsheet = $reader->load($this->pathName);
        $this->sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $this->setFormatData();
    }

    /**
     * @throws \Exception
     */
    public function codesIsUnique()
    {
        $codes = array_filter(array_map(function(array $row) {
            return $row['code'] ?: false;
        }, $this->data));
        $uniqueCodes = array_unique($codes);
        if (count($codes) > count($uniqueCodes)) {
            throw new \Exception('Коды не уникальны');
        }
    }

    public function filterData($function)
    {
        $newData = [];
        foreach ($this->data as $row) {
            $newData[] = $function($row);
        }

        return array_filter($newData);
    }

    public function getData()
    {
        return $this->data;
    }

    private function setFormatData()
    {
        $this->initialHeadData();
        $this->data = [];
        for ($i = $this->headRowKey+1; array_key_exists($i, $this->sheetData); $i++) {
            $row = $this->sheetData[$i];
            if ($row[$this->codeColumnKey] && $row[$this->quantityColumnKey]) {
                $this->data[] = [
                    'code' => $row[$this->codeColumnKey],
                    'quantity' => $row[$this->quantityColumnKey],
                ];
            }
        }
    }

    private function initialHeadData()
    {
        $this->headRowKey = $this->searchCodeCell();
        $this->searchQuantityCell();
    }

    /**
     * Set property $quantityColumnKey.
     * @throws \RuntimeException
     */
    private function searchQuantityCell()
    {
        $columnKey = $this->searchCellInRow($this->sheetData[$this->headRowKey], self::QUANTITY_HEAD);
        if ($columnKey) {
            $this->quantityColumnKey = $columnKey;

            return;
        }

        throw new \RuntimeException('Не найдена колонка с количеством в файле импорта');
    }

    /**
     * Set property $codeColumnKey and return row key for header.
     * @return string|int
     * @throws \RuntimeException
     */
    private function searchCodeCell()
    {
        foreach ($this->sheetData as $rowKey => $row) {
            $columnKey = $this->searchCellInRow($row, self::CODE_HEAD);
            if ($columnKey) {
                $this->codeColumnKey = $columnKey;

                return $rowKey;
            }
        }

        throw new \RuntimeException('Не найдена колонка с кодами в файле импорта');
    }

    private function searchCellInRow(array $row, $query)
    {
        foreach ($row as $columnKey => $column) {
            if (mb_strtolower(trim($column)) == $query) {
                return $columnKey;
            }
        }

        return null;
    }
}