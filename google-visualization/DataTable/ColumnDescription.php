<?php
  namespace Google\Visualization\DataSource\DataTable;

  class ColumnDescription
  {
    protected $id;
    protected $type;
    protected $label;
    protected $pattern;
    protected $customProperties;

    public function  __construct($id, $type, $label)
    {
      $this->id = $id;
      $this->type = $type;
      $this->label = $label;
      $this->pattern = "";
    }

    public function getId()
    {
      return $this->id;
    }

    public function getType()
    {
      return $this->type;
    }

    public function getLabel()
    {
      return $this->label;
    }

    public function getPattern()
    {
      return $this->pattern;
    }

    public function setPattern($pattern)
    {
      $this->pattern = $pattern;
      return $this;
    }

    public function setLabel($label)
    {
      $this->label = $label;
      return $this;
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
