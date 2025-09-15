<?php

require __DIR__ . '/../vendor/autoload.php';

// Bootstrap the application
$app = require __DIR__ . '/../bootstrap/app.php';

// Load routes
$routes = require __DIR__ . '/../routes/api.php';
$routes($app);

// Run the application
$app->run();