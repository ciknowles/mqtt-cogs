<?php
  /**
   * The AutoloadByNamespace class provides a way to autoload classes based on their namespace.  The root or base
   * namespace is used as a keyword to point to a specific directory on the filesystem.
   *
   * Usage:
   * <code>
   * <?php
   *   spl_autoload_register('AutoloadByNamespace::autoload'); // Add AutoloadByNamespace to the autoload stack
   *   AutoloadByNamespace::register('MyKey', '/path/to/includes'); // Registers base namespace "MyKey"
   *   $u = MyKey\Utils\MyUtility(); // Autoloads file "/path/to/includes/Utils/MyUtiltiy.php"
   * ?>
   * </code>
   *
   * @author Brent G. Gardner <brent@ebrent.net>
   */

  class AutoloadByNamespace
  {
    /**
     * @var array Associative array for paths
     */
    static private $paths = array();

    /**
     * The autoload function to be added to the autoload stack via spl_autoload_register.
     *
     * @param string $class The fully qualified class name to be autoloaded
     */
    static public function autoload($class, $base = '')
    {
      if (($pos = strpos($class, '\\')) === FALSE)
      {
        // No base namespace given
        return;
      }
      $base .= substr($class, 0, $pos);
      $class = substr($class, $pos);
      if (in_array($base, array_keys(self::$paths)) === FALSE)
      {
        self::autoload(substr($class, 1), $base . "\\"); // Check for nested base namespace (MyKey\MySubkey\MyClass)
        // Base namespace not registered
        return;
      }
      $classPath = self::$paths[$base].'/'.str_replace('\\', '/', $class) . '.php';
      if (($realClassPath = realpath($classPath)) === FALSE)
      {
        self::autoload(substr($class, 1), $base . "\\"); // Check for nested base namespace (MyKey\MySubkey\MyClass)
        // Invalid path
        return;
      }
      require_once $realClassPath;
      return;
    }

    /**
     * Maps a base namespace to a path on the filesystem.
     *
     * @param string $base The base namespace.
     * @param string $path The absolute filesystem path where the class files exist.
     * @return string Returns the canonicalized absolute path on success or FALSE on failure.
     */
    static public function register($base, $path)
    {
      if (($path = realpath($path)) !== FALSE)
      {
          self::$paths[$base] = $path;
      }
      return $path;
    }
  }
?>
