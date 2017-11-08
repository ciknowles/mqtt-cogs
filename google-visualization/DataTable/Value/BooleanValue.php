<?php
  namespace Google\Visualization\DataSource\DataTable\Value;

  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class BooleanValue extends Value
  {
    protected $value;

    public function __construct($value)
    {
      $this->value = (boolean) $value;
    }

    public static function getNullValue()
    {
      return new static(FALSE);
    }

    public function getType()
    {
      return ValueType::BOOLEAN;
    }

    public function getValue()
    {
      if (is_null($this->value))
      {
        throw new NullValueException("This null boolean has no value");
      }
      return $this->value;
    }

    public function isNull()
    {
      return is_null($this->value);
    }

    public function compareTo(Value $other)
    {
      if ($this == $other)
      {
        return 0;
      }
      if ($this->isNull())
      {
        return -1;
      }
      if ($other->isNull())
      {
        return 1;
      }
      return ($this->value == $other->value ? 0 : ($this->value ? 1 : -1));
    }

    public function __toString()
    {
      if (is_null($this->value))
      {
        return "null";
      }
      return $this->value ? "true" : "false";
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
      return $this->value ? "true" : "false";
    }
  }
?>
