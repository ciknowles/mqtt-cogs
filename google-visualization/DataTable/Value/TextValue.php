<?php
  namespace Google\Visualization\DataSource\DataTable\Value;

  use Collator;

  use Google\Visualization\DataSource\DataTable\Value\ValueType;
  use Google\Visualization\DataSource\Util\Comparator;

  class TextValue extends Value
  {
    protected $value;

    public function __construct($value)
    {
      $this->value = (string) $value;
    }

    public static function getNullValue()
    {
      return new static("");
    }

    public function getType()
    {
      return ValueType::TEXT;
    }

    public function __toString()
    {
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
      return strcmp($this->value, $other->value);
    }

    public function getObjectToFormat()
    {
      return $this->value;
    }

    public function getTextLocalizedComparator($ulocale)
    {
      $comparator = new Comparator();
      $collator = new Collator($ulocale);
      $comparator->compare = function(TextValue $tv1, TextValue $tv2) use ($collator)
      {
        if ($tv1 == $tv2)
        {
          return 0;
        }
        return $collator->compare($tv1, $tv2);
      };
      return $comparator;
    }

    public function getValue()
    {
      return $this->value;
    }

    public function innerToQueryString()
    {
      if (strpos($this->value, "\"") !== FALSE)
      {
        if (strpos($this->value, "'") !== FALSE)
        {
          throw new RuntimeException("Cannot run toQueryString() on string values that contain both \" and '.");
        } else
        {
          return "'" . $this->value . "'";
        }
      } else
      {
        return "\"" . $this->value . "\"";
      }
    }
  }
?>
