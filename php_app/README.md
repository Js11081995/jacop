# El Horno Rojo - Sistema de Gestión

Este sistema permite gestionar inventario de insumos, recetas, producción y ventas para un almacén de pizzas envasadas al vacío.

## Requisitos
- PHP 5.4 o superior.
- Extensión PDO activa.
- SQLite3 (por defecto) o MySQL.

## Instalación (SQLite - Por defecto)
1. Sube la carpeta `php_app` a tu servidor.
2. Asegúrate de que el servidor tenga permisos de escritura en la carpeta para poder manejar el archivo `database.sqlite`.
3. Accede a `index.php` desde tu navegador.

## Migración a MySQL
Si prefieres usar MySQL en lugar de SQLite, sigue estos pasos:

### 1. Crear la base de datos en MySQL
Ejecuta el siguiente script en tu servidor MySQL (vía phpMyAdmin o terminal):

```sql
CREATE DATABASE el_horno_rojo;
USE el_horno_rojo;

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
    stock_actual INT DEFAULT 0
);

CREATE TABLE recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    insumo_id INT,
    cantidad_requerida DECIMAL(10,3),
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (insumo_id) REFERENCES insumos(id) ON DELETE CASCADE
);

CREATE TABLE produccion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    cantidad INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
);

CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT,
    cantidad INT,
    precio_unitario DECIMAL(10,2),
    total DECIMAL(10,2),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
);
```

### 2. Modificar `db.php`
Cambia el contenido de `db.php` por el siguiente:

```php
<?php
$host = 'localhost';
$db   = 'el_horno_rojo';
$user = 'tu_usuario';
$pass = 'tu_contraseña';
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

## Estructura de Archivos
- `index.php`: Panel principal con alertas y resumen.
- `insumos.php`: Gestión de materias primas.
- `productos.php`: Definición de pizzas y sus recetas.
- `produccion.php`: Registro de producción (descuenta insumos).
- `ventas.php`: Registro de ventas (descuenta productos terminados).
- `style.css`: Estilos visuales del sistema.
