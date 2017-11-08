<?php
  namespace Google\Visualization\DataSource\DataTable;

  use Google\Visualization\DataSource\DataTable\Value\Value;

  class TableCell
  {
    protected $value;
    protected $formattedValue;
    protected $customProperties;

    public function __construct(Value $value, $formattedValue = NULL)
    {
      $this->value = $value;
      $this->formattedValue = $formattedValue;
    }

    public function getValue()
    {
      return $this->value;
    }

    public function getFormattedValue()
    {
      return $this->formattedValue;
    }

    public function setFormattedValue($formattedValue)
    {
      $this->formattedValue = $formattedValue;
      return $this;
    }

    public function getType()
    {
      return $this->value->getType();
    }

    public function isNull()
    {
      return $this->value->isNull();
    }

    public function getCustomProperties()
    {
      if (is_null($this->customProperties))
      {
        return array();
      }
      return $this->customProperties;
    }
  }
?>
