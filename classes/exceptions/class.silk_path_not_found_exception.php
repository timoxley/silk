<?php

	/**
	* @package SilkExceptions
	* SilkPathNotFoundException occurs when a file cannot be found.
	* @author Tim Oxley
	*/
	class SilkPathNotFoundException extends RuntimeException {
		public function __construct($filename, $code = 0) {
			$message = "Path not found: $filename ";
			return parent::__construct($message, $code);
		}
	}
?>
