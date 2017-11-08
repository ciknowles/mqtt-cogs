<?php
  namespace Google\Visualization\DataSource\Base;

  class DataSourceException extends \Exception
  {
    protected $reasonType;
    protected $messageToUser;

    public function __construct($reasonType, $messageToUser)
    {
      $this->messageToUser = $messageToUser;
      $this->reasonType = $reasonType;
      parent::__construct($messageToUser);
    }

    public function getMessageToUser()
    {
      return $this->messageToUser;
    }

    public function getReasonType()
    {
      return $this->reasonType;
    }
  }
?>
