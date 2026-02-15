<?php
require_once 'db.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] === 'registrar_produccion') {
        $producto_id = $_POST['producto_id'];
        $cantidad_producida = $_POST['cantidad'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM recetas WHERE producto_id = ?");
            $stmt->execute([$producto_id]);
            $receta = $stmt->fetchAll();

            if (empty($receta)) {
                throw new Exception("El producto no tiene una receta definida.");
            }

            foreach ($receta as $item) {
                $cantidad_necesaria = $item['cantidad_requerida'] * $cantidad_producida;

                $stmt_ins = $pdo->prepare("SELECT nombre, cantidad FROM insumos WHERE id = ?");
                $stmt_ins->execute([$item['insumo_id']]);
                $insumo = $stmt_ins->fetch();

                if ($insumo['cantidad'] < $cantidad_necesaria) {
                    throw new Exception("Stock insuficiente de " . $insumo['nombre'] . ". Necesario: $cantidad_necesaria, Disponible: " . $insumo['cantidad']);
                }

                $stmt_update_ins = $pdo->prepare("UPDATE insumos SET cantidad = cantidad - ? WHERE id = ?");
                $stmt_update_ins->execute([$cantidad_necesaria, $item['insumo_id']]);
            }

            $stmt_prod = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?");
            $stmt_prod->execute([$cantidad_producida, $producto_id]);

            $stmt_log = $pdo->prepare("INSERT INTO produccion (producto_id, cantidad) VALUES (?, ?)");
            $stmt_log->execute([$producto_id, $cantidad_producida]);

            $pdo->commit();
            $mensaje = "Producción registrada exitosamente. Se han actualizado los stocks.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$productos = $pdo->query("SELECT id, nombre FROM productos ORDER BY nombre ASC")->fetchAll();
$historial = $pdo->query("SELECT p.*, pr.nombre as producto_nombre FROM produccion p JOIN productos pr ON p.producto_id = pr.id ORDER BY p.fecha DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Producción - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>El Horno Rojo - Registro de Producción</h1>
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
            <h2>Registrar Nueva Producción</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="registrar_produccion">
                <select name="producto_id" required>
                    <option value="">Seleccionar Producto...</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="cantidad" placeholder="Cantidad de Pizzas" min="1" required>
                <button type="submit">Registrar Producción</button>
            </form>
        </section>

        <section>
            <h2>Historial de Producción (Últimos 10)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $h): ?>
                        <tr>
                            <td><?php echo $h['fecha']; ?></td>
                            <td><?php echo htmlspecialchars($h['producto_nombre']); ?></td>
                            <td><?php echo $h['cantidad']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
