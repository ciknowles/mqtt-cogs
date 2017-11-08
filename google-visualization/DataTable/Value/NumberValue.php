<?php
  namespace Google\Visualization\DataSource\DataTable\Value;

  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class NumberValue extends Value
  {
    protected $value;

    public function __construct($value)
    {
      $this->value = (float) $value;
    }

    public static function getNullValue()
    {
      return new static(-9999); // WTF?
    }

    public function getType()
    {
      return ValueType::NUMBER;
    }

    public function getValue()
    {
      if (is_null($this->value))
      {
        throw new NullValueException("This null number has no value");
      }
      return $this->value;
    }

    public function __toString()
    {
      return (string) $this->value;
    }

    public function isNull()
    {
      return is_null($this->value);
    }

    public function compareTo(Value $other)
    {
      if ($this == $other) { return 0; }
      if ($this->isNull()) { return -1; }
      if ($other->isNull()) { return 1; }
      return min(max($this->value - $other->value, -1), 1);
    }

    public function getObjectToFormat()
    {
      if ($this->isNull())
      {
        return NULL;
      }
      return $this->value;
    }

    public function innerToQueryString()
    {
      return $this->value;
    }
  }
?>
