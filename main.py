from pathlib import Path
import pandas as pd
import logging
import shutil
import os
import os
from dotenv import load_dotenv

load_dotenv()



from sqlalchemy import create_engine, text
from utils import (
    normalize_column_name,
    detect_file_encoding
)



# ============================================================
# CONFIGURATION
# ============================================================

DATA_FOLDER = Path("data/incoming")
PROCESSED_FOLDER = Path("data/processed")
REJECTED_FOLDER = Path("data/rejected")

RAW_TABLE_NAME = "raw_dessertes"
TABLE_NAME = "dessertes"
HISTORY_TABLE_NAME = "import_history"


# ============================================================
# BASES DE DONNÉES
# ============================================================

# PostgreSQL
POSTGRES_URL = os.getenv(
    "POSTGRES_URL",
    "postgresql+psycopg2://postgres:postgres@localhost:5432/kawa2"
)


# MySQL
MYSQL_URL = os.getenv(
    "MYSQL_URL",
    "mysql+pymysql://root:root@localhost:3306/kawa2"
)



# ============================================================
# ACTIVATION DES BASES
# ============================================================

USE_POSTGRES = os.getenv(
    "USE_POSTGRES",
    "true"
).lower() == "true"

USE_MYSQL = os.getenv(
    "USE_MYSQL",
    "true"
).lower() == "true"


# ============================================================
# CREATION DES ENGINES
# ============================================================

engines = {}


if USE_POSTGRES:

    engines["postgresql"] = create_engine(
        POSTGRES_URL,
        pool_pre_ping=True,
        pool_recycle=3600
    )


if USE_MYSQL:

    engines["mysql"] = create_engine(
        MYSQL_URL,
        pool_pre_ping=True,
        pool_recycle=3600
    )


# ============================================================
# LOGGING
# ============================================================

Path("logs").mkdir(
    exist_ok=True
)

logging.basicConfig(
    filename="logs/loader.log",
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(message)s"
)

logger = logging.getLogger(__name__)


# ============================================================
# CREATION DES TABLES
# ============================================================

def create_tables(db_name, engine):

    logger.info(
        f"Création/vérification des tables pour {db_name}"
    )

    if db_name == "postgresql":

        sql = """

        CREATE SCHEMA IF NOT EXISTS analytics;

        CREATE TABLE IF NOT EXISTS analytics.import_history
        (
            id SERIAL PRIMARY KEY,

            filename VARCHAR(255) UNIQUE NOT NULL,

            rows_imported INTEGER,

            imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );


        CREATE TABLE IF NOT EXISTS public.dessertes
        (
            id SERIAL PRIMARY KEY,

            numero_desserte VARCHAR(30) NOT NULL,

            numero_tournee VARCHAR(50),

            centre VARCHAR(100),

            client VARCHAR(150),

            type_desserte VARCHAR(100),

            site_expedition VARCHAR(200),

            date_expedition DATE,

            heure_expedition TIME,

            kilometrage_depart INTEGER,

            site_destination VARCHAR(200),

            date_arrivee DATE,

            heure_arrivee TIME,

            kilometrage_arrivee INTEGER,

            site_facturer VARCHAR(200),

            numero_colis TEXT,

            montant_colis NUMERIC(18,2),

            numero_sous_colis VARCHAR(50),

            signataire_ramassage VARCHAR(150),

            signataire_livraison VARCHAR(150),

            etat_desserte VARCHAR(50),

            date_import TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            CONSTRAINT uq_dessertes_numero
                UNIQUE (numero_desserte)
        );

        """

    elif db_name == "mysql":

        sql = """

        CREATE TABLE IF NOT EXISTS import_history
        (
            id INT AUTO_INCREMENT PRIMARY KEY,

            filename VARCHAR(255) NOT NULL UNIQUE,

            rows_imported INT,

            imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );


        CREATE TABLE IF NOT EXISTS dessertes
        (
            id INT AUTO_INCREMENT PRIMARY KEY,

            numero_desserte VARCHAR(30) NOT NULL,

            numero_tournee VARCHAR(50),

            centre VARCHAR(100),

            client VARCHAR(150),

            type_desserte VARCHAR(100),

            site_expedition VARCHAR(200),

            date_expedition DATE,

            heure_expedition TIME,

            kilometrage_depart INT,

            site_destination VARCHAR(200),

            date_arrivee DATE,

            heure_arrivee TIME,

            kilometrage_arrivee INT,

            site_facturer VARCHAR(200),

            numero_colis TEXT,

            montant_colis DECIMAL(18,2),

            numero_sous_colis VARCHAR(50),

            signataire_ramassage VARCHAR(150),

            signataire_livraison VARCHAR(150),

            etat_desserte VARCHAR(50),

            date_import TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY uq_dessertes_numero
                (numero_desserte)
        );

        """

    else:

        raise ValueError(
            f"Base de données non supportée : {db_name}"
        )


    with engine.begin() as conn:

        # Exécuter les commandes séparément
        for statement in sql.split(";"):

            statement = statement.strip()

            if statement:

                conn.execute(
                    text(statement)
                )


# ============================================================
# VERIFICATION FICHIER
# ============================================================

def file_already_loaded(
    db_name,
    engine,
    filename
):

    if db_name == "postgresql":

        history_table = "analytics.import_history"

    else:

        history_table = "import_history"


    with engine.begin() as conn:

        result = conn.execute(
            text(f"""
                SELECT 1
                FROM {history_table}
                WHERE filename = :filename
                LIMIT 1
            """),
            {
                "filename": filename
            }
        )

        return result.fetchone() is not None


# ============================================================
# HISTORIQUE
# ============================================================

def register_file(
    db_name,
    engine,
    filename,
    rows
):

    if db_name == "postgresql":

        history_table = "analytics.import_history"

    else:

        history_table = "import_history"


    with engine.begin() as conn:

        conn.execute(
            text(f"""
                INSERT INTO {history_table}
                (
                    filename,
                    rows_imported
                )
                VALUES
                (
                    :filename,
                    :rows
                )
            """),
            {
                "filename": filename,
                "rows": rows
            }
        )


# ============================================================
# PREPARATION DES DONNEES
# ============================================================

def prepare_dataframe(df, filename):

    # Normalisation des colonnes

    column_mapping = {
        col: normalize_column_name(col)
        for col in df.columns
    }

    df = df.rename(
        columns=column_mapping
    )


    # Métadonnées techniques

    df["source_file"] = filename

    df["import_date"] = pd.Timestamp.now()


    return df


# ============================================================
# INSERTION FINAL POSTGRESQL
# ============================================================

def insert_postgresql(
    engine,
    raw_table,
    table_name
):

    sql = f"""

        INSERT INTO {table_name}
        (
            numero_desserte,
            numero_tournee,
            centre,
            client,
            type_desserte,
            site_expedition,
            date_expedition,
            heure_expedition,
            kilometrage_depart,
            site_destination,
            date_arrivee,
            heure_arrivee,
            kilometrage_arrivee,
            site_facturer,
            numero_colis,
            montant_colis,
            numero_sous_colis,
            signataire_ramassage,
            signataire_livraison,
            etat_desserte
        )

        SELECT

            numero_desserte,

            numero_tournee,

            centre,

            client,

            type_desserte,

            site_expedition,

            NULLIF(date_d_expedition, '')::DATE,

            NULLIF(heure_d_expedition, '')::TIME,

            NULLIF(kilometrage_depart, '')::INTEGER,

            site_destination,

            NULLIF(date_arrivee, '')::DATE,

            NULLIF(heure_arrivee, '')::TIME,

            NULLIF(kilometrage_arrivee, '')::INTEGER,

            site_a_facturer,

            numero_colis,

            NULLIF(montant_colis, '')::NUMERIC,

            numero_sous_colis,

            signataire_ramassage,

            signataire_livraison,

            etat_desserte

        FROM {raw_table}

        WHERE numero_tournee IS NOT NULL

        ON CONFLICT (numero_desserte)

        DO NOTHING

    """


    with engine.begin() as conn:

        result = conn.execute(
            text(sql)
        )

        return result.rowcount


# ============================================================
# INSERTION FINAL MYSQL
# ============================================================

def insert_mysql(
    engine,
    raw_table,
    table_name
):

    sql = f"""

        INSERT INTO {table_name}
        (
            numero_desserte,
            numero_tournee,
            centre,
            client,
            type_desserte,
            site_expedition,
            date_expedition,
            heure_expedition,
            kilometrage_depart,
            site_destination,
            date_arrivee,
            heure_arrivee,
            kilometrage_arrivee,
            site_facturer,
            numero_colis,
            montant_colis,
            numero_sous_colis,
            signataire_ramassage,
            signataire_livraison,
            etat_desserte
        )

        SELECT

            numero_desserte,

            numero_tournee,

            centre,

            client,

            type_desserte,

            site_expedition,

            NULLIF(date_d_expedition, ''),

            NULLIF(heure_d_expedition, ''),

            NULLIF(kilometrage_depart, 0),

            site_destination,

            NULLIF(date_arrivee, ''),

            NULLIF(heure_arrivee, ''),

            NULLIF(kilometrage_arrivee, 0),

            site_a_facturer,

            numero_colis,

            NULLIF(montant_colis, ''),

            numero_sous_colis,

            signataire_ramassage,

            signataire_livraison,

            etat_desserte

        FROM {raw_table}

        WHERE numero_tournee IS NOT NULL

        ON DUPLICATE KEY UPDATE

            numero_tournee = VALUES(numero_tournee),

            centre = VALUES(centre),

            client = VALUES(client),

            etat_desserte = VALUES(etat_desserte)

    """


    with engine.begin() as conn:

        result = conn.execute(
            text(sql)
        )

        return result.rowcount


# ============================================================
# IMPORT DANS UNE BASE
# ============================================================

def load_to_database(
    db_name,
    engine,
    df,
    filename
):

    logger.info(
        f"[{db_name}] Début chargement {filename}"
    )


    # Vérification historique

    if file_already_loaded(
        db_name,
        engine,
        filename
    ):

        logger.warning(
            f"[{db_name}] {filename} déjà importé"
        )

        return 0


    # --------------------------------------------------------
    # RAW
    # --------------------------------------------------------

    df.to_sql(
        RAW_TABLE_NAME,
        engine,
        if_exists="append",
        index=False,
        chunksize=5000,
        method="multi"
    )


    logger.info(
        f"[{db_name}] RAW chargée : {len(df)} lignes"
    )


    # --------------------------------------------------------
    # TABLE FINALE
    # --------------------------------------------------------

    if db_name == "postgresql":

        rows_inserted = insert_postgresql(
            engine,
            RAW_TABLE_NAME,
            TABLE_NAME
        )

    elif db_name == "mysql":

        rows_inserted = insert_mysql(
            engine,
            RAW_TABLE_NAME,
            TABLE_NAME
        )

    else:

        raise ValueError(
            f"Base inconnue : {db_name}"
        )


    # --------------------------------------------------------
    # HISTORIQUE
    # --------------------------------------------------------

    register_file(
        db_name,
        engine,
        filename,
        len(df)
    )


    logger.info(
        f"[{db_name}] {filename} terminé : "
        f"{len(df)} lignes RAW / "
        f"{rows_inserted} lignes finales"
    )


    return rows_inserted


# ============================================================
# IMPORT FICHIER
# ============================================================

def load_file(file):

    filename = file.name

    logger.info(
        f"================================================"
    )

    logger.info(
        f"Début traitement : {filename}"
    )


    try:

        # ----------------------------------------------------
        # ENCODAGE
        # ----------------------------------------------------

        try:

            encoding = detect_file_encoding(file)

        except Exception:

            encoding = "utf-8"


        logger.info(
            f"Encodage détecté : {encoding}"
        )


        # ----------------------------------------------------
        # LECTURE CSV
        # ----------------------------------------------------

        df = pd.read_csv(
            file,
            sep=";",
            dtype=str,
            encoding=encoding
        )


        logger.info(
            f"{filename} : {len(df)} lignes"
        )


        # ----------------------------------------------------
        # PREPARATION
        # ----------------------------------------------------

        df = prepare_dataframe(
            df,
            filename
        )


        # ----------------------------------------------------
        # CHARGEMENT DANS LES BASES
        # ----------------------------------------------------

        successful_databases = []

        for db_name, engine in engines.items():

            try:

                load_to_database(
                    db_name,
                    engine,
                    df,
                    filename
                )

                successful_databases.append(
                    db_name
                )

            except Exception:

                logger.exception(
                    f"[{db_name}] "
                    f"Erreur pendant import {filename}"
                )

                raise


        # ----------------------------------------------------
        # ARCHIVE
        # ----------------------------------------------------

        PROCESSED_FOLDER.mkdir(
            exist_ok=True
        )


        shutil.copy2(
            file,
            PROCESSED_FOLDER / filename
        )


        logger.info(
            f"{filename} archivé"
        )


        logger.info(
            f"Bases chargées : "
            f"{', '.join(successful_databases)}"
        )


    except Exception:

        logger.exception(
            f"Erreur globale import {filename}"
        )


        REJECTED_FOLDER.mkdir(
            exist_ok=True
        )


        shutil.copy2(
            file,
            REJECTED_FOLDER / filename
        )


        logger.error(
            f"{filename} déplacé vers rejected"
        )


# ============================================================
# MAIN
# ============================================================

def main():

    logger.info(
        "==============================================="
    )

    logger.info(
        "DEMARRAGE DU LOADER KAWA2"
    )

    logger.info(
        f"Bases actives : "
        f"{', '.join(engines.keys())}"
    )


    # --------------------------------------------------------
    # CREATION DES TABLES
    # --------------------------------------------------------

    for db_name, engine in engines.items():

        try:

            create_tables(
                db_name,
                engine
            )

        except Exception:

            logger.exception(
                f"Impossible d'initialiser {db_name}"
            )

            raise


    # --------------------------------------------------------
    # RECHERCHE CSV
    # --------------------------------------------------------

    files = list(
        DATA_FOLDER.glob("*.csv")
    )


    logger.info(
        f"{len(files)} fichier(s) trouvé(s)"
    )


    # --------------------------------------------------------
    # TRAITEMENT
    # --------------------------------------------------------

    for file in files:

        load_file(file)


    logger.info(
        "FIN DU LOADER KAWA2"
    )


# ============================================================
# ENTRY POINT
# ============================================================

if __name__ == "__main__":

    main()