<?php
/**
 * Application Middleware
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2016 Bowling Green State University Libraries
 * @license MIT
 * @package Proxy Borrower
 */

// Add handler for CSRF protection.
$app->add($container[\Slim\Csrf\Guard::class]);
