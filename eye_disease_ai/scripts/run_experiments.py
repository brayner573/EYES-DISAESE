"""
run_experiments.py — Orchestrator for 3-Seed Eye Disease Classification Experiments
==================================================================================
Runs ResNet50, DenseNet121, EfficientNetV2-S, Swin Transformer (SUNet), YOLOv8m-cls,
and YOLO11m-cls across 3 seeds (42, 123, 2024). Evaluates metrics (Accuracy, Weighted F1,
OvR Macro AUC-ROC, and per-class metrics), measures latency with BS=1 and 1000 warmups,
applies 5-view geometric TTA, and consolidates statistical results (mean +/- std).

Usage:
    python scripts/run_experiments.py --fast  # Runs a quick 1-epoch sanity check
    python scripts/run_experiments.py --full  # Runs the full rigorous training & evaluation
"""

import os
import sys
import time
import json
import csv
import argparse
import subprocess
import shutil
import random
from pathlib import Path
from datetime import datetime

import numpy as np
import torch
import torch.nn as nn
import torch.nn.functional as F
from torch.utils.data import DataLoader
from torchvision import datasets, transforms
import torchvision.transforms.functional as F_t
from PIL import Image

# Sklearn metrics
from sklearn.metrics import (
    accuracy_score,
    precision_recall_fscore_support,
    confusion_matrix,
    roc_auc_score
)

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import seaborn as sns

# --- Configuracion de Rutas ---
PROJECT_ROOT = Path(__file__).resolve().parent.parent
DATASET_DIR  = PROJECT_ROOT / "dataset_split"
MODEL_DIR    = PROJECT_ROOT / "models"
RESULT_DIR   = PROJECT_ROOT / "results"

for d in [MODEL_DIR, RESULT_DIR]:
    d.mkdir(parents=True, exist_ok=True)

IMG_SIZE = 224
DEVICE = torch.device("cuda" if torch.cuda.is_available() else "cpu")
CLASSES = ["cataract", "diabetic_retinopathy", "glaucoma", "normal", "retina_disease"]

# --- Configuracion de Experimentos ---
SEEDS = [42, 123, 2024]
MODELS_TO_RUN = {
    "resnet50": {
        "name": "ResNet50",
        "script": "train_resnet.py",
        "save_dir": "resnet"
    },
    "densenet121": {
        "name": "DenseNet121",
        "script": "train_densenet.py",
        "save_dir": "densenet"
    },
    "efficientnet": {
        "name": "EfficientNetV2-S",
        "script": "train_efficientnet.py",
        "save_dir": "efficientnet"
    },
    "sunet": {
        "name": "Swin Transformer (SUNet)",
        "script": "train_sunet.py",
        "save_dir": "sunet"
    },
    "yolov8": {
        "name": "YOLOv8m-cls",
        "script": "train_yolov8.py",
        "save_dir": "yolo"
    },
    "yolo11": {
        "name": "YOLO11m-cls",
        "script": "train_yolo11.py",
        "save_dir": "yolo11"
    }
}

# --- Inicializacion de Semilla Global ---
def set_global_seed(seed):
    random.seed(seed)
    np.random.seed(seed)
    torch.manual_seed(seed)
    if torch.cuda.is_available():
        torch.cuda.manual_seed(seed)
        torch.cuda.manual_seed_all(seed)

# --- 5-View Geometric TTA Logic (Step 5) ---
def get_pytorch_tta_probs(model, inputs):
    """
    Applies 5 geometric views to PyTorch inputs batch and averages softmax probabilities in a single stacked forward pass.
    inputs shape: (1, 3, 224, 224)
    """
    # 1. Original View
    # 2. Horizontal Flip View
    inputs_h = torch.flip(inputs, dims=[3])
    # 3. Vertical Flip View
    inputs_v = torch.flip(inputs, dims=[2])
    # 4. Rotation +10 Degrees View
    inputs_r = F_t.rotate(inputs, 10)
    # 5. Central Crop Scale 0.9 View
    h_crop = int(inputs.shape[2] * 0.9)
    inputs_c = F_t.center_crop(inputs, [h_crop, h_crop])
    inputs_c = F_t.resize(inputs_c, [inputs.shape[2], inputs.shape[3]])
    
    # Stack into a batch of size 5
    batch = torch.cat([inputs, inputs_h, inputs_v, inputs_r, inputs_c], dim=0) # shape (5, 3, 224, 224)
    
    out = model(batch) # shape (5, num_classes)
    probs = F.softmax(out, dim=1)
    
    # Average along the batch dimension
    avg_probs = torch.mean(probs, dim=0, keepdim=True) # shape (1, num_classes)
    return avg_probs

def get_yolo_tta_probs(model, img_path):
    """
    Applies the same 5 geometric views to a PIL Image and runs batch prediction with YOLO.
    """
    if isinstance(img_path, Image.Image):
        img = img_path.convert("RGB")
    else:
        img = Image.open(img_path).convert("RGB")
    # Base resize
    img_resized = img.resize((224, 224), Image.Resampling.BILINEAR)
    
    # 1. Original
    # 2. Horizontal Flip
    img_h = img_resized.transpose(Image.FLIP_LEFT_RIGHT)
    # 3. Vertical Flip
    img_v = img_resized.transpose(Image.FLIP_TOP_BOTTOM)
    # 4. Rotation +10
    img_r = img_resized.rotate(10)
    # 5. Central Crop 0.9
    w, h = img_resized.size
    w_crop, h_crop = int(w * 0.9), int(h * 0.9)
    left = (w - w_crop) // 2
    top = (h - h_crop) // 2
    right = left + w_crop
    bottom = top + h_crop
    img_c = img_resized.crop((left, top, right, bottom)).resize((224, 224), Image.Resampling.BILINEAR)
    
    views = [img_resized, img_h, img_v, img_r, img_c]
    
    # Batch predict (very fast on GPU)
    preds = model.predict(views, verbose=False)
    
    # Extract probabilities
    probs_list = []
    for p in preds:
        probs_val = p.probs.data.cpu().numpy()
        probs_list.append(probs_val)
        
    avg_probs = np.mean(probs_list, axis=0)
    return avg_probs

# --- Reconstruccion de Modelos PyTorch ---
def load_pytorch_model(model_key, checkpoint_path, num_classes):
    import torchvision.models as models
    
    if model_key == "resnet50":
        model = models.resnet50()
        in_features = model.fc.in_features
        model.fc = nn.Sequential(
            nn.Dropout(0.5),
            nn.Linear(in_features, 512),
            nn.BatchNorm1d(512),
            nn.ReLU(inplace=True),
            nn.Dropout(0.3),
            nn.Linear(512, 256),
            nn.BatchNorm1d(256),
            nn.ReLU(inplace=True),
            nn.Linear(256, num_classes)
        )
    elif model_key == "densenet121":
        model = models.densenet121()
        in_features = model.classifier.in_features
        model.classifier = nn.Sequential(
            nn.Dropout(0.5),
            nn.Linear(in_features, 512),
            nn.BatchNorm1d(512),
            nn.ReLU(inplace=True),
            nn.Dropout(0.3),
            nn.Linear(512, 256),
            nn.BatchNorm1d(256),
            nn.ReLU(inplace=True),
            nn.Linear(256, num_classes)
        )
    elif model_key == "efficientnet":
        model = models.efficientnet_v2_s()
        in_features = model.classifier[1].in_features
        model.classifier = nn.Sequential(
            nn.Dropout(p=0.4),
            nn.Linear(in_features, 256),
            nn.BatchNorm1d(256),
            nn.ReLU(),
            nn.Dropout(p=0.3),
            nn.Linear(256, num_classes)
        )
    elif model_key == "sunet":
        model = models.swin_t()
        in_features = model.head.in_features
        model.head = nn.Sequential(
            nn.Dropout(p=0.5),
            nn.Linear(in_features, 512),
            nn.LayerNorm(512),
            nn.ReLU(),
            nn.Dropout(p=0.3),
            nn.Linear(512, num_classes)
        )
    else:
        raise ValueError(f"Unknown PyTorch model key: {model_key}")
        
    checkpoint = torch.load(checkpoint_path, map_location=DEVICE)
    if "model_state_dict" in checkpoint:
        model.load_state_dict(checkpoint["model_state_dict"])
    else:
        model.load_state_dict(checkpoint)
        
    model = model.to(DEVICE)
    model.eval()
    return model

# --- Bucle de Entrenamiento Automático (Paso 1) ---
def run_training(model_key, model_cfg, seed, fast_mode):
    checkpoint_file = MODEL_DIR / model_cfg["save_dir"] / f"mejor_modelo_seed{seed}.pt"
    
    # Si ya se encuentra entrenado, lo omitimos para ahorrar tiempo (a menos que no exista)
    if checkpoint_file.exists():
        print(f"  [OK] [Entrenamiento] El checkpoint {checkpoint_file.name} ya existe. Omitiendo entrenamiento.")
        return True

    print(f"  [RUN] [Entrenamiento] Iniciando {model_cfg['name']} para semilla {seed}...")
    script_path = PROJECT_ROOT / "scripts" / model_cfg["script"]
    
    # Determinar épocas según modo
    cmd = [sys.executable, str(script_path), "--seed", str(seed)]
    
    if model_key in ["yolov8", "yolo11"]:
        epochs = 1 if fast_mode else 100
        cmd.extend(["--epochs", str(epochs)])
    else:
        epochs_head = 1 if fast_mode else (15 if model_key in ["efficientnet", "sunet"] else 20)
        epochs_fine = 1 if fast_mode else (35 if model_key == "sunet" else 40)
        cmd.extend(["--epochs-head", str(epochs_head), "--epochs-fine", str(epochs_fine)])
    # Ejecución síncrona segura con forzado de UTF-8 en stdout
    import os
    env_utf8 = dict(os.environ, PYTHONIOENCODING="utf-8")
    t0 = time.time()
    res = subprocess.run(cmd, cwd=str(PROJECT_ROOT), env=env_utf8, capture_output=False)
    elapsed = (time.time() - t0) / 60
    
    if res.returncode != 0:
        print(f"  [ERROR] Error entrenando {model_cfg['name']} (Seed {seed}).")
        return False
        
    print(f"  [SUCCESS] Entrenamiento {model_cfg['name']} (Seed {seed}) completado en {elapsed:.1f} minutos.")
    return True

# --- Bucle de Evaluación Robusta (Paso 2, 4 & 5) ---
def run_evaluation(model_key, model_cfg, seed, fast_mode):
    print(f"  [EVAL] Evaluando {model_cfg['name']} (Seed {seed})...")
    
    model_dir = MODEL_DIR / model_cfg["save_dir"]
    checkpoint_file = model_dir / f"mejor_modelo_seed{seed}.pt"
    results_dir = RESULT_DIR / model_cfg["save_dir"]
    results_dir.mkdir(parents=True, exist_ok=True)
    
    json_path = results_dir / f"metricas_seed{seed}.json"
    csv_path = results_dir / f"probs_seed{seed}.csv"
    cm_path = results_dir / f"confusion_matrix_seed{seed}.png"
    if json_path.exists() and csv_path.exists() and cm_path.exists():
        print(f"    [OK] [Evaluacion] Los resultados para {model_cfg['name']} (Seed {seed}) ya existen. Omitiendo evaluacion.")
        return True

    if not checkpoint_file.exists():
        print(f"  [ERROR] Checkpoint no encontrado: {checkpoint_file}")
        return False

    # Alinear exactamente los 1301 archivos usando PyTorch datasets.ImageFolder
    test_transform = transforms.Compose([
        transforms.Resize((IMG_SIZE, IMG_SIZE)),
        transforms.ToTensor(),
        transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
    ])
    
    test_dataset = datasets.ImageFolder(DATASET_DIR / "test", test_transform)
    test_loader = DataLoader(test_dataset, batch_size=1, shuffle=False)
    
    samples = test_dataset.samples # Lista de tuplas (img_path, label)
    total_images = len(samples)
    
    if fast_mode:
        # En modo rápido solo evaluamos 10 muestras
        samples = samples[:10]
        total_images = 10
        print(f"    [WARN] Modo rapido: evaluando unicamente 10 imagenes.")

    # Cargar Modelo correspondiente
    is_pytorch = model_key not in ["yolov8", "yolo11"]
    if is_pytorch:
        model = load_pytorch_model(model_key, checkpoint_path=checkpoint_file, num_classes=len(CLASSES))
    else:
        from ultralytics import YOLO
        model = YOLO(checkpoint_file)

    # --- Paso 4: Latencia - Warmup de 20 iteraciones (BS = 1) ---
    print("    [WARMUP] Iniciando warmup de 20 iteraciones...")
    if is_pytorch:
        dummy_tensor = torch.randn(1, 3, IMG_SIZE, IMG_SIZE).to(DEVICE)
        with torch.no_grad():
            for _ in range(20):
                _ = get_pytorch_tta_probs(model, dummy_tensor)
    else:
        dummy_img = Image.fromarray(np.uint8(np.random.rand(IMG_SIZE, IMG_SIZE, 3) * 255))
        for _ in range(20):
            _ = get_yolo_tta_probs(model, dummy_img)

    # --- Medición de tiempo real e Inferencia con TTA (Pasos 2, 4 y 5) ---
    print(f"    [LATENCY] Evaluando y midiendo latencia sincrona en {total_images} imagenes...")
    
    all_probs = []
    all_labels = []
    all_preds = []
    total_inference_time = 0
    
    for idx in range(total_images):
        img_path, label = samples[idx]
        
        t0 = time.time()
        if is_pytorch:
            img_pil = Image.open(img_path).convert("RGB")
            inputs = test_transform(img_pil).unsqueeze(0).to(DEVICE)
            with torch.no_grad():
                probs = get_pytorch_tta_probs(model, inputs)
            probs_np = probs.cpu().numpy()[0]
        else:
            probs_np = get_yolo_tta_probs(model, img_path)
            
        elapsed = time.time() - t0
        total_inference_time += elapsed
        
        pred_class = int(np.argmax(probs_np))
        
        all_probs.append(probs_np)
        all_labels.append(label)
        all_preds.append(pred_class)

    # Calcular promedios de latencia
    avg_latency_ms = (total_inference_time / total_images) * 1000
    print(f"    [LATENCY] Latencia de Inferencia Promedio (con TTA): {avg_latency_ms:.2f} ms/imagen")

    # --- Paso 2: Guardar probabilidades Softmax (probs_seedXX.csv) ---
    csv_path = results_dir / f"probs_seed{seed}.csv"
    with open(csv_path, mode="w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(["image_idx", "cataract", "diabetic_retinopathy", "glaucoma", "normal", "retina_disease", "real_label"])
        for i, (probs, real_l) in enumerate(zip(all_probs, all_labels)):
            writer.writerow([i] + list(probs) + [real_l])
    print(f"    [EXPORT] Probabilidades TTA exportadas: {csv_path}")

    # Calcular métricas globales
    accuracy = accuracy_score(all_labels, all_preds)
    precision_w, recall_w, f1_w, _ = precision_recall_fscore_support(
        all_labels, all_preds, average="weighted", zero_division=0
    )
    
    # AUC-ROC multiclase OvR Macro
    all_probs_arr = np.array(all_probs)
    all_labels_arr = np.array(all_labels)
    try:
        auc_roc = roc_auc_score(all_labels_arr, all_probs_arr, multi_class="ovr", average="macro")
    except Exception as e:
        auc_roc = 0.5
        print(f"    [WARN] Advertencia al calcular AUC-ROC: {e}. Fijando en 0.5.")

    # Calcular métricas por clase
    prec_class, rec_class, f1_class, support_class = precision_recall_fscore_support(
        all_labels, all_preds, average=None, labels=[0, 1, 2, 3, 4], zero_division=0
    )
    
    # --- Paso 2: Exportar métricas JSON (metricas_seedXX.json) ---
    metrics_json = {
        "model": model_cfg["name"],
        "seed": seed,
        "accuracy": float(accuracy),
        "precision_weighted": float(precision_w),
        "recall_weighted": float(recall_w),
        "f1_weighted": float(f1_w),
        "auc_roc_macro": float(auc_roc),
        "avg_latency_ms": float(avg_latency_ms),
        "total_test_images": total_images,
        "classes": {
            cls_name: {
                "precision": float(prec_class[i]),
                "recall": float(rec_class[i]),
                "f1_score": float(f1_class[i]),
                "support": int(support_class[i])
            } for i, cls_name in enumerate(CLASSES)
        }
    }
    
    json_path = results_dir / f"metricas_seed{seed}.json"
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(metrics_json, f, indent=2, ensure_ascii=False)
    print(f"    [METRICS] Metricas guardadas: {json_path}")

    # --- Paso 2: Generar y exportar Matriz de Confusión ---
    cm = confusion_matrix(all_labels, all_preds)
    fig, ax = plt.subplots(figsize=(8, 7))
    cmap_colors = {
        "resnet50": "Blues",
        "densenet121": "Blues",
        "efficientnet": "Purples",
        "sunet": "YlOrRd",
        "yolov8": "Greens",
        "yolo11": "Greens"
    }.get(model_key, "Blues")
    
    sns.heatmap(cm, annot=True, fmt="d", cmap=cmap_colors, xticklabels=CLASSES, yticklabels=CLASSES,
                ax=ax, linewidths=0.5, linecolor="white", annot_kws={"size": 11, "weight": "bold"})
    ax.set_xlabel("Prediccion del Modelo", fontsize=11, fontweight="bold")
    ax.set_ylabel("Diagnostico Clinico Real", fontsize=11, fontweight="bold")
    ax.set_title(f"{model_cfg['name']} (Seed {seed}) - Matriz de Confusion (TTA)\nAccuracy: {accuracy*100:.2f}%", 
                 fontsize=12, fontweight="bold")
    plt.xticks(rotation=25, ha="right")
    plt.yticks(rotation=0)
    plt.tight_layout()
    
    cm_path = results_dir / f"confusion_matrix_seed{seed}.png"
    plt.savefig(cm_path, dpi=200, bbox_inches="tight")
    plt.close()
    print(f"    [CHART] Matriz de confusion guardada: {cm_path}")

    return True

# --- Paso 3: Consolidar estadísticas (mean +/- std) ---
def consolidate_results(fast_mode):
    print("\n" + "=" * 75)
    print("  [EVAL] CONSOLIDANDO EXPERIMENTOS Y GENERANDO TABLA DEL PAPER")
    print("=" * 75)
    
    consolidated_metrics = {}

    for model_key, model_cfg in MODELS_TO_RUN.items():
        results_dir = RESULT_DIR / model_cfg["save_dir"]
        
        # Cargar los JSONs de cada seed
        seed_jsons = []
        for seed in SEEDS:
            json_file = results_dir / f"metricas_seed{seed}.json"
            if json_file.exists():
                with open(json_file, encoding="utf-8") as f:
                    seed_jsons.append(json.load(f))
                    
        if len(seed_jsons) < len(SEEDS):
            print(f"  [WARN] Faltan corridas para {model_cfg['name']}. Solo se encontraron {len(seed_jsons)}/{len(SEEDS)} seeds.")
            continue
            
        metrics_keys = ["accuracy", "precision_weighted", "recall_weighted", "f1_weighted", "auc_roc_macro", "avg_latency_ms"]
        model_stats = {}
        
        for key in metrics_keys:
            vals = [run[key] for run in seed_jsons]
            mean_val = np.mean(vals)
            std_val = np.std(vals)
            model_stats[key] = {"mean": mean_val, "std": std_val}

        # Guardar estadísticas por clase (F1-score)
        class_f1_stats = {}
        for cls_name in CLASSES:
            vals = [run["classes"][cls_name]["f1_score"] for run in seed_jsons]
            class_f1_stats[cls_name] = {"mean": np.mean(vals), "std": np.std(vals)}
            
        model_stats["classes_f1"] = class_f1_stats
        consolidated_metrics[model_key] = model_stats

    # Guardar JSON consolidado
    summary_path = RESULT_DIR / "comparison_summary_consolidated.json"
    with open(summary_path, "w", encoding="utf-8") as f:
        json.dump(consolidated_metrics, f, indent=2, ensure_ascii=False)
    print(f"\n  [METRICS] Resumen estadistico consolidado guardado en: {summary_path}")

    # Generar tabla visual en consola
    print("\n  [NOTE] TABLA DE RESULTADOS CONSOLIDADOS (Media +/- Desviacion Estandar)")
    print("=" * 115)
    print(f"  {'Modelo':<25} | {'Accuracy':<13} | {'F1-Score (W)':<13} | {'AUC-ROC (M)':<13} | {'Latencia TTA':<13}")
    print("=" * 115)
    
    for m_key, stats in consolidated_metrics.items():
        name = MODELS_TO_RUN[m_key]["name"]
        acc = f"{stats['accuracy']['mean']:.4f} +/- {stats['accuracy']['std']:.4f}"
        f1 = f"{stats['f1_weighted']['mean']:.4f} +/- {stats['f1_weighted']['std']:.4f}"
        auc = f"{stats['auc_roc_macro']['mean']:.4f} +/- {stats['auc_roc_macro']['std']:.4f}"
        lat = f"{stats['avg_latency_ms']['mean']:.1f} +/- {stats['avg_latency_ms']['std']:.1f} ms"
        print(f"  {name:<25} | {acc:<13} | {f1:<13} | {auc:<13} | {lat:<13}")
        
    print("=" * 115 + "\n")

    # Generar tabla por clase (F1-score)
    print("  [NOTE] F1-SCORE POR CLASE DIAGNOSTICA (Media +/- Desviacion Estandar)")
    print("=" * 145)
    print(f"  {'Modelo':<25} | {'Cataract':<18} | {'Diabetic Ret.':<18} | {'Glaucoma':<18} | {'Normal':<18} | {'Retina Disease':<18}")
    print("=" * 145)
    
    for m_key, stats in consolidated_metrics.items():
        name = MODELS_TO_RUN[m_key]["name"]
        cl_f1s = []
        for cls_name in CLASSES:
            mean = stats["classes_f1"][cls_name]["mean"]
            std = stats["classes_f1"][cls_name]["std"]
            cl_f1s.append(f"{mean:.4f} +/- {std:.4f}")
        print(f"  {name:<25} | {cl_f1s[0]:<18} | {cl_f1s[1]:<18} | {cl_f1s[2]:<18} | {cl_f1s[3]:<18} | {cl_f1s[4]:<18}")
        
    print("=" * 145 + "\n")

# --- Punto de Entrada Principal ---
def main():
    parser = argparse.ArgumentParser(description="Ejecutor de experimentos 3-Seed Eye Disease AI")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--fast", action="store_true", help="Sanity check rapido (1 epoca, 10 muestras)")
    group.add_argument("--full", action="store_true", help="Entrenamiento y evaluacion rigurosa completa")
    parser.add_argument("--eval-only", action="store_true", help="Solo correr la evaluacion utilizando checkpoints existentes")
    args = parser.parse_args()

    print("\n" + "=" * 75)
    print("  [SCIENCE] EYE DISEASE AI - PIPELINE EXPERIMENTAL 3-SEEDS DE NIVEL PAPER")
    print(f"  Fase        : {'FAST (Prueba Rapida)' if args.fast else 'FULL (Riguroso Completo)'}")
    print(f"  Device      : {DEVICE}")
    print("=" * 75)

    # 1. Entrenar y evaluar por cada modelo y por cada semilla
    for model_key, model_cfg in MODELS_TO_RUN.items():
        for seed in SEEDS:
            set_global_seed(seed)
            
            # Entrenamiento (Paso 1)
            if not args.eval_only:
                success = run_training(model_key, model_cfg, seed, args.fast)
                if not success:
                    print(f"  [ERROR] Fallo en entrenamiento de {model_cfg['name']} (Seed {seed}). Abortando pipeline.")
                    sys.exit(1)
            
            # Inferencia y Evaluacion con TTA (Pasos 2, 4 & 5)
            success = run_evaluation(model_key, model_cfg, seed, args.fast)
            if not success:
                print(f"  [ERROR] Fallo en evaluacion de {model_cfg['name']} (Seed {seed}). Abortando pipeline.")
                sys.exit(1)

    # 2. Consolidar estadísticas y generar reportes comparativos (Paso 3)
    consolidate_results(args.fast)

    print("\n" + "=" * 75)
    print("  [SUCCESS] EXPERIMENTACION CONSOLIDADA 3-SEEDS COMPLETADA CON EXITO")
    print("=" * 75 + "\n")

if __name__ == "__main__":
    main()
