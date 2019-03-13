<?php

namespace App\Services\Report;

use App\Entity\Report;
use App\Entity\ReportCondition;
use App\Repository\ReportConditionRepository;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class RevenueService
 * @package App\Services\Report
 *
 * @property EntityManagerInterface $em
 * @property ReportConditionRepository $conditionRepository
 * @property array $fields
 * @property string $query
 * @property array $conditions
 */
class QueryBuilderService
{
    const VALUE = '#VALUE#';
    const WHERE = '#WHERE#';
    const AND = '#AND#';
    const OR = '#OR#';
    const CONDITIONS = '#CONDITIONS#';

    private $em;
    private $fields;
    private $query;
    private $conditionRepository;
    private $conditions;

    public function __construct(ObjectManager $em)
    {
        $this->em = $em;
        $this->conditionRepository = $em->getRepository(ReportCondition::class);
    }

    public function generateQuery(Report $report): string
    {
        $this->query = $report->getQuery();
        $this->initFields($report);
        $this->insertConditions();
        $this->insertWhere();
        $this->insertAnd();
        $this->insertOr();

        return $this->query;
    }

    public function initFields(Report $report): void
    {
        $request = Request::createFromGlobals();
        if (!$this->fields = $request->query->get('field')) {
            $this->fields = [];
            /** @var ReportCondition $condition */
            foreach ($report->getConditions()->toArray() as $condition) {
                $this->fields[$condition->getId()] = '';
            }
        }
    }

    private function insertConditions(): void
    {
        $this->conditions = [];
        foreach ($this->fields as $id => $value) {
            if ($value) {
                /** @var ReportCondition $condition */
                $condition = $this->conditionRepository->find($id);
                $this->conditions[] = str_replace(
                    self::VALUE,
                    $value,
                    $condition->getValue()
                );
            }
        }
        $this->insertData(
            self::CONDITIONS,
            implode(' AND ', $this->conditions)
        );
    }

    private function insertWhere(): void
    {
        $this->insertData(
            self::WHERE,
            empty($this->conditions) ? ' ' : ' WHERE '
        );
    }

    private function insertAnd(): void
    {
        $this->insertData(
            self::AND,
            empty($this->conditions) ? ' ' : ' AND '
        );
    }

    private function insertOr(): void
    {
        $this->insertData(
            self::OR,
            empty($this->conditions) ? ' ' : ' OR '
        );
    }

    private function insertData($from, $to): void
    {
        $this->query = str_replace($from, $to, $this->query);
    }
}
