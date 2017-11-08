<?php
  namespace Google\Visualization\DataSource\Util;

  class Map
  {
    protected $keys;
    protected $values;

    public function __construct()
    {
      $this->clear();
    }

    public function clear()
    {
      $this->keys = array();
      $this->values = array();
      return $this;
    }

    public function containsKey($key)
    {
      return in_array($key, $this->keys);
    }

    public function containsValue($value)
    {
      return in_array($value, $this->values);
    }

    public function get($key)
    {
      if (($i = array_search($key, $this->keys)) === FALSE)
      {
        return NULL;
      }
      return $this->values[$i];
    }

    public function isEmpty()
    {
      return $this->size() == 0;
    }

    public function keySet()
    {
      return $this->keys;
    }

    public function put($key, $value)
    {
      $this->keys[] = $key;
      $this->values[] = $value;
      return $this;
    }

    public function remove($key)
    {
      $i = array_search($key, $this->keys);
      array_splice($this->keys, $i, 1);
      array_splice($this->values, $i, 1);
      return $this;
    }

    public function size()
    {
      return count($this->keys);
    }

    public function values()
    {
      return $this->values;
    }
  }
?>
