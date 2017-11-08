<?php
  namespace Google\Visualization\DataSource\Base;

  class BooleanFormat
  {
    protected $trueString;
    protected $falseString;

    public function __construct($trueString, $falseString = NULL)
    {
      if (is_null($trueString) && is_null($falseString))
      {
        $trueString = "true";
        $falseString = "false";
      } else if (is_null($falseString))
      {
        $pattern = $trueString;
        $valuePatterns = explode(":", $pattern);
        if (count($valuePatterns) != 2)
        {
          throw new IllegalArguemntException("Cannot construct a boolean format from " . $pattern . ". The pattern must contain a single ':' character");
        }
        $trueString = $valuePatterns[0];
        $falseString = $valuePatterns[1];
      } else
      {
        throw new NullPointerException();
      }
      $this->trueString = $trueString;
      $this->falseString = $falseString;
    }

    public function format($obj, $appendTo = "", $pos = array())
    {
      if (!is_null($obj) && !is_bool($obj))
      {
        throw new IllegalArgumentException();
      }
      $val = $obj;
      if (is_null($val))
      {
        $pos[0] = 0;
        $pos[1] = 0;
      } else if ($val)
      {
        $appendTo .= $this->trueString;
        $pos[0] = 0;
        $pos[1] = strlen($this->trueString) - 1;
      } else
      {
        $appendTo .= $this->falseString;
        $pos[0] = 0;
        $pos[1] = strlen($this->falseString) - 1;
      }
      return $appendTo;
    }
  }
?>
