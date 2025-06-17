from app import db
from app.models import Fardo, Lote # Ensure these imports are correct
from sqlalchemy import func
from datetime import datetime
import logging

logger = logging.getLogger(__name__) # Or use specific logger like logger_lote_generator

def determine_mic_profile(mic_value): # Moved here as it's specific to lote generation logic
    mic_value_lower = str(mic_value).lower()
    if mic_value_lower == "bajo":
        return "bajo"
    elif mic_value_lower in ["normal", "alto"]:
        return "normal_alto"
    logger.warning(f"LoteGen: Unknown MIC value '{mic_value}' treated as 'desconocido'.")
    return "desconocido"

def determine_color_profile(color_value): # Moved here
    return str(color_value).lower().strip().replace(' ', '_') if color_value else "normal"

def generate_unique_lote_codigo(maquina, tipo_algodon, mic_profile, color_profile):
    today_str = datetime.utcnow().strftime('%Y%m%d')
    base_code = f"{maquina}-{tipo_algodon}-{mic_profile}-{color_profile}-{today_str}"

    last_lote = Lote.query.filter(Lote.codigo_lote.like(f"{base_code}-%"))                           .order_by(Lote.codigo_lote.desc()).first()

    seq_num = 1
    if last_lote:
        try:
            last_seq_str = last_lote.codigo_lote.split('-')[-1]
            seq_num = int(last_seq_str) + 1
        except (IndexError, ValueError):
            logger.warning(f"LoteGen: Could not parse seq num from {last_lote.codigo_lote}. Fallback count.")
            current_max_seq = db.session.query(func.max(func.substr(Lote.codigo_lote, len(base_code) + 2)))                                  .filter(Lote.codigo_lote.like(f"{base_code}-%")).scalar()
            if current_max_seq and current_max_seq.isdigit():
                seq_num = int(current_max_seq) + 1
            else:
                seq_num = 1
    return f"{base_code}-{seq_num:03d}"

def generate_lotes_automaticos(autorizacion_especial=False): #allow_mixing_override changed to autorizacion_especial
    fardos_para_asignar = Fardo.query.filter(Fardo.lote_id == None, Fardo.dado_de_baja == False)                                      .order_by(Fardo.maquina, Fardo.tipo, Fardo.mic, Fardo.color_predominante, Fardo.fecha).all()

    if not fardos_para_asignar:
        return {"success": True, "message": "No hay fardos disponibles para generar nuevos lotes.", "lotes_creados": 0, "log": []}

    lotes_creados_count = 0
    current_lote_fardos = []
    current_lote_criteria = {}
    log_messages = []

    for fardo in fardos_para_asignar:
        fardo_mic_profile = determine_mic_profile(fardo.mic)
        fardo_color_profile = determine_color_profile(fardo.color_predominante)

        fardo_criteria = {
            'maquina': fardo.maquina,
            'tipo': fardo.tipo,
            'mic_profile': fardo_mic_profile,
            'color_profile': fardo_color_profile
        }

        # Start: Refined criteria check and lot creation logic
        is_match_criteria = (
            current_lote_criteria.get('maquina') == fardo_criteria['maquina'] and
            current_lote_criteria.get('tipo') == fardo_criteria['tipo'] and
            current_lote_criteria.get('mic_profile') == fardo_criteria['mic_profile']
        )

        if not autorizacion_especial:
            is_match_criteria = is_match_criteria and (current_lote_criteria.get('color_profile') == fardo_criteria['color_profile'])

        if not current_lote_fardos:
            current_lote_criteria = fardo_criteria.copy()
            current_lote_fardos.append(fardo)
        elif len(current_lote_fardos) < 124 and is_match_criteria:
            current_lote_fardos.append(fardo)
        else:
            # Create lot with current_lote_fardos
            if current_lote_fardos:
                actual_lot_color_profile = current_lote_criteria['color_profile']
                if autorizacion_especial:
                    first_fardo_color_in_lote = determine_color_profile(current_lote_fardos[0].color_predominante)
                    if not all(determine_color_profile(f.color_predominante) == first_fardo_color_in_lote for f in current_lote_fardos):
                        actual_lot_color_profile = "mixto"
                    else:
                        actual_lot_color_profile = first_fardo_color_in_lote

                codigo_lote = generate_unique_lote_codigo(
                    current_lote_criteria['maquina'], current_lote_criteria['tipo'],
                    current_lote_criteria['mic_profile'], actual_lot_color_profile
                )
                nuevo_lote = Lote(
                    codigo_lote=codigo_lote,
                    tipo_algodon=current_lote_criteria['tipo'],
                    maquina=current_lote_criteria['maquina'],
                    mic_profile=current_lote_criteria['mic_profile'],
                    color_profile=actual_lot_color_profile,
                    autorizado=autorizacion_especial,
                    completo=(len(current_lote_fardos) == 124)
                )
                db.session.add(nuevo_lote)
                for f_in_lote in current_lote_fardos:
                    f_in_lote.lote = nuevo_lote
                try:
                    db.session.commit()
                    lotes_creados_count += 1
                    log_messages.append(f"Lote {codigo_lote} creado con {len(current_lote_fardos)} fardos.")
                except Exception as e:
                    db.session.rollback()
                    logger.error(f"LoteGen: Error committing lote {codigo_lote}: {e}")
                    log_messages.append(f"ERROR: Lote {codigo_lote} no guardado. Fardos: {len(current_lote_fardos)}.")
            # Start new lot
            current_lote_fardos = [fardo]
            current_lote_criteria = fardo_criteria.copy()
        # End: Refined criteria check and lot creation logic

        if len(current_lote_fardos) == 124: # Exactly 124 fardos, create a complete lot
            actual_lot_color_profile = current_lote_criteria['color_profile']
            if autorizacion_especial:
                first_fardo_color_in_lote = determine_color_profile(current_lote_fardos[0].color_predominante)
                if not all(determine_color_profile(f.color_predominante) == first_fardo_color_in_lote for f in current_lote_fardos):
                    actual_lot_color_profile = "mixto"
                else:
                    actual_lot_color_profile = first_fardo_color_in_lote

            codigo_lote = generate_unique_lote_codigo(
                current_lote_criteria['maquina'], current_lote_criteria['tipo'],
                current_lote_criteria['mic_profile'], actual_lot_color_profile
            )
            nuevo_lote = Lote(
                codigo_lote=codigo_lote,
                tipo_algodon=current_lote_criteria['tipo'],
                maquina=current_lote_criteria['maquina'],
                mic_profile=current_lote_criteria['mic_profile'],
                color_profile=actual_lot_color_profile,
                autorizado=autorizacion_especial,
                completo=True
            )
            db.session.add(nuevo_lote)
            for f_in_lote in current_lote_fardos:
                f_in_lote.lote = nuevo_lote
            try:
                db.session.commit()
                lotes_creados_count += 1
                log_messages.append(f"Lote {codigo_lote} creado con {len(current_lote_fardos)} fardos (completo).")
            except Exception as e:
                db.session.rollback()
                logger.error(f"LoteGen: Error committing full lote {codigo_lote}: {e}")
                log_messages.append(f"ERROR: Lote completo {codigo_lote} no guardado. Fardos: {len(current_lote_fardos)}.")
            # Reset for next lot
            current_lote_fardos = []
            current_lote_criteria = {}
    # End of the loop for fardos_para_asignar

    # Process any remaining fardos that didn't form a full lot of 124
    if current_lote_fardos: # Check if there are remaining fardos
        actual_lot_color_profile = current_lote_criteria['color_profile']
        if autorizacion_especial:
            first_fardo_color_in_lote = determine_color_profile(current_lote_fardos[0].color_predominante)
            if not all(determine_color_profile(f.color_predominante) == first_fardo_color_in_lote for f in current_lote_fardos):
                actual_lot_color_profile = "mixto"
            else:
                actual_lot_color_profile = first_fardo_color_in_lote

        codigo_lote = generate_unique_lote_codigo(
            current_lote_criteria['maquina'], current_lote_criteria['tipo'],
            current_lote_criteria['mic_profile'], actual_lot_color_profile
        )
        nuevo_lote = Lote(
            codigo_lote=codigo_lote,
            tipo_algodon=current_lote_criteria['tipo'],
            maquina=current_lote_criteria['maquina'],
            mic_profile=current_lote_criteria['mic_profile'],
            color_profile=actual_lot_color_profile,
            autorizado=autorizacion_especial,
            completo=(len(current_lote_fardos) == 124) # This will be False if less than 124
        )
        db.session.add(nuevo_lote)
        for f_in_lote in current_lote_fardos:
            f_in_lote.lote = nuevo_lote
        try:
            db.session.commit()
            lotes_creados_count += 1
            log_messages.append(f"Lote final {codigo_lote} creado con {len(current_lote_fardos)} fardos.")
        except Exception as e:
            db.session.rollback()
            logger.error(f"LoteGen: Error committing final lote {codigo_lote}: {e}")
            log_messages.append(f"ERROR: Lote final {codigo_lote} no guardado. Fardos: {len(current_lote_fardos)}.")

    if lotes_creados_count > 0:
        msg = f"Proceso de generación de lotes completado. {lotes_creados_count} lotes creados."
    else:
        msg = "No se crearon nuevos lotes."
        if not fardos_para_asignar and not log_messages:
             msg = "No hay fardos disponibles (no asignados y no dados de baja) para generar nuevos lotes."

    return {"success": True, "message": msg, "lotes_creados": lotes_creados_count, "log": log_messages}
