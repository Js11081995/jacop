<?php
$db_path = __DIR__ . '/database.sqlite';
try {
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Inicialización automática si no existen las tablas
    $res = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='insumos'");
    if (!$res->fetch()) {
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    } else {
        // Migración automática: Añadir columnas a 'ventas' si no existen (v5 updates)
        $stmt = $pdo->query("PRAGMA table_info(ventas)");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

        if (!in_array('costo_unitario', $columns)) {
            $pdo->exec("ALTER TABLE ventas ADD COLUMN costo_unitario REAL DEFAULT 0");
        }
        if (!in_array('venta_id', $columns)) {
            $pdo->exec("ALTER TABLE ventas ADD COLUMN venta_id TEXT");
        }
    }

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
