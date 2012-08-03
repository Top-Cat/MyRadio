<?php
/**
 * This file provides the MyURYError class for MyURY
 * @package MyURY_Core
 */

/**
 * Provides error handling so that php errors can be displayed nicely.
 * 
 * @author Andy Durant <aj@ury.org.uk>
 * @version 03082012
 * @package MyURY_Core
 */
class MyURYError {
  
/**
 * @var type $error_type An array that matches error codes from $errno to a short string which names the error type (such as "User-generated error", or "User-generated warning")
 */
  private static $error_type = array(
    E_ERROR => 'Fatal error',
    E_WARNING => 'Warning',
    E_PARSE => 'Parse error',
    E_NOTICE => 'Notice',
    E_CORE_ERROR => 'Core error',
    E_CORE_WARNING => 'Core warning',
    E_COMPILE_ERROR => 'Compile-time error',
    E_COMPILE_WARNING => 'Compile-time warning',
    E_USER_ERROR => 'User-generated error',
    E_USER_WARNING => 'User-generated warning',
    E_USER_NOTICE => 'User-generated notice',
    E_STRICT => 'Runtime notice',
    E_RECOVERABLE_ERROR => 'Recoverable error'
  );
  /**
   * @var type $php_errorlist An array holding all php errors as arrays of [$error_name,$errstr,$errfile,$errline]
   */
  public static $php_errorlist = array();
  
  /**
   * Places all phpErrors into the array $php_errorblock
   * @param type $errno A numeric value which corresponds to the type of error (Notice, Fatal Error, User-generated warning, etc).
   * @param type $errstr A string that contains the error message text, ideally including details that identify the cause of the error.
   * @param type $errfile The full local path of the file which has triggered this error (such as /var/www/public_html/badscript.php).
   * @param type $errline The line number where the error was generated (within the file identified by $errfile).
   */
  public static function errorsToArray($errno, $errstr, $errfile, $errline) {
    $error_name = (isset(self::$error_type[$errno]) ? self::$error_type[$errno] : 'Unknown error code');
    $php_error = array(
        'name' => $error_name, 
        'string' => $errstr, 
        'file' => htmlspecialchars($errfile, ENT_NOQUOTES, 'UTF-8'), 
        'line' => $errline);
    array_push(self::$php_errorlist, $php_error);
  }
  
}