"""
generate_real_charts.py — Generador de Gráficos Clínicos Reales
============================================================
Este script extrae las métricas reales logradas por tus 6 modelos
(ResNet50, DenseNet121, EfficientNetV2-S, Swin Transformer, YOLOv8, YOLO11)
y genera gráficos científicos unificados de alta definición (300 DPI) para tu artículo.

Genera:
1. results/real_metrics_comparison.png — Comparativa de exactitud y F1-score.
2. results/real_latencies_comparison.png — Comparativa de latencia en GPU (ms).
3. results/real_classes_comparison.png — F1-score por cada patología y modelo.
4. results/real_results_dashboard.png — Dashboard académico integrado de tres paneles.

Autor: Eye Disease AI Project
"""

import os
import json
from pathlib import Path
import matplotlib.pyplot as plt
import numpy as np
import seaborn as sns

# ─── Configuración de Estilo Premium para Publicación ──────────────────────────
sns.set_theme(style="white")
plt.rcParams.update({
    'font.family': 'sans-serif',
    'font.sans-serif': ['Arial', 'DejaVu Sans', 'Liberation Sans'],
    'font.size': 11,
    'axes.labelsize': 12,
    'axes.titlesize': 13,
    'xtick.labelsize': 10,
    'ytick.labelsize': 10,
    'figure.titlesize': 16,
    'axes.spines.top': False,
    'axes.spines.right': False,
    'grid.alpha': 0.2,
    'grid.linestyle': '--'
})

# Paleta de colores distintivos y armoniosos para los 6 modelos
MODEL_COLORS = {
    'ResNet50': '#3b82f6',         # Azul Premium
    'DenseNet121': '#06b6d4',      # Cyan
    'EfficientNetV2-S': '#64748b', # Gris / Muted
    'Swin-T (SUNet)': '#f59e0b',   # Ámbar / Naranja
    'YOLOv8m-cls': '#10b981',      # Esmeralda
    'YOLO11m-cls': '#a855f7'       # Púrpura / SOTA
}

# ─── Datos Experimentales Reales Registrados en results/ ────────────────────────
PROJECT_ROOT = Path(__file__).resolve().parent.parent
RESULT_DIR   = PROJECT_ROOT / "results"
RESULT_DIR.mkdir(parents=True, exist_ok=True)

# Cargar datos consolidados dinámicamente si existen
CONSOLIDATED_JSON = RESULT_DIR / "comparison_summary_consolidated.json"

if CONSOLIDATED_JSON.exists():
    print(f"Loading consolidated metrics from {CONSOLIDATED_JSON}...")
    with open(CONSOLIDATED_JSON, "r", encoding="utf-8") as f:
        raw_data = json.load(f)
    
    # Mapeo de claves
    key_mapping = {
        'resnet50': 'ResNet50',
        'densenet121': 'DenseNet121',
        'efficientnet': 'EfficientNetV2-S',
        'sunet': 'Swin-T (SUNet)',
        'yolov8': 'YOLOv8m-cls',
        'yolo11': 'YOLO11m-cls'
    }
    
    MODELS_DATA = {}
    MODELS_ERRORS = {} # Para almacenar desviaciones estándar para barras de error
    
    for k, v in raw_data.items():
        if k in key_mapping:
            pres_name = key_mapping[k]
            MODELS_DATA[pres_name] = {
                'accuracy': v['accuracy']['mean'],
                'f1': v['f1_weighted']['mean'],
                'precision': v['precision_macro']['mean'],
                'recall': v['recall_macro']['mean'],
                'latency': v['avg_latency_ms']['mean'],
                'class_f1': {
                    'Catarata': v['classes_f1']['cataract']['mean'] / 100.0 if v['classes_f1']['cataract']['mean'] > 1.0 else v['classes_f1']['cataract']['mean'],
                    'Ret. Diabética': v['classes_f1']['diabetic_retinopathy']['mean'] / 100.0 if v['classes_f1']['diabetic_retinopathy']['mean'] > 1.0 else v['classes_f1']['diabetic_retinopathy']['mean'],
                    'Glaucoma': v['classes_f1']['glaucoma']['mean'] / 100.0 if v['classes_f1']['glaucoma']['mean'] > 1.0 else v['classes_f1']['glaucoma']['mean'],
                    'Normal': v['classes_f1']['normal']['mean'] / 100.0 if v['classes_f1']['normal']['mean'] > 1.0 else v['classes_f1']['normal']['mean'],
                    'Retina Dis.': v['classes_f1']['retina_disease']['mean'] / 100.0 if v['classes_f1']['retina_disease']['mean'] > 1.0 else v['classes_f1']['retina_disease']['mean']
                }
            }
            # Guardamos las desviaciones estándar correspondientes
            MODELS_ERRORS[pres_name] = {
                'accuracy_std': v['accuracy']['std'],
                'f1_std': v['f1_weighted']['std'],
                'latency_std': v['avg_latency_ms']['std'],
                'class_f1_std': {
                    'Catarata': v['classes_f1']['cataract']['std'] / 100.0 if v['classes_f1']['cataract']['std'] > 1.0 else v['classes_f1']['cataract']['std'],
                    'Ret. Diabética': v['classes_f1']['diabetic_retinopathy']['std'] / 100.0 if v['classes_f1']['diabetic_retinopathy']['std'] > 1.0 else v['classes_f1']['diabetic_retinopathy']['std'],
                    'Glaucoma': v['classes_f1']['glaucoma']['std'] / 100.0 if v['classes_f1']['glaucoma']['std'] > 1.0 else v['classes_f1']['glaucoma']['std'],
                    'Normal': v['classes_f1']['normal']['std'] / 100.0 if v['classes_f1']['normal']['std'] > 1.0 else v['classes_f1']['normal']['std'],
                    'Retina Dis.': v['classes_f1']['retina_disease']['std'] / 100.0 if v['classes_f1']['retina_disease']['std'] > 1.0 else v['classes_f1']['retina_disease']['std']
                }
            }
else:
    print("Warning: comparison_summary_consolidated.json not found, using hardcoded fallback.")
    MODELS_DATA = {
        'ResNet50': {
            'accuracy': 75.25, 'f1': 76.67, 'precision': 80.17, 'recall': 75.25, 'latency': 65.14,
            'class_f1': {'Catarata': 0.85, 'Ret. Diabética': 0.83, 'Glaucoma': 0.69, 'Normal': 0.58, 'Retina Dis.': 0.43}
        },
        'DenseNet121': {
            'accuracy': 69.20, 'f1': 71.63, 'precision': 77.92, 'recall': 69.20, 'latency': 39.32,
            'class_f1': {'Catarata': 0.83, 'Ret. Diabética': 0.78, 'Glaucoma': 0.67, 'Normal': 0.51, 'Retina Dis.': 0.37}
        },
        'EfficientNetV2-S': {
            'accuracy': 61.44, 'f1': 58.13, 'precision': 61.15, 'recall': 61.44, 'latency': 41.69,
            'class_f1': {'Catarata': 0.68, 'Ret. Diabética': 0.74, 'Glaucoma': 0.49, 'Normal': 0.13, 'Retina Dis.': 0.39}
        },
        'Swin-T (SUNet)': {
            'accuracy': 73.82, 'f1': 74.69, 'precision': 76.94, 'recall': 73.82, 'latency': 32.40,
            'class_f1': {'Catarata': 0.83, 'Ret. Diabética': 0.84, 'Glaucoma': 0.68, 'Normal': 0.49, 'Retina Dis.': 0.37}
        },
        'YOLOv8m-cls': {
            'accuracy': 75.69, 'f1': 75.86, 'precision': 77.74, 'recall': 75.69, 'latency': 26.61,
            'class_f1': {'Catarata': 0.75, 'Ret. Diabética': 0.85, 'Glaucoma': 0.65, 'Normal': 0.62, 'Retina Dis.': 0.21}
        },
        'YOLO11m-cls': {
            'accuracy': 75.84, 'f1': 74.34, 'precision': 75.15, 'recall': 75.84, 'latency': 28.28,
            'class_f1': {'Catarata': 0.83, 'Ret. Diabética': 0.83, 'Glaucoma': 0.70, 'Normal': 0.48, 'Retina Dis.': 0.32}
        }
    }
    MODELS_ERRORS = {m: {'accuracy_std': 0.0, 'f1_std': 0.0, 'latency_std': 0.0, 'class_f1_std': {c: 0.0 for c in ['Catarata', 'Ret. Diabética', 'Glaucoma', 'Normal', 'Retina Dis.']}} for m in MODELS_DATA}


PROJECT_ROOT = Path(__file__).resolve().parent.parent
RESULT_DIR   = PROJECT_ROOT / "results"
RESULT_DIR.mkdir(parents=True, exist_ok=True)

def plot_metrics_comparison():
    """Genera gráfico comparativo de Accuracy y F1-score para los 6 modelos."""
    fig, ax = plt.subplots(figsize=(10, 6), dpi=300)
    
    models = list(MODELS_DATA.keys())
    acc_vals = [MODELS_DATA[m]['accuracy'] for m in models]
    f1_vals = [MODELS_DATA[m]['f1'] for m in models]
    colors = [MODEL_COLORS[m] for m in models]
    
    x = np.arange(len(models))
    width = 0.35
    
    rects1 = ax.bar(x - width/2, acc_vals, width, label='Exactitud (Accuracy %)', color=colors, alpha=0.9, edgecolor='none')
    rects2 = ax.bar(x + width/2, f1_vals, width, label='Weighted F1-Score %', color=colors, alpha=0.6, hatch='//', edgecolor='white', linewidth=1)
    
    # Anotar valores en barra
    def add_labels(rects):
        for rect in rects:
            height = rect.get_height()
            ax.annotate(f'{height:.1f}%',
                        xy=(rect.get_x() + rect.get_width() / 2, height),
                        xytext=(0, 3),  # 3 points vertical offset
                        textcoords="offset points",
                        ha='center', va='bottom', fontsize=8.5, fontweight='bold')

    add_labels(rects1)
    add_labels(rects2)
    
    ax.set_title("Comparativa de Rendimiento Global por Modelo (Test Set)", fontweight='bold', fontsize=14, pad=15)
    ax.set_ylabel("Rendimiento (%)", fontweight='bold')
    ax.set_xticks(x)
    ax.set_xticklabels(models, rotation=15, ha='right', fontweight='semibold')
    ax.set_ylim(0, 105)
    ax.grid(axis='y', linestyle=':', alpha=0.4)
    ax.legend(['Exactitud (Accuracy)', 'F1-Score (Weighted)'], loc='lower left', frameon=True, facecolor='#f8fafc', edgecolor='none')
    
    plt.tight_layout()
    path = RESULT_DIR / "real_metrics_comparison.png"
    plt.savefig(path, dpi=300, bbox_inches='tight')
    plt.close()
    print(f"Metrics plot saved to: {path}")

def plot_latencies_comparison():
    """Genera gráfico de latencia de inferencia en GPU (ms)."""
    fig, ax = plt.subplots(figsize=(8, 5.5), dpi=300)
    
    models = list(MODELS_DATA.keys())
    latencies = [MODELS_DATA[m]['latency'] for m in models]
    colors = [MODEL_COLORS[m] for m in models]
    
    y = np.arange(len(models))
    
    # Gráfico horizontal para legibilidad
    bars = ax.barh(y, latencies, height=0.55, color=colors, alpha=0.85, edgecolor='none')
    
    # Anotar valores
    for bar in bars:
        width = bar.get_width()
        ax.annotate(f' {width:.2f} ms',
                    xy=(width, bar.get_y() + bar.get_height() / 2),
                    xytext=(3, 0),  # 3 points horizontal offset
                    textcoords="offset points",
                    ha='left', va='center', fontsize=9.5, fontweight='bold')
                    
    ax.set_title("Latencia de Inferencia Promedio por Imagen (GPU RTX 5060)", fontweight='bold', fontsize=13, pad=15)
    ax.set_xlabel("Tiempo de Inferencia (milisegundos)", fontweight='bold')
    ax.set_yticks(y)
    ax.set_yticklabels(models, fontweight='semibold')
    ax.set_xlim(0, 75)
    ax.grid(axis='x', linestyle=':', alpha=0.4)
    
    # Agregar nota sobre batch sizes
    ax.text(38, 1.2, "Todos los modelos: Batch 1\n(con TTA geométrico de 5 vistas en GPU)", 
            bbox=dict(boxstyle="round,pad=0.5", facecolor="#f1f5f9", edgecolor="none", alpha=0.9),
            fontsize=8.5, color="#475569")
            
    plt.tight_layout()
    path = RESULT_DIR / "real_latencies_comparison.png"
    plt.savefig(path, dpi=300, bbox_inches='tight')
    plt.close()
    print(f"Latencies plot saved to: {path}")

def plot_classes_comparison():
    """Genera gráfico comparativo detallado de F1-score por clase y por modelo."""
    fig, ax = plt.subplots(figsize=(11, 6.5), dpi=300)
    
    classes = ['Catarata', 'Ret. Diabética', 'Glaucoma', 'Normal', 'Retina Dis.']
    models = list(MODELS_DATA.keys())
    
    x = np.arange(len(classes))
    width = 0.13
    
    # Graficar barras para cada modelo
    for idx, model in enumerate(models):
        f1_scores = [MODELS_DATA[model]['class_f1'][c] for c in classes]
        ax.bar(x + (idx - 2.5) * width, f1_scores, width, 
               label=model, color=MODEL_COLORS[model], alpha=0.85)
               
    ax.set_title("Desempeño Detallado de Clasificación (F1-score por Clase)", fontweight='bold', fontsize=14, pad=15)
    ax.set_ylabel("F1-Score", fontweight='bold')
    ax.set_xticks(x)
    ax.set_xticklabels(classes, fontweight='semibold', fontsize=11)
    ax.set_ylim(0, 1.15)
    ax.grid(axis='y', linestyle=':', alpha=0.4)
    ax.legend(loc='upper right', frameon=True, facecolor='#f8fafc', edgecolor='none', fontsize=9.5)
    
    plt.tight_layout()
    path = RESULT_DIR / "real_classes_comparison.png"
    plt.savefig(path, dpi=300, bbox_inches='tight')
    plt.close()
    print(f"Class-by-class comparison plot saved to: {path}")

def plot_combined_dashboard():
    """Genera un gran Dashboard Científico consolidado de 3 paneles."""
    fig = plt.figure(figsize=(16, 12), dpi=300)
    grid = fig.add_gridspec(2, 2, height_ratios=[1, 1], wspace=0.25, hspace=0.35)
    
    models = list(MODELS_DATA.keys())
    colors = [MODEL_COLORS[m] for m in models]
    
    print("Creating integrated dashboard...")
    
    # ─── Panel 1: Métricas Globales (Top Left) ───
    ax1 = fig.add_subplot(grid[0, 0])
    acc_vals = [MODELS_DATA[m]['accuracy'] for m in models]
    f1_vals = [MODELS_DATA[m]['f1'] for m in models]
    x = np.arange(len(models))
    width = 0.35
    rects1 = ax1.bar(x - width/2, acc_vals, width, label='Exactitud (Accuracy)', color=colors, alpha=0.9)
    rects2 = ax1.bar(x + width/2, f1_vals, width, label='F1-Score (Weighted)', color=colors, alpha=0.6, hatch='//', edgecolor='white')
    
    for r in rects1:
        ax1.annotate(f'{r.get_height():.1f}%', xy=(r.get_x() + r.get_width()/2, r.get_height()),
                    xytext=(0, 2), textcoords="offset points", ha='center', va='bottom', fontsize=8, fontweight='bold')
    
    ax1.set_title("A. Comparativa de Métricas Globales", fontweight='semibold')
    ax1.set_ylabel("Rendimiento (%)")
    ax1.set_xticks(x)
    ax1.set_xticklabels(models, rotation=20, ha='right', fontsize=9, fontweight='semibold')
    ax1.set_ylim(0, 105)
    ax1.grid(axis='y', linestyle=':', alpha=0.4)
    ax1.legend(['Exactitud', 'F1-Score'], loc='lower left', fontsize=8.5, frameon=True, facecolor='#f8fafc', edgecolor='none')
    
    # ─── Panel 2: Latencias de Inferencia (Top Right) ───
    ax2 = fig.add_subplot(grid[0, 1])
    latencies = [MODELS_DATA[m]['latency'] for m in models]
    bars = ax2.barh(x, latencies, height=0.55, color=colors, alpha=0.85)
    for b in bars:
        ax2.annotate(f' {b.get_width():.2f} ms', xy=(b.get_width(), b.get_y() + b.get_height()/2),
                    xytext=(2, 0), textcoords="offset points", ha='left', va='center', fontsize=8.5, fontweight='bold')
    
    ax2.set_title("B. Latencia de Inferencia Promedio (GPU)", fontweight='semibold')
    ax2.set_xlabel("Tiempo (milisegundos)")
    ax2.set_yticks(x)
    ax2.set_yticklabels(models, fontsize=9, fontweight='semibold')
    ax2.set_xlim(0, 76)
    ax2.grid(axis='x', linestyle=':', alpha=0.4)
    
    # ─── Panel 3: Desempeño por Clase (Bottom) ───
    ax3 = fig.add_subplot(grid[1, :])
    classes = ['Catarata', 'Ret. Diabética', 'Glaucoma', 'Normal', 'Retina Dis.']
    x_classes = np.arange(len(classes))
    bar_width = 0.13
    
    for idx, model in enumerate(models):
        f1_scores = [MODELS_DATA[model]['class_f1'][c] for c in classes]
        ax3.bar(x_classes + (idx - 2.5) * bar_width, f1_scores, bar_width, 
               label=model, color=MODEL_COLORS[model], alpha=0.85)
               
    ax3.set_title("C. Desempeño de Clasificación por Patología (F1-score)", fontweight='semibold')
    ax3.set_ylabel("F1-Score")
    ax3.set_xticks(x_classes)
    ax3.set_xticklabels(classes, fontweight='semibold', fontsize=10.5)
    ax3.set_ylim(0, 1.15)
    ax3.grid(axis='y', linestyle=':', alpha=0.4)
    ax3.legend(loc='upper right', frameon=True, facecolor='#f8fafc', edgecolor='none', fontsize=9)
    
    plt.suptitle("Dashboard Comparativo de Rendimiento y Eficiencia de los Modelos Clínicos", 
                 fontsize=16, fontweight='bold', color='#0f172a', y=0.96)
                 
    path = RESULT_DIR / "real_results_dashboard.png"
    plt.savefig(path, dpi=300, bbox_inches='tight')
    plt.close()
    print(f"Combined dashboard saved to: {path}")

def main():
    print("=========================================================")
    # Generar todos los gráficos
    plot_metrics_comparison()
    plot_latencies_comparison()
    plot_classes_comparison()
    plot_combined_dashboard()
    print("=========================================================")
    print("SUCCESS! All real scientific graphics have been created")
    print("in high resolution inside the 'results/' folder.")
    print("=========================================================")

if __name__ == "__main__":
    main()
