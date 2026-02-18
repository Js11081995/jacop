<?php
require_once 'db.php';

$mensaje = '';
$harina = isset($_REQUEST['harina']) ? floatval($_REQUEST['harina']) : 0;

$agua = $harina * 0.67;
$aceite = $harina * 0.03;
$sal = $harina * 20;
$levadura = $harina * 1;

$peso_total = ($harina * 1000) + ($agua * 1000) + ($aceite * 1000) + $sal + $levadura;
$bollos = $peso_total > 0 ? floor($peso_total / 250) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_produccion' && $bollos > 0) {
    try {
        $pdo->beginTransaction();

        // Obtener precios actuales para calcular costo
        $insumos_query = $pdo->query("SELECT nombre, precio_unitario, unidad FROM insumos WHERE nombre IN ('Harina', 'Agua', 'Aceite', 'Sal', 'Levadura Seca')")->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

        $costo_batch = 0;
        $costo_batch += $harina * $insumos_query['Harina']['precio_unitario'];
        $costo_batch += $agua * $insumos_query['Agua']['precio_unitario'];
        $costo_batch += $aceite * $insumos_query['Aceite']['precio_unitario'];
        $costo_batch += ($sal / 1000) * $insumos_query['Sal']['precio_unitario'];
        $costo_batch += ($levadura / 1000) * $insumos_query['Levadura Seca']['precio_unitario'];

        $costo_por_bollo = $costo_batch / $bollos;

        // 1. Descontar Harina
        $pdo->prepare("UPDATE insumos SET cantidad = cantidad - ? WHERE nombre = 'Harina'")->execute([$harina]);
        // 2. Descontar Agua
        $pdo->prepare("UPDATE insumos SET cantidad = cantidad - ? WHERE nombre = 'Agua'")->execute([$agua]);
        // 3. Descontar Aceite
        $pdo->prepare("UPDATE insumos SET cantidad = cantidad - ? WHERE nombre = 'Aceite'")->execute([$aceite]);
        // 4. Descontar Sal
        $pdo->prepare("UPDATE insumos SET cantidad = cantidad - ? WHERE nombre = 'Sal'")->execute([$sal]);
        // 5. Descontar Levadura
        $pdo->prepare("UPDATE insumos SET cantidad = cantidad - ? WHERE nombre = 'Levadura Seca'")->execute([$levadura]);

        // 6. Sumar Bollos y Actualizar su costo
        $pdo->prepare("UPDATE insumos SET cantidad = cantidad + ?, precio_unitario = ?, tipo = 'elaborado' WHERE nombre LIKE 'Prepizza%' OR nombre LIKE 'Bollo%'")->execute([$bollos, $costo_por_bollo]);

        $pdo->commit();
        $mensaje = "Producción de $bollos bollos registrada. Costo calculado por unidad: Gs. " . number_format($costo_por_bollo, 0, ',', '.');
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al registrar producción: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calculadora de Masa - El Horno Rojo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <h1>Producción de Prepizzas (Masa)</h1>

        <?php if ($mensaje): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 5px solid #2e7d32;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2>Cálculo de Proporciones (Fórmula 70% Hidratación)</h2>
            <form method="GET">
                <label for="harina">Kilogramos de Harina a preparar:</label>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <input type="number" step="0.1" name="harina" id="harina" value="<?php echo $harina; ?>" required style="flex: 1;">
                    <button type="submit" class="btn">Calcular Ingredientes</button>
                </div>
            </form>

            <?php if ($harina > 0): ?>
                <div style="margin-top: 20px; padding: 15px; background: #fffde7; border-left: 5px solid #fbc02d;">
                    <h3>Para <?php echo $harina; ?> Kg de Harina necesitas:</h3>
                    <ul>
                        <li><strong>Agua:</strong> <?php echo number_format($agua, 3); ?> Litros (<?php echo $agua * 1000; ?> ml)</li>
                        <li><strong>Aceite:</strong> <?php echo number_format($aceite, 3); ?> Litros (<?php echo $aceite * 1000; ?> ml)</li>
                        <li><strong>Sal:</strong> <?php echo number_format($sal, 1); ?> Gramos</li>
                        <li><strong>Levadura Seca:</strong> <?php echo number_format($levadura, 1); ?> Gramos</li>
                    </ul>
                    <hr>
                    <p style="font-size: 1.2em;">
                        Peso total estimado: <strong><?php echo number_format($peso_total / 1000, 3); ?> Kg</strong>
                    </p>
                    <p style="font-size: 1.5em; color: #b71c1c;">
                        Bollos resultantes (250g): <strong><?php echo $bollos; ?> unidades</strong>
                    </p>

                    <form method="POST" onsubmit="return confirm('¿Confirmas que has preparado esta masa? Se descontarán las materias primas del inventario y se sumarán los bollos al stock de Prepizzas.')">
                        <input type="hidden" name="harina" value="<?php echo $harina; ?>">
                        <input type="hidden" name="accion" value="registrar_produccion">
                        <button type="submit" class="btn" style="width: 100%; font-size: 1.2em; padding: 15px; background: #2e7d32;">
                            REGISTRAR PRODUCCIÓN Y ACTUALIZAR STOCK
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top: 20px;">
            <h3>Proporciones Utilizadas (por cada 1 Kg de harina):</h3>
            <table style="width: 100%;">
                <tr><td>Harina</td><td>1000 g</td></tr>
                <tr><td>Agua (67%)</td><td>670 ml</td></tr>
                <tr><td>Aceite (3%)</td><td>30 ml</td></tr>
                <tr><td>Sal</td><td>20 g</td></tr>
                <tr><td>Levadura Seca</td><td>1 g</td></tr>
                <tr style="border-top: 2px solid #ddd; font-weight: bold;"><td>Peso Final Prepizza</td><td>250 g</td></tr>
            </table>
        </section>
    </main>
</body>
</html>
