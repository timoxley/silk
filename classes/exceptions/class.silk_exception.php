<?php

class SilkException extends Exception implements ChainOfCommandPattern {

private $next = null;

public __construct($message = '', $code) {
    parent::__construct($message, $code);
}

// ChainOfCommandPattern methods
public get_next() {
    return $this->next;
}

public function set_next($parentException) {
    $this->next = $parentException
}


// shortcut methods for clarity
public function set_parent($parentException) {
    $this->set_next($parentException);
}


public function get_parent() {
    return $this->get_next();
}

}

?>
