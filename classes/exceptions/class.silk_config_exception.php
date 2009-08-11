<?php

    /**
	 * @package SilkExceptions
	 * Occurs when there's a problem with the silk configuration
     * For example, a required system component could not be found during silk initialisation or
     * a required configuration setting was not supplied in the config file 
	 * @author Tim Oxley
     */
	class SilkConfigException extends Exception {
		public function __construct($key, $code = 0) {
			$message = "Configuration Problem: $key ";
			parent::__construct($message, $code);
		}
	}
?>
