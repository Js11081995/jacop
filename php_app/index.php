<?php
require_once 'db.php';

// Alertas de insumos bajos
$insumos_bajos = $pdo->query("SELECT * FROM insumos WHERE cantidad <= stock_minimo")->fetchAll();

// Estadísticas básicas de ventas (minoristas)
$total_ventas = $pdo->query("SELECT SUM(total) as total FROM ventas")->fetch()['total'] ?: 0;
$cantidad_ventas = $pdo->query("SELECT COUNT(*) as cuenta FROM ventas")->fetch()['cuenta'];

// Estadísticas de ventas mayoristas
$total_mayoristas = $pdo->query("SELECT SUM(total) as total FROM pedidos_mayoristas WHERE estado = 'pagado' OR estado = 'entregado'")->fetch()['total'] ?: 0;
$cantidad_mayoristas = $pdo->query("SELECT COUNT(*) as cuenta FROM pedidos_mayoristas")->fetch()['cuenta'];

// Productos terminados en stock
$productos_stock = $pdo->query("SELECT * FROM productos WHERE stock_actual > 0")->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>El Horno Rojo - Sistema de Gestión</h1>
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
        <div class="dashboard-grid">

            <section class="card">
                <h2>Alertas de Insumos</h2>
                <?php if (empty($insumos_bajos)): ?>
                    <p class="badge-ok">Todos los insumos están en niveles óptimos.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($insumos_bajos as $i): ?>
                            <li style="margin-bottom: 10px; padding: 10px; border-left: 5px solid #ff9800; background: #fff3e0;">
                                <strong><?php echo htmlspecialchars($i['nombre']); ?></strong>:
                                <?php echo $i['cantidad']; ?> <?php echo $i['unidad']; ?>
                                (Mínimo: <?php echo $i['stock_minimo']; ?>)
                                <br>
                                <span class="badge-warning">BAJO STOCK</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Resumen Financiero</h2>
                <div style="font-size: 1.2em; margin-bottom: 10px;">
                    Minoristas: <strong>Gs. <?php echo number_format($total_ventas, 0, ',', '.'); ?></strong> (<?php echo $cantidad_ventas; ?> op.)
                </div>
                <div style="font-size: 1.2em; margin-bottom: 10px;">
                    Mayoristas: <strong>Gs. <?php echo number_format($total_mayoristas, 0, ',', '.'); ?></strong> (<?php echo $cantidad_mayoristas; ?> op.)
                </div>
                <hr>
                <div style="font-size: 1.5em; color: #b71c1c;">
                    Total: <strong>Gs. <?php echo number_format($total_ventas + $total_mayoristas, 0, ',', '.'); ?></strong>
                </div>
                <br>
                <a href="ventas.php" class="btn">Nueva Venta</a>
                <a href="mayoristas.php" class="btn">Nuevo Pedido Mayorista</a>
            </section>

            <section class="card">
                <h2>Stock de Productos Terminados</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos_stock as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                                <td><?php echo $p['stock_actual']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($productos_stock)): ?>
                            <tr><td colspan="2">No hay productos terminados en stock.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <br>
                <a href="produccion.php" class="btn">Registrar Producción</a>
            </section>

            <section class="card">
                <h2>Acceso Rápido</h2>
                <ul>
                    <li><a href="calculadora_masa.php">Calculadora de Masa (bollos)</a></li>
                    <li><a href="insumos.php">Gestionar Materias Primas</a></li>
                    <li><a href="productos.php">Definir Recetas y Precios</a></li>
                    <li><a href="promociones.php">Gestionar Combos y Promos</a></li>
                </ul>
            </section>

        </div>
    </main>
</body>
</html>
