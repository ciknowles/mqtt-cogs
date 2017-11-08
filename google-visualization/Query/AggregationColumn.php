<?php
  namespace Google\Visualization\DataSource\Query;

  use Google\Visualization\Datasource\Base\InvalidQueryException;
  use Google\Visualization\Datasource\Base\MessagesEnum;
  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class AggregationColumn extends AbstractColumn
  {
    protected $aggregatedColumn;
    protected $aggregationType;

    public function __construct(SimpleColumn $aggregatedColumn, $aggregationType)
    {
      $this->aggregatedColumn = $aggregatedColumn;
      $this->aggregationType = $aggregationType;
    }

    public function getId()
    {
      return $this->aggregatedColumn->getId();
    }

    public function getAggregatedColumn()
    {
      return $this->aggregatedColumn;
    }

    public function getAggregationType()
    {
      return $this->aggregationType;
    }

    public function getAllSimpleColumnIds()
    {
      return array($this->aggregatedColumn->getId());
    }

    public function getAllAggregationColumns()
    {
      return array($this);
    }

    public function getAllSimpleColumns()
    {
      return array();
    }

    public function getAllScalarFunctionColumns()
    {
      return array();
    }

    public function validateColumn(DataTable $dataTable)
    {
      $valueType = $dataTable->getColumnDescription($this->aggregatedColumn->getId())->getType();
      $userLocale = $dataTable->getLocaleForUserMessages();
      switch ($this->aggregationType)
      {
        case AggregationType::COUNT:
        case AggregationType::MAX:
        case AggregationType::MIN:
          break;
        case AggregationType::AVG:
        case AggregationType::SUM:
          if ($valueType != ValueType::NUMBER)
          {
            throw new InvalidQueryException(MessagesEnum::getMessage(MessagesEnum::AVG_SUM_ONLY_NUMERIC, $dataTable->getLocaleForUserMessages()));
          }
          break;
        default:
          throw new RuntimeException(MessagesEnum::getMessageWithArgs(MessagesEnum::INVALID_AGG_TYPE, $dataTable->getLocaleForUserMessages(), $aggregationType));
      }
      return $this;
    }

    public function getValueType(DataTable $dataTable)
    {
      $originalValueType = $dataTable->getColumnDescription($this->aggregatedColumn->getId())->getType();
      switch($this->aggregationType)
      {
        case AggregationType::COUNT:
          $valueType = ValueType::NUMBER;
          break;
        case AggregationType::AVG:
        case AggregationType::SUM:
        case AggregationType::MAX:
        case AggregationType::MIN:
          $valueType = $originalValueType;
          break;
        default:
          throw new RuntimeException(MessagesEnum::INVALID_AGG_TYPE);
      }
      return $valueType;
    }

    public function equals($o)
    {
      if ($o instanceof AggregationColumn)
      {
        return $this->aggregatedColumn->equals($o->aggregatedColumn) && $this->aggregationType == $o->aggregationType;
      }
      return FALSE;
    }

    public function toQueryString()
    {
      return strtoupper($this->aggregationType) . "(" . $this->aggregatedColumn->toQueryString() . ")";
    }
  }
?>
