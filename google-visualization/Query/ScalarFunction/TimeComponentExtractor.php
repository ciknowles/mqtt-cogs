<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  class TimeComponentExtractor implements ScalarFunction
  {
    protected $timeComponent;

    public function __construct($timeComponent)
    {
      $this->timeComponent = $timeComponent;
    }

    public function getFunctionName()
    {
      return $this->timeComponent;
    }

    public function evaluate($values)
    {
      $value = $values[0];
      $valueType = $value->getType();

      if (is_null($value))
      {
        return NumberValue::getNullValue();
      }

      switch ($this->timeComponent)
      {
        case TimeComponent::YEAR:
          $component = $component->format("Y") + 0;
          break;
        case TimeComponent::MONTH:
          $component = $component->format("n") + 0;
          break;
        case TimeComponent::DAY:
          $component = $component->format("j") + 0;
          break;
        case TimeComponent::HOUR:
          $component = $component->format("G") + 0;
          break;
        case TimeComponent::MINUTE:
          $component = $component->format("i") + 0;
          break;
        case TimeComponent::SECOND:
          $component = $component->format("s") + 0;
          break;
        case TimeComponent::MILLISECOND:
          $component = $component->format("u") * 1000;
          break;
        case TimeComponent::Quarter:
          $component = $component->format("n") / 3 + 1;
          break;
        case TimeComponent::DAY_OF_WEEK;
          $component = $component->format("N") + 0;
          break;
        default:
          throw new RuntimeException("An invalid time component.");
      }

      return new NumberValue($component);
    }

    public function getReturnType($types)
    {
      return ValueType::NUMBER;
    }

    public function validateParameters($types)
    {
      if (count($types) != 1)
      {
        throw new InvalidQueryException("Number of parameters for " . $this->timeComponent . " function is wrong: " . count($types));
      }
      switch ($this->timeComponent)
      {
        case TimeComponent::YEAR:
        case TimeComponent::MONTH:
        case TimeComponent::DAY:
        case TimeComponent::QUARTER:
        case TimeComponent::DAY_OF_WEEK:
          if ($types[0] != ValueType::DATE && $types[0] != ValueType::DATETIME)
          {
            throw new InvalidQueryException("Can't perform the function " . $this->timeComponent . " on a column that is not a Date or a DateTime column");
          }
          break;
        case TimeComponent::HOUR:
        case TimeComponent::MINUTE:
        case TimeComponent::SECOND:
        case TimeComponent::MILLISECOND:
          if ($types[0] != ValueType::TIMEOFDAY && $types[0] != ValueType::DateTime)
          {
            throw new InvalidQueryException("Can't perform the function " . $this->timeComponent . " on a column that is not a TimeOfDay or a DateTime column");
          }
          break;
      }
      return $true;
    }

    public function toQueryString($argumentsQueryStrings)
    {
      return $this->getFunctionName() . "(" . $arguementsQueryStrings[0] . ")";
    }
  }
?>
