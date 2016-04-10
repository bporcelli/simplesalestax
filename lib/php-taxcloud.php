<?php

/**
 * @file
 * Load php-taxcloud library.
 */

if (!class_exists('ClassLoader')) {
  require_once(__DIR__ . '/Autoload.php');
}

$classLoader = new ClassLoader;
$classLoader->registerNamespaces(array(
  'TaxCloud' => array(__DIR__)
));
$classLoader->register();
