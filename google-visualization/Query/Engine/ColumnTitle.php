<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\DataTable\ColumnDescription;
  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;
  use Google\Visualization\DataSource\Query\AggregationColumn;
  use Google\Visualization\DataSource\Query\AggregationType;

  class ColumnTitle
  {
    protected $values;
    public $aggregation;
    protected $isMultiAggregationQuery;
    const PIVOT_COLUMNS_SEPARATOR = ",";
    const PIVOT_AGGREGATION_SEPARATOR = " ";

    public function __construct($values, AggregationColumn $aggregationColumn, $isMultiAggregationQuery)
    {
      $this->values = $values;
      $this->aggregation = $aggregationColumn;
      $this->isMultiAggregationQuery = $isMultiAggregationQuery;
    }

    public function getValues()
    {
      return $this->values;
    }

    public function createColumnDescription(DataTable $originalTable)
    {
      $colDesc = $originalTable->getColumnDescription($this->aggregation->getAggregatedColumn()->getId());
      return $this->createAggregationColumnDescription($colDesc);
    }

    protected function createIdPivotPrefix()
    {
      if (!$this->isPivot())
      {
        return "";
      }
      return implode(self::PIVOT_COLUMNS_SEPARATOR, $this->values) . self::PIVOT_AGGREGATION_SEPARATOR;
    }

    protected function createLabelPivotPart()
    {
      if (!$this->isPivot())
      {
        return "";
      }
      return implode(self::PIVOT_COLUMNS_SEPARATOR, $this->values);
    }

    protected function isPivot()
    {
      return count($this->values) > 0;
    }

    protected function createAggregationColumnDescription(ColumnDescription $originalColumnDescription)
    {
      $aggregationType = $this->aggregation->getAggregationType();
      $columnId = $this->createIdPivotPrefix() . $this->aggregation->getId();
      $type = $originalColumnDescription->getType();
      $aggregationLabelPart = $aggregationType . " " . $originalColumnDescription->getLabel();
      $pivotLabelPart = $this->createLabelPivotPart();
      if ($this->isPivot())
      {
        if ($isMultiAggregationQuery)
        {
          $label = $pivotLabelPart . " " . $aggregationLabelPart;
        } else
        {
          $label = $pivotLabelPart;
        }
      } else
      {
        $label = $aggregationLabelPart;
      }

      if ($this->canUseSameTypeForAggregation($type, $aggregationType))
      {
        $result = new ColumnDescription($columnId, $type, $label);
      } else
      {
        $result = new ColumnDescription($columnId, ValueType::NUMBER, $label);
      }

      return $result;
    }

    protected function canUseSameTypeForAggregation($valueType, $aggregationType)
    {
      if ($valueType == ValueType::NUMBER)
      {
        $ans = TRUE;
      } else
      {
        switch ($aggregationType)
        {
          case AggregationType::MIN:
          case AggregationType::MAX:
            $ans = TRUE;
            break;
          case AggregationType::SUM:
          case AggregationType::AVG:
          case AggregationType::COUNT:
            $ans = FALSE;
            break;
          default:
            throw new IllegalArgumentException();
        }
      }
      return $ans;
    }
  }
?>
