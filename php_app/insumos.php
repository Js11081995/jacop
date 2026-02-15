<?php
require_once 'db.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'crear') {
            $stmt = $pdo->prepare("INSERT INTO insumos (nombre, cantidad, unidad, stock_minimo, precio_unitario) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['nombre'], $_POST['cantidad'], $_POST['unidad'], $_POST['stock_minimo'], $_POST['precio_unitario']]);
            $mensaje = "Insumo creado correctamente.";
        } elseif ($_POST['accion'] === 'editar') {
            $stmt = $pdo->prepare("UPDATE insumos SET nombre = ?, cantidad = ?, unidad = ?, stock_minimo = ?, precio_unitario = ? WHERE id = ?");
            $stmt->execute([$_POST['nombre'], $_POST['cantidad'], $_POST['unidad'], $_POST['stock_minimo'], $_POST['precio_unitario'], $_POST['id']]);
            $mensaje = "Insumo actualizado correctamente.";
        } elseif ($_POST['accion'] === 'eliminar') {
            $stmt = $pdo->prepare("DELETE FROM insumos WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $mensaje = "Insumo eliminado.";
        }
    }
}

$insumos = $pdo->query("SELECT * FROM insumos ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Insumos - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>El Horno Rojo - Inventario de Insumos</h1>
        <nav>
            <a href="index.php">Dashboard</a> |
            <a href="insumos.php">Insumos</a> |
            <a href="productos.php">Productos</a> |
            <a href="produccion.php">Producción</a> |
            <a href="ventas.php">Ventas</a> |
            <a href="mayoristas.php">Mayoristas</a> |
            <a href="promociones.php">Promociones</a> |
            <a href="calculadora_masa.php">Calculadora</a>
        </nav>
    </header>

    <main>
        <?php if ($mensaje): ?>
            <p class="mensaje"><?php echo $mensaje; ?></p>
        <?php endif; ?>

        <section>
            <h2>Añadir Nuevo Insumo</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <input type="text" name="nombre" placeholder="Nombre (ej. Harina)" required>
                <input type="number" step="0.01" name="cantidad" placeholder="Cantidad Inicial" required>
                <input type="text" name="unidad" placeholder="Unidad (ej. Kg, L)" required>
                <input type="number" step="0.01" name="stock_minimo" placeholder="Stock Mínimo" required>
                <input type="number" step="1" name="precio_unitario" placeholder="Costo Unitario (Gs.)" required>
                <button type="submit">Guardar</button>
            </form>
        </section>

        <section>
            <h2>Listado de Insumos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Cantidad Actual</th>
                        <th>Unidad</th>
                        <th>Stock Mínimo</th>
                        <th>Costo Unitario (Gs.)</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insumos as $insumo): ?>
                        <tr class="<?php echo ($insumo['cantidad'] <= $insumo['stock_minimo']) ? 'alerta' : ''; ?>">
                            <form method="POST" id="form-edit-<?php echo $insumo['id']; ?>">
                                <input type="hidden" name="id" value="<?php echo $insumo['id']; ?>">
                            </form>
                            <td><input type="text" name="nombre" value="<?php echo htmlspecialchars($insumo['nombre']); ?>" form="form-edit-<?php echo $insumo['id']; ?>"></td>
                            <td><input type="number" step="0.01" name="cantidad" value="<?php echo $insumo['cantidad']; ?>" form="form-edit-<?php echo $insumo['id']; ?>"></td>
                            <td><input type="text" name="unidad" value="<?php echo htmlspecialchars($insumo['unidad']); ?>" form="form-edit-<?php echo $insumo['id']; ?>"></td>
                            <td><input type="number" step="0.01" name="stock_minimo" value="<?php echo $insumo['stock_minimo']; ?>" form="form-edit-<?php echo $insumo['id']; ?>"></td>
                            <td><input type="number" step="1" name="precio_unitario" value="<?php echo $insumo['precio_unitario']; ?>" form="form-edit-<?php echo $insumo['id']; ?>"></td>
                            <td>
                                <?php if ($insumo['cantidad'] <= 0): ?>
                                    <span class="badge-error">AGOTADO</span>
                                <?php elseif ($insumo['cantidad'] <= $insumo['stock_minimo']): ?>
                                    <span class="badge-warning">BAJO STOCK</span>
                                <?php else: ?>
                                    <span class="badge-ok">OK</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="submit" name="accion" value="editar" form="form-edit-<?php echo $insumo['id']; ?>">Actualizar</button>
                                <button type="submit" name="accion" value="eliminar" form="form-edit-<?php echo $insumo['id']; ?>" onclick="return confirm('¿Seguro?')">Eliminar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
