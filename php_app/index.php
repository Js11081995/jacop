<?php
require_once 'db.php';

// Alertas de insumos bajos
$insumos_bajos = $pdo->query("SELECT * FROM insumos WHERE cantidad <= stock_minimo AND tipo = 'materia_prima'")->fetchAll();

// Estadísticas de ventas
$total_ventas = $pdo->query("SELECT SUM(total) as total FROM ventas")->fetch()['total'] ?: 0;
$total_mayoristas = $pdo->query("SELECT SUM(total) as total FROM pedidos_mayoristas WHERE estado = 'pagado' OR estado = 'entregado'")->fetch()['total'] ?: 0;

// Stock de Productos Terminados
$productos_stock = $pdo->query("SELECT * FROM productos WHERE stock_actual > 0")->fetchAll();

// Stock de Prepizzas (Separado según pedido del usuario)
$prepizzas = $pdo->query("SELECT * FROM insumos WHERE nombre LIKE 'Prepizza%' OR nombre LIKE 'Bollo%'")->fetchAll();

// Valorización del Inventario
$valor_inventario = $pdo->query("SELECT SUM(cantidad * precio_unitario) as total FROM insumos")->fetch()['total'] ?: 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <div class="dashboard-grid">

            <section class="card">
                <h2>Estado de Insumos</h2>
                <?php if (empty($insumos_bajos)): ?>
                    <p class="badge-ok">Inventario de materias primas en niveles óptimos.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($insumos_bajos as $i): ?>
                            <li style="margin-bottom: 10px; padding: 10px; border-left: 5px solid #ff9800; background: #fff3e0;">
                                <strong><?php echo htmlspecialchars($i['nombre']); ?></strong>:
                                <?php echo $i['cantidad']; ?> <?php echo $i['unidad']; ?>
                                <br><span class="badge-warning">BAJO STOCK (Mín: <?php echo $i['stock_minimo']; ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <hr>
                <div style="font-size: 1.1em; margin-top: 10px;">
                    Inversión Total en Stock: <br>
                    <strong style="font-size: 1.4em; color: #2e7d32;">Gs. <?php echo number_format($valor_inventario, 0, ',', '.'); ?></strong>
                </div>
            </section>

            <section class="card">
                <h2>Resumen Financiero (Ventas)</h2>
                <div style="font-size: 1.2em; margin-bottom: 10px;">
                    Minoristas: <strong>Gs. <?php echo number_format($total_ventas, 0, ',', '.'); ?></strong>
                </div>
                <div style="font-size: 1.2em; margin-bottom: 10px;">
                    Mayoristas: <strong>Gs. <?php echo number_format($total_mayoristas, 0, ',', '.'); ?></strong>
                </div>
                <hr>
                <div style="font-size: 1.5em; color: #b71c1c;">
                    Total Ingresos: <strong>Gs. <?php echo number_format($total_ventas + $total_mayoristas, 0, ',', '.'); ?></strong>
                </div>
                <br>
                <a href="ventas.php" class="btn">Registrar Venta</a>
                <a href="insumos.php" class="btn" style="background: #555;">Ver Inventario Completo</a>
            </section>

            <section class="card">
                <h2>Stock de Prepizzas (Bollos)</h2>
                <?php foreach ($prepizzas as $pre): ?>
                    <div style="text-align: center; padding: 15px; background: #e3f2fd; border-radius: 8px; border: 1px solid #2196f3;">
                        <span style="font-size: 2.5em; font-weight: bold; color: #1565c0;"><?php echo $pre['cantidad']; ?></span>
                        <br>Unidades disponibles
                        <br><small>Costo unitario actual: Gs. <?php echo number_format($pre['precio_unitario'], 0, ',', '.'); ?></small>
                    </div>
                <?php endforeach; ?>
                <br>
                <a href="calculadora_masa.php" class="btn" style="width: 100%; text-align: center;">Producir Más Masa</a>
            </section>

            <section class="card">
                <h2>Stock de Pizzas Terminadas</h2>
                <table>
                    <thead>
                        <tr><th>Producto</th><th>Stock</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos_stock as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                                <td><strong><?php echo $p['stock_actual']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($productos_stock)): ?>
                            <tr><td colspan="2">No hay pizzas listas para la venta.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <br>
                <a href="produccion.php" class="btn" style="width: 100%; text-align: center;">Registrar Producción de Pizza</a>
            </section>

        </div>
    </main>
</body>
</html>
