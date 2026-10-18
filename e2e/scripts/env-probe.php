<?php
echo json_encode(['getenv' => getenv('database.default.database'), 'ENV' => $_ENV['database.default.database'] ?? null,
    'SERVER' => $_SERVER['database.default.database'] ?? null, 'order' => ini_get('variables_order')]);
