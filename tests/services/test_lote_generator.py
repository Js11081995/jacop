import pytest
from app import db
from app.models import Fardo, Lote
from app.services.lote_generator import generate_lotes_automaticos, determine_mic_profile, determine_color_profile
from datetime import datetime

# Assuming 'test_client' fixture from conftest.py handles app context and db setup/teardown

def _create_fardo(maquina, tipo, mic, color, peso_neto=100.0, numero_fardo="F1", dado_de_baja=False, lote_id=None, fecha=None):
    f = Fardo(
        numero_fardo=numero_fardo,
        maquina=maquina,
        tipo=tipo,
        mic=mic,
        color_predominante=color,
        peso_neto=peso_neto,
        peso_tara=10.0,
        dado_de_baja=dado_de_baja,
        lote_id=lote_id,
        fecha_hora_produccion=fecha if fecha else datetime.utcnow(),
        observaciones="Test fardo"
    )
    db.session.add(f)
    return f

def test_generate_lotes_standard(test_client):
    # Clean up potential previous test data
    Fardo.query.delete()
    Lote.query.delete()
    db.session.commit()

    # Create fardos that should form one standard lot
    for i in range(5): # Using 5 for brevity, main logic is about criteria matching
        _create_fardo(maquina="M1", tipo="T1", mic="normal", color="blanco", numero_fardo=f"F{i+1}")
    db.session.commit()

    result = generate_lotes_automaticos(autorizacion_especial=False)
    assert result["lotes_creados"] == 1

    lotes = Lote.query.all()
    assert len(lotes) == 1
    lote = lotes[0]
    assert lote.autorizado == False
    assert lote.color_profile == determine_color_profile("blanco")
    assert lote.fardos.count() == 5

    fardos_in_db = Fardo.query.all()
    for fardo in fardos_in_db:
        assert fardo.lote_id == lote.id

def test_generate_lotes_standard_separate_colors(test_client):
    Fardo.query.delete()
    Lote.query.delete()
    db.session.commit()

    # Fardos with same (maquina, tipo, mic) but two different colors
    for i in range(3):
        _create_fardo(maquina="M1", tipo="T1", mic="normal", color="blanco", numero_fardo=f"B{i+1}")
    for i in range(3):
        _create_fardo(maquina="M1", tipo="T1", mic="normal", color="rojo", numero_fardo=f"R{i+1}")
    db.session.commit()

    result = generate_lotes_automaticos(autorizacion_especial=False)
    assert result["lotes_creados"] == 2

    lotes = Lote.query.order_by(Lote.color_profile).all()
    assert len(lotes) == 2

    assert lotes[0].color_profile == determine_color_profile("blanco")
    assert lotes[0].autorizado == False
    assert lotes[0].fardos.count() == 3

    assert lotes[1].color_profile == determine_color_profile("rojo")
    assert lotes[1].autorizado == False
    assert lotes[1].fardos.count() == 3

def test_generate_lotes_authorized_mixed_colors(test_client):
    Fardo.query.delete()
    Lote.query.delete()
    db.session.commit()

    # Fardos with same (maquina, tipo, mic) but two different colors
    _create_fardo(maquina="M1", tipo="T1", mic="normal", color="blanco", numero_fardo="B1")
    _create_fardo(maquina="M1", tipo="T1", mic="normal", color="blanco", numero_fardo="B2")
    _create_fardo(maquina="M1", tipo="T1", mic="normal", color="rojo", numero_fardo="R1")
    _create_fardo(maquina="M1", tipo="T1", mic="normal", color="rojo", numero_fardo="R2")
    db.session.commit()

    result = generate_lotes_automaticos(autorizacion_especial=True)
    assert result["lotes_creados"] == 1

    lotes = Lote.query.all()
    assert len(lotes) == 1
    lote = lotes[0]

    assert lote.autorizado == True
    assert lote.color_profile == "mixto" # Expecting "mixto" due to mixed colors with authorization
    assert lote.fardos.count() == 4

    fardos_in_db = Fardo.query.all()
    for fardo in fardos_in_db:
        assert fardo.lote_id == lote.id

def test_generate_lotes_authorized_mic_still_separates(test_client):
    Fardo.query.delete()
    Lote.query.delete()
    db.session.commit()

    # Fardos with same (maquina, tipo) but different mics and colors
    # Group 1: M1, T1, bajo mic, mixed colors
    _create_fardo(maquina="M1", tipo="T1", mic="bajo", color="blanco", numero_fardo="LB1")
    _create_fardo(maquina="M1", tipo="T1", mic="bajo", color="azul", numero_fardo="LA1")
    # Group 2: M1, T1, normal mic, mixed colors
    _create_fardo(maquina="M1", tipo="T1", mic="normal", color="rojo", numero_fardo="NR1")
    _create_fardo(maquina="M1", tipo="T1", mic="normal", color="verde", numero_fardo="NV1")
    db.session.commit()

    result = generate_lotes_automaticos(autorizacion_especial=True)
    assert result["lotes_creados"] == 2

    lotes = Lote.query.order_by(Lote.mic_profile).all()
    assert len(lotes) == 2

    # Lot 1 (bajo mic)
    lote_bajo = None
    lote_normal_alto = None

    for l in lotes:
        if l.mic_profile == determine_mic_profile("bajo"):
            lote_bajo = l
        elif l.mic_profile == determine_mic_profile("normal"): # normal_alto
            lote_normal_alto = l

    assert lote_bajo is not None
    assert lote_bajo.autorizado == True
    assert lote_bajo.mic_profile == determine_mic_profile("bajo")
    assert lote_bajo.color_profile == "mixto" # Mixed colors within this MIC group
    assert lote_bajo.fardos.count() == 2
    for fardo_code in ["LB1", "LA1"]:
        f = Fardo.query.filter_by(numero_fardo=fardo_code).first()
        assert f.lote_id == lote_bajo.id

    assert lote_normal_alto is not None
    assert lote_normal_alto.autorizado == True
    assert lote_normal_alto.mic_profile == determine_mic_profile("normal") # normal_alto
    assert lote_normal_alto.color_profile == "mixto" # Mixed colors within this MIC group
    assert lote_normal_alto.fardos.count() == 2
    for fardo_code in ["NR1", "NV1"]:
        f = Fardo.query.filter_by(numero_fardo=fardo_code).first()
        assert f.lote_id == lote_normal_alto.id

def test_generate_lotes_no_fardos(test_client):
    Fardo.query.delete()
    Lote.query.delete()
    db.session.commit()

    result = generate_lotes_automaticos(autorizacion_especial=False)
    assert result["lotes_creados"] == 0
    assert "No hay fardos disponibles" in result["message"]
    lotes = Lote.query.all()
    assert len(lotes) == 0

def test_generate_lotes_max_capacity_then_new_lot(test_client):
    Fardo.query.delete()
    Lote.query.delete()
    db.session.commit()

    for i in range(125): # 124 for one full lot, 1 for the next
        _create_fardo(maquina="M_CAP", tipo="T_CAP", mic="normal", color="azul", numero_fardo=f"CAP{i+1}")
    db.session.commit()

    result = generate_lotes_automaticos(autorizacion_especial=False)
    assert result["lotes_creados"] == 2 # One full, one partial

    lotes = Lote.query.order_by(Lote.id).all()
    assert len(lotes) == 2
    assert lotes[0].fardos.count() == 124
    assert lotes[0].completo == True
    assert lotes[1].fardos.count() == 1
    assert lotes[1].completo == False

    # Ensure correct fardos are in correct lots
    first_124_fardos = Fardo.query.filter(Fardo.numero_fardo.like("CAP%")).order_by(Fardo.id).limit(124).all()
    for f in first_124_fardos:
        assert f.lote_id == lotes[0].id

    last_fardo = Fardo.query.filter(Fardo.numero_fardo == "CAP125").first()
    assert last_fardo.lote_id == lotes[1].id

# Helper to get a fardo's criteria dictionary, similar to how it's done in the service
def get_fardo_criteria_dict(fardo):
    return {
        'maquina': fardo.maquina,
        'tipo': fardo.tipo,
        'mic_profile': determine_mic_profile(fardo.mic),
        'color_profile': determine_color_profile(fardo.color_predominante)
    }

def test_generate_lotes_order_priority(test_client):
    """
    Tests if fardos are processed in the correct order:
    maquina, tipo, mic, color_predominante, fecha
    """
    Fardo.query.delete()
    Lote.query.delete()
    db.session.commit()

    # All M1, T1, normal mic, blanco color
    # Fardo created later but with earlier date should be processed first if other criteria match
    f1 = _create_fardo(maquina="M1", tipo="T1", mic="normal", color="blanco", numero_fardo="F_DATE_EARLY", fecha=datetime(2023, 1, 1))
    f2 = _create_fardo(maquina="M1", tipo="T1", mic="normal", color="blanco", numero_fardo="F_DATE_LATE", fecha=datetime(2023, 1, 5))

    # Different color - should be a separate lot
    f3 = _create_fardo(maquina="M1", tipo="T1", mic="normal", color="rojo", numero_fardo="F_ROJO", fecha=datetime(2023, 1, 2))

    # Different MIC - should be a separate lot
    f4 = _create_fardo(maquina="M1", tipo="T1", mic="bajo", color="blanco", numero_fardo="F_BAJO_MIC", fecha=datetime(2023, 1, 3))

    # Different Tipo - should be a separate lot
    f5 = _create_fardo(maquina="M1", tipo="T2", mic="normal", color="blanco", numero_fardo="F_TIPO2", fecha=datetime(2023, 1, 4))

    # Different Maquina - should be a separate lot
    f6 = _create_fardo(maquina="M2", tipo="T1", mic="normal", color="blanco", numero_fardo="F_MAQ2", fecha=datetime(2023, 1, 1)) # Same early date as F1

    db.session.commit()

    result = generate_lotes_automaticos(autorizacion_especial=False)
    # Expect 5 lots: (F_DATE_EARLY, F_DATE_LATE), (F_ROJO), (F_BAJO_MIC), (F_TIPO2), (F_MAQ2)
    assert result["lotes_creados"] == 5

    lotes = Lote.query.all()
    assert len(lotes) == 5

    # Verify f1 and f2 are in the same lot
    lote_f1 = Fardo.query.filter_by(numero_fardo="F_DATE_EARLY").first().lote
    lote_f2 = Fardo.query.filter_by(numero_fardo="F_DATE_LATE").first().lote
    assert lote_f1 is not None
    assert lote_f1 == lote_f2
    assert lote_f1.fardos.count() == 2

    # Verify other fardos are in their own lots
    assert Fardo.query.filter_by(numero_fardo="F_ROJO").first().lote != lote_f1
    assert Fardo.query.filter_by(numero_fardo="F_BAJO_MIC").first().lote != lote_f1
    assert Fardo.query.filter_by(numero_fardo="F_TIPO2").first().lote != lote_f1
    assert Fardo.query.filter_by(numero_fardo="F_MAQ2").first().lote != lote_f1

    # Check that the lot for M2 machine only contains F_MAQ2
    lote_f6 = Fardo.query.filter_by(numero_fardo="F_MAQ2").first().lote
    assert lote_f6.fardos.count() == 1
    assert lote_f6.maquina == "M2"

    # Check that the lot for T2 tipo only contains F_TIPO2
    lote_f5 = Fardo.query.filter_by(numero_fardo="F_TIPO2").first().lote
    assert lote_f5.fardos.count() == 1
    assert lote_f5.tipo_algodon == "T2"

    # Check that the lot for bajo MIC only contains F_BAJO_MIC
    lote_f4 = Fardo.query.filter_by(numero_fardo="F_BAJO_MIC").first().lote
    assert lote_f4.fardos.count() == 1
    assert lote_f4.mic_profile == determine_mic_profile("bajo")

    # Check that the lot for rojo color only contains F_ROJO
    lote_f3 = Fardo.query.filter_by(numero_fardo="F_ROJO").first().lote
    assert lote_f3.fardos.count() == 1
    assert lote_f3.color_profile == determine_color_profile("rojo")

    # The first lot created (for M1, T1, normal, blanco) should have f1 and f2
    # and its creation date should reflect the earliest fardo.
    # This is implicitly tested by f1 and f2 being in the same lot.
    # The service uses current_lote_criteria, which is based on the first fardo in a lot.
    # The sorting for fardo processing is `Fardo.maquina, Fardo.tipo, Fardo.mic, Fardo.color_predominante, Fardo.fecha`
    # So f1 (M1, T1, normal, blanco, Jan 1) should be processed first for its group.
    # f2 (M1, T1, normal, blanco, Jan 5) should join it.
    # f6 (M2, T1, normal, blanco, Jan 1) will start a new group due to Maquina.
    # f4 (M1, T1, bajo, blanco, Jan 3) will start a new group due to MIC.
    # f3 (M1, T1, normal, rojo, Jan 2) will start a new group due to Color.
    # f5 (M1, T2, normal, blanco, Jan 4) will start a new group due to Tipo.

    # The exact order of lot creation in the DB isn't guaranteed unless we query with an order_by on lot ID or creation time.
    # The key is that the correct fardos end up in the correct lots.

    # Verify the lot for F1 and F2 has the criteria of F1
    assert lote_f1.maquina == "M1"
    assert lote_f1.tipo_algodon == "T1"
    assert lote_f1.mic_profile == determine_mic_profile("normal")
    assert lote_f1.color_profile == determine_color_profile("blanco")

print("Created tests/services/test_lote_generator.py")
