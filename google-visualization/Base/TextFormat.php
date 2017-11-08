<?php
  namespace Google\Visualization\DataSource\Base;

  class TextFormat
  {
    public function format($obj, $appendTo = "", $pos = array())
    {
      if (is_null($obj) || !is_string($obj))
      {
        throw new IllegalArgumentExcaption();
      }
      $text = $obj;
      $appendTo .= $text;
      $pos[0] = 0;
      if (strlen($text) == 0)
      {
        $pos[1] = 0;
      } else
      {
        $pos[1] = strlen($text) - 1;
      }
      return $appendTo;
    }
  }
?>
