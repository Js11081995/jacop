import pytest
from app import db
from app.models import Fardo, Lote
from app.services.report_generator import get_reporte_por_maquina_data, determine_mic_profile, determine_color_profile
from datetime import datetime

# Assuming 'test_client' fixture from conftest.py handles app context and db setup/teardown

def _create_fardo_for_report(maquina, tipo, mic, color, peso_neto=100.0, numero_fardo="F1", dado_de_baja=False, lote_obj=None, fecha=None):
    f = Fardo(
        numero_fardo=numero_fardo,
        maquina=maquina,
        tipo=tipo,
        mic=mic,
        color_predominante=color,
        peso_neto=peso_neto,
        peso_tara=10.0,
        dado_de_baja=dado_de_baja,
        lote=lote_obj, # Assign Lote object directly
        fecha_hora_produccion=fecha if fecha else datetime.utcnow(),
        observaciones="Test fardo for report"
    )
    db.session.add(f)
    return f

def _create_lote_for_report(codigo_lote, tipo_algodon, maquina, mic_profile, color_profile, entregado=False, autorizado=False):
    l = Lote(
        codigo_lote=codigo_lote,
        tipo_algodon=tipo_algodon,
        maquina=maquina,
        mic_profile=mic_profile,
        color_profile=color_profile,
        entregado=entregado,
        autorizado=autorizado,
        completo=False # For simplicity in test setup
    )
    db.session.add(l)
    return l

def test_get_reporte_por_maquina_data_empty(test_client):
    Fardo.query.delete()
    Lote.query.delete()
    db.session.commit()

    report_data = get_reporte_por_maquina_data()
    assert report_data == []

def test_get_reporte_por_maquina_data_various_fardos(test_client):
    Fardo.query.delete()
    Lote.query.delete()
    db.session.commit()

    # Lotes
    lote1_m1_t1_norm_bco = _create_lote_for_report("L1", "T1", "M1", determine_mic_profile("normal"), determine_color_profile("blanco"), entregado=False)
    lote2_m1_t1_norm_bco_ent = _create_lote_for_report("L2", "T1", "M1", determine_mic_profile("normal"), determine_color_profile("blanco"), entregado=True)
    lote3_m1_t2_bajo_rjo = _create_lote_for_report("L3", "T2", "M1", determine_mic_profile("bajo"), determine_color_profile("rojo"), entregado=False)
    lote4_m2_t1_norm_azl = _create_lote_for_report("L4", "T1", "M2", determine_mic_profile("normal"), determine_color_profile("azul"), entregado=True)
    db.session.commit()

    # Fardos
    # Group 1: M1, T1, normal, blanco
    _create_fardo_for_report("M1", "T1", "normal", "blanco", peso_neto=100, numero_fardo="G1F1_disp") # Disponible
    _create_fardo_for_report("M1", "T1", "normal", "blanco", peso_neto=110, numero_fardo="G1F2_lote1", lote_obj=lote1_m1_t1_norm_bco) # En Lote (L1)
    _create_fardo_for_report("M1", "T1", "normal", "blanco", peso_neto=120, numero_fardo="G1F3_lote2", lote_obj=lote2_m1_t1_norm_bco_ent) # Entregado (L2)
    _create_fardo_for_report("M1", "T1", "normal", "blanco", peso_neto=10, numero_fardo="G1F4_baja", dado_de_baja=True) # Dado de baja

    # Group 2: M1, T2, bajo, rojo
    _create_fardo_for_report("M1", "T2", "bajo", "rojo", peso_neto=130, numero_fardo="G2F1_disp") # Disponible
    _create_fardo_for_report("M1", "T2", "bajo", "rojo", peso_neto=140, numero_fardo="G2F2_lote3", lote_obj=lote3_m1_t2_bajo_rjo) # En Lote (L3)

    # Group 3: M2, T1, normal, azul
    _create_fardo_for_report("M2", "T1", "normal", "azul", peso_neto=150, numero_fardo="G3F1_lote4", lote_obj=lote4_m2_t1_norm_azl) # Entregado (L4)
    _create_fardo_for_report("M2", "T1", "normal", "azul", peso_neto=160, numero_fardo="G3F2_lote4", lote_obj=lote4_m2_t1_norm_azl) # Entregado (L4)

    # Another disponible for M1, T1, normal, blanco to test sum
    _create_fardo_for_report("M1", "T1", "normal", "blanco", peso_neto=90, numero_fardo="G1F5_disp") # Disponible
    db.session.commit()

    report_data = get_reporte_por_maquina_data()

    assert len(report_data) == 3 # Three distinct groups (M1/T1/norm/bco, M1/T2/bajo/rjo, M2/T1/norm/azl)

    # Sort data to make assertions predictable
    report_data.sort(key=lambda x: (x['maquina'], x['tipo'], x['mic_profile'], x['color_profile']))

    # Assertions for Group 1: M1, T1, normal_alto, blanco
    g1_data = None
    for item in report_data:
        if (item['maquina'] == "M1" and
            item['tipo'] == "T1" and
            item['mic_profile'] == determine_mic_profile("normal") and
            item['color_profile'] == determine_color_profile("blanco")):
            g1_data = item
            break
    assert g1_data is not None
    assert g1_data['disponibles'] == 2 # G1F1_disp, G1F5_disp
    assert g1_data['en_lote'] == 1    # G1F2_lote1
    assert g1_data['entregados'] == 1 # G1F3_lote2
    assert g1_data['peso_neto_total'] == (100.0 + 110.0 + 120.0 + 90.0) # Excludes G1F4_baja (10.0)

    # Assertions for Group 2: M1, T2, bajo, rojo
    g2_data = None
    for item in report_data:
        if (item['maquina'] == "M1" and
            item['tipo'] == "T2" and
            item['mic_profile'] == determine_mic_profile("bajo") and
            item['color_profile'] == determine_color_profile("rojo")):
            g2_data = item
            break
    assert g2_data is not None
    assert g2_data['disponibles'] == 1 # G2F1_disp
    assert g2_data['en_lote'] == 1    # G2F2_lote3
    assert g2_data['entregados'] == 0
    assert g2_data['peso_neto_total'] == (130.0 + 140.0)

    # Assertions for Group 3: M2, T1, normal_alto, azul
    g3_data = None
    for item in report_data:
        if (item['maquina'] == "M2" and
            item['tipo'] == "T1" and
            item['mic_profile'] == determine_mic_profile("normal") and # normal_alto
            item['color_profile'] == determine_color_profile("azul")):
            g3_data = item
            break
    assert g3_data is not None
    assert g3_data['disponibles'] == 0
    assert g3_data['en_lote'] == 0
    assert g3_data['entregados'] == 2 # G3F1_lote4, G3F2_lote4
    assert g3_data['peso_neto_total'] == (150.0 + 160.0)

    # Check that no fardos dado_de_baja are included in any count or peso_neto_total
    # This is implicitly checked by the counts above, but explicitly:
    total_peso_from_report = sum(item['peso_neto_total'] for item in report_data)
    fardos_not_baja = Fardo.query.filter(Fardo.dado_de_baja == False).all()
    expected_total_peso = sum(f.peso_neto for f in fardos_not_baja)
    assert total_peso_from_report == expected_total_peso

    all_fardos_count_from_report = sum(item['disponibles'] + item['en_lote'] + item['entregados'] for item in report_data)
    assert all_fardos_count_from_report == len(fardos_not_baja)

print("Created tests/services/test_report_generator.py")
