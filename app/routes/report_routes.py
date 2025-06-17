from flask import Blueprint, render_template, request, flash, make_response, redirect, url_for
from sqlalchemy import func # Added in subtask 8
from app import db
from app.models import Lote, TakeOutReport
from app.services.report_generator import generate_take_out_report_pdf_bytes,                                            generate_take_out_report_excel_bytes,                                            save_take_out_report_history,                                            get_reporte_por_maquina_data # Added in subtask 7 (via prompt for subtask 8)
import json # Added in subtask 8
from datetime import datetime # Added in subtask 6 for filenames

report_bp = Blueprint('report_bp', __name__, template_folder='../templates/reports')

@report_bp.route('/reports/take_out', methods=['GET', 'POST'])
def take_out_report_form():
    if request.method == 'POST':
        try:
            lote_ids_str = request.form.getlist('lote_ids')
            if not lote_ids_str:
                flash('Debe seleccionar al menos un lote.', 'warning')
                return redirect(url_for('report_bp.take_out_report_form'))

            lote_ids = [int(id_str) for id_str in lote_ids_str]

            client_name = request.form.get('client_name', 'Consumidor Final')
            entrega_firma = request.form.get('entrega_firma', 'Responsable Entrega')
            recibe_firma = request.form.get('recibe_firma', 'Responsable Recepción')
            include_individual_weights = 'include_individual_weights' in request.form
            report_format = request.form.get('report_format', 'pdf')

            lote_ids_comma_separated = ",".join(lote_ids_str)

            if report_format == 'pdf':
                pdf_bytes = generate_take_out_report_pdf_bytes(lote_ids, client_name, include_individual_weights, entrega_firma, recibe_firma)
                response = make_response(pdf_bytes)
                response.headers['Content-Type'] = 'application/pdf'
                response.headers['Content-Disposition'] = f'attachment; filename="take_out_report_{client_name.replace(" ","_")}_{datetime.utcnow().strftime("%Y%m%d%H%M%S")}.pdf"'
            elif report_format == 'excel':
                excel_bytes = generate_take_out_report_excel_bytes(lote_ids, client_name, include_individual_weights, entrega_firma, recibe_firma)
                response = make_response(excel_bytes)
                response.headers['Content-Type'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                response.headers['Content-Disposition'] = f'attachment; filename="take_out_report_{client_name.replace(" ","_")}_{datetime.utcnow().strftime("%Y%m%d%H%M%S")}.xlsx"'
            else:
                flash('Formato de reporte no válido.', 'danger')
                return redirect(url_for('report_bp.take_out_report_form'))

            save_take_out_report_history(client_name, lote_ids_comma_separated)
            flash('Reporte TAKE OUT generado y marcado como entregado.', 'success')
            return response

        except ValueError as ve:
            flash(f'Error al generar reporte: {ve}', 'danger')
        except Exception as e:
            flash(f'Error inesperado al generar reporte: {e}', 'danger')

        return redirect(url_for('report_bp.take_out_report_form'))

    available_lotes = Lote.query.filter_by(entregado=False).order_by(Lote.codigo_lote.desc()).all()
    return render_template('take_out_report_form.html', lotes=available_lotes)

@report_bp.route('/reports/por_maquina', methods=['GET']) # From subtask 7 (added to prompt of subtask 8)
def reporte_por_maquina_view():
    try:
        data = get_reporte_por_maquina_data()
        return render_template('reporte_por_maquina.html', data=data)
    except Exception as e:
        flash(f'Error generando reporte por máquina: {e}', 'danger')
        return redirect(url_for('fardo_bp.list_fardos'))

@report_bp.route('/reports/historial_take_out', methods=['GET']) # From subtask 8
def historial_take_out_view():
    page = request.args.get('page', 1, type=int)
    search_cliente = request.args.get('search_cliente', '').strip()
    search_fecha = request.args.get('search_fecha', '').strip()

    query = TakeOutReport.query

    if search_cliente:
        query = query.filter(TakeOutReport.cliente_nombre.ilike(f'%{search_cliente}%'))

    if search_fecha:
        try:
            from datetime import date # Ensure date is imported if not already from datetime module
            parsed_date = datetime.strptime(search_fecha, '%Y-%m-%d').date()
            query = query.filter(func.date(TakeOutReport.fecha_generacion) == parsed_date)
        except ValueError:
            flash('Formato de fecha inválido. Use YYYY-MM-DD.', 'warning')

    historial_pagination = query.order_by(TakeOutReport.fecha_generacion.desc()).paginate(page=page, per_page=15)

    return render_template(
        'historial_entregas.html',
        historial_pagination=historial_pagination,
        search_cliente=search_cliente,
        search_fecha=search_fecha
    )

@report_bp.route('/reports/historial_take_out/<int:report_id>/regenerate', methods=['POST']) # From subtask 8
def regenerate_take_out_report(report_id):
    report_record = TakeOutReport.query.get_or_404(report_id)

    try:
        lote_ids_str_list = []
        if report_record.lotes_incluidos_info and report_record.lotes_incluidos_info.strip().startswith('['):
            try:
                lotes_info_list = json.loads(report_record.lotes_incluidos_info)
                lote_ids_str_list = [str(item['id']) for item in lotes_info_list if isinstance(item, dict) and 'id' in item]
            except json.JSONDecodeError:
                flash('Error al decodificar información de lotes del historial (JSON).', 'danger')
                return redirect(url_for('report_bp.historial_take_out_view'))
        elif report_record.lotes_incluidos_info:
            lote_ids_str_list = [id_str.strip() for id_str in report_record.lotes_incluidos_info.split(',') if id_str.strip().isdigit()]

        if not lote_ids_str_list:
            flash('No se encontraron IDs de lote válidos en el registro del historial.', 'danger')
            return redirect(url_for('report_bp.historial_take_out_view'))

        lote_ids = [int(id_str) for id_str in lote_ids_str_list]

        client_name = report_record.cliente_nombre
        entrega_firma_default = "Encargado (Archivo)"
        recibe_firma_default = client_name
        include_individual_weights_default = True
        report_format = request.form.get('report_format', 'pdf')

        if report_format == 'pdf':
            pdf_bytes = generate_take_out_report_pdf_bytes(
                lote_ids, client_name, include_individual_weights_default,
                entrega_firma_default, recibe_firma_default
            )
            response = make_response(pdf_bytes)
            response.headers['Content-Type'] = 'application/pdf'
            response.headers['Content-Disposition'] = f'attachment; filename="take_out_report_REPRINT_{report_id}_{client_name.replace(" ","_")}.pdf"'
        elif report_format == 'excel':
            excel_bytes = generate_take_out_report_excel_bytes(
                lote_ids, client_name, include_individual_weights_default,
                entrega_firma_default, recibe_firma_default
            )
            response = make_response(excel_bytes)
            response.headers['Content-Type'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            response.headers['Content-Disposition'] = f'attachment; filename="take_out_report_REPRINT_{report_id}_{client_name.replace(" ","_")}.xlsx"'
        else:
            flash('Formato de reporte no válido para regeneración.', 'danger')
            return redirect(url_for('report_bp.historial_take_out_view'))

        flash(f'Reporte ID {report_id} regenerado.', 'success')
        return response

    except ValueError as ve:
        flash(f'Error al regenerar reporte ID {report_id}: {ve}', 'danger')
    except Exception as e:
        flash(f'Error inesperado al regenerar reporte ID {report_id}: {e}', 'danger')

    return redirect(url_for('report_bp.historial_take_out_view'))
