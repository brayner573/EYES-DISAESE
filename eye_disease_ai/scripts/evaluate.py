"""
evaluate.py — Comparación ResNet50 vs YOLOv8
=============================================
Compara ambos modelos en el test set y genera reportes visuales.

Uso:
    python scripts/evaluate.py
"""

import json, sys
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np

PROJECT_ROOT = Path(__file__).resolve().parent.parent
RESULT_DIR   = PROJECT_ROOT / "results"
RESNET_DIR   = RESULT_DIR / "resnet"
YOLO_DIR     = RESULT_DIR / "yolo"
RESULT_DIR.mkdir(parents=True, exist_ok=True)


def find_latest_metrics(directory: Path, prefix: str) -> dict | None:
    """Encuentra el archivo de métricas JSON más reciente."""
    files = sorted(directory.glob(f"{prefix}_metrics_*.json"), reverse=True)
    if not files:
        return None
    with open(files[0], encoding="utf-8") as f:
        return json.load(f)


def parse_report(filepath: Path) -> dict:
    """Parsea un classification_report de sklearn."""
    metrics = {}
    with open(filepath, encoding="utf-8") as f:
        lines = f.readlines()
    for line in lines:
        parts = line.strip().split()
        if len(parts) == 5 and parts[0] not in ("macro", "weighted", "accuracy"):
            try:
                metrics[parts[0]] = {"precision": float(parts[1]),
                                     "recall": float(parts[2]), "f1": float(parts[3])}
            except ValueError:
                continue
        elif len(parts) >= 2 and parts[0] == "accuracy":
            metrics["__accuracy__"] = float(parts[1])
    return metrics


def find_latest_report(directory: Path, prefix: str) -> Path | None:
    """Encuentra el reporte más reciente."""
    files = sorted(directory.glob(f"{prefix}_classification_report_*.txt"), reverse=True)
    return files[0] if files else None


def plot_comparison(resnet_m: dict, yolo_m: dict, classes: list):
    """Genera gráfica comparativa de métricas por clase."""
    x = np.arange(len(classes))
    width = 0.35
    fig, axes = plt.subplots(1, 3, figsize=(18, 6))
    fig.suptitle("Comparación ResNet50 vs YOLOv8 (Test Set)",
                 fontsize=14, fontweight="bold")

    colors = [("#3498DB", "#E74C3C")]
    for i, metric in enumerate(["precision", "recall", "f1"]):
        r_vals = [resnet_m.get(c, {}).get(metric, 0) for c in classes]
        y_vals = [yolo_m.get(c, {}).get(metric, 0) for c in classes]
        axes[i].bar(x - width/2, r_vals, width, label="ResNet50", color="#3498DB", alpha=0.85)
        axes[i].bar(x + width/2, y_vals, width, label="YOLOv8", color="#27AE60", alpha=0.85)
        axes[i].set_title(metric.capitalize(), fontweight="bold")
        axes[i].set_xticks(x); axes[i].set_xticklabels(classes, rotation=25, ha="right")
        axes[i].set_ylim(0, 1.15); axes[i].legend(); axes[i].grid(axis="y", alpha=0.3)

    plt.tight_layout()
    out = RESULT_DIR / "comparison_resnet_vs_yolo.png"
    plt.savefig(out, dpi=200, bbox_inches="tight"); plt.close()
    print(f"  📊 Gráfica: {out}")


def main():
    print("\n" + "═" * 65)
    print("  📊 COMPARACIÓN — ResNet50 vs YOLOv8")
    print("═" * 65)

    resnet_metrics = find_latest_metrics(RESNET_DIR, "resnet")
    yolo_metrics = find_latest_metrics(YOLO_DIR, "yolo")

    missing = []
    if not resnet_metrics:
        missing.append("ResNet50 (ejecuta train_resnet.py)")
    if not yolo_metrics:
        missing.append("YOLOv8 (ejecuta train_yolov8.py)")
    if missing:
        print("\n  ❌ Faltan métricas de: " + ", ".join(missing))
        sys.exit(1)

    # Tabla comparativa
    print(f"\n  {'Métrica':<20} {'ResNet50':>12} {'YOLOv8':>12} {'Mejor':>12}")
    print(f"  {'─' * 56}")
    for metric in ["accuracy", "precision", "recall", "f1_score"]:
        r = resnet_metrics.get(metric, 0)
        y = yolo_metrics.get(metric, 0)
        winner = "ResNet50" if r >= y else "YOLOv8"
        print(f"  {metric:<20} {r:>12.4f} {y:>12.4f} {winner:>12}")

    r_acc = resnet_metrics.get("accuracy", 0)
    y_acc = yolo_metrics.get("accuracy", 0)
    winner = "ResNet50" if r_acc >= y_acc else "YOLOv8"
    print(f"\n  🏆 Ganador general: {winner}")
    print(f"     ResNet50: {r_acc*100:.2f}% | YOLOv8: {y_acc*100:.2f}%")

    # Buscar reportes para gráfica por clase
    r_report = find_latest_report(RESNET_DIR, "resnet")
    y_report = find_latest_report(YOLO_DIR, "yolo")
    if r_report and y_report:
        r_parsed = parse_report(r_report)
        y_parsed = parse_report(y_report)
        classes = [k for k in r_parsed if not k.startswith("__")]
        if classes:
            plot_comparison(r_parsed, y_parsed, classes)

    # Guardar resumen
    summary = {"resnet50": resnet_metrics, "yolo": yolo_metrics, "winner": winner}
    sp = RESULT_DIR / "comparison_summary.json"
    with open(sp, "w", encoding="utf-8") as f:
        json.dump(summary, f, indent=2, ensure_ascii=False)
    print(f"  📋 Resumen: {sp}")

    print("\n  📌 Siguiente: python scripts/predict.py <imagen>")
    print("═" * 65 + "\n")


if __name__ == "__main__":
    main()
