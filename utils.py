"""
Utilitaires d'aide à l'ingestion : journalisation, détection d'encodage, nettoyage de colonnes.
"""

import logging
import re
import unicodedata
from pathlib import Path
import pandas as pd

def setup_logger(name: str = "ingestion") -> logging.Logger:
    """Configure un logger lisible pour la console et les fichiers."""
    logger = logging.getLogger(name)
    logger.setLevel(logging.INFO)
    if not logger.handlers:
        ch = logging.StreamHandler()
        ch.setLevel(logging.INFO)
        formatter = logging.Formatter(
            "[%(asctime)s] [%(levelname)s] %(message)s", datefmt="%Y-%m-%d %H:%M:%S"
        )
        ch.setFormatter(formatter)
        logger.addHandler(ch)
    return logger

def normalize_column_name(col_name: str) -> str:
    """
    Normalise le nom de colonne SQL :
    - suppression des espaces superflus aux extrémités
    - passage en minuscules
    - remplacement des caractères accentués
    - remplacement des caractères non alphanumériques par '_'
    """
    col = col_name.strip()
    # Supprimer les accents
    col = unicodedata.normalize('NFKD', col).encode('ASCII', 'ignore').decode('utf-8')
    # Passer en minuscule
    col = col.lower()
    # Remplacer les caractères non alphanumériques par '_'
    col = re.sub(r'[^a-z0-9]+', '_', col)
    # Enlever '_' en début ou fin
    col = col.strip('_')
    return col

def detect_file_encoding(file_path: Path, candidate_encodings: list[str]) -> str:
    """Tente de lire les premières lignes du fichier avec différents encodages."""
    for enc in candidate_encodings:
        try:
            with open(file_path, "r", encoding=enc) as f:
                f.readline()
                f.readline()
            return enc
        except (UnicodeDecodeError, Exception):
            continue
    return "utf-8"
