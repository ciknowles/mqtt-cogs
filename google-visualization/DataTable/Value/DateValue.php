<?php
  namespace Google\Visualization\DataSource\DataTable\Value;

  use DateTime;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class DateValue extends Value
  {
    protected $dateTime;
    protected $year;
    protected $month;
    protected $dayOfMonth;

    public function __construct($dateStr = NULL)
    {
      if (!is_null($dateStr))
      {
        $this->dateTime = new DateTime($dateStr);
        $this->year = $this->dateTime->format("Y") + 0;
        $this->month = $this->dateTime->format("n") - 1;
        $this->dayOfMonth = $this->dateTime->format("j");
      }
    }

    public static function getNullValue()
    {
      return new static();
    }

    public function __toString()
    {
      if (is_null($this->dateTime))
      {
        return "null";
      }
      return $this->dateTime->format("Y-m-d");
    }

    public function getDateTime()
    {
      return $this->dateTime;
    }

    public function getYear()
    {
      return $this->year;
    }

    public function getMonth()
    {
      return $this->month;
    }

    public function getDayOfMonth()
    {
      return $this->dayOfMonth;
    }

    public function getType()
    {
      return ValueType::DATE;
    }

    public function getObjectToFormat()
    {
      if ($this->isNull())
      {
        return NULL;
      }
      return $this->dateTime;
    }

    public function isNull()
    {
      return is_null($this->dateTime);
    }

    public function compareTo(Value $other)
    {
      if ($this == $other)
      {
        return 0;
      }
      $otherDate = $other;
      if ($this->isNull())
      {
        return -1;
      }
      if ($otherDate->isNull())
      {
        return 1;
      }
      if ($this->dateTime > $otherDate->dateTime)
      {
        return 1;
      } else if ($this->dateTime < $otherDate->dateTime)
      {
        return -1;
      }
      return 0;
    }

    protected function innerToQueryString()
    {
      return "DATE '" . $this->getYear() . "-" . sprintf("%02d", $this->getMonth() + 1) . "-" . sprintf("%02d", $this->getDayOfMonth()) . "'";
    }
  }
?>
