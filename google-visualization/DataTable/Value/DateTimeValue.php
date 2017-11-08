<?php
  namespace Google\Visualization\DataSource\DataTable\Value;

  use Google\Visualization\DataSource\DataTable\Value\DateValue;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class DateTimeValue extends DateValue
  {
    public function getHourOfDay()
    {
      return (int) $this->dateTime->format("H");
    }

    public function getMinute()
    {
      return (int) $this->dateTime->format("i");
    }

    public function getSecond()
    {
      return (int) $this->dateTime->format("s");
    }

    public function getMillisecond()
    {
      return (int) $this->dateTime->format("u") * 1000;
    }

    public function getType()
    {
      return ValueType::DATETIME;
    }
  }
?>
