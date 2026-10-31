<?php
declare(strict_types=1);

// Parámetros centrales de la aplicación y de la conexión a MySQL.
return [
    'app_name' => 'E&R Instalaciones Eléctricas',
    'timezone' => 'America/Mexico_City',
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'erdatabase',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
