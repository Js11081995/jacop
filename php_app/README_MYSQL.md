# Migración a MySQL - El Horno Rojo

El sistema está configurado por defecto para usar **SQLite** (archivo `database.sqlite`), lo cual es ideal para empezar sin configurar servidores.

Si prefieres usar **MySQL**, sigue estos pasos:

### 1. Crear la Base de Datos
Ejecuta este SQL en tu servidor MySQL:

```sql
CREATE TABLE insumos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    cantidad DECIMAL(10,2) DEFAULT 0,
    unidad VARCHAR(50) NOT NULL,
    stock_minimo DECIMAL(10,2) DEFAULT 0,
    precio_unitario DECIMAL(10,2) DEFAULT 0
);

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    precio_venta DECIMAL(10,2) DEFAULT 0,
    stock_actual INT DEFAULT 0,
    tipo VARCHAR(50) DEFAULT 'normal'
);

CREATE TABLE recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    insumo_id INT,
    cantidad_requerida DECIMAL(10,3),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (insumo_id) REFERENCES insumos(id),
    UNIQUE(producto_id, insumo_id)
);

CREATE TABLE produccion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    cantidad INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente VARCHAR(255),
    producto_id INT,
    cantidad INT,
    precio_unitario DECIMAL(10,2),
    total DECIMAL(10,2),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE pedidos_mayoristas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente VARCHAR(255) NOT NULL,
    producto_id INT,
    cantidad INT,
    precio_unitario DECIMAL(10,2),
    total DECIMAL(10,2),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(50) DEFAULT 'pendiente',
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE promociones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    precio_promo DECIMAL(10,2),
    descripcion TEXT,
    activo TINYINT DEFAULT 1
);

-- Cargar datos iniciales
INSERT INTO insumos (id, nombre, unidad, stock_minimo, precio_unitario) VALUES
(4, 'Harina', 'Kg', 10, 5000), (5, 'Agua', 'L', 5, 1000), (6, 'Aceite', 'L', 1, 15000), (7, 'Sal', 'g', 500, 3),
(8, 'Levadura Seca', 'g', 100, 80), (9, 'Muzzarella', 'g', 1000, 45), (10, 'Salsa', 'g', 500, 15), (11, 'Pesto', 'g', 100, 60),
(12, 'Pepperoni', 'g', 200, 90), (13, 'Cherrys', 'g', 200, 25), (14, 'Catupiry', 'g', 200, 55), (15, 'Queso Azul', 'g', 100, 120),
(16, 'Parmesano', 'g', 100, 100), (17, 'Bolsa Gofrada', 'unidades', 10, 4850), (18, 'Oregano', 'g', 100, 15), (19, 'Aceitunas', 'g', 200, 30),
(20, 'Tomates y Ajos Confitados', 'g', 100, 50), (21, 'Cebolla Morada', 'g', 200, 10), (22, 'Pomodoro', 'g', 500, 15);

INSERT INTO productos (id, nombre, precio_venta) VALUES
(3, 'Muzzarella Clasica', 45000), (4, 'Muzza y Pesto', 50000), (5, 'Pepperoni', 55000), (6, 'Cherrys', 50000), (7, '4 quesos', 60000);

INSERT INTO recetas (producto_id, insumo_id, cantidad_requerida) VALUES
(3, 4, 0.145), (3, 5, 0.097), (3, 6, 0.004), (3, 7, 2.9), (3, 8, 0.15), (3, 22, 45), (3, 9, 120), (3, 19, 5), (3, 18, 3), (3, 17, 1),
(4, 4, 0.145), (4, 5, 0.097), (4, 6, 0.004), (4, 7, 2.9), (4, 8, 0.15), (4, 22, 45), (4, 9, 120), (4, 19, 5), (4, 11, 45), (4, 18, 3), (4, 17, 1),
(5, 4, 0.145), (5, 5, 0.097), (5, 6, 0.004), (5, 7, 2.9), (5, 8, 0.15), (5, 22, 45), (5, 9, 120), (5, 19, 5), (5, 12, 40), (5, 18, 3), (5, 17, 1),
(6, 4, 0.145), (6, 5, 0.097), (6, 6, 0.004), (6, 7, 2.9), (6, 8, 0.15), (6, 22, 45), (6, 20, 30), (6, 19, 5), (6, 21, 30), (6, 14, 10), (6, 18, 3), (6, 17, 1),
(7, 4, 0.145), (7, 5, 0.097), (7, 6, 0.004), (7, 7, 2.9), (7, 8, 0.15), (7, 22, 45), (7, 9, 120), (7, 15, 30), (7, 16, 30), (7, 14, 30), (7, 18, 3), (7, 17, 1);
```

### 2. Actualizar `db.php`
Cambia el contenido de `php_app/db.php` por el siguiente:

```php
<?php
$host = 'localhost';
$db   = 'nombre_de_tu_base';
$user = 'usuario';
$pass = 'contraseña';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
```
