<?php
$db_path = __DIR__ . '/database.sqlite';
try {
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Inicialización automática si es necesario
    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
