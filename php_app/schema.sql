CREATE TABLE IF NOT EXISTS insumos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE,
    cantidad REAL DEFAULT 0,
    unidad TEXT NOT NULL,
    stock_minimo REAL DEFAULT 0,
    precio_unitario REAL DEFAULT 0,
    tipo TEXT DEFAULT 'materia_prima'
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
    venta_id TEXT, -- Identificador único para ventas con múltiples items
    cliente TEXT,
    producto_id INTEGER,
    cantidad INTEGER,
    precio_unitario REAL,
    costo_unitario REAL DEFAULT 0, -- Costo de producción en el momento de la venta
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
INSERT OR IGNORE INTO insumos (nombre, unidad, stock_minimo, precio_unitario, tipo) VALUES
('Harina', 'Kg', 10, 5000, 'materia_prima'),
('Agua', 'L', 5, 1000, 'materia_prima'),
('Aceite', 'L', 1, 15000, 'materia_prima'),
('Sal', 'g', 500, 3000, 'materia_prima'), -- Precio por Kg ahora (aunque unidad sea g)
('Levadura Seca', 'g', 100, 80000, 'materia_prima'), -- Precio por Kg
('Muzzarella', 'g', 1000, 45000, 'materia_prima'), -- Precio por Kg
('Pomodoro', 'g', 500, 15000, 'materia_prima'),
('Pesto', 'g', 100, 60000, 'materia_prima'),
('Pepperoni', 'g', 200, 90000, 'materia_prima'),
('Cherrys', 'g', 200, 25000, 'materia_prima'),
('Catupiry', 'g', 200, 55000, 'materia_prima'),
('Queso Azul', 'g', 100, 120000, 'materia_prima'),
('Parmesano', 'g', 100, 100000, 'materia_prima'),
('Bolsa Gofrada', 'unidades', 10, 4850, 'materia_prima'),
('Oregano', 'g', 100, 15000, 'materia_prima'),
('Aceitunas', 'g', 200, 30000, 'materia_prima'),
('Tomates y Ajos Confitados', 'g', 100, 50000, 'materia_prima'),
('Cebolla Morada', 'g', 200, 10000, 'materia_prima'),
('Prepizza (Bollo 250g)', 'unidades', 20, 0, 'elaborado');
