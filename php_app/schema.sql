CREATE TABLE IF NOT EXISTS insumos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    cantidad REAL DEFAULT 0,
    unidad TEXT NOT NULL,
    stock_minimo REAL DEFAULT 0,
    precio_unitario REAL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS productos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    precio_venta REAL DEFAULT 0,
    stock_actual INTEGER DEFAULT 0
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
