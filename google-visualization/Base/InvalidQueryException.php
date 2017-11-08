<?php
  namespace Google\Visualization\DataSource\Base;

  class InvalidQueryException extends DataSourceException
  {
    public function __construct($messageToUser)
    {
      parent::__construct(ReasonType::INVALID_QUERY, $messageToUser);
    }
  }
?>
