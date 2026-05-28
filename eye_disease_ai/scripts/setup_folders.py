"""
setup_folders.py — Creador de Estructura del Proyecto
=====================================================
Genera todas las carpetas necesarias para el proyecto de detección
de enfermedades oculares con YOLOv8 y ResNet50.

Uso:
    python scripts/setup_folders.py

Autor: Eye Disease AI Project
"""

import os
from pathlib import Path

# ─── Configuración ────────────────────────────────────────────────────────────
PROJECT_ROOT = Path(__file__).resolve().parent.parent

# Clases del dataset
CLASSES = [
    "cataract",
    "diabetic_retinopathy",
    "glaucoma",
    "normal",
    "retina_disease",
]

# Splits del dataset
SPLITS = ["train", "val", "test"]

# ─── Estructura completa del proyecto ─────────────────────────────────────────
DIRECTORIES = [
    # raw_data: imágenes originales sin procesar
    *[f"raw_data/{cls}" for cls in CLASSES],

    # dataset_split: datos organizados por split
    *[f"dataset_split/{split}/{cls}" for split in SPLITS for cls in CLASSES],

    # Modelos entrenados
    "models/yolo",
    "models/resnet",

    # Resultados de entrenamiento y evaluación
    "results/yolo",
    "results/resnet",

    # Notebooks de análisis
    "notebooks",

    # Scripts (ya existe, pero por si acaso)
    "scripts",

    # Configuraciones
    "configs",

    # Logs de entrenamiento
    "logs",
]


def create_structure():
    """Crea toda la estructura de carpetas del proyecto."""
    print("=" * 65)
    print("  🏗️  CREANDO ESTRUCTURA DEL PROYECTO — Eye Disease AI")
    print("=" * 65)
    print(f"\n  📂 Raíz del proyecto: {PROJECT_ROOT}\n")

    created = 0
    existed = 0

    for rel_path in DIRECTORIES:
        full_path = PROJECT_ROOT / rel_path
        if full_path.exists():
            existed += 1
            print(f"  ✓ Ya existe   : {rel_path}")
        else:
            full_path.mkdir(parents=True, exist_ok=True)
            created += 1
            print(f"  ✚ Creada      : {rel_path}")

    # Crear archivos .gitkeep en carpetas vacías (para Git)
    gitkeep_dirs = [
        "raw_data", "models/yolo", "models/resnet",
        "results/yolo", "results/resnet", "notebooks", "logs",
    ]
    for d in gitkeep_dirs:
        gitkeep = PROJECT_ROOT / d / ".gitkeep"
        if not gitkeep.exists():
            gitkeep.touch()

    print(f"\n{'─' * 65}")
    print(f"  📊 Resumen:")
    print(f"     Carpetas creadas  : {created}")
    print(f"     Carpetas existían : {existed}")
    print(f"     Total             : {created + existed}")
    print(f"\n  🎯 Clases configuradas ({len(CLASSES)}):")
    for cls in CLASSES:
        print(f"       • {cls}")
    print(f"\n  📌 Siguiente paso:")
    print(f"     1. Coloca tus imágenes en raw_data/<clase>/")
    print(f"     2. Ejecuta: python scripts/split_data.py")
    print("=" * 65)


if __name__ == "__main__":
    create_structure()
