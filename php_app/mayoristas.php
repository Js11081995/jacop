<?php
require_once 'db.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'registrar_pedido') {
            $cliente = $_POST['cliente'];
            $producto_id = $_POST['producto_id'];
            $cantidad = $_POST['cantidad'];
            $precio_unitario = $_POST['precio_unitario'];
            $total = $cantidad * $precio_unitario;

            try {
                $pdo->beginTransaction();

                // 1. Verificar stock
                $stmt = $pdo->prepare("SELECT stock_actual, nombre FROM productos WHERE id = ?");
                $stmt->execute([$producto_id]);
                $producto = $stmt->fetch();

                if ($producto['stock_actual'] < $cantidad) {
                    throw new Exception("Stock insuficiente de " . $producto['nombre'] . ". Disponible: " . $producto['stock_actual']);
                }

                // 2. Descontar stock
                $stmt_upd = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?");
                $stmt_upd.execute([$cantidad, $producto_id]);

                // 3. Registrar pedido
                $stmt_ins = $pdo->prepare("INSERT INTO pedidos_mayoristas (cliente, producto_id, cantidad, precio_unitario, total, estado) VALUES (?, ?, ?, ?, ?, 'entregado')");
                $stmt_ins->execute([$cliente, $producto_id, $cantidad, $precio_unitario, $total]);

                $pdo->commit();
                $mensaje = "Pedido mayorista registrado con éxito.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        } elseif ($_POST['accion'] === 'cambiar_estado') {
            $stmt = $pdo->prepare("UPDATE pedidos_mayoristas SET estado = ? WHERE id = ?");
            $stmt->execute([$_POST['estado'], $_POST['id']]);
            $mensaje = "Estado actualizado.";
        }
    }
}

$productos = $pdo->query("SELECT id, nombre, stock_actual FROM productos ORDER BY nombre ASC")->fetchAll();
$pedidos = $pdo->query("SELECT pm.*, p.nombre as producto_nombre FROM pedidos_mayoristas pm JOIN productos p ON pm.producto_id = p.id ORDER BY pm.fecha DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos Mayoristas - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>El Horno Rojo - Gestión Mayorista</h1>
        <nav>
            <a href="index.php">Dashboard</a> |
            <a href="insumos.php">Insumos</a> |
            <a href="productos.php">Productos</a> |
            <a href="produccion.php">Producción</a> |
            <a href="ventas.php">Ventas</a> |
            <a href="mayoristas.php">Mayoristas</a>
        </nav>
    </header>

    <main>
        <?php if ($mensaje): ?>
            <p class="mensaje"><?php echo $mensaje; ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="mensaje" style="background-color: #ffebee; color: #c62828; border-color: #ef9a9a;">Error: <?php echo $error; ?></p>
        <?php endif; ?>

        <section>
            <h2>Registrar Venta Mayorista</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="registrar_pedido">
                <input type="text" name="cliente" placeholder="Nombre del Cliente / Empresa" required>
                <select name="producto_id" required>
                    <option value="">Seleccionar Producto...</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?> (Stock: <?php echo $p['stock_actual']; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="cantidad" placeholder="Cantidad" required>
                <input type="number" name="precio_unitario" placeholder="Precio Especial (Gs.)" required>
                <button type="submit">Registrar Pedido</button>
            </form>
        </section>

        <section>
            <h2>Historial de Pedidos Mayoristas</h2>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $ped): ?>
                        <tr>
                            <td><?php echo $ped['fecha']; ?></td>
                            <td><?php echo htmlspecialchars($ped['cliente']); ?></td>
                            <td><?php echo htmlspecialchars($ped['producto_nombre']); ?></td>
                            <td><?php echo $ped['cantidad']; ?></td>
                            <td>Gs. <?php echo number_format($ped['precio_unitario'], 0, ',', '.'); ?></td>
                            <td>Gs. <?php echo number_format($ped['total'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="badge-<?php echo ($ped['estado'] == 'pagado' ? 'ok' : ($ped['estado'] == 'entregado' ? 'warning' : 'error')); ?>">
                                    <?php echo strtoupper($ped['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $ped['id']; ?>">
                                    <select name="estado" onchange="this.form.submit()">
                                        <option value="pendiente" <?php if($ped['estado']=='pendiente') echo 'selected'; ?>>Pendiente</option>
                                        <option value="entregado" <?php if($ped['estado']=='entregado') echo 'selected'; ?>>Entregado</option>
                                        <option value="pagado" <?php if($ped['estado']=='pagado') echo 'selected'; ?>>Pagado</option>
                                    </select>
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
