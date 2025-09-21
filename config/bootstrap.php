<?php
// Cargar autoload de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar variables del entorno desde el archivo .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();