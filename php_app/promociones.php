<?php
require_once 'db.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        try {
            if ($_POST['accion'] === 'crear_promo') {
                $stmt = $pdo->prepare("INSERT INTO promociones (nombre, precio_promo, descripcion) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['nombre'], $_POST['precio_promo'], $_POST['descripcion']]);
                $mensaje = "Promoción creada correctamente.";
            } elseif ($_POST['accion'] === 'eliminar') {
                $stmt = $pdo->prepare("DELETE FROM promociones WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $mensaje = "Promoción eliminada.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensaje = "Error: Ya existe una promoción con ese nombre.";
            } else {
                $mensaje = "Error: " . $e->getMessage();
            }
        }
    }
}

$promociones = $pdo->query("SELECT * FROM promociones ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Promociones - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <?php if ($mensaje): ?>
            <p class="mensaje"><?php echo $mensaje; ?></p>
        <?php endif; ?>

        <section>
            <h2>Crear Nueva Promoción / Combo</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="crear_promo">
                <input type="text" name="nombre" placeholder="Nombre (ej. Combo 2x1)" required>
                <input type="number" name="precio_promo" placeholder="Precio (Gs.)" required>
                <input type="text" name="descripcion" placeholder="Descripción (ej. 2 pizzas de muzzarella)">
                <button type="submit">Guardar Promoción</button>
            </form>
        </section>

        <section>
            <h2>Listado de Promociones</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Precio (Gs.)</th>
                        <th>Descripción</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promociones as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                            <td>Gs. <?php echo number_format($p['precio_promo'], 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($p['descripcion']); ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="accion" value="eliminar" onclick="return confirm('¿Seguro?')">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($promociones)): ?>
                        <tr><td colspan="4">No hay promociones activas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
