<?php
  namespace Google\Visualization\DataSource\Util;

  class TreeSet extends Set
  {
    protected $comparator;

    public function __construct($comparator)
    {
      parent::__construct();
      $this->comparator = $comparator;
    }

    public function add($e)
    {
      if (parent::add($e))
      {
        $this->uasort($this->comparator->compare);
        return TRUE;
      }
      return FALSE;
    }
  }
?>
