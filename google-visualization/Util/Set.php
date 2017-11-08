<?php
  namespace Google\Visualization\DataSource\Util;

  class Set extends \ArrayObject
  {
    public function add($e)
    {
      $a = $this->getArrayCopy();
      if (!in_array($e, $a))
      {
        $this->append($e);
        return TRUE;
      }
      return FALSE;
    }
  }
?>
