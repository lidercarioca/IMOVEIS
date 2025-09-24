<?php
// Configurações globais de log
return [
    'path' => __DIR__ . '/../logs/',
    'files' => [
        'backup' => 'backup_error.log',
        'restore' => 'restore_error.log',
        'property' => 'property_debug.log',
        'settings' => 'debug_settings.log',
        'hours' => 'horas.log',
        'security' => 'security.log',
        'access' => 'access.log',
        'error' => 'error.log'
    ],
    'retention' => [
        'days' => 30, // Manter logs por 30 dias
        'max_size' => 10485760 // 10MB por arquivo
    ],
    'format' => '[%datetime%] %level%: %message% %context%'
];