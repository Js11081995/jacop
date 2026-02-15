CREATE TABLE IF NOT EXISTS insumos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE,
    cantidad REAL DEFAULT 0,
    unidad TEXT NOT NULL,
    stock_minimo REAL DEFAULT 0,
    precio_unitario REAL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS productos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE,
    precio_venta REAL DEFAULT 0,
    stock_actual INTEGER DEFAULT 0,
    tipo TEXT DEFAULT 'normal'
);

CREATE TABLE IF NOT EXISTS recetas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    producto_id INTEGER,
    insumo_id INTEGER,
    cantidad_requerida REAL,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (insumo_id) REFERENCES insumos(id)
);

CREATE TABLE IF NOT EXISTS produccion (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    producto_id INTEGER,
    cantidad INTEGER,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE IF NOT EXISTS ventas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    producto_id INTEGER,
    cantidad INTEGER,
    precio_unitario REAL,
    total REAL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE IF NOT EXISTS pedidos_mayoristas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente TEXT NOT NULL,
    producto_id INTEGER,
    cantidad INTEGER,
    precio_unitario REAL,
    total REAL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado TEXT DEFAULT 'pendiente',
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE IF NOT EXISTS promociones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    precio_promo REAL,
    descripcion TEXT,
    activo INTEGER DEFAULT 1
);

-- Semillas iniciales (Ingredientes)
INSERT OR IGNORE INTO insumos (id, nombre, unidad, stock_minimo, precio_unitario) VALUES
(4, 'Harina', 'Kg', 10, 5000),
(5, 'Agua', 'L', 5, 1000),
(6, 'Aceite', 'L', 1, 15000),
(7, 'Sal', 'g', 500, 3),
(8, 'Levadura Seca', 'g', 100, 80),
(9, 'Muzzarella', 'g', 1000, 45),
(10, 'Salsa', 'g', 500, 15),
(11, 'Pesto', 'g', 100, 60),
(12, 'Pepperoni', 'g', 200, 90),
(13, 'Cherrys', 'g', 200, 25),
(14, 'Catupiry', 'g', 200, 55),
(15, 'Queso Azul', 'g', 100, 120),
(16, 'Parmesano', 'g', 100, 100),
(17, 'Bolsa Gofrada', 'unidades', 10, 4850);

-- Semillas iniciales (Productos)
INSERT OR IGNORE INTO productos (id, nombre, precio_venta) VALUES
(3, 'Muzzarella Clasica', 45000),
(4, 'Muzza y Pesto', 50000),
(5, 'Pepperoni', 55000),
(6, 'Cherrys', 50000),
(7, '4 quesos', 60000);

-- Recetas
-- Muzzarella Clasica
INSERT OR IGNORE INTO recetas (producto_id, insumo_id, cantidad_requerida) VALUES
(3, 4, 0.145), (3, 5, 0.097), (3, 6, 0.004), (3, 7, 2.9), (3, 8, 0.15), (3, 9, 150), (3, 10, 80), (3, 17, 1);
-- Muzza y Pesto
INSERT OR IGNORE INTO recetas (producto_id, insumo_id, cantidad_requerida) VALUES
(4, 4, 0.145), (4, 5, 0.097), (4, 6, 0.004), (4, 7, 2.9), (4, 8, 0.15), (4, 9, 120), (4, 10, 80), (4, 11, 30), (4, 17, 1);
-- Pepperoni
INSERT OR IGNORE INTO recetas (producto_id, insumo_id, cantidad_requerida) VALUES
(5, 4, 0.145), (5, 5, 0.097), (5, 6, 0.004), (5, 7, 2.9), (5, 8, 0.15), (5, 9, 120), (5, 10, 80), (5, 12, 40), (5, 17, 1);
-- Cherrys
INSERT OR IGNORE INTO recetas (producto_id, insumo_id, cantidad_requerida) VALUES
(6, 4, 0.145), (6, 5, 0.097), (6, 6, 0.004), (6, 7, 2.9), (6, 8, 0.15), (6, 9, 120), (6, 10, 80), (6, 13, 30), (6, 14, 40), (6, 17, 1);
-- 4 quesos
INSERT OR IGNORE INTO recetas (producto_id, insumo_id, cantidad_requerida) VALUES
(7, 4, 0.145), (7, 5, 0.097), (7, 6, 0.004), (7, 7, 2.9), (7, 8, 0.15), (7, 9, 80), (7, 10, 80), (7, 14, 40), (7, 15, 30), (7, 16, 30), (7, 17, 1);
