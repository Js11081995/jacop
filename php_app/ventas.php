<?php
require_once 'db.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] === 'registrar_venta') {
        $producto_id = $_POST['producto_id'];
        $cantidad_vendida = $_POST['cantidad'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT nombre, stock_actual, precio_venta FROM productos WHERE id = ?");
            $stmt->execute([$producto_id]);
            $producto = $stmt->fetch();

            if (!$producto) {
                throw new Exception("Producto no encontrado.");
            }

            if ($producto['stock_actual'] < $cantidad_vendida) {
                throw new Exception("Stock insuficiente de " . $producto['nombre'] . ". Disponible: " . $producto['stock_actual']);
            }

            $total = $cantidad_vendida * $producto['precio_venta'];

            $stmt_update = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?");
            $stmt_update->execute([$cantidad_vendida, $producto_id]);

            $stmt_log = $pdo->prepare("INSERT INTO ventas (producto_id, cantidad, precio_unitario, total) VALUES (?, ?, ?, ?)");
            $stmt_log->execute([$producto_id, $cantidad_vendida, $producto['precio_venta'], $total]);

            $pdo->commit();
            $mensaje = "Venta registrada exitosamente por un total de Gs. " . number_format($total, 0, ',', '.');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$productos = $pdo->query("SELECT id, nombre, stock_actual, precio_venta FROM productos ORDER BY nombre ASC")->fetchAll();
$historial = $pdo->query("SELECT v.*, pr.nombre as producto_nombre FROM ventas v JOIN productos pr ON v.producto_id = pr.id ORDER BY v.fecha DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>El Horno Rojo - Registro de Ventas</h1>
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
        <?php if ($error): ?>
            <p class="mensaje" style="background-color: #ffebee; color: #c62828; border-color: #ef9a9a;"><?php echo $error; ?></p>
        <?php endif; ?>

        <section>
            <h2>Registrar Nueva Venta (Minorista)</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="registrar_venta">
                <select name="producto_id" required>
                    <option value="">Seleccionar Producto...</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?> (Gs. <?php echo number_format($p['precio_venta'], 0, ',', '.'); ?>) - Stock: <?php echo $p['stock_actual']; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="cantidad" placeholder="Cantidad" min="1" required>
                <button type="submit">Registrar Venta</button>
            </form>
        </section>

        <section>
            <h2>Historial de Ventas Recientes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $v): ?>
                        <tr>
                            <td><?php echo $v['fecha']; ?></td>
                            <td><?php echo htmlspecialchars($v['producto_nombre']); ?></td>
                            <td><?php echo $v['cantidad']; ?></td>
                            <td>Gs. <?php echo number_format($v['precio_unitario'], 0, ',', '.'); ?></td>
                            <td>Gs. <?php echo number_format($v['total'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
