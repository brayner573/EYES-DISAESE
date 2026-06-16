"""
train_sunet.py — Entrenamiento Swin Transformer (SUNet Architecture)
======================================================================
Entrena un modelo Swin Transformer (Swin-T) optimizado para alta precisión.
Ideal para capturar relaciones globales en la imagen del ojo mediante
mecanismos de auto-atención en ventanas desplazadas.

Implementa mejoras avanzadas de nivel profesional:
    - Entrenamiento en 2 Fases:
        * Fase 1: Entrenar cabeza de clasificación (backbone congelado)
        * Fase 2: Ajuste fino del Transformer con Weight Decay estricto
    - Mixed Precision (AMP) automático con GradScaler
    - WeightedRandomSampler (Manejo del desbalance de clases)
    - Parada Temprana Inteligente con restauración de mejores pesos
    - TensorBoard logging integrado dinámicamente
    - Evaluación exhaustiva con TTA (Test-Time Augmentation)
    - Reportes en texto y JSON en carpeta results
    - Visualizaciones premium (Seaborn Heatmap YlOrRd)

Uso:
    python scripts/train_sunet.py
"""

import os, sys, copy, time, json, argparse
from pathlib import Path
from datetime import datetime

import torch
import torch.nn as nn
import torch.optim as optim
from torch.optim.lr_scheduler import CosineAnnealingLR, ReduceLROnPlateau
from torch.amp import GradScaler, autocast
from torchvision import datasets, models, transforms
from torch.utils.data import DataLoader, WeightedRandomSampler
from torch.utils.tensorboard import SummaryWriter
import torch.nn.functional as F
import numpy as np

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

# ─── Rutas ────────────────────────────────────────────────────────────────────
PROJECT_ROOT = Path(__file__).resolve().parent.parent
DATASET_DIR  = PROJECT_ROOT / "dataset_split"
MODEL_DIR    = PROJECT_ROOT / "models" / "sunet"
RESULT_DIR   = PROJECT_ROOT / "results" / "sunet"
LOG_DIR      = PROJECT_ROOT / "logs"

for d in [MODEL_DIR, RESULT_DIR, LOG_DIR]:
    d.mkdir(parents=True, exist_ok=True)

IMG_SIZE = 224 # Swin-T usa 224x224 por defecto
DEVICE   = torch.device("cuda" if torch.cuda.is_available() else "cpu")


def get_optimal_config():
    """Detecta GPU y retorna configuración óptima para Swin Transformer."""
    cfg = {"batch_size": 16, "num_workers": 0, "pin_memory": False, "amp": False}
    if torch.cuda.is_available():
        vram = torch.cuda.get_device_properties(0).total_memory / (1024**3)
        cfg["amp"] = True
        cfg["pin_memory"] = True
        # Swin Transformer es más pesado en memoria de activación que ResNet
        if vram >= 16:
            cfg["batch_size"], cfg["num_workers"] = 32, 6
        elif vram >= 12:
            cfg["batch_size"], cfg["num_workers"] = 24, 4
        elif vram >= 8:
            cfg["batch_size"], cfg["num_workers"] = 16, 4
        else:
            cfg["batch_size"], cfg["num_workers"] = 8, 2
        if os.name == "nt":
            cfg["num_workers"] = 0
        torch.backends.cudnn.benchmark = True
    return cfg


def get_transforms():
    """Transforms optimizados para Swin Transformer."""
    return {
        "train": transforms.Compose([
            transforms.Resize((IMG_SIZE + 32, IMG_SIZE + 32)),
            transforms.RandomCrop(IMG_SIZE),
            transforms.RandomHorizontalFlip(p=0.5),
            transforms.RandomVerticalFlip(p=0.2),
            transforms.ColorJitter(brightness=0.3, contrast=0.3, saturation=0.2, hue=0.05),
            transforms.RandomRotation(30),
            transforms.RandomAffine(degrees=0, translate=(0.1, 0.1), scale=(0.8, 1.2)),
            transforms.RandomGrayscale(p=0.1),
            transforms.GaussianBlur(kernel_size=3, sigma=(0.1, 1.5)),
            transforms.ToTensor(),
            transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225]),
            transforms.RandomErasing(p=0.2, scale=(0.02, 0.2)),
        ]),
        "val": transforms.Compose([
            transforms.Resize((IMG_SIZE, IMG_SIZE)),
            transforms.ToTensor(),
            transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225]),
        ]),
        "test": transforms.Compose([
            transforms.Resize((IMG_SIZE, IMG_SIZE)),
            transforms.ToTensor(),
            transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225]),
        ]),
    }


def build_model(num_classes, freeze_backbone=True):
    """Construye Swin-T con cabeza clasificadora robusta."""
    model = models.swin_t(weights=models.Swin_T_Weights.DEFAULT)
    if freeze_backbone:
        for param in model.parameters():
            param.requires_grad = False
            
    in_features = model.head.in_features
    model.head = nn.Sequential(
        nn.Dropout(p=0.5),
        nn.Linear(in_features, 512),
        nn.LayerNorm(512),
        nn.ReLU(),
        nn.Dropout(p=0.3),
        nn.Linear(512, num_classes)
    )
    return model


class EarlyStopping:
    """Early stopping inteligente para restaurar mejores pesos."""
    def __init__(self, patience=10, min_delta=1e-4):
        self.patience = patience
        self.min_delta = min_delta
        self.counter = 0
        self.best_score = None
        self.should_stop = False
        self.best_weights = None

    def __call__(self, val_acc, model):
        if self.best_score is None:
            self.best_score = val_acc
            self.best_weights = copy.deepcopy(model.state_dict())
        elif val_acc < self.best_score + self.min_delta:
            self.counter += 1
            if self.counter >= self.patience:
                self.should_stop = True
        else:
            self.best_score = val_acc
            self.best_weights = copy.deepcopy(model.state_dict())
            self.counter = 0


def train_phase(model, dataloaders, dataset_sizes, optimizer, scheduler,
                num_epochs, phase_name, use_amp=False, patience=10, 
                tb_writer=None, epoch_offset=0):
    """Bucle de entrenamiento con soporte para Transformers."""
    model.to(DEVICE)
    criterion = nn.CrossEntropyLoss(label_smoothing=0.1)
    scaler = GradScaler("cuda", enabled=use_amp)
    early_stop = EarlyStopping(patience=patience)

    history = {"train_loss": [], "val_loss": [], "train_acc": [], "val_acc": []}

    print(f"\n{'═' * 65}")
    print(f"  🔄 FASE: {phase_name.upper()}")
    print(f"  Épocas: {num_epochs} | Early Stop: {patience} épocas")
    print(f"{'═' * 65}")

    for epoch in range(num_epochs):
        t0 = time.time()
        global_epoch = epoch_offset + epoch
        
        for phase in ["train", "val"]:
            model.train() if phase == "train" else model.eval()
            running_loss, running_corrects = 0.0, 0

            for inputs, labels in dataloaders[phase]:
                inputs, labels = inputs.to(DEVICE, non_blocking=True), labels.to(DEVICE, non_blocking=True)
                optimizer.zero_grad(set_to_none=True)

                with torch.set_grad_enabled(phase == "train"):
                    with autocast("cuda", enabled=use_amp and DEVICE.type == "cuda"):
                        outputs = model(inputs)
                        loss = criterion(outputs, labels)
                    _, preds = torch.max(outputs, 1)
                    
                    if phase == "train":
                        scaler.scale(loss).backward()
                        scaler.unscale_(optimizer)
                        torch.nn.utils.clip_grad_norm_(model.parameters(), max_norm=1.0)
                        scaler.step(optimizer)
                        scaler.update()

                running_loss += loss.item() * inputs.size(0)
                running_corrects += torch.sum(preds == labels.data).item()

            epoch_loss = running_loss / dataset_sizes[phase]
            epoch_acc = running_corrects / dataset_sizes[phase]
            history[f"{phase}_loss"].append(epoch_loss)
            history[f"{phase}_acc"].append(epoch_acc)

            # TensorBoard
            if tb_writer:
                tb_writer.add_scalar(f"Loss/{phase}", epoch_loss, global_epoch)
                tb_writer.add_scalar(f"Accuracy/{phase}", epoch_acc, global_epoch)

            if phase == "val":
                if isinstance(scheduler, ReduceLROnPlateau):
                    scheduler.step(epoch_loss)
                elif scheduler:
                    scheduler.step()
                early_stop(epoch_acc, model)

        elapsed = time.time() - t0
        lr_current = optimizer.param_groups[0]["lr"]
        marker = " ★" if early_stop.best_score == history["val_acc"][-1] else ""
        
        print(f"  [{epoch+1:03d}/{num_epochs}] "
              f"TL: {history['train_loss'][-1]:.4f} TA: {history['train_acc'][-1]:.4f} | "
              f"VL: {history['val_loss'][-1]:.4f} VA: {history['val_acc'][-1]:.4f} | "
              f"LR: {lr_current:.2e} | {elapsed:.1f}s{marker}")

        if early_stop.should_stop:
            print(f"\n  ⏹️  Early stopping activado en época {epoch+1}")
            break

    print(f"\n  🏆 Mejor Val Accuracy de Fase: {early_stop.best_score:.4f} ({early_stop.best_score*100:.2f}%)")
    model.load_state_dict(early_stop.best_weights)
    return model, history, (epoch_offset + epoch + 1)


def tta_predict(model, inputs):
    """Test Time Augmentation: Inferencia robusta."""
    out1 = model(inputs)
    inputs_flipped = torch.flip(inputs, dims=[3])
    out2 = model(inputs_flipped)
    probs = (F.softmax(out1, dim=1) + F.softmax(out2, dim=1)) / 2.0
    return probs


def evaluate_test(model, dataloaders, class_names, run_name):
    """Evalúa el Swin Transformer con TTA y genera métricas completas."""
    from sklearn.metrics import (classification_report, confusion_matrix,
                                  accuracy_score, precision_recall_fscore_support)
    import seaborn as sns

    model.eval()
    all_preds, all_labels = [], []
    total_time = 0

    with torch.no_grad():
        for inputs, labels in dataloaders["test"]:
            inputs = inputs.to(DEVICE, non_blocking=True)
            t0 = time.time()
            probs = tta_predict(model, inputs)
            total_time += time.time() - t0
            _, preds = torch.max(probs, 1)
            all_preds.extend(preds.cpu().numpy())
            all_labels.extend(labels.numpy())

    accuracy = accuracy_score(all_labels, all_preds)
    precision, recall, f1, _ = precision_recall_fscore_support(
        all_labels, all_preds, average="weighted", zero_division=0)
    avg_ms = (total_time / max(len(all_preds), 1)) * 1000
    report = classification_report(all_labels, all_preds, target_names=class_names, zero_division=0)

    print(f"\n{'═' * 65}")
    print(f"  📊 REPORTE EN TEST SET — Swin Transformer (SUNet con TTA)")
    print(f"{'═' * 65}")
    print(report)
    print(f"  🎯 Accuracy Global : {accuracy:.4f} ({accuracy*100:.2f}%)")
    print(f"  ⚡ Inferencia TTA  : {avg_ms:.1f} ms/imagen")

    # Guardar reporte
    rpt_path = RESULT_DIR / f"sunet_classification_report_{run_name}.txt"
    with open(rpt_path, "w", encoding="utf-8") as f:
        f.write(f"Swin-T Classification Report — {run_name}\n{'=' * 65}\n\n")
        f.write(report)
        f.write(f"\nAccuracy: {accuracy:.4f}\nPrecision: {precision:.4f}\n")
        f.write(f"Recall: {recall:.4f}\nF1-score: {f1:.4f}\n")
    print(f"\n  📄 Reporte: {rpt_path}")

    # Confusion matrix
    cm = confusion_matrix(all_labels, all_preds)
    fig, ax = plt.subplots(figsize=(10, 8))
    sns.heatmap(cm, annot=True, fmt="d", cmap="YlOrRd", xticklabels=class_names,
                yticklabels=class_names, ax=ax, linewidths=0.5, linecolor="white",
                annot_kws={"size": 12, "weight": "bold"})
    ax.set_xlabel("Predicción del Modelo", fontsize=12, fontweight="bold")
    ax.set_ylabel("Diagnóstico Real", fontsize=12, fontweight="bold")
    ax.set_title(f"Swin-T (SUNet) — Matriz de Confusión (Test Set)\nAccuracy: {accuracy*100:.2f}%",
                 fontsize=14, fontweight="bold")
    plt.xticks(rotation=30, ha="right"); plt.yticks(rotation=0)
    plt.tight_layout()
    cm_path = RESULT_DIR / f"sunet_confusion_matrix_{run_name}.png"
    plt.savefig(cm_path, dpi=200, bbox_inches="tight"); plt.close()
    print(f"  📊 Matriz: {cm_path}")

    # JSON
    metrics = {"model": "Swin-T (SUNet)", "run_name": run_name, "timestamp": datetime.now().isoformat(),
               "accuracy": float(accuracy), "precision": float(precision), "recall": float(recall),
               "f1_score": float(f1), "avg_inference_ms": float(avg_ms),
               "total_test_images": len(all_preds), "class_names": class_names}
    mp = RESULT_DIR / f"sunet_metrics_{run_name}.json"
    with open(mp, "w", encoding="utf-8") as f:
        json.dump(metrics, f, indent=2, ensure_ascii=False)
    print(f"  📋 Métricas: {mp}")


def plot_history(h1, h2, run_name):
    """Grafica curvas de entrenamiento combinadas."""
    fig, axes = plt.subplots(1, 2, figsize=(14, 5))
    for key, ax, title in [("loss", axes[0], "Loss"), ("acc", axes[1], "Accuracy")]:
        train_data = h1[f"train_{key}"] + h2[f"train_{key}"]
        val_data = h1[f"val_{key}"] + h2[f"val_{key}"]
        epochs = range(1, len(train_data) + 1)
        sep = len(h1[f"train_{key}"])
        ax.plot(epochs, train_data, label="Train", linewidth=2, color="crimson")
        ax.plot(epochs, val_data, label="Val", linewidth=2, color="orange")
        ax.axvline(sep, linestyle="--", color="gray", alpha=0.7, label="Fine-tune start")
        ax.set_title(title, fontsize=13, fontweight="bold")
        ax.set_xlabel("Época"); ax.set_ylabel(title)
        ax.legend(); ax.grid(alpha=0.3)
    plt.suptitle("Swin-T (SUNet) — Curvas de Entrenamiento", fontsize=15, fontweight="bold")
    plt.tight_layout()
    path = RESULT_DIR / f"sunet_training_curves_{run_name}.png"
    plt.savefig(path, dpi=200, bbox_inches="tight"); plt.close()
    print(f"  📈 Curvas: {path}")


def parse_args():
    p = argparse.ArgumentParser(description="Entrenar Swin Transformer (SUNet) para enfermedades oculares")
    p.add_argument("--epochs-head", type=int, default=15, help="Épocas fase 1 (default: 15)")
    p.add_argument("--epochs-fine", type=int, default=35, help="Épocas fase 2 (default: 35)")
    p.add_argument("--lr-head", type=float, default=1e-3, help="LR fase 1 (default: 1e-3)")
    p.add_argument("--lr-fine", type=float, default=2e-5, help="LR fase 2 (default: 2e-5 para Swin)")
    p.add_argument("--patience", type=int, default=10, help="Early stopping (default: 10)")
    p.add_argument("--batch", type=int, default=None, help="Batch size (auto)")
    p.add_argument("--seed", type=int, default=42, help="Semilla aleatoria (default: 42)")
    return p.parse_args()


def main():
    args = parse_args()
    
    # Configurar semillas fijas para reproducibilidad
    import random
    random.seed(args.seed)
    np.random.seed(args.seed)
    torch.manual_seed(args.seed)
    if torch.cuda.is_available():
        torch.cuda.manual_seed(args.seed)
        torch.cuda.manual_seed_all(args.seed)
        
    cfg = get_optimal_config()
    batch = args.batch or cfg["batch_size"]
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    run_name = f"sunet_swint_{timestamp}"
    
    tb_writer = SummaryWriter(log_dir=str(LOG_DIR / run_name))

    print("\n" + "█" * 65)
    print("  🔬 EYE DISEASE AI — Swin Transformer (SUNet Architecture)")
    print(f"  ⚡ Semilla: {args.seed} | 2 Fases (AdamW) | Regularización Swin | AMP | TTA")
    print("█" * 65)
    print(f"\n  Device     : {DEVICE}")
    if torch.cuda.is_available():
        print(f"  GPU        : {torch.cuda.get_device_name(0)}")
        print(f"  VRAM       : {torch.cuda.get_device_properties(0).total_memory/(1024**3):.1f} GB")
        print(f"  AMP        : {'✅' if cfg['amp'] else '❌'}")
    print(f"  Batch size : {batch}")
    print(f"  Workers    : {cfg['num_workers']}")

    # Datasets
    data_transforms = get_transforms()
    image_datasets = {s: datasets.ImageFolder(DATASET_DIR / s, data_transforms[s]) for s in ["train", "val", "test"]}
    dataset_sizes = {s: len(image_datasets[s]) for s in ["train", "val", "test"]}
    class_names = image_datasets["train"].classes
    num_classes = len(class_names)
    
    # Weighted Sampler
    targets = image_datasets["train"].targets
    class_counts = np.bincount(targets)
    class_weights = 1.0 / class_counts
    sample_weights = class_weights[targets]
    sampler = WeightedRandomSampler(weights=sample_weights, num_samples=len(sample_weights), replacement=True)

    dataloaders = {
        "train": DataLoader(image_datasets["train"], batch_size=batch, sampler=sampler,
                            num_workers=cfg["num_workers"], pin_memory=cfg["pin_memory"],
                            persistent_workers=cfg["num_workers"] > 0),
        "val": DataLoader(image_datasets["val"], batch_size=batch, shuffle=False,
                          num_workers=cfg["num_workers"], pin_memory=cfg["pin_memory"],
                          persistent_workers=cfg["num_workers"] > 0),
        "test": DataLoader(image_datasets["test"], batch_size=batch, shuffle=False,
                           num_workers=cfg["num_workers"], pin_memory=cfg["pin_memory"],
                           persistent_workers=cfg["num_workers"] > 0)
    }

    print(f"\n  Clases ({num_classes}): {class_names}")
    for s in ["train", "val", "test"]:
        print(f"    {s:5s}: {dataset_sizes[s]:>5} imágenes")

    # Guardar clases
    class_map = {i: name for i, name in enumerate(class_names)}
    with open(MODEL_DIR / "sunet_classes.json", "w") as f:
        json.dump(class_map, f, indent=2)

    # Fase 1: Solo Cabeza
    model = build_model(num_classes, freeze_backbone=True)
    opt1 = optim.AdamW(filter(lambda p: p.requires_grad, model.parameters()),
                       lr=args.lr_head, weight_decay=1e-4)
    sch1 = CosineAnnealingLR(opt1, T_max=args.epochs_head)
    model, h1, offset = train_phase(model, dataloaders, dataset_sizes, opt1, sch1,
                                    args.epochs_head, "Fase 1 — Cabeza Swin", cfg["amp"], 
                                    args.patience, tb_writer=tb_writer, epoch_offset=0)

    # Fase 2: Fine-Tuning completo con tasas de aprendizaje diferenciales del Transformer
    for param in model.parameters():
        param.requires_grad = True
        
    # Agrupar parámetros para aplicar tasas de aprendizaje diferenciales (evita colapso de características pretrained)
    backbone_params = []
    head_params = []
    for name, param in model.named_parameters():
        if "head" in name:
            head_params.append(param)
        else:
            backbone_params.append(param)
            
    opt2 = optim.AdamW([
        {"params": backbone_params, "lr": args.lr_fine * 0.1},  # 10x menor para el backbone Swin
        {"params": head_params, "lr": args.lr_fine}            # LR normal para la cabeza
    ], weight_decay=0.05)
    
    sch2 = ReduceLROnPlateau(opt2, mode='min', factor=0.5, patience=3)
    
    model, h2, _ = train_phase(model, dataloaders, dataset_sizes, opt2, sch2,
                               args.epochs_fine, "Fase 2 — Swin Fine-Tuning", cfg["amp"], 
                               args.patience, tb_writer=tb_writer, epoch_offset=offset)

    tb_writer.close()

    # Guardar modelo
    save_path = MODEL_DIR / f"mejor_modelo_seed{args.seed}.pt"
    torch.save({"model_state_dict": model.state_dict(), "class_names": class_names,
                "num_classes": num_classes, "timestamp": datetime.now().isoformat(), "seed": args.seed}, save_path)
    print(f"\n  💾 Modelo guardado: {save_path} ({save_path.stat().st_size/(1024*1024):.1f} MB)")

    # Evaluación y curvas
    evaluate_test(model, dataloaders, class_names, run_name)
    plot_history(h1, h2, run_name)

    print("\n" + "█" * 65)
    print("  ✅ ENTRENAMIENTO SWIN TRANSFORMER (SUNET) COMPLETADO")
    print(f"  📊 Puedes ver las métricas usando: tensorboard --logdir logs")
    print("█" * 65 + "\n")


if __name__ == "__main__":
    main()
