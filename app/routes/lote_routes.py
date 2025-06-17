from flask import Blueprint, render_template, request, redirect, url_for, flash
from app import db # Corrected path
from app.models import Lote, Fardo # Corrected path
from app.services.lote_generator import generate_lotes_automaticos # Corrected path
import logging

lote_bp = Blueprint('lote_bp', __name__, template_folder='../templates/lotes')
logger = logging.getLogger(__name__) # It's fine to use __name__ or a specific one

@lote_bp.route('/lotes')
def list_lotes():
    page = request.args.get('page', 1, type=int)
    lotes_pagination = Lote.query.order_by(Lote.fecha_creacion.desc(), Lote.codigo_lote).paginate(page=page, per_page=15)
    return render_template('list_lotes.html', lotes_pagination=lotes_pagination)

@lote_bp.route('/lotes/generate', methods=['POST'])
def trigger_generate_lotes():
    try:
        # Retrieve the value for autorizacion_especial from form data
        autorizacion_especial_value = 'autorizacion_checkbox' in request.form

        result = generate_lotes_automaticos(autorizacion_especial=autorizacion_especial_value)

        if result.get("log"):
            for log_msg in result["log"]:
                flash(log_msg, 'info')

        if result["lotes_creados"] > 0:
            flash(result["message"], 'success')
        else:
            flash(result["message"], 'info')

    except Exception as e:
        db.session.rollback() # Rollback in case of error during generation
        logger.error(f"Error crítico en trigger_generate_lotes: {e}", exc_info=True)
        flash(f"Error crítico durante la generación de lotes: {str(e)}", 'danger')

    return redirect(url_for('lote_bp.list_lotes'))

@lote_bp.route('/lotes/<int:lote_id>')
def view_lote(lote_id):
    lote = Lote.query.get_or_404(lote_id)
    # Order fardos if desired, e.g., by numero_fardo
    fardos_en_lote = Fardo.query.filter_by(lote_id=lote.id).order_by(Fardo.numero_fardo).all()
    return render_template('view_lote.html', lote=lote, fardos_en_lote=fardos_en_lote)
