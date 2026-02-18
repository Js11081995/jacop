<?php
require_once 'db.php';

$mensaje = '';
$error = '';

/**
 * Calcula el costo de producción actual de un producto
 */
function calcularCostoProduccion($pdo, $producto_id) {
    $stmt = $pdo->prepare("SELECT r.cantidad_requerida, i.unidad, i.precio_unitario
                            FROM recetas r
                            JOIN insumos i ON r.insumo_id = i.id
                            WHERE r.producto_id = ?");
    $stmt->execute([$producto_id]);
    $items = $stmt->fetchAll();

    $costo_total = 0;
    foreach ($items as $item) {
        $divisor = ($item['unidad'] === 'g' || $item['unidad'] === 'ml') ? 1000 : 1;
        $costo_total += ($item['cantidad_requerida'] / $divisor) * $item['precio_unitario'];
    }
    return $costo_total;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] === 'registrar_venta') {
        $cliente = $_POST['cliente'];
        $productos_ids = $_POST['producto_id']; // Array
        $cantidades = $_POST['cantidad']; // Array
        $venta_id = 'V' . time() . rand(100, 999);

        try {
            $pdo->beginTransaction();
            $total_venta = 0;
            $items_procesados = 0;

            for ($i = 0; $i < count($productos_ids); $i++) {
                $p_id = $productos_ids[$i];
                $cant = intval($cantidades[$i]);

                if (empty($p_id) || $cant <= 0) continue;

                $stmt = $pdo->prepare("SELECT nombre, stock_actual, precio_venta FROM productos WHERE id = ?");
                $stmt->execute([$p_id]);
                $producto = $stmt->fetch();

                if (!$producto) {
                    throw new Exception("Producto ID $p_id no encontrado.");
                }

                if ($producto['stock_actual'] < $cant) {
                    throw new Exception("Stock insuficiente de " . $producto['nombre'] . ". Disponible: " . $producto['stock_actual']);
                }

                $costo_unitario = calcularCostoProduccion($pdo, $p_id);
                $subtotal = $cant * $producto['precio_venta'];
                $total_venta += $subtotal;

                // Descontar stock
                $stmt_update = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?");
                $stmt_update->execute([$cant, $p_id]);

                // Registrar item de venta
                $stmt_log = $pdo->prepare("INSERT INTO ventas (venta_id, cliente, producto_id, cantidad, precio_unitario, costo_unitario, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_log->execute([$venta_id, $cliente, $p_id, $cant, $producto['precio_venta'], $costo_unitario, $subtotal]);

                $items_procesados++;
            }

            if ($items_procesados === 0) {
                throw new Exception("Debe seleccionar al menos un producto con cantidad válida.");
            }

            $pdo->commit();
            $mensaje = "Venta $venta_id registrada exitosamente por un total de Gs. " . number_format($total_venta, 0, ',', '.');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$productos_lista = $pdo->query("SELECT id, nombre, stock_actual, precio_venta FROM productos ORDER BY nombre ASC")->fetchAll();
$historial = $pdo->query("SELECT v.*, pr.nombre as producto_nombre FROM ventas v JOIN productos pr ON v.producto_id = pr.id ORDER BY v.fecha DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .venta-item { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #f9f9f9; padding: 10px; border-radius: 4px; }
        .btn-remove { background: #c62828; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <h1>Registro de Ventas</h1>

        <?php if ($mensaje): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 5px solid #2e7d32;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 5px solid #c62828;">
                Error: <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2>Nueva Venta</h2>
            <form method="POST" id="venta-form">
                <input type="hidden" name="accion" value="registrar_venta">
                <div style="margin-bottom: 20px;">
                    <label>Cliente:</label>
                    <input type="text" name="cliente" placeholder="Nombre del Cliente" required style="width: 100%; max-width: 400px;">
                </div>

                <div id="productos-contenedor">
                    <div class="venta-item">
                        <select name="producto_id[]" required style="flex: 2;">
                            <option value="">Seleccionar Sabor...</option>
                            <?php foreach ($productos_lista as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['nombre']); ?> (Gs. <?php echo number_format($p['precio_venta'], 0, ',', '.'); ?>) - Stock: <?php echo $p['stock_actual']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="cantidad[]" placeholder="Cant." min="1" required style="width: 80px;">
                        <span>Unid.</span>
                    </div>
                </div>

                <button type="button" onclick="agregarFila()" class="btn" style="background: #555; margin-bottom: 20px;">+ Agregar otro sabor</button>

                <div style="border-top: 2px solid #eee; padding-top: 20px;">
                    <button type="submit" class="btn" style="width: 100%; font-size: 1.2em; padding: 15px;">FINALIZAR VENTA</button>
                </div>
            </form>
        </section>

        <section>
            <h2>Historial de Ventas Recientes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>ID Venta</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th>Cant.</th>
                        <th>Precio Unit.</th>
                        <th>Costo Est.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $v): ?>
                        <tr>
                            <td><?php echo date('d/m H:i', strtotime($v['fecha'])); ?></td>
                            <td><small><?php echo $v['venta_id']; ?></small></td>
                            <td><?php echo htmlspecialchars($v['cliente']); ?></td>
                            <td><?php echo htmlspecialchars($v['producto_nombre']); ?></td>
                            <td><?php echo $v['cantidad']; ?></td>
                            <td>Gs. <?php echo number_format($v['precio_unitario'], 0, ',', '.'); ?></td>
                            <td>Gs. <?php echo number_format($v['costo_unitario'], 0, ',', '.'); ?></td>
                            <td><strong>Gs. <?php echo number_format($v['total'], 0, ',', '.'); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>

    <script>
    function agregarFila() {
        const contenedor = document.getElementById('productos-contenedor');
        const nuevaFila = document.createElement('div');
        nuevaFila.className = 'venta-item';
        nuevaFila.innerHTML = `
            <select name="producto_id[]" required style="flex: 2;">
                <option value="">Seleccionar Sabor...</option>
                <?php foreach ($productos_lista as $p): ?>
                    <option value="<?php echo $p['id']; ?>">
                        <?php echo htmlspecialchars($p['nombre']); ?> (Gs. <?php echo number_format($p['precio_venta'], 0, ',', '.'); ?>) - Stock: <?php echo $p['stock_actual']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="cantidad[]" placeholder="Cant." min="1" required style="width: 80px;">
            <span>Unid.</span>
            <button type="button" class="btn-remove" onclick="this.parentElement.remove()">X</button>
        `;
        contenedor.appendChild(nuevaFila);
    }
    </script>
</body>
</html>
