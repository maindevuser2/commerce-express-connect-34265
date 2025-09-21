<?php
// Archivo de prueba para verificar la conexión a la base de datos
require_once 'config/database.php';

echo "<h1>Prueba de Conexión a la Base de Datos</h1>\n";

try {
    $database = new Database();
    
    echo "<h2>1. Probando conexión...</h2>\n";
    $connection = $database->getConnection();
    
    if ($connection) {
        echo "✅ Conexión exitosa<br>\n";
        
        // Obtener información de la base de datos
        echo "<h2>2. Información de la base de datos:</h2>\n";
        $info = $database->getDatabaseInfo();
        if ($info) {
            echo "📊 Base de datos: " . $info['db_name'] . "<br>\n";
            echo "📊 Versión MySQL: " . $info['version'] . "<br>\n";
        }
        
        // Verificar tablas
        echo "<h2>3. Verificando tablas:</h2>\n";
        $tablesInfo = $database->checkTablesExist();
        if ($tablesInfo) {
            echo "📋 Tablas existentes: " . count($tablesInfo['existing']) . "<br>\n";
            echo "📋 Tablas faltantes: " . count($tablesInfo['missing']) . "<br>\n";
            
            if (!empty($tablesInfo['missing'])) {
                echo "<strong>⚠️ Tablas faltantes:</strong><br>\n";
                foreach ($tablesInfo['missing'] as $table) {
                    echo "- $table<br>\n";
                }
                
                echo "<h2>4. Ejecutando script de estructura...</h2>\n";
                if ($database->executeStructureScript()) {
                    echo "✅ Script de estructura ejecutado exitosamente<br>\n";
                } else {
                    echo "❌ Error ejecutando script de estructura<br>\n";
                }
            } else {
                echo "✅ Todas las tablas requeridas existen<br>\n";
            }
        }
        
        // Obtener estadísticas
        echo "<h2>5. Estadísticas:</h2>\n";
        $stats = $database->getStats();
        if ($stats) {
            echo "👥 Usuarios: " . $stats['users'] . "<br>\n";
            echo "📚 Playlists activas: " . $stats['active_playlists'] . "<br>\n";
            echo "🛒 Órdenes: " . $stats['orders'] . "<br>\n";
            echo "🔐 Intentos de login recientes: " . $stats['recent_login_attempts'] . "<br>\n";
        }
        
        // Probar limpieza de datos
        echo "<h2>6. Probando limpieza de datos de seguridad...</h2>\n";
        if ($database->cleanupSecurityData()) {
            echo "✅ Limpieza de datos completada<br>\n";
        } else {
            echo "❌ Error en limpieza de datos<br>\n";
        }
        
    } else {
        echo "❌ Error en la conexión<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>\n";
    echo "📍 Archivo: " . $e->getFile() . " línea " . $e->getLine() . "<br>\n";
}

echo "<hr>\n";
echo "<h2>Configuración actual:</h2>\n";
echo "🌐 Entorno: " . (isDevelopment() ? 'Desarrollo' : 'Producción') . "<br>\n";
echo "🗄️ Host DB: " . DB_HOST . "<br>\n";
echo "🗄️ Nombre DB: " . DB_NAME . "<br>\n";
echo "🗄️ Usuario DB: " . DB_USER . "<br>\n";
echo "🔑 reCAPTCHA configurado: " . (!empty(RECAPTCHA_SITE_KEY) ? 'Sí' : 'No') . "<br>\n";

if (isDevelopment()) {
    echo "<hr>\n";
    echo "<h2>Variables de entorno cargadas:</h2>\n";
    echo "<pre>\n";
    $envVars = array_filter($_ENV, function($key) {
        return strpos($key, 'DB_') === 0 || strpos($key, 'RECAPTCHA_') === 0 || strpos($key, 'APP_') === 0;
    }, ARRAY_FILTER_USE_KEY);
    
    foreach ($envVars as $key => $value) {
        if (strpos($key, 'PASSWORD') !== false || strpos($key, 'SECRET') !== false) {
            $value = str_repeat('*', strlen($value));
        }
        echo "$key = $value\n";
    }
    echo "</pre>\n";
}
?>
