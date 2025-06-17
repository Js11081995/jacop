import pandas as pd
from app import db
from app.models import Fardo
from datetime import datetime

def process_excel(file_stream):
    try:
        df = pd.read_excel(file_stream, engine='openpyxl')

        required_columns = [
            'Numero de fardo', 'Tipo', 'Peso neto', 'Peso tara',
            'Fermentado', 'MIC', 'Observaciones', 'Fecha', 'Maquina'
        ]

        df.columns = [col.lower().replace(' ', '_') for col in df.columns]
        normalized_required_columns = [col.lower().replace(' ', '_') for col in required_columns]

        missing_cols = [col for col in normalized_required_columns if col not in df.columns]
        if missing_cols:
            return {"success": False, "message": f"Columnas faltantes en el Excel: {', '.join(missing_cols)}", "duplicates": [], "new_fardos": 0, "updated_fardos": 0}

        fardos_processed_count = 0
        fardos_updated_count = 0 # This remains 0 as per current logic in this function
        duplicate_fardos_details = []
        final_message = ""

        for index, row in df.iterrows():
            numero_fardo_str = str(row['numero_de_fardo']).strip()
            if not numero_fardo_str:
                continue

            existing_fardo = Fardo.query.filter_by(numero_fardo=numero_fardo_str).first()

            if existing_fardo:
                duplicate_fardos_details.append({
                    "numero_fardo": numero_fardo_str,
                    "action_taken": "skipped_for_now"
                })
                continue

            try:
                fecha_obj = pd.to_datetime(row['fecha']).date() if pd.notnull(row['fecha']) else datetime.utcnow().date()

                tipo_val = str(row['tipo']).strip() if pd.notnull(row['tipo']) else "sin tipo"
                if tipo_val.lower() == "sin tipo":
                    tipo_str = "sin tipo"
                else:
                    try:
                        tipo_float = float(tipo_val)
                        tipo_str = str(tipo_float)
                    except ValueError:
                        tipo_str = tipo_val

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
                fardos_processed_count += 1
            except Exception as e:
                db.session.rollback() # Rollback for this specific row error
                return {"success": False, "message": f"Error procesando fila {index+2} para fardo {numero_fardo_str}: {e}", "duplicates": duplicate_fardos_details, "new_fardos": 0, "updated_fardos": fardos_updated_count}

        if not duplicate_fardos_details:
            db.session.commit()
            final_message = "Excel procesado exitosamente."
            # fardos_processed_count remains as is, representing committed fardos
        else:
            db.session.rollback() # Rollback all adds if duplicates were found
            final_message = "Excel procesado parcialmente. Fardos nuevos no fueron guardados debido a duplicados. Resuelva los duplicados."
            fardos_processed_count = 0 # Reset count as nothing new was committed due to rollback

        return {
            "success": True, # The operation of processing the file itself was successful (even if some data was not committed)
            "message": final_message,
            "duplicates": duplicate_fardos_details,
            "new_fardos": fardos_processed_count, # This will now be 0 if duplicates caused rollback
            "updated_fardos": fardos_updated_count # Remains 0 as per current logic
        }

    except Exception as e:
        db.session.rollback()
        return {"success": False, "message": f"Error al leer o procesar el archivo Excel: {e}", "duplicates": [], "new_fardos": 0, "updated_fardos": 0}

def update_fardo_data(numero_fardo, new_data_row):
    fardo = Fardo.query.filter_by(numero_fardo=str(numero_fardo)).first()
    if not fardo:
        return False, "Fardo no encontrado para actualizar."

    try:
        fardo.tipo = str(new_data_row['tipo']).strip() if pd.notnull(new_data_row['tipo']) else "sin tipo"
        if fardo.tipo.lower() == "sin tipo":
            fardo.tipo = "sin tipo"
        else:
            try:
                fardo.tipo = str(float(new_data_row['tipo']))
            except ValueError:
                pass # Keep original if not floatable

        fardo.peso_neto = float(new_data_row['peso_neto'])
        fardo.peso_tara = float(new_data_row['peso_tara'])
        fardo.fermentado = str(new_data_row['fermentado']).lower() in ['true', '1', 'yes', 'si']
        fardo.mic = str(new_data_row['mic']).lower()
        fardo.color_predominante = str(new_data_row.get('color_predominante', fardo.color_predominante)).lower()
        fardo.observaciones = str(new_data_row['observaciones'])
        fardo.fecha = pd.to_datetime(new_data_row['fecha']).date() if pd.notnull(new_data_row['fecha']) else fardo.fecha
        fardo.cliente = str(new_data_row.get('cliente', '')).strip() if pd.notnull(new_data_row.get('cliente')) else fardo.cliente
        fardo.maquina = str(new_data_row['maquina']).upper()

        db.session.commit()
        return True, "Fardo actualizado."
    except Exception as e:
        db.session.rollback()
        return False, f"Error actualizando fardo {numero_fardo}: {e}"
