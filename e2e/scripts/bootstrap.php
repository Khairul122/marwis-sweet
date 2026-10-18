<?php
if (getenv('MARWIS_E2E') !== '1') {
    throw new RuntimeException('Bootstrap ini khusus proses E2E.');
}
foreach (explode('|', getenv('MARWIS_E2E_KEYS') ?: '') as $key) {
    $value = getenv($key);
    if ($key === '' || $value === false) continue;
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
