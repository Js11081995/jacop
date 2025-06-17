from app import db
from datetime import datetime

class Lote(db.Model):
    __tablename__ = 'lote'
    id = db.Column(db.Integer, primary_key=True)
    codigo_lote = db.Column(db.String(100), unique=True, nullable=False, index=True)
    tipo_algodon = db.Column(db.String(50), nullable=False)
    maquina = db.Column(db.String(50), nullable=False)
    mic_profile = db.Column(db.String(50), nullable=False) # REVISED
    color_profile = db.Column(db.String(50), nullable=False, default="normal") # REVISED
    fecha_creacion = db.Column(db.DateTime, default=datetime.utcnow)
    completo = db.Column(db.Boolean, default=False)
    entregado = db.Column(db.Boolean, default=False)

    fardos = db.relationship('Fardo', back_populates='lote', lazy='dynamic')

    def __repr__(self):
        return f'<Lote {self.codigo_lote}>'
