<?php
/**
 * 
 * @todo Proper Documentation
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 21072012
 * @package MyURY_Profile
 */
require 'Views/MyURY/Profile/bootstrap.php';

$twig->setTemplate('MyURY/Profile/user.twig')
        ->addVariable('title', 'View Member')
        ->addVariable('heading', 'View Profile')
        ->addVariable('user', $userData)
        ->addVariable('name', $name)
        // @todo use an array for all this data
        // @todo use a separate array for years paid
        // @todo use an array for officerships held
        // @todo use an array for training status
        ->render();