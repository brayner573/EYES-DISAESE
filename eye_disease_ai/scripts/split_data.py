"""
split_data.py — División Estratificada del Dataset
====================================================
Toma imágenes de raw_data/ y las divide en train/val/test
con proporciones configurables y balanceo de clases.

Características:
    - Split estratificado (70/15/15 por defecto)
    - Verificación de integridad de imágenes
    - Reporte detallado de distribución
    - Reproducible con semilla fija
    - Copia segura (no mueve archivos originales)

Uso:
    python scripts/split_data.py
    python scripts/split_data.py --train 0.8 --val 0.1 --test 0.1

Autor: Eye Disease AI Project
"""

import os
import sys
import shutil
import random
import argparse
from pathlib import Path
from collections import defaultdict

from PIL import Image
from tqdm import tqdm

# ─── Configuración ────────────────────────────────────────────────────────────
PROJECT_ROOT = Path(__file__).resolve().parent.parent
RAW_DIR      = PROJECT_ROOT / "raw_data"
SPLIT_DIR    = PROJECT_ROOT / "dataset_split"

VALID_EXTENSIONS = {".jpg", ".jpeg", ".png", ".bmp", ".webp", ".tif", ".tiff"}
SEED = 42


def parse_args():
    """Parsea argumentos de línea de comandos."""
    parser = argparse.ArgumentParser(
        description="Divide el dataset raw_data/ en train/val/test"
    )
    parser.add_argument("--train", type=float, default=0.70,
                        help="Proporción para entrenamiento (default: 0.70)")
    parser.add_argument("--val", type=float, default=0.15,
                        help="Proporción para validación (default: 0.15)")
    parser.add_argument("--test", type=float, default=0.15,
                        help="Proporción para test (default: 0.15)")
    parser.add_argument("--seed", type=int, default=SEED,
                        help="Semilla para reproducibilidad (default: 42)")
    parser.add_argument("--clean", action="store_true",
                        help="Limpiar dataset_split/ antes de copiar")
    parser.add_argument("--verify", action="store_true", default=True,
                        help="Verificar integridad de imágenes (default: True)")
    return parser.parse_args()


def verify_image(img_path: Path) -> bool:
    """Verifica que una imagen sea válida y se pueda abrir."""
    try:
        with Image.open(img_path) as img:
            img.verify()
        return True
    except Exception:
        return False


def collect_images(class_dir: Path, do_verify: bool = True) -> list:
    """Recolecta todas las imágenes válidas de una carpeta de clase."""
    images = []
    corrupted = []

    for f in class_dir.rglob("*"):
        if f.suffix.lower() in VALID_EXTENSIONS:
            if do_verify:
                if verify_image(f):
                    images.append(f)
                else:
                    corrupted.append(f)
            else:
                images.append(f)

    if corrupted:
        print(f"    ⚠️  {len(corrupted)} imagen(es) corrupta(s) omitida(s)")
        for c in corrupted[:5]:
            print(f"       → {c.name}")
        if len(corrupted) > 5:
            print(f"       ... y {len(corrupted) - 5} más")

    return images


def split_files(files: list, train_ratio: float, val_ratio: float,
                seed: int = SEED) -> tuple:
    """Divide archivos en train/val/test de forma aleatoria."""
    random.seed(seed)
    shuffled = files.copy()
    random.shuffle(shuffled)

    n = len(shuffled)
    n_train = int(n * train_ratio)
    n_val   = int(n * val_ratio)

    train = shuffled[:n_train]
    val   = shuffled[n_train:n_train + n_val]
    test  = shuffled[n_train + n_val:]

    return train, val, test


def copy_files_to_split(files: list, dest_dir: Path, class_name: str,
                         split_name: str) -> int:
    """Copia archivos al directorio de destino con nombres únicos."""
    dest_dir.mkdir(parents=True, exist_ok=True)
    copied = 0

    for idx, src in enumerate(files):
        ext  = src.suffix.lower()
        name = f"{class_name}_{split_name}_{idx:05d}{ext}"
        dst  = dest_dir / name
        shutil.copy2(src, dst)
        copied += 1

    return copied


def main():
    args = parse_args()

    # Validar proporciones
    total_ratio = args.train + args.val + args.test
    if abs(total_ratio - 1.0) > 0.01:
        print(f"  ❌ Error: Las proporciones deben sumar 1.0 "
              f"(actual: {total_ratio:.2f})")
        sys.exit(1)

    print("\n" + "=" * 65)
    print("  📦 DIVISIÓN DEL DATASET — Eye Disease AI")
    print("=" * 65)
    print(f"\n  📂 Origen   : {RAW_DIR}")
    print(f"  📂 Destino  : {SPLIT_DIR}")
    print(f"  📊 Split    : train={args.train:.0%} / val={args.val:.0%} / test={args.test:.0%}")
    print(f"  🎲 Semilla  : {args.seed}")
    print(f"  🔍 Verificar: {'Sí' if args.verify else 'No'}")

    # Limpiar si se solicita
    if args.clean and SPLIT_DIR.exists():
        print(f"\n  🧹 Limpiando {SPLIT_DIR}...")
        shutil.rmtree(SPLIT_DIR)

    # Detectar clases automáticamente
    if not RAW_DIR.exists():
        print(f"\n  ❌ Error: No existe {RAW_DIR}")
        print(f"     Ejecuta primero: python scripts/setup_folders.py")
        sys.exit(1)

    classes = sorted([
        d.name for d in RAW_DIR.iterdir()
        if d.is_dir() and not d.name.startswith(".")
    ])

    if not classes:
        print(f"\n  ❌ Error: No se encontraron clases en {RAW_DIR}")
        print(f"     Coloca imágenes en carpetas dentro de raw_data/")
        sys.exit(1)

    print(f"\n  🎯 Clases detectadas ({len(classes)}): {', '.join(classes)}\n")

    # Procesar cada clase
    stats = {}
    total_images = 0

    for class_name in classes:
        class_dir = RAW_DIR / class_name
        print(f"  [{class_name.upper()}]")

        # Recolectar imágenes
        images = collect_images(class_dir, do_verify=args.verify)
        print(f"    Imágenes válidas: {len(images)}")

        if len(images) == 0:
            print(f"    ⚠️  Sin imágenes — omitiendo clase")
            continue

        # Dividir
        train, val, test = split_files(
            images, args.train, args.val, args.seed
        )

        # Copiar a destino
        for split_name, split_files_list in [
            ("train", train), ("val", val), ("test", test)
        ]:
            dest = SPLIT_DIR / split_name / class_name
            copy_files_to_split(split_files_list, dest, class_name, split_name)

        stats[class_name] = {
            "total": len(images),
            "train": len(train),
            "val":   len(val),
            "test":  len(test),
        }
        total_images += len(images)

        print(f"    → train: {len(train)} | val: {len(val)} | test: {len(test)}")

    # ─── Resumen ──────────────────────────────────────────────────────────────
    print(f"\n{'═' * 65}")
    print("  📊 RESUMEN FINAL DEL DATASET")
    print(f"{'═' * 65}")
    print(f"\n  {'Clase':<22} {'Total':>7} {'Train':>7} {'Val':>7} {'Test':>7}")
    print(f"  {'─' * 55}")

    for cls, s in stats.items():
        print(f"  {cls:<22} {s['total']:>7} {s['train']:>7} "
              f"{s['val']:>7} {s['test']:>7}")

    print(f"  {'─' * 55}")
    print(f"  {'TOTAL':<22} {total_images:>7}")
    print(f"\n  ✅ Dataset listo en: {SPLIT_DIR}")

    # Verificar balance
    if stats:
        counts = [s["total"] for s in stats.values()]
        ratio = max(counts) / max(min(counts), 1)
        if ratio > 3.0:
            print(f"\n  ⚠️  ADVERTENCIA: Desbalance significativo detectado")
            print(f"     Ratio max/min: {ratio:.1f}x")
            print(f"     Considera usar data augmentation o class weights")

    print(f"\n  📌 Siguiente paso:")
    print(f"     python scripts/train_yolov8.py")
    print(f"     python scripts/train_resnet.py")
    print("=" * 65 + "\n")


if __name__ == "__main__":
    main()
