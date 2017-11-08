<?php
  namespace Google\Visualization\DataSource\DataTable\Value;

  use Google\Visualization\DataSource\Util\Comparator;

  abstract class Value
  {
    abstract public function getType();
    abstract public function isNull();
    abstract protected function innerToQueryString();

    public static function getNullValueFromValueType($type)
    {
      switch($type)
      {
        case ValueType::BOOLEAN:
          return BooleanValue::getNullValue();
        case ValueType::TEXT:
          return TextValue::getNullValue();
        case ValueType::NUMBER:
          return NumberValue::getNullValue();
        case ValueType::TIMEOFDAY:
          return TimeOfDayValue::getNullValue();
        case ValueType::DATE:
          return DateValue::getNullValue();
        case ValueType::DATETIME:
          return DateTimeValue::getNullValue();
        default:
          return NULL;
      }
    }

    public function toQueryString()
    {
      if ($this->isNull())
      {
        throw new RuntimeException("Cannot run toQueryString() on a null value.");
      }
      return $this->innerToQueryString();
    }

    public static function getLocalizedComparator($ulocale)
    {
      $comparator = new Comparator();
      $textValueComparator = TextValue::getTextLocalizedComparator($ulocale);
      $comparator->compare = function(Value $value1, Value $value2) use ($textValueComparator)
      {
        if ($value1 == $value2)
        {
          return 0;
        }
        if ($value1->getType() == ValueType::TEXT)
        {
          return $textValueComparator->compare($value1, $value2);
        } else
        {
          return $value1->compareTo($value2);
        }
      };
      return $comparator;
    }
  }
?>
