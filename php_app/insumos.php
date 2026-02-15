<?php
require_once 'db.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        try {
            if ($_POST['accion'] === 'crear') {
                $stmt = $pdo->prepare("INSERT INTO insumos (nombre, cantidad, unidad, stock_minimo, precio_unitario, tipo) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['nombre'], $_POST['cantidad'], $_POST['unidad'], $_POST['stock_minimo'], $_POST['precio_unitario'], $_POST['tipo']]);
                $mensaje = "Insumo creado correctamente.";
            } elseif ($_POST['accion'] === 'actualizar') {
                $stmt = $pdo->prepare("UPDATE insumos SET cantidad = ?, stock_minimo = ?, precio_unitario = ? WHERE id = ?");
                $stmt->execute([$_POST['cantidad'], $_POST['stock_minimo'], $_POST['precio_unitario'], $_POST['id']]);
                $mensaje = "Insumo actualizado.";
            } elseif ($_POST['accion'] === 'eliminar') {
                $stmt = $pdo->prepare("DELETE FROM insumos WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $mensaje = "Insumo eliminado.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensaje = "Error: Ya existe un insumo con ese nombre.";
            } else {
                $mensaje = "Error en la base de datos: " . $e->getMessage();
            }
        }
    }
}

$insumos = $pdo->query("SELECT * FROM insumos ORDER BY tipo DESC, nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario de Insumos - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .tipo-badge {
            font-size: 0.8em;
            padding: 2px 6px;
            border-radius: 4px;
            background: #eee;
            color: #666;
        }
        .tipo-elaborado {
            background: #e3f2fd;
            color: #1565c0;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <h1>Gestión de Inventario</h1>

        <?php if ($mensaje): ?>
            <p class="mensaje"><?php echo $mensaje; ?></p>
        <?php endif; ?>

        <section class="card">
            <h2>Registrar Nuevo Insumo / Materia Prima</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                    <input type="text" name="nombre" placeholder="Nombre (ej. Harina)" required>
                    <select name="unidad" required>
                        <option value="Kg">Kilogramos (Kg)</option>
                        <option value="L">Litros (L)</option>
                        <option value="unidades">Unidades</option>
                        <option value="g">Gramos (g)</option>
                    </select>
                    <input type="number" step="0.01" name="cantidad" placeholder="Cantidad Inicial" required>
                    <input type="number" step="0.01" name="stock_minimo" placeholder="Stock Mínimo (Alerta)" required>
                    <input type="number" name="precio_unitario" placeholder="Precio por Kg/L/Unidad (Gs.)" required>
                    <select name="tipo">
                        <option value="materia_prima">Materia Prima (Compra)</option>
                        <option value="elaborado">Elaborado (Producción propia)</option>
                    </select>
                </div>
                <button type="submit" class="btn" style="margin-top: 10px;">Guardar Insumo</button>
            </form>
        </section>

        <section>
            <h2>Listado de Existencias</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Stock Actual</th>
                        <th>Unidad</th>
                        <th>Stock Mín.</th>
                        <th>Precio por Kg/L/Ud</th>
                        <th>Valorización</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insumos as $i): ?>
                        <tr <?php if ($i['cantidad'] <= $i['stock_minimo']) echo 'style="background-color: #fffde7;"'; ?>>
                            <form method="POST">
                                <input type="hidden" name="id" value="<?php echo $i['id']; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($i['nombre']); ?></strong>
                                    <br>
                                    <span class="tipo-badge <?php echo $i['tipo'] === 'elaborado' ? 'tipo-elaborado' : ''; ?>">
                                        <?php echo $i['tipo'] === 'elaborado' ? 'ELABORADO' : 'MATERIA PRIMA'; ?>
                                    </span>
                                </td>
                                <td><input type="number" step="0.01" name="cantidad" value="<?php echo $i['cantidad']; ?>" style="width: 80px;"></td>
                                <td><?php echo $i['unidad']; ?></td>
                                <td><input type="number" step="0.01" name="stock_minimo" value="<?php echo $i['stock_minimo']; ?>" style="width: 80px;"></td>
                                <td>Gs. <input type="number" name="precio_unitario" value="<?php echo $i['precio_unitario']; ?>" style="width: 100px;"></td>
                                <td>Gs. <?php echo number_format($i['cantidad'] * $i['precio_unitario'], 0, ',', '.'); ?></td>
                                <td>
                                    <button type="submit" name="accion" value="actualizar" class="btn" style="padding: 5px 10px;">Actualizar</button>
                                    <button type="submit" name="accion" value="eliminar" class="btn" style="padding: 5px 10px; background: #c62828;" onclick="return confirm('¿Eliminar insumo?')">Eliminar</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
