<?php
  namespace Google\Visualization\DataSource\Util;

  class TreeMap extends Map
  {
    protected $comparator;

    public function __construct(Comparator $comparator)
    {
      parent::__construct();
      $this->comparator = $comparator;
    }

    public function comparator()
    {
      return $this->comparator;
    }

    public function put($key, $value)
    {
      parent::put($key, $value);
      uasort($this->keys, array($this->comparator, "compare"));
      $sortedValues = array();
      foreach ($this->keys as $i => $key)
      {
        $sortedValues[] = $this->values[$i];
      }
      $this->keys = array_values($this->keys);
      $this->values = $sortedValues;
      return $this;
    }
  }
?>
