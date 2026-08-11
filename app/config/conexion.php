<?php
/**
 * Conexión local de Puntos Red.
 *
 * No requiere MySQL ni variables de entorno: todos los datos se guardan en
 * data/puntos_red.sqlite. El archivo se crea y carga con los datos de ejemplo
 * la primera vez que se abre la aplicación.
 */

require_once __DIR__ . '/sqlite_inicializador.php';

$pdo = null;

function obtener_conexion(): PDO
{
    global $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $rutaBaseDatos = __DIR__ . '/../data/puntos_red.sqlite';
    inicializar_base_datos_sqlite($rutaBaseDatos);

    $pdo = new PDO('sqlite:' . $rutaBaseDatos, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    return $pdo;
}

function verificar_conexion(): bool
{
    try {
        obtener_conexion()->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        error_log('Error al abrir el archivo local de datos: ' . $e->getMessage());
        return false;
    }
}

$pdo = obtener_conexion();
