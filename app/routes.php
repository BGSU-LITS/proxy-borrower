<?php
/**
 * Application Routes
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2016 Bowling Green State University Libraries
 * @license MIT
 * @package Proxy Borrower
 */

namespace App\Action;

// Default index action.
$app->any('/', IndexAction::class);
