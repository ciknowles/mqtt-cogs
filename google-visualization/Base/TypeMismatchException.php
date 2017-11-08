<?php
  namespace Google\Visualization\DataSource\Base;

  class TypeMismatchException extends DataSourceException
  {
    public function __construct($message)
    {
      parent::__construct(ReasonType::OTHER, $message);
    }
  }
?>
