<?php
require_once 'db.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'crear_producto') {
            $stmt = $pdo->prepare("INSERT INTO productos (nombre, precio_venta, stock_actual) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['nombre'], $_POST['precio_venta'], 0]);
            $mensaje = "Producto '" . htmlspecialchars($_POST['nombre']) . "' creado correctamente.";
        } elseif ($_POST['accion'] === 'editar_producto') {
            $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, precio_venta = ? WHERE id = ?");
            $stmt->execute([$_POST['nombre'], $_POST['precio_venta'], $_POST['id']]);
            $mensaje = "Producto actualizado.";
        } elseif ($_POST['accion'] === 'añadir_insumo_receta') {
            try {
                $stmt = $pdo->prepare("INSERT INTO recetas (producto_id, insumo_id, cantidad_requerida) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['producto_id'], $_POST['insumo_id'], $_POST['cantidad_requerida']]);
                $mensaje = "Insumo añadido a la receta.";
            } catch (PDOException $e) {
                $mensaje = "Error: El insumo ya está en la receta o los datos son inválidos.";
            }
        } elseif ($_POST['accion'] === 'eliminar_insumo_receta') {
            $stmt = $pdo->prepare("DELETE FROM recetas WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $mensaje = "Insumo eliminado de la receta.";
        } elseif ($_POST['accion'] === 'eliminar_producto') {
            $pdo->prepare("DELETE FROM recetas WHERE producto_id = ?")->execute([$_POST['id']]);
            $pdo->prepare("DELETE FROM productos WHERE id = ?")->execute([$_POST['id']]);
            $mensaje = "Producto eliminado.";
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
    <title>Gestión de Productos y Recetas - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .producto-card {
            border: 2px solid #b71c1c;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            background: #fff;
        }
        .recipe-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .recipe-table th, .recipe-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .recipe-table th {
            background-color: #f2f2f2;
        }
        .btn-delete {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 4px;
        }
        .cost-summary {
            margin-top: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-left: 5px solid #b71c1c;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <h1>Gestión de Productos (Pizzas)</h1>

        <?php if ($mensaje): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2>+ Crear Nuevo Producto</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="crear_producto">
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="nombre" placeholder="Nombre de la Pizza" required style="flex: 2;">
                    <input type="number" name="precio_venta" placeholder="Precio Venta (Gs.)" required style="flex: 1;">
                    <button type="submit" class="btn">Crear Producto</button>
                </div>
            </form>
        </section>

        <section>
            <h2>Listado de Productos y sus Recetas</h2>
            <?php if (empty($productos)): ?>
                <p>No hay productos registrados. Comienza creando uno arriba.</p>
            <?php endif; ?>

            <?php foreach ($productos as $producto): ?>
                <div class="producto-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                        <form method="POST" style="display: flex; gap: 10px; background: none; border: none; padding: 0; width: 80%;">
                            <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" style="font-size: 1.2em; font-weight: bold;">
                            <span style="align-self: center;">Gs.</span>
                            <input type="number" name="precio_venta" value="<?php echo $producto['precio_venta']; ?>" style="width: 100px;">
                            <button type="submit" name="accion" value="editar_producto" class="btn" style="padding: 5px 10px;">Actualizar</button>
                        </form>
                        <form method="POST" style="background: none; border: none; padding: 0;">
                            <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                            <button type="submit" name="accion" value="eliminar_producto" class="btn-delete" onclick="return confirm('¿Eliminar producto y su receta?')">Eliminar Producto</button>
                        </form>
                    </div>

                    <p>Stock Disponible: <strong><?php echo $producto['stock_actual']; ?></strong> unidades</p>

                    <h4>Ingredientes de la Receta</h4>
                    <table class="recipe-table">
                        <thead>
                            <tr>
                                <th>Insumo</th>
                                <th>Cantidad</th>
                                <th>Costo Unit.</th>
                                <th>Subtotal</th>
                                <th>Eliminar</th>
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
                                            <button type="submit" name="accion" value="eliminar_insumo_receta" class="btn-delete" style="padding: 2px 5px;">x</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="cost-summary">
                        <strong>Costo Producción: Gs. <?php echo number_format($costo_total, 0, ',', '.'); ?></strong> |
                        <strong style="color: #2e7d32;">Ganancia: Gs. <?php echo number_format($producto['precio_venta'] - $costo_total, 0, ',', '.'); ?></strong>
                    </div>

                    <form method="POST" style="margin-top: 15px; background: #f1f1f1; padding: 10px; border-radius: 4px;">
                        <input type="hidden" name="accion" value="añadir_insumo_receta">
                        <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                        <strong>Añadir Ingrediente:</strong>
                        <select name="insumo_id" required>
                            <option value="">Seleccionar Insumo...</option>
                            <?php foreach ($todos_insumos as $ins): ?>
                                <option value="<?php echo $ins['id']; ?>"><?php echo htmlspecialchars($ins['nombre']); ?> (<?php echo htmlspecialchars($ins['unidad']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" step="0.001" name="cantidad_requerida" placeholder="Cantidad" required style="width: 80px;">
                        <button type="submit" class="btn" style="padding: 5px 10px;">Añadir</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
