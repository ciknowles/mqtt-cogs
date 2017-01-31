<?php
namespace flock;

use RuntimeException;
use LogicException;
use ErrorException;

/**
 * Process lock
 *
 * @package F3\Flock
 * @version $id$
 * @author Alexey Karapetov <karapetov@gmail.com>
 */
class Lock
{
	const BLOCKING = true;
	const NON_BLOCKING = false;

	private $file;
	private $handler;

	/**
	 * __construct
	 *
	 * @param string $file PID flie name
	 */
	public function __construct($file)
	{
		$this->file = $file;
	}

	/**
	 * Dtor releases the lock if there is one
	 *
	 * @return void
	 */
	public function __destruct()
	{
		if ($this->handler) {
			$this->release();
		}
	}

	/**
	 * Acquire lock
	 *
	 * @param boolean $block Block and wait until it frees and then acquire
	 * @return boolean Has be acquired?
	 * @throws ErrorException if can not access the file
	 */
	public function acquire($block = self::NON_BLOCKING)
	{
		if ($this->handler) {
			throw new LogicException('Lock is already acqiured');
		}
		// For flock() to work properly, the file must exist at the moment we do fopen()
		// So we create it first. touch()'s return value can be ignored, as the possible
		// error would be cought in the next step.
		touch($this->file);
		// The manuals usually recommend to use 'c' mode. But there can be a race condition.
		// If the file is deleted after touch() but before fopen(), it can be recreated and opened with flock()
		// by two processes simultaneously, and they both would be able to acquire an exclusive lock!
		// So when opening the file we MUST BE SURE it exists!
		// The 'r+' mode enables writing to an existing file, but it fails if the file does not exist.
		$this->handler = @fopen($this->file, 'r+');
		if (!$this->handler) {
			$this->throwLastErrorException();
		}
		$flag = LOCK_EX;
		if (!$block) {
			$flag |= LOCK_NB;
		}
		if (!flock($this->handler, $flag)) {
			$this->closeFile();
			return false;
		}
		if (@ftruncate($this->handler, 0) && @fwrite($this->handler, getmypid()) && @fflush($this->handler)) {
			return true;
		}
		$this->throwLastErrorException();
	}

	/**
	 * Throw ErrorException containing information from error_get_last()
	 *
	 * @return void
	 * @throws ErrorException
	 */
	private function throwLastErrorException()
	{
		$error = error_get_last();
		throw new ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']);
	}

	/**
	 * Close file, clear $this->handler
	 *
	 * @return void
	 */
	protected function closeFile()
	{
		if (!@fclose($this->handler)) {
			$this->throwLastErrorException();
		}
		$this->handler = null;
	}

	/**
	 * Release the lock
	 *
	 * @return void
	 * @throws LogicException if lock has not been acquired
	 * @throws ErrorException if could not truncate lock file
	 * @throws RuntimeException if could not release lock
	 */
	public function release()
	{
		if (!$this->handler) {
			throw new LogicException('Lock is not acqiured');
		}
		if (!@ftruncate($this->handler, 0)) {
			$this->throwLastErrorException();
		}
		if (!flock($this->handler, LOCK_UN)) {
			throw new RuntimeException(sprintf('Unable to release lock on %s', $this->file));
		}
		$this->closeFile();
	}
}
