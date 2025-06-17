import pytest
from app import create_app, db # This should now work if /app is in sys.path
import os
import sys

# Add the project root directory (/app) to sys.path
# so that 'from app import ...' works correctly from within tests.
# The conftest.py is at /app/cotton_base/tests/conftest.py
# The app directory is at /app/app/
# Project root is /app
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..')))


@pytest.fixture(scope='session')
def app():
    app_instance = create_app({
        'TESTING': True,
        'SQLALCHEMY_DATABASE_URI': 'sqlite:///:memory:',
        'SECRET_KEY': 'my-test-secret-key',
        'WTF_CSRF_ENABLED': False,
        'SQLALCHEMY_TRACK_MODIFICATIONS': False,
    })
    with app_instance.app_context():
        db.create_all()
    yield app_instance

@pytest.fixture
def client(app):
    return app.test_client()

@pytest.fixture
def runner(app):
    return app.test_cli_runner()

@pytest.fixture
def app_context(app):
    with app.app_context():
        yield
