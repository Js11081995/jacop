from flask import Flask
from flask_sqlalchemy import SQLAlchemy
from flask_migrate import Migrate
import os

db = SQLAlchemy()
migrate = Migrate()

def create_app(test_config=None): # MODIFIED: Added test_config parameter
    app = Flask(__name__, instance_relative_config=True)

    # Default configuration (values from original file)
    app.config.from_mapping(
        SECRET_KEY=os.environ.get('SECRET_KEY', 'dev_secret_key_0101'),
        SQLALCHEMY_DATABASE_URI=f"sqlite:///{os.path.join(app.instance_path, 'cotton_base.sqlite')}",
        SQLALCHEMY_TRACK_MODIFICATIONS=False
    )

    if test_config:
        # Load the test config if passed in, overriding defaults
        app.config.from_mapping(test_config)
    # else:
        # Optionally load instance config when not testing
        # app.config.from_pyfile('config.py', silent=True)

    # Ensure the instance folder exists
    try:
        os.makedirs(app.instance_path)
    except OSError:
        pass # Already exists or race condition

    db.init_app(app)
    migrate.init_app(app, db)

    # Import models here IF they are not picked up otherwise (e.g., by blueprint imports)
    # For db.create_all() to work, models must be imported somewhere.
    # The blueprints import models, which should be sufficient.
    # If issues arise with tests not finding tables, uncommenting this is a first check.
    # with app.app_context():
    #     from app.models import Fardo, Lote, TakeOutReport

    # Import and register blueprints
    from app.routes.fardo_routes import fardo_bp
    from app.routes.lote_routes import lote_bp
    from app.routes.report_routes import report_bp
    # Note: The sed commands for these imports might have left them unindented.
    # Python should handle it, but cleaner formatting would be ideal if doing manually.

    app.register_blueprint(fardo_bp, url_prefix='/cotton')
    app.register_blueprint(lote_bp, url_prefix='/cotton')
    app.register_blueprint(report_bp, url_prefix='/cotton')

    # Original hello route
    @app.route('/hello')
    def hello():
        return 'Hello, CottonBase!'

    return app
