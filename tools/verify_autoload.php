<?php
require __DIR__ . '/../vendor/autoload.php';
$classes = [
    'App\\Services\\FinanceService',
    'App\\Models\\CommissionModel',
    'App\\Models\\BaseModel',
];
foreach ($classes as $class) {
    $exists = class_exists($class, true);
    echo "$class => ";
    if ($exists) {
        $r = new ReflectionClass($class);
        echo $r->getFileName();
    } else {
        echo 'NOT FOUND';
    }
    echo PHP_EOL;
}
