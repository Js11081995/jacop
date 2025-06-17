from app import db
from datetime import datetime

class TakeOutReport(db.Model):
    __tablename__ = 'take_out_report'
    id = db.Column(db.Integer, primary_key=True)
    fecha_generacion = db.Column(db.DateTime, default=datetime.utcnow, nullable=False)
    cliente_nombre = db.Column(db.String(200), nullable=False)
    # Storing as JSON string. For more complex queries, a separate table might be better.
    lotes_incluidos_info = db.Column(db.Text, nullable=False) # JSON string: [{"lote_codigo": "XYZ", "tipo": "2.5", ...}]
    # filename_pdf = db.Column(db.String(255)) # Example if storing filenames
    # filename_excel = db.Column(db.String(255)) # Example if storing filenames

    def __repr__(self):
        return f'<TakeOutReport {self.id} for {self.cliente_nombre} on {self.fecha_generacion.strftime("%Y-%m-%d")}>'
