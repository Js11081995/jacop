import pytest
from app import db # Should be found via sys.path hack in conftest.py
from app.models import Fardo # Should be found
from app.services.excel_processor import process_excel, update_fardo_data # Should be found
import pandas as pd
from io import BytesIO
from datetime import date

# Basic test setup that uses the app_context fixture from conftest.py
def test_process_excel_empty_file(app_context):
    # Create an empty Excel file in memory
    output = BytesIO()
    # writer = pd.ExcelWriter(output, engine='openpyxl') # Old way
    # writer.close() # For pandas < 1.3.0 use save(), for >= 1.3.0 use close()
    # Simpler way to create empty valid xlsx for testing missing columns
    pd.DataFrame().to_excel(output, index=False)
    output.seek(0)

    result = process_excel(output)
    assert not result['success']
    # Expecting missing columns because the DataFrame is empty
    assert "Columnas faltantes" in result['message']


def test_process_excel_valid_fardos(app_context):
    # Prepare data for a valid Excel file
    data = {
        'Numero de fardo': ['F001', 'F002'],
        'Tipo': ['2.5', '3.0'],
        'Peso neto': [220.5, 210.0],
        'Peso tara': [5.5, 5.0],
        'Fermentado': [False, True],
        'MIC': ['normal', 'alto'],
        'Color Predominante': ['normal', 'crema'],
        'Observaciones': ['Buena calidad', 'Un poco húmedo'],
        'Fecha': [date(2023, 1, 15), date(2023, 1, 16)],
        'Cliente': ['ClienteA', None],
        'Maquina': ['MURRAY', 'CONTINENTAL']
    }
    df = pd.DataFrame(data)

    output = BytesIO()
    df.to_excel(output, index=False, engine='openpyxl')
    # excel_bytes = output.getvalue() # Get bytes before seek(0) if you need to reuse stream content
    output.seek(0) # Reset stream position

    result = process_excel(output)

    assert result['success'], f"Processing failed: {result.get('message', 'No message')}"
    assert result['new_fardos'] == 2
    assert not result['duplicates']

    fardo1 = Fardo.query.filter_by(numero_fardo='F001').first()
    assert fardo1 is not None
    assert fardo1.tipo == '2.5'
    assert fardo1.mic == 'normal'
    assert fardo1.color_predominante == 'normal' # excel_processor uses .get('color_predominante', 'normal')
    assert fardo1.maquina == 'MURRAY'
    assert fardo1.fermentado == False

    fardo2 = Fardo.query.filter_by(numero_fardo='F002').first()
    assert fardo2 is not None
    assert fardo2.tipo == '3.0'
    assert fardo2.mic == 'alto'
    assert fardo2.color_predominante == 'crema'
    assert fardo2.maquina == 'CONTINENTAL'
    assert fardo2.fermentado == True
    assert fardo2.cliente is None

def test_process_excel_with_duplicates(app_context):
    initial_fardo = Fardo(
        numero_fardo='D001', tipo='4.0', peso_neto=200, peso_tara=5,
        fermentado=False, mic='bajo', color_predominante='normal', observaciones='Initial',
        fecha=date(2023, 2, 1), maquina='MURRAY'
    )
    db.session.add(initial_fardo)
    db.session.commit()

    data = {
        'Numero de fardo': ['D001', 'D002'],
        'Tipo': ['4.1', '4.5'],
        'Peso neto': [201.0, 205.0],
        'Peso tara': [5.1, 5.5],
        'Fermentado': [False, False],
        'MIC': ['bajo', 'normal'],
        'Color Predominante': ['normal', 'normal'],
        'Observaciones': ['Updated data for D001', 'New fardo D002'],
        'Fecha': [date(2023, 2, 2), date(2023, 2, 3)],
        'Maquina': ['MURRAY', 'CONTINENTAL']
    }
    df = pd.DataFrame(data)
    output = BytesIO()
    df.to_excel(output, index=False, engine='openpyxl')
    output.seek(0)

    result = process_excel(output)

    assert result['success']
    assert len(result['duplicates']) == 1
    assert result['duplicates'][0]['numero_fardo'] == 'D001'

    # excel_processor commits only if no duplicates are found.
    assert result['new_fardos'] == 0 # Because D001 was a duplicate, nothing committed.

    fardo_d001_db = Fardo.query.filter_by(numero_fardo='D001').first()
    assert fardo_d001_db.tipo == '4.0'

    fardo_d002_db = Fardo.query.filter_by(numero_fardo='D002').first()
    assert fardo_d002_db is None


def test_update_fardo_data(app_context):
    fardo_to_update = Fardo(
        numero_fardo='U001', tipo='5.0', peso_neto=210, peso_tara=6,
        fermentado=False, mic='normal', color_predominante='normal', observaciones='To be updated',
        fecha=date(2023, 3, 1), maquina='MURRAY'
    )
    db.session.add(fardo_to_update)
    db.session.commit()

    new_data_row = pd.Series({
        # excel_processor normalizes column names before calling update_fardo_data
        'tipo': '5.1', # Raw column names from DataFrame used as keys here
        'peso_neto': 215.0,
        'peso_tara': 6.5,
        'fermentado': 'yes',
        'mic': 'alto',
        'color_predominante': 'manchado',
        'observaciones': 'Successfully Updated',
        'fecha': date(2023, 3, 2),
        'cliente': 'ClienteB',
        'maquina': 'CONTINENTAL'
    })

    success, msg = update_fardo_data('U001', new_data_row)
    assert success, f"Update failed: {msg}"

    updated_fardo = Fardo.query.filter_by(numero_fardo='U001').first()
    assert updated_fardo is not None
    assert updated_fardo.tipo == '5.1'
    assert updated_fardo.peso_neto == 215.0
    assert updated_fardo.fermentado is True
    assert updated_fardo.mic == 'alto'
    assert updated_fardo.color_predominante == 'manchado'
    assert updated_fardo.observaciones == 'Successfully Updated'
    assert updated_fardo.fecha == date(2023, 3, 2)
    assert updated_fardo.cliente == 'ClienteB'
    assert updated_fardo.maquina == 'CONTINENTAL'
