<?php
/**
 * 
 * @todo Proper Documentation
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 21072012
 * @package MyURY_Core
 */
header('Content-Type: text/json');
header('HTTP/1.1 200 OK');

//Decode to datasource if needed
$data = CoreUtils::dataSourceParser($data);

echo json_encode($data);