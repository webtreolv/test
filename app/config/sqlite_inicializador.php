<?php

/** Crea el archivo SQLite local a partir del respaldo SQL incluido. */
function inicializar_base_datos_sqlite(string $rutaBaseDatos): void
{
    if (file_exists($rutaBaseDatos) && filesize($rutaBaseDatos) > 0) {
        return;
    }

    $directorio = dirname($rutaBaseDatos);
    if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear el directorio local de datos.');
    }

    $respaldo = __DIR__ . '/../slq/puntos_red.sql';
    if (!is_readable($respaldo)) {
        throw new RuntimeException('No se encontró el respaldo SQL inicial.');
    }

    $conexion = new PDO('sqlite:' . $rutaBaseDatos, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $conexion->exec('PRAGMA foreign_keys = OFF');
    $conexion->beginTransaction();

    try {
        foreach (separar_sentencias_sql(convertir_sql_mysql_a_sqlite((string) file_get_contents($respaldo))) as $sentencia) {
            if ($sentencia !== '') {
                $conexion->exec($sentencia);
            }
        }

        // Campo usado por los reportes de canjes; no estaba incluido en el respaldo original.
        $conexion->exec('ALTER TABLE historial_puntos ADD COLUMN monto_pesos REAL NOT NULL DEFAULT 0');
        $conexion->commit();
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        @unlink($rutaBaseDatos);
        throw new RuntimeException('No se pudo preparar el archivo local de datos: ' . $e->getMessage(), 0, $e);
    }
}

function convertir_sql_mysql_a_sqlite(string $sql): string
{
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = str_replace('`', '', $sql);
    $sql = preg_replace('/\bINT\s+UNSIGNED\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bINT\s+UNSIGNED\b/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bINT\b/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bDECIMAL\s*\([^)]*\)/i', 'REAL', $sql);
    $sql = preg_replace('/\bVARCHAR\s*\([^)]*\)/i', 'TEXT', $sql);
    $sql = preg_replace('/\bDATETIME\b/i', 'TEXT', $sql);
    $sql = preg_replace('/\bENUM\s*\([^)]*\)/i', 'TEXT', $sql);
    $sql = preg_replace('/\s+ENGINE\s*=\s*\w+(?:\s+DEFAULT\s+CHARSET\s*=\s*\w+)?(?:\s+COLLATE\s*=\s*\w+)?/i', '', $sql);
    $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+\w+\s*\(([^)]*)\)/i', ', UNIQUE ($1)', $sql);

    return $sql;
}

/** Divide en sentencias sin cortar textos SQL entre comillas simples. */
function separar_sentencias_sql(string $sql): array
{
    $resultado = [];
    $actual = '';
    $enComilla = false;
    $longitud = strlen($sql);

    for ($i = 0; $i < $longitud; $i++) {
        $caracter = $sql[$i];
        if ($caracter === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
            $enComilla = !$enComilla;
        }
        if ($caracter === ';' && !$enComilla) {
            $limpia = trim($actual);
            if ($limpia !== '' && !preg_match('/^(SELECT|SHOW)\b/i', $limpia)) {
                $resultado[] = $limpia;
            }
            $actual = '';
            continue;
        }
        $actual .= $caracter;
    }

    return $resultado;
}
