<?php
/**
 * Craft web bootstrap file
 */

// Load shared bootstrap
require dirname(__DIR__) . '/bootstrap.php';

// Serve the control panel from the site root (headless CMS), except for the
// public GraphQL endpoint which must stay a site request (see config/routes.php)
define('CRAFT_CP', !preg_match('#^/api(/|\?|$)#', $_SERVER['REQUEST_URI'] ?? ''));

// Load and run Craft
/** @var craft\web\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
$app->run();
