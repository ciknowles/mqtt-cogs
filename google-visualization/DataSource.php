<?php
  namespace Google\Visualization\DataSource;

  abstract class DataSource implements DataTableGenerator
  {
    public function __construct()
    {
      DataSourceHelper::executeDataSource($this, $this->isRestrictedAccessMode());
    }

    protected function isRestrictedAccessMode()
    {
      return TRUE;
    }

    public function getCapabilities()
    {
      return Capabilities::NONE;
    }
  }
?>
