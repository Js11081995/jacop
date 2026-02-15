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
    }

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
