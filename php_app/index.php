<?php
require_once 'db.php';

// Alertas de insumos bajos
$insumos_bajos = $pdo->query("SELECT * FROM insumos WHERE cantidad <= stock_minimo AND tipo = 'materia_prima'")->fetchAll();

// Estadísticas de ventas minoristas
$stats_ventas = $pdo->query("SELECT
    SUM(total) as ingresos,
    SUM(costo_unitario * cantidad) as costos,
    SUM((precio_unitario - costo_unitario) * cantidad) as ganancia
    FROM ventas")->fetch();

$total_ventas = $stats_ventas['ingresos'] ?: 0;
$total_costos = $stats_ventas['costos'] ?: 0;
$total_ganancia = $stats_ventas['ganancia'] ?: 0;

// Mayoristas (solo ingresos por ahora)
$total_mayoristas = $pdo->query("SELECT SUM(total) as total FROM pedidos_mayoristas WHERE estado = 'pagado' OR estado = 'entregado'")->fetch()['total'] ?: 0;

// Stock de Productos Terminados
$productos_stock = $pdo->query("SELECT * FROM productos WHERE stock_actual > 0")->fetchAll();

// Stock de Prepizzas
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
    <style>
        .metric-card { text-align: center; padding: 10px; border-radius: 4px; margin-bottom: 10px; }
        .metric-value { font-size: 1.4em; font-weight: bold; display: block; }
        .metric-label { font-size: 0.9em; color: #666; }
    </style>
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
                    Inversión Total en Stock (Valorización): <br>
                    <strong style="font-size: 1.4em; color: #2e7d32;">Gs. <?php echo number_format($valor_inventario, 0, ',', '.'); ?></strong>
                </div>
            </section>

            <section class="card">
                <h2>Análisis de Ganancias (Minorista)</h2>
                <div class="metric-card" style="background: #e8f5e9; border: 1px solid #c8e6c9;">
                    <span class="metric-label">Ingresos por Ventas</span>
                    <span class="metric-value" style="color: #2e7d32;">Gs. <?php echo number_format($total_ventas, 0, ',', '.'); ?></span>
                </div>
                <div class="metric-card" style="background: #ffebee; border: 1px solid #ffcdd2;">
                    <span class="metric-label">Costo de Producción (Vendido)</span>
                    <span class="metric-value" style="color: #c62828;">Gs. <?php echo number_format($total_costos, 0, ',', '.'); ?></span>
                </div>
                <div class="metric-card" style="background: #fff9c4; border: 1px solid #fff176;">
                    <span class="metric-label">GANANCIA REAL</span>
                    <span class="metric-value" style="color: #fbc02d; font-size: 1.8em;">Gs. <?php echo number_format($total_ganancia, 0, ',', '.'); ?></span>
                </div>
                <hr>
                <div style="font-size: 1em; color: #666;">
                    Margen de Ganancia Promedio: <strong><?php echo $total_ventas > 0 ? round(($total_ganancia / $total_ventas) * 100, 1) : 0; ?>%</strong>
                </div>
                <br>
                <a href="ventas.php" class="btn" style="width: 100%; text-align: center;">Registrar Nueva Venta</a>
            </section>

            <section class="card">
                <h2>Resumen General</h2>
                <div style="font-size: 1.1em; margin-bottom: 15px;">
                    Ventas Mayoristas: <strong>Gs. <?php echo number_format($total_mayoristas, 0, ',', '.'); ?></strong>
                </div>
                <hr>
                <div style="font-size: 1.4em; color: #b71c1c;">
                    Ingresos Totales (M+M): <br>
                    <strong>Gs. <?php echo number_format($total_ventas + $total_mayoristas, 0, ',', '.'); ?></strong>
                </div>
                <br>
                <a href="mayoristas.php" class="btn" style="background: #555; width: 100%; text-align: center;">Gestionar Mayoristas</a>
            </section>

            <section class="card">
                <h2>Stock Prepizzas</h2>
                <?php foreach ($prepizzas as $pre): ?>
                    <div style="text-align: center; padding: 10px; background: #e3f2fd; border-radius: 8px; border: 1px solid #2196f3; margin-bottom: 10px;">
                        <span style="font-size: 1.8em; font-weight: bold; color: #1565c0;"><?php echo $pre['cantidad']; ?></span>
                        <br>Bollos Disponibles
                    </div>
                <?php endforeach; ?>
                <a href="calculadora_masa.php" class="btn" style="width: 100%; text-align: center; padding: 5px;">Producción Masa</a>
                <hr>
                <h3>Stock de Pizzas Listas</h3>
                <table style="font-size: 0.9em;">
                    <?php foreach ($productos_stock as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                            <td style="text-align: right;"><strong><?php echo $p['stock_actual']; ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </section>

        </div>
    </main>
</body>
</html>
