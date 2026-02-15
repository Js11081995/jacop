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
    FOREIGN KEY (insumo_id) REFERENCES insumos(id),
    UNIQUE(producto_id, insumo_id)
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
    cliente TEXT,
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

-- Insumos Básicos
INSERT OR IGNORE INTO insumos (nombre, unidad, stock_minimo, precio_unitario) VALUES
('Harina', 'Kg', 10, 5000),
('Agua', 'L', 5, 1000),
('Aceite', 'L', 1, 15000),
('Sal', 'g', 500, 3),
('Levadura Seca', 'g', 100, 80),
('Muzzarella', 'g', 1000, 45),
('Pomodoro', 'g', 500, 15),
('Pesto', 'g', 100, 60),
('Pepperoni', 'g', 200, 90),
('Cherrys', 'g', 200, 25),
('Catupiry', 'g', 200, 55),
('Queso Azul', 'g', 100, 120),
('Parmesano', 'g', 100, 100),
('Bolsa Gofrada', 'unidades', 10, 4850),
('Oregano', 'g', 100, 15),
('Aceitunas', 'g', 200, 30),
('Tomates y Ajos Confitados', 'g', 100, 50),
('Cebolla Morada', 'g', 200, 10),
('Bollo de Masa (250g)', 'unidades', 20, 0); -- El costo se calculará o será el de los insumos
