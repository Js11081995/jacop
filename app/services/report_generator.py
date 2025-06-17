from flask import render_template, make_response
from weasyprint import HTML, CSS
from openpyxl import Workbook
from openpyxl.styles import Font, Alignment, Border, Side
from app.models import Lote, Fardo, TakeOutReport # Ensure Fardo, Lote, TakeOutReport are imported
from app import db
from datetime import datetime
import io
import os
import logging # Added for logger_rpm

# --- Content from Subtask 6: PDF/Excel for Take Out Reports ---
def generate_take_out_report_pdf_bytes(lote_ids, client_name, include_individual_weights, entrega_firma, recibe_firma):
    lotes = Lote.query.filter(Lote.id.in_(lote_ids)).order_by(Lote.codigo_lote).all()
    if not lotes:
        raise ValueError("No lotes found for the given IDs.")

    report_date = datetime.utcnow()
    total_fardos_general = 0
    total_peso_neto_general = 0.0
    total_peso_tara_general = 0.0

    lotes_summary_list = []
    for lote in lotes:
        fardos_count = lote.fardos.count()
        peso_neto_lote = sum(f.peso_neto for f in lote.fardos)
        peso_tara_lote = sum(f.peso_tara for f in lote.fardos)

        lotes_summary_list.append({
            'codigo': lote.codigo_lote,
            'tipo': lote.tipo_algodon,
            'cantidad': fardos_count,
            'peso_neto': peso_neto_lote,
            'peso_tara': peso_tara_lote,
            'peso_total': peso_neto_lote - peso_tara_lote
        })
        total_fardos_general += fardos_count
        total_peso_neto_general += peso_neto_lote
        total_peso_tara_general += peso_tara_lote

    html_string = render_template(
        'reports/take_out_report_pdf_template.html', # Path relative to app's template folder
        lotes=lotes,
        lotes_summary=lotes_summary_list,
        client_name=client_name,
        report_date=report_date,
        include_individual_weights=include_individual_weights,
        total_fardos_general=total_fardos_general,
        total_peso_neto_general=total_peso_neto_general,
        total_peso_tara_general=total_peso_tara_general,
        gran_total_peso_general=total_peso_neto_general - total_peso_tara_general,
        entrega_firma=entrega_firma,
        recibe_firma=recibe_firma
    )
    pdf_bytes = HTML(string=html_string).write_pdf()
    return pdf_bytes

def generate_take_out_report_excel_bytes(lote_ids, client_name, include_individual_weights, entrega_firma, recibe_firma):
    lotes = Lote.query.filter(Lote.id.in_(lote_ids)).order_by(Lote.codigo_lote).all()
    if not lotes:
        raise ValueError("No lotes found for the given IDs.")

    wb = Workbook()
    ws_cover = wb.active
    ws_cover.title = "Caratula"

    bold_font = Font(bold=True)
    center_alignment = Alignment(horizontal='center', vertical='center')

    ws_cover.merge_cells('A1:F1')
    ws_cover['A1'] = "TAKE OUT REPORT"
    ws_cover['A1'].font = Font(size=16, bold=True)
    ws_cover['A1'].alignment = center_alignment

    ws_cover['A3'] = "Fecha:"
    ws_cover['B3'] = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')
    ws_cover['A4'] = "Cliente:"
    ws_cover['B4'] = client_name

    ws_cover['A6'] = "Entrega (Firma):"
    ws_cover['B6'] = entrega_firma
    ws_cover['D6'] = "Recibe (Firma):"
    ws_cover['E6'] = recibe_firma
    ws_cover['B7'] = "____________________"
    ws_cover['E7'] = "____________________"

    headers_summary = ["Lote", "Tipo", "Cantidad Fardos", "Peso Neto (kg)", "Peso Tara (kg)", "Peso Total (kg)"]
    for i, header in enumerate(headers_summary, start=1):
        ws_cover.cell(row=9, column=i, value=header).font = bold_font

    row_idx = 10
    total_fardos_general = 0
    total_peso_neto_general = 0.0
    total_peso_tara_general = 0.0

    for lote in lotes:
        fardos_count = lote.fardos.count()
        peso_neto_lote = sum(f.peso_neto for f in lote.fardos)
        peso_tara_lote = sum(f.peso_tara for f in lote.fardos)
        peso_total_lote = peso_neto_lote - peso_tara_lote

        ws_cover.cell(row=row_idx, column=1, value=lote.codigo_lote)
        ws_cover.cell(row=row_idx, column=2, value=lote.tipo_algodon)
        ws_cover.cell(row=row_idx, column=3, value=fardos_count)
        ws_cover.cell(row=row_idx, column=4, value=peso_neto_lote)
        ws_cover.cell(row=row_idx, column=5, value=peso_tara_lote)
        ws_cover.cell(row=row_idx, column=6, value=peso_total_lote)

        total_fardos_general += fardos_count
        total_peso_neto_general += peso_neto_lote
        total_peso_tara_general += peso_tara_lote
        row_idx += 1

    ws_cover.cell(row=row_idx, column=2, value="TOTALES").font = bold_font
    ws_cover.cell(row=row_idx, column=3, value=total_fardos_general).font = bold_font
    ws_cover.cell(row=row_idx, column=4, value=total_peso_neto_general).font = bold_font
    ws_cover.cell(row=row_idx, column=5, value=total_peso_tara_general).font = bold_font
    ws_cover.cell(row=row_idx, column=6, value=total_peso_neto_general - total_peso_tara_general).font = bold_font

    for lote in lotes:
        ws_detail = wb.create_sheet(title=f"Lote {lote.codigo_lote[:20]}")
        ws_detail['A1'] = f"Detalle Lote: {lote.codigo_lote}"
        ws_detail['A1'].font = bold_font
        ws_detail.merge_cells('A1:D1')

        headers_detail = ["Número Fardo", "Tipo", "MIC", "Color", "Observaciones"]
        if include_individual_weights:
            headers_detail.extend(["Peso Neto", "Peso Tara"])

        for col, header in enumerate(headers_detail, start=1):
            ws_detail.cell(row=3, column=col, value=header).font = bold_font

        detail_row_idx = 4
        for fardo in lote.fardos.order_by(Fardo.numero_fardo).all():
            col_idx = 1
            ws_detail.cell(row=detail_row_idx, column=col_idx, value=fardo.numero_fardo); col_idx+=1
            ws_detail.cell(row=detail_row_idx, column=col_idx, value=fardo.tipo); col_idx+=1
            ws_detail.cell(row=detail_row_idx, column=col_idx, value=fardo.mic); col_idx+=1
            ws_detail.cell(row=detail_row_idx, column=col_idx, value=fardo.color_predominante); col_idx+=1
            ws_detail.cell(row=detail_row_idx, column=col_idx, value=fardo.observaciones); col_idx+=1
            if include_individual_weights:
                ws_detail.cell(row=detail_row_idx, column=col_idx, value=fardo.peso_neto); col_idx+=1
                ws_detail.cell(row=detail_row_idx, column=col_idx, value=fardo.peso_tara); col_idx+=1
            detail_row_idx += 1

        for column_cells in ws_detail.columns:
            length = max(len(str(cell.value) or "") for cell in column_cells)
            ws_detail.column_dimensions[column_cells[0].column_letter].width = length + 2

    excel_bytes_io = io.BytesIO()
    wb.save(excel_bytes_io)
    excel_bytes_io.seek(0)
    return excel_bytes_io.getvalue()

def save_take_out_report_history(client_name, lote_ids_info_str):
    report_record = TakeOutReport(
        cliente_nombre=client_name,
        lotes_incluidos_info=lote_ids_info_str
    )
    db.session.add(report_record)

    lote_id_list = [int(id_str) for id_str in lote_ids_info_str.split(',') if id_str.strip().isdigit()]
    lotes_to_mark = Lote.query.filter(Lote.id.in_(lote_id_list)).all()
    for lote in lotes_to_mark:
        lote.entregado = True

    db.session.commit()
    return report_record.id

# --- Content from Subtask 7: Reporte por Maquina ---
logger_rpm = logging.getLogger(__name__) # Using module's __name__ is fine

def determine_mic_profile(mic_value):
    mic_value_lower = str(mic_value).lower()
    if mic_value_lower == "bajo":
        return "bajo"
    elif mic_value_lower in ["normal", "alto"]:
        return "normal_alto"
    logger_rpm.warning(f"RPM Report: Unknown MIC value '{mic_value}' treated as 'desconocido'.")
    return "desconocido"

def determine_color_profile(color_value):
    return str(color_value).lower().strip().replace(' ', '_') if color_value else "normal"

def get_reporte_por_maquina_data():
    all_fardos = Fardo.query.filter(Fardo.dado_de_baja == False).all()
    summary = {}

    for fardo in all_fardos:
        maquina = fardo.maquina
        tipo = fardo.tipo
        mic_profile = determine_mic_profile(fardo.mic)
        color_profile = determine_color_profile(fardo.color_predominante)

        if maquina not in summary:
            summary[maquina] = {}

        key = (tipo, mic_profile, color_profile)
        if key not in summary[maquina]:
            summary[maquina][key] = {
                'disponibles': 0,
                'en_lote': 0,
                'entregados': 0,
                'peso_neto_total': 0.0
            }

        summary[maquina][key]['peso_neto_total'] += (fardo.peso_neto if fardo.peso_neto else 0.0)

        if fardo.lote_id is None:
            summary[maquina][key]['disponibles'] += 1
        else:
            # Ensure fardo.lote is loaded, especially if using lazy loading (though direct access is common)
            if fardo.lote: # Check if lote relationship is populated
                if fardo.lote.entregado:
                    summary[maquina][key]['entregados'] += 1
                else:
                    summary[maquina][key]['en_lote'] += 1
            else:
                # This case should ideally not happen if data integrity is maintained
                # (fardo has lote_id but lote object is not found).
                # Could log a warning or count as 'disponibles' if lote_id is somehow stale.
                logger_rpm.warning(f"Fardo {fardo.id} has lote_id {fardo.lote_id} but lote object not found. Counted as 'disponibles'.")
                summary[maquina][key]['disponibles'] += 1


    report_data = []
    for maquina, maquina_data in summary.items():
        for (tipo, mic, color), counts in maquina_data.items():
            report_data.append({
                'maquina': maquina,
                'tipo': tipo,
                'mic_profile': mic,
                'color_profile': color,
                'disponibles': counts['disponibles'],
                'en_lote': counts['en_lote'],
                'entregados': counts['entregados'],
                'peso_neto_total': round(counts['peso_neto_total'], 2) # Round to 2 decimal places
            })

    report_data.sort(key=lambda x: (x['maquina'], x['tipo'], x['mic_profile'], x['color_profile']))
    return report_data
