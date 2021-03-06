<?php
interface SilkSingleton
{
	static public function get_instance();
	
	// Empty clone and wakeup methods prevents external instantiation of copies of the Singleton class,
	// thus eliminating the possibility of duplicate objects.  The methods can be empty, or
	// can contain additional code (most probably generating error messages in response
	// to attempts to call).
	public function __clone();
	
	public function __wakeup();
}
?>