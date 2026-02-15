<?php
require_once 'db.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'crear_producto') {
            $stmt = $pdo->prepare("INSERT INTO productos (nombre, precio_venta, stock_actual) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['nombre'], $_POST['precio_venta'], 0]);
            $mensaje = "Producto creado correctamente.";
        } elseif ($_POST['accion'] === 'editar_producto') {
            $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, precio_venta = ? WHERE id = ?");
            $stmt->execute([$_POST['nombre'], $_POST['precio_venta'], $_POST['id']]);
            $mensaje = "Producto actualizado.";
        } elseif ($_POST['accion'] === 'añadir_insumo_receta') {
            $stmt = $pdo->prepare("INSERT INTO recetas (producto_id, insumo_id, cantidad_requerida) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['producto_id'], $_POST['insumo_id'], $_POST['cantidad_requerida']]);
            $mensaje = "Insumo añadido a la receta.";
        } elseif ($_POST['accion'] === 'eliminar_insumo_receta') {
            $stmt = $pdo->prepare("DELETE FROM recetas WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $mensaje = "Insumo eliminado de la receta.";
        }
    }
}

$productos = $pdo->query("SELECT * FROM productos ORDER BY nombre ASC")->fetchAll();
$todos_insumos = $pdo->query("SELECT id, nombre, unidad FROM insumos ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <?php if ($mensaje): ?>
            <p class="mensaje"><?php echo $mensaje; ?></p>
        <?php endif; ?>

        <section>
            <h2>Añadir Nuevo Producto (Pizza)</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="crear_producto">
                <input type="text" name="nombre" placeholder="Nombre (ej. Muzzarella Clasica)" required>
                <input type="number" name="precio_venta" placeholder="Precio de Venta (Gs.)" required>
                <button type="submit">Guardar Producto</button>
            </form>
        </section>

        <section>
            <h2>Listado de Productos</h2>
            <?php foreach ($productos as $producto): ?>
                <div class="producto-card">
                    <form method="POST" style="display:block; background:none; border:none; padding:0;">
                        <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                        <h3>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>">
                            - Gs. <input type="number" name="precio_venta" value="<?php echo $producto['precio_venta']; ?>">
                            <button type="submit" name="accion" value="editar_producto">Actualizar</button>
                        </h3>
                        <p>Stock Actual: <strong><?php echo $producto['stock_actual']; ?></strong> unidades</p>
                    </form>

                    <h4>Receta / Insumos Necesarios</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Insumo</th>
                                <th>Cantidad Necesaria</th>
                                <th>Costo Unitario</th>
                                <th>Subtotal</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("SELECT r.*, i.nombre, i.unidad, i.precio_unitario FROM recetas r JOIN insumos i ON r.insumo_id = i.id WHERE r.producto_id = ?");
                            $stmt->execute([$producto['id']]);
                            $receta_items = $stmt->fetchAll();
                            $costo_total = 0;
                            foreach ($receta_items as $item):
                                $subtotal = $item['cantidad_requerida'] * $item['precio_unitario'];
                                $costo_total += $subtotal;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nombre']); ?> (<?php echo htmlspecialchars($item['unidad']); ?>)</td>
                                    <td><?php echo $item['cantidad_requerida']; ?></td>
                                    <td>Gs. <?php echo number_format($item['precio_unitario'], 0, ',', '.'); ?></td>
                                    <td>Gs. <?php echo number_format($subtotal, 0, ',', '.'); ?></td>
                                    <td>
                                        <form method="POST" style="background:none; border:none; padding:0;">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="accion" value="eliminar_insumo_receta" onclick="return confirm('¿Eliminar de la receta?')">x</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Costo de Producción Total</th>
                                <th>Gs. <?php echo number_format($costo_total, 0, ',', '.'); ?></th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="3">Ganancia Estimada por Unidad</th>
                                <th style="color: green;">Gs. <?php echo number_format($producto['precio_venta'] - $costo_total, 0, ',', '.'); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>

                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="accion" value="añadir_insumo_receta">
                        <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                        <select name="insumo_id" required>
                            <option value="">Seleccionar Insumo...</option>
                            <?php foreach ($todos_insumos as $ins): ?>
                                <option value="<?php echo $ins['id']; ?>"><?php echo htmlspecialchars($ins['nombre']); ?> (<?php echo htmlspecialchars($ins['unidad']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" step="0.001" name="cantidad_requerida" placeholder="Cantidad" required>
                        <button type="submit">Añadir a Receta</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
