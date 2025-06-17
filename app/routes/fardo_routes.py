from flask import Blueprint, render_template, request, redirect, url_for, flash, jsonify, session
from app import db # Refers to /app/app/db
from app.models import Fardo # Refers to /app/app/models/Fardo
from app.services.excel_processor import process_excel, update_fardo_data # Refers to /app/app/services/excel_processor.py
import pandas as pd
from datetime import datetime # Added for edit_fardo date parsing
import io # Added for upload_excel BytesIO

fardo_bp = Blueprint('fardo_bp', __name__, template_folder='../templates/fardos')

@fardo_bp.route('/fardos')
def list_fardos():
    page = request.args.get('page', 1, type=int)
    fardos_pagination = Fardo.query.order_by(Fardo.fecha.desc(), Fardo.numero_fardo).paginate(page=page, per_page=20)
    return render_template('list_fardos.html', fardos_pagination=fardos_pagination)

@fardo_bp.route('/upload_excel', methods=['GET', 'POST'])
def upload_excel():
    if request.method == 'POST':
        if 'excel_file' not in request.files:
            flash('No file part', 'danger')
            return redirect(request.url)
        file = request.files['excel_file']
        if file.filename == '':
            flash('No selected file', 'danger')
            return redirect(request.url)
        if file and file.filename.endswith('.xlsx'):
            try:
                file_stream_content = file.stream.read()

                # For initial processing and storing in session if duplicates
                temp_df = pd.read_excel(io.BytesIO(file_stream_content), engine='openpyxl')
                temp_df.columns = [col.lower().replace(' ', '_') for col in temp_df.columns]

                # Pass a new BytesIO stream for actual processing by process_excel
                result = process_excel(io.BytesIO(file_stream_content))

                if result["duplicates"]:
                    flash(f'{len(result["duplicates"])} fardos duplicados encontrados. Por favor, revise y decida.', 'warning')
                    session['duplicate_fardos_info'] = result["duplicates"]
                    # Storing DataFrame as JSON in session
                    session['uploaded_excel_data'] = temp_df.to_json(orient='split', date_format='iso')
                    return redirect(url_for('fardo_bp.handle_duplicates_page'))

                if result["success"]:
                    flash(f'{result["new_fardos"]} fardos nuevos agregados. {result["updated_fardos"]} fardos actualizados.', 'success')
                else:
                    flash(f'Error: {result["message"]}', 'danger')

                return redirect(url_for('fardo_bp.list_fardos'))

            except Exception as e:
                flash(f'Error procesando el archivo: {e}', 'danger')
                return redirect(request.url)
        else:
            flash('Formato de archivo incorrecto. Por favor, suba un archivo .xlsx', 'warning')
            return redirect(request.url)

    return render_template('upload_excel.html')

@fardo_bp.route('/handle_duplicates', methods=['GET', 'POST'])
def handle_duplicates_page():
    if 'duplicate_fardos_info' not in session or 'uploaded_excel_data' not in session:
        flash('No hay información de duplicados para procesar.', 'info')
        return redirect(url_for('fardo_bp.upload_excel'))

    duplicates_info = session['duplicate_fardos_info']
    uploaded_df = pd.read_json(session['uploaded_excel_data'], orient='split')

    if request.method == 'POST':
        newly_added_count = 0
        updated_count = 0
        skipped_count = 0

        processed_indices = []

        for dup_info in duplicates_info:
            fardo_num = dup_info['numero_fardo']
            action = request.form.get(f'action_{fardo_num}', 'skip')

            # Find the row in uploaded_df. Column 'numero_de_fardo' is normalized.
            row_data_series_list = uploaded_df[uploaded_df['numero_de_fardo'].astype(str) == str(fardo_num)]
            if row_data_series_list.empty:
                flash(f"No se encontró data para fardo duplicado {fardo_num} en datos de sesión.", "warning")
                continue
            row_data_series = row_data_series_list.iloc[0]
            original_row_index = row_data_series.name # Get original index from DataFrame
            processed_indices.append(original_row_index)

            if action == 'replace':
                success, msg = update_fardo_data(fardo_num, row_data_series) # update_fardo_data expects normalized keys (already in row_data_series)
                if success:
                    updated_count +=1
                else:
                    flash(f"Error actualizando fardo {fardo_num}: {msg}", "danger")
            else:
                skipped_count +=1

        for index, row in uploaded_df.iterrows():
            if index in processed_indices:
                continue

            numero_fardo_str = str(row['numero_de_fardo']).strip()
            existing_fardo = Fardo.query.filter_by(numero_fardo=numero_fardo_str).first()
            if existing_fardo:
                skipped_count += 1
                continue

            try:
                fecha_obj = pd.to_datetime(row['fecha']).date() if pd.notnull(row['fecha']) else datetime.utcnow().date()
                tipo_val = str(row['tipo']).strip() if pd.notnull(row['tipo']) else "sin tipo"
                if tipo_val.lower() == "sin tipo":
                    tipo_str = "sin tipo"
                else:
                    try: tipo_str = str(float(tipo_val))
                    except ValueError: tipo_str = tipo_val

                new_fardo = Fardo(
                    numero_fardo=numero_fardo_str,
                    tipo=tipo_str,
                    peso_neto=float(row['peso_neto']),
                    peso_tara=float(row['peso_tara']),
                    fermentado=str(row['fermentado']).lower() in ['true', '1', 'yes', 'si'],
                    mic=str(row['mic']).lower(),
                    color_predominante=str(row.get('color_predominante', 'normal')).lower(),
                    observaciones=str(row['observaciones']),
                    fecha=fecha_obj,
                    cliente=str(row.get('cliente', '')).strip() if pd.notnull(row.get('cliente')) else None,
                    maquina=str(row['maquina']).upper()
                )
                db.session.add(new_fardo)
                newly_added_count += 1
            except Exception as e:
                flash(f"Error agregando nuevo fardo {numero_fardo_str} (fila original {index+2}): {e}", "danger")
                db.session.rollback()

        try:
            db.session.commit()
            flash(f'{newly_added_count} fardos nuevos agregados. {updated_count} fardos actualizados. {skipped_count} fardos omitidos.', 'success')
        except Exception as e:
            db.session.rollback()
            flash(f'Error al guardar cambios en la base de datos: {e}', 'danger')

        session.pop('duplicate_fardos_info', None)
        session.pop('uploaded_excel_data', None)
        return redirect(url_for('fardo_bp.list_fardos'))

    return render_template('handle_duplicates.html', duplicates=duplicates_info)


@fardo_bp.route('/fardos/<int:fardo_id>/edit', methods=['GET', 'POST'])
def edit_fardo(fardo_id):
    fardo = Fardo.query.get_or_404(fardo_id)
    if request.method == 'POST':
        try:
            fardo.numero_fardo = request.form['numero_fardo']
            fardo.tipo = request.form['tipo']
            fardo.peso_neto = float(request.form['peso_neto'])
            fardo.peso_tara = float(request.form['peso_tara'])
            fardo.fermentado = 'fermentado' in request.form
            fardo.mic = request.form['mic']
            fardo.color_predominante = request.form['color_predominante']
            fardo.observaciones = request.form['observaciones']
            fardo.fecha = datetime.strptime(request.form['fecha'], '%Y-%m-%d').date()
            fardo.cliente = request.form.get('cliente')
            fardo.maquina = request.form['maquina']
            fardo.dado_de_baja = 'dado_de_baja' in request.form

            db.session.commit()
            flash('Fardo actualizado exitosamente.', 'success')
            return redirect(url_for('fardo_bp.list_fardos'))
        except Exception as e:
            db.session.rollback()
            flash(f'Error actualizando fardo: {e}', 'danger')
    return render_template('edit_fardo.html', fardo=fardo)

@fardo_bp.route('/fardos/<int:fardo_id>/toggle_baja', methods=['POST'])
def toggle_baja_fardo(fardo_id):
    fardo = Fardo.query.get_or_404(fardo_id)
    fardo.dado_de_baja = not fardo.dado_de_baja
    status = "dado de baja" if fardo.dado_de_baja else "activado"
    try:
        db.session.commit()
        flash(f'Fardo {fardo.numero_fardo} marcado como {status}.', 'success')
    except Exception as e:
        db.session.rollback()
        flash(f'Error actualizando estado del fardo: {e}', 'danger')
    return redirect(url_for('fardo_bp.list_fardos'))
