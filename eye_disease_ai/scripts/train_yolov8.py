"""
train_yolov8.py — Entrenamiento YOLOv8 Classification Avanzado
================================================================
Entrena un modelo YOLOv8m-cls (Medium) optimizado para alta precisión
en detección de enfermedades oculares.

Características:
    - Modelo YOLOv8m-cls preentrenado (Mayor profundidad y precisión)
    - Early stopping inteligente
    - Data augmentation agresiva (MixUp, Erasing, AutoAugment)
    - Optimizado para NVIDIA RTX 5060 con CUDA (AMP)
    - Logs profesionales y guardado automático
    - Métricas completas por clase y matriz de confusión

Uso:
    python scripts/train_yolov8.py
"""

import os
import sys
import json
import shutil
import time
import argparse
from pathlib import Path
from datetime import datetime

import torch
import numpy as np

# ─── Configuración del proyecto ───────────────────────────────────────────────
PROJECT_ROOT = Path(__file__).resolve().parent.parent
DATASET_DIR  = PROJECT_ROOT / "dataset_split"
MODEL_DIR    = PROJECT_ROOT / "models" / "yolo"
RESULT_DIR   = PROJECT_ROOT / "results" / "yolo"
LOG_DIR      = PROJECT_ROOT / "logs"

for d in [MODEL_DIR, RESULT_DIR, LOG_DIR]:
    d.mkdir(parents=True, exist_ok=True)


# ─── Utilidades de GPU ────────────────────────────────────────────────────────
def get_gpu_info():
    """Detecta información de GPU y retorna configuración óptima."""
    info = {
        "cuda_available": torch.cuda.is_available(),
        "device": "cpu",
        "gpu_name": None,
        "gpu_memory_gb": 0,
        "recommended_batch": 16,
        "recommended_workers": 0,
    }

    if torch.cuda.is_available():
        info["device"] = "cuda:0"
        info["gpu_name"] = torch.cuda.get_device_name(0)
        info["gpu_memory_gb"] = torch.cuda.get_device_properties(0).total_memory / (1024**3)

        # Batch size para YOLOv8m-cls suele requerir un poco más de VRAM que el Nano
        vram = info["gpu_memory_gb"]
        if vram >= 16:
            info["recommended_batch"] = 32
            info["recommended_workers"] = 8
        elif vram >= 12:
            info["recommended_batch"] = 32
            info["recommended_workers"] = 6
        elif vram >= 8:
            info["recommended_batch"] = 16
            info["recommended_workers"] = 4
        elif vram >= 6:
            info["recommended_batch"] = 8
            info["recommended_workers"] = 2
        else:
            info["recommended_batch"] = 4
            info["recommended_workers"] = 2

        if os.name == "nt":
            info["recommended_workers"] = 0
    return info


def print_system_info(gpu_info: dict):
    print("\n" + "═" * 65)
    print("  🖥️  INFORMACIÓN DEL SISTEMA")
    print("═" * 65)
    print(f"  Python      : {sys.version.split()[0]}")
    print(f"  PyTorch     : {torch.__version__}")
    print(f"  CUDA dispo. : {'✅ Sí' if gpu_info['cuda_available'] else '❌ No'}")

    if gpu_info["cuda_available"]:
        print(f"  GPU         : {gpu_info['gpu_name']}")
        print(f"  VRAM        : {gpu_info['gpu_memory_gb']:.1f} GB")
        print(f"  Batch recom.: {gpu_info['recommended_batch']} (YOLOv8 Medium)")
        print(f"  Workers rec.: {gpu_info['recommended_workers']}")
        torch.backends.cudnn.benchmark = True
    print("═" * 65)


def verify_dataset() -> bool:
    print("\n" + "─" * 65)
    print("  📋 VERIFICANDO DATASET")
    print("─" * 65)
    all_ok = True
    total_images = 0

    for split in ["train", "val", "test"]:
        split_path = DATASET_DIR / split
        if not split_path.exists():
            print(f"  ❌ No existe: {split_path}")
            all_ok = False
            continue

        classes = sorted([d.name for d in split_path.iterdir() if d.is_dir()])
        count = sum(
            len([f for f in (split_path / c).rglob("*")
                 if f.suffix.lower() in {".jpg", ".jpeg", ".png", ".bmp", ".webp"}])
            for c in classes
        )
        total_images += count
        print(f"  ✅ {split:5s}: {len(classes)} clases, {count:>5} imágenes")

    if total_images == 0:
        all_ok = False
    return all_ok


# ─── Entrenamiento ────────────────────────────────────────────────────────────
def train_yolo(args, gpu_info: dict):
    from ultralytics import YOLO

    batch  = args.batch if args.batch else gpu_info["recommended_batch"]
    workers = args.workers if args.workers is not None else gpu_info["recommended_workers"]
    device = 0 if gpu_info["cuda_available"] else "cpu"

    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    run_name  = f"yolov8m_eye_cls_{timestamp}"

    print("\n" + "═" * 65)
    print("  🚀 ENTRENAMIENTO YOLOv8m — ALTA PRECISIÓN")
    print("═" * 65)
    print(f"  Modelo base    : {args.model}")
    print(f"  Épocas         : {args.epochs}")
    print(f"  Batch size     : {batch}")
    print(f"  Image size     : {args.imgsz}x{args.imgsz}")
    print(f"  Device         : {'GPU' if device == 0 else 'CPU'}")
    print("═" * 65)

    model = YOLO(args.model)

    t_start = time.time()

    # Optimizaciones avanzadas de Data Augmentation para evitar Overfitting
    # en clases minoritarias.
    results = model.train(
        data=str(DATASET_DIR),
        epochs=args.epochs,
        imgsz=args.imgsz,
        batch=batch,
        patience=args.patience,
        project=str(MODEL_DIR),
        name=run_name,
        exist_ok=True,
        pretrained=True,
        device=device,
        optimizer="AdamW",
        lr0=0.0005,        # Reducido de 0.001 para estabilizar el fine-tuning
        lrf=0.01,
        weight_decay=5e-4,
        warmup_epochs=5,
        label_smoothing=0.1, # Evita sobreconfianza sistemática (antes omitido en YOLO)
        deterministic=True,  # Fuerza determinismo para reproducibilidad entre ejecuciones
        
        # Data Augmentation optimizado para desbalance severo
        augment=True,
        hsv_h=0.015,
        hsv_s=0.5,
        hsv_v=0.4,
        flipud=0.3,
        fliplr=0.5,
        degrees=25.0,
        translate=0.2,
        scale=0.4,
        erasing=0.2,       
        mixup=0.05,        # Reducido de 0.2 para evitar difuminar la clase minoritaria (Desprendimiento)
        auto_augment="randaugment", 
 
        verbose=True,
        seed=args.seed,
        cos_lr=True,
        amp=True,
        workers=workers,
    )

    elapsed = time.time() - t_start
    print(f"\n  ⏱️  Tiempo total de entrenamiento: {elapsed/60:.1f} minutos")

    return model, results, run_name


# ─── Evaluación ───────────────────────────────────────────────────────────────
def evaluate_model(model, run_name: str):
    from sklearn.metrics import (
        classification_report, confusion_matrix, accuracy_score,
        precision_recall_fscore_support
    )
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
    import seaborn as sns

    print("\n" + "═" * 65)
    print("  📊 EVALUACIÓN EN TEST SET (YOLOv8m)")
    print("═" * 65)

    class_names = sorted([d.name for d in (DATASET_DIR / "train").iterdir() if d.is_dir()])
    test_dir = DATASET_DIR / "test"
    all_preds, all_labels = [], []
    total_time = 0

    for label_idx, cls_name in enumerate(class_names):
        cls_dir = test_dir / cls_name
        if not cls_dir.exists(): continue

        img_files = [f for f in cls_dir.rglob("*") if f.suffix.lower() in {".jpg", ".png", ".webp"}]

        for img_path in img_files:
            t0 = time.time()
            # Inferencia con augment_inference de YOLOv8
            pred = model.predict(str(img_path), verbose=False, augment=True)
            total_time += time.time() - t0

            pred_class = pred[0].probs.top1
            all_preds.append(pred_class)
            all_labels.append(label_idx)

    if not all_preds:
        return

    accuracy = accuracy_score(all_labels, all_preds)
    precision, recall, f1, support = precision_recall_fscore_support(
        all_labels, all_preds, average="weighted", zero_division=0
    )
    avg_inference_time = (total_time / len(all_preds)) * 1000

    report = classification_report(all_labels, all_preds, target_names=class_names, zero_division=0)

    print(f"\n{report}")
    print(f"  🎯 Accuracy global  : {accuracy:.4f} ({accuracy*100:.2f}%)")
    print(f"  📈 Precision (avg)  : {precision:.4f}")
    print(f"  📈 Recall (avg)     : {recall:.4f}")
    print(f"  📈 F1-score (avg)   : {f1:.4f}")
    print(f"  ⚡ Inferencia TTA   : {avg_inference_time:.1f} ms/imagen")

    report_path = RESULT_DIR / f"yolo_classification_report_{run_name}.txt"
    with open(report_path, "w", encoding="utf-8") as f:
        f.write(f"YOLOv8m Classification Report — {run_name}\n{'=' * 65}\n\n")
        f.write(report)
        f.write(f"\nAccuracy  : {accuracy:.4f}\nPrecision : {precision:.4f}\n")
        f.write(f"Recall    : {recall:.4f}\nF1-score  : {f1:.4f}\n")
    print(f"\n  📄 Reporte guardado: {report_path}")

    cm = confusion_matrix(all_labels, all_preds)
    fig, ax = plt.subplots(figsize=(10, 8))
    sns.heatmap(cm, annot=True, fmt="d", cmap="Greens", xticklabels=class_names, yticklabels=class_names,
                ax=ax, linewidths=0.5, linecolor="white", annot_kws={"size": 12, "weight": "bold"})
    ax.set_xlabel("Predicción", fontsize=12, fontweight="bold")
    ax.set_ylabel("Real", fontsize=12, fontweight="bold")
    ax.set_title(f"YOLOv8m — Matriz de Confusión (Test)\nAccuracy: {accuracy*100:.2f}%", fontsize=14, fontweight="bold")
    plt.xticks(rotation=30, ha="right"); plt.yticks(rotation=0)
    plt.tight_layout()
    cm_path = RESULT_DIR / f"yolo_confusion_matrix_{run_name}.png"
    plt.savefig(cm_path, dpi=200, bbox_inches="tight"); plt.close()
    print(f"  📊 Matriz guardada: {cm_path}")

    metrics = {
        "model": "YOLOv8m", "run_name": run_name, "timestamp": datetime.now().isoformat(),
        "accuracy": float(accuracy), "precision": float(precision), "recall": float(recall),
        "f1_score": float(f1), "avg_inference_ms": float(avg_inference_time),
        "total_test_images": len(all_preds), "class_names": class_names,
    }
    metrics_path = RESULT_DIR / f"yolo_metrics_{run_name}.json"
    with open(metrics_path, "w", encoding="utf-8") as f:
        json.dump(metrics, f, indent=2, ensure_ascii=False)


def export_best_model(run_name: str, seed: int):
    best_pt = MODEL_DIR / run_name / "weights" / "best.pt"
    dest    = MODEL_DIR / f"mejor_modelo_seed{seed}.pt"

    if best_pt.exists():
        shutil.copy2(best_pt, dest)
        print(f"\n  💾 Modelo exportado: {dest} ({dest.stat().st_size / (1024*1024):.1f} MB)")
    else:
        last_pt = MODEL_DIR / run_name / "weights" / "last.pt"
        if last_pt.exists():
            shutil.copy2(last_pt, dest)
            print(f"\n  💾 Usando last.pt como fallback: {dest}")


def parse_args():
    parser = argparse.ArgumentParser(description="Entrenar YOLOv8 Classification para enfermedades oculares")
    parser.add_argument("--model", type=str, default="yolov8m-cls.pt", help="Modelo base (default: yolov8m-cls.pt)")
    parser.add_argument("--epochs", type=int, default=150, help="Número de épocas (default: 150)")
    parser.add_argument("--batch", type=int, default=None, help="Batch size (auto)")
    parser.add_argument("--imgsz", type=int, default=224, help="Tamaño de imagen (default: 224)")
    parser.add_argument("--patience", type=int, default=30, help="Early stopping patience (default: 30)")
    parser.add_argument("--workers", type=int, default=None, help="Num workers dataloader")
    parser.add_argument("--no-eval", action="store_true", help="Omitir evaluación en test set")
    parser.add_argument("--seed", type=int, default=42, help="Semilla aleatoria (default: 42)")
    return parser.parse_args()


def main():
    args = parse_args()
    
    # Configurar semillas para reproducibilidad
    import random
    random.seed(args.seed)
    np.random.seed(args.seed)
    torch.manual_seed(args.seed)
    if torch.cuda.is_available():
        torch.cuda.manual_seed(args.seed)
        torch.cuda.manual_seed_all(args.seed)
        
    print("\n" + "█" * 65)
    print("  🔬 EYE DISEASE AI — YOLOv8m Classification Training")
    print(f"  ⚡ Semilla: {args.seed} | Modelo Medium | MixUp | AutoAugment")
    print("█" * 65)

    gpu_info = get_gpu_info()
    print_system_info(gpu_info)

    if not verify_dataset():
        sys.exit(1)

    model, results, run_name = train_yolo(args, gpu_info)
    export_best_model(run_name, args.seed)

    if not args.no_eval:
        evaluate_model(model, run_name)

    print("\n" + "█" * 65)
    print("  ✅ ENTRENAMIENTO YOLOv8m COMPLETADO")
    print("█" * 65 + "\n")


if __name__ == "__main__":
    main()
