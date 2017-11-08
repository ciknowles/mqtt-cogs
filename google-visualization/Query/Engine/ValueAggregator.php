<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\DataTable\Value\NumberValue;
  use Google\Visualization\DataSource\DataTable\Value\Value;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;
  use Google\Visualization\DataSource\Query\AggregationType;

  class ValueAggregator
  {
    protected $valueType;
    protected $max;
    protected $min;
    protected $sum = 0;
    protected $count = 0;

    public function __construct($valueType)
    {
      $this->valueType = $valueType;
      $this->min = $this->max = Value::getNullValueFromValueType($valueType);
    }

    public function aggregate(Value $value)
    {
      if (!$value->isNull())
      {
        $this->count++;
        if ($this->valueType == ValueType::NUMBER)
        {
          $this->sum += $value->getValue();
        }
        if ($this->count == 1)
        {
          $this->max = $this->min = $value;
        } else
        {
          $this->max = $this->max->compareTo($value) >= 0 ? $this->max : $value;
          $this->min = $this->min->compareTo($value) <= 0 ? $this->min : $value;
        }
      } else if ($this->count == 0)
      {
        $this->min = $this->max = $value;
      }
      return $this;
    }

    protected function getSum()
    {
      if ($this->valueType != ValueType::NUMBER)
      {
        throw new UnsupportedOperationException();
      }
      return $this->sum;
    }

    protected function getAverage()
    {
      if ($this->valueType != ValueType::NUMBER)
      {
        throw new UnsupportedOperationException();
      }
      return $this->count > 0 ? $this->sum / $this->count : NULL;
    }

    public function getValue($type)
    {
      switch ($type)
      {
        case AggregationType::AVG:
          $v = ($this->count != 0) ? new NumberValue($this->getAverage()) : NumberValue::getNullValue();
          break;
        case AggregationType::COUNT:
          $v = new NumberValue($this->count);
          break;
        case AggregationType::MAX:
          $v = $this->max;
          if ($this->count == 0)
          {
            $v = Value::getNullValueFromValueType($v->getType());
          }
          break;
        case AggregationType::MIN:
          $v = $this->min;
          if ($this->count == 0)
          {
            $v = Value::getNullValueFromValueType($v->getType());
          }
          break;
        case AggregationType::SUM:
          $v = ($this->count != 0) ? new NumberValue($this->getSum()) : NumberValue::getNullValue();
          break;
        default:
          throw new RuntimeException("Invalid AggregationType");
      }
      return $v;
    }
  }
?>
