<?php
  namespace Google\Visualization\DataSource\Query;

  abstract class ComparisonFilter extends QueryFilter
  {
    const OPERATOR_EQ = "=";
    const OPERATOR_NE1 = "!=";
    const OPERATOR_NE2 = "<>";
    const OPERATOR_LT = "<";
    const OPERATOR_GT = ">";
    const OPERATOR_LE = "<=";
    const OPERATOR_GE = ">=";
    const OPERATOR_CONTAINS = "CONTAINS";
    const OPERATOR_STARTS_WITH = "STARTS WTIH";
    const OPERATOR_ENDS_WITH = "ENDS WITH";
    const OPERATOR_MATCHES = "MATCHES";
    const OPERATOR_LIKE = "LIKE";

    protected $operator;

    public function __construct($operator)
    {
      $this->operator = $operator;
    }

    public function isOperatorMatch($v1, $v2)
    {
      switch ($this->operator)
      {
        case self::OPERATOR_EQ:
        case self::OPERATOR_NE1:
        case self::OPERATOR_NE2:
        case self::OPERATOR_LT:
        case self::OPERATOR_GT:
        case self::OPERATOR_LE:
        case self::OPERATOR_GE:
          if ($v1->getType() != $v2->getType()) { return FALSE; }
        default:
      }
      switch ($this->operator)
      {
        case self::OPERATOR_EQ:
          return $v1->compareTo($v2) == 0;
        case self::OPERATOR_NE1:
        case self::OPERATOR_NE2:
          return $v1->compareTo($v2) != 0;
        case self::OPERATOR_LT:
          return $v1->compareTo($v2) < 0;
        case self::OPERATOR_GT:
          return $v1->compareTo($v2) > 0;
        case self::OPERATOR_LE:
          return $v1->compareTo($v2) <= 0;
        case self::OPERATOR_GE:
          return $v1->compareTo($v2) >= 0;
        case self::OPERATOR_CONTAINS:
          return strpos($v1->__toString(), $v2->__toString());
        case self::OPERATOR_STARTS_WITH:
          return strpos($v1->__toString(), $v2->__toString()) === 0;
        case self::OPERATOR_ENDS_WITH:
          return strpos(strrev($v1->__toString()), strrev($v2->__toString())) === 0;
        case self::OPERATOR_MATCHES:
          return preg_match("/" . $v2->__toString() . "/", $v1->__toString()) === 1;
        case self::OPERATOR_LIKE:
          return preg_match("/" . str_replace("_", ".", str_replace("%", ".*", $v2->__toString())) . "/", $v2->__toString()) === 1;
      }
      return FALSE;
    }

    public function getOperator()
    {
      return $this->operator;
    }
  }
?>
