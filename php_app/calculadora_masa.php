<?php
require_once 'db.php';

$harina = isset($_GET['harina']) ? floatval($_GET['harina']) : 0;

$agua = $harina * 0.67;
$aceite = $harina * 0.03;
$sal = $harina * 20;
$levadura = $harina * 1;

$peso_total = ($harina * 1000) + ($agua * 1000) + ($aceite * 1000) + $sal + $levadura;
$bollos = $peso_total > 0 ? floor($peso_total / 250) : 0;
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
        <section class="card">
            <h2>Cálculo de Proporciones</h2>
            <form method="GET">
                <label for="harina">Kilogramos de Harina:</label>
                <input type="number" step="0.1" name="harina" id="harina" value="<?php echo $harina; ?>" required>
                <button type="submit">Calcular</button>
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
                        Peso total de la masa: <strong><?php echo number_format($peso_total / 1000, 3); ?> Kg</strong>
                    </p>
                    <p style="font-size: 1.5em; color: #b71c1c;">
                        Bollos generados (250g c/u): <strong><?php echo $bollos; ?> unidades</strong>
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <section>
            <h3>Fórmulas fijas utilizadas (por Kg de harina):</h3>
            <ul>
                <li>Hidratación: 70% (670ml Agua / 30ml Aceite)</li>
                <li>Sal: 20g</li>
                <li>Levadura Seca: 1g</li>
            </ul>
        </section>
    </main>
</body>
</html>
