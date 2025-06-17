from app import db
# from datetime import date # datetime.date is part of Python's datetime module, no separate import needed if datetime is imported

class Fardo(db.Model):
    __tablename__ = 'fardo'
    id = db.Column(db.Integer, primary_key=True)
    numero_fardo = db.Column(db.String(100), unique=True, nullable=False, index=True)
    tipo = db.Column(db.String(50), nullable=False)
    peso_neto = db.Column(db.Float, nullable=False)
    peso_tara = db.Column(db.Float, nullable=False)
    fermentado = db.Column(db.Boolean, default=False, nullable=False)
    mic = db.Column(db.String(50), nullable=False) # "bajo", "normal", "alto"
    color_predominante = db.Column(db.String(50), default="normal", nullable=False) # REVISED
    observaciones = db.Column(db.String(500))
    fecha = db.Column(db.Date, nullable=False)
    cliente = db.Column(db.String(200))
    maquina = db.Column(db.String(50), nullable=False)
    dado_de_baja = db.Column(db.Boolean, default=False, nullable=False)

    lote_id = db.Column(db.Integer, db.ForeignKey('lote.id'))
    lote = db.relationship('Lote', back_populates='fardos')

    def __repr__(self):
        return f'<Fardo {self.numero_fardo}>'
