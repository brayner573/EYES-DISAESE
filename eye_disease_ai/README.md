# 🔬 Eye Disease AI — Detección de Enfermedades Oculares

Sistema profesional de clasificación de enfermedades oculares mediante imágenes de oftalmoscopio, utilizando **YOLOv8 Classification** y **ResNet50 Transfer Learning**.

---

## 📋 Clases de Clasificación

| Clase | Descripción |
|---|---|
| `cataract` | Catarata ocular |
| `diabetic_retinopathy` | Retinopatía diabética |
| `glaucoma` | Glaucoma |
| `normal` | Ojo sano |
| `retina_disease` | Enfermedad de retina |

---

## 🏗️ Estructura del Proyecto

```
eye_disease_ai/
├── raw_data/                  # Imágenes originales por clase
├── dataset_split/             # Dataset dividido (train/val/test)
├── models/                    # Modelos entrenados
│   ├── yolo/
│   └── resnet/
├── results/                   # Métricas y gráficas
│   ├── yolo/
│   └── resnet/
├── notebooks/                 # Análisis exploratorio
├── scripts/                   # Scripts principales
│   ├── setup_folders.py       # Crear estructura
│   ├── split_data.py          # Dividir dataset
│   ├── train_yolov8.py        # Entrenar YOLOv8
│   ├── train_resnet.py        # Entrenar ResNet50
│   ├── evaluate.py            # Comparar modelos
│   └── predict.py             # Inferencia
├── configs/                   # Configuraciones YAML
├── requirements.txt
├── README.md
└── .gitignore
```

---

## ⚙️ Requisitos del Sistema

| Componente | Recomendado |
|---|---|
| GPU | NVIDIA RTX 5060 (16 GB VRAM) |
| CUDA | 12.4+ |
| Python | 3.10 |
| OS | Windows 10/11 |
| RAM | 16 GB mínimo |

---

## 🚀 Instalación Paso a Paso

### 1. Instalar CUDA Toolkit

1. Verifica tu GPU: `nvidia-smi`
2. Descarga CUDA Toolkit 12.4 desde: https://developer.nvidia.com/cuda-downloads
3. Instala con opciones por defecto
4. Verifica: `nvcc --version`

### 2. Crear Entorno Virtual

```powershell
# Navegar al proyecto
cd D:\MODELO_EYES\eye_disease_ai

# Crear entorno virtual
python -m venv venv

# Activar entorno (Windows PowerShell)
.\venv\Scripts\Activate.ps1

# Activar entorno (Windows CMD)
.\venv\Scripts\activate.bat
```

### 3. Instalar PyTorch con CUDA

```powershell
# PyTorch con CUDA 12.4 (RTX 5060)
pip install torch torchvision --index-url https://download.pytorch.org/whl/cu124
```

> **Verificar GPU:**
> ```python
> python -c "import torch; print(f'CUDA: {torch.cuda.is_available()}, GPU: {torch.cuda.get_device_name(0)}')"
> ```

### 4. Instalar Dependencias

```powershell
pip install -r requirements.txt
```

---

## 📦 Preparación del Dataset

### 1. Crear estructura de carpetas

```powershell
python scripts/setup_folders.py
```

### 2. Colocar imágenes

Coloca las imágenes en `raw_data/` organizadas por clase:

```
raw_data/
├── cataract/          # imágenes de cataratas
├── diabetic_retinopathy/
├── glaucoma/
├── normal/
└── retina_disease/
```

### 3. Dividir dataset (70/15/15)

```powershell
python scripts/split_data.py

# Opciones personalizadas:
python scripts/split_data.py --train 0.8 --val 0.1 --test 0.1 --clean
```

---

## 🎯 Entrenamiento

### Entrenar YOLOv8 Classification

```powershell
python scripts/train_yolov8.py

# Con parámetros personalizados:
python scripts/train_yolov8.py --epochs 100 --batch 32 --model yolov8s-cls.pt --patience 20
```

### Entrenar ResNet50 Transfer Learning

```powershell
python scripts/train_resnet.py

# Con parámetros personalizados:
python scripts/train_resnet.py --epochs-head 15 --epochs-fine 30 --patience 10
```

---

## 📊 Evaluación y Comparación

### Comparar ambos modelos

```powershell
python scripts/evaluate.py
```

Genera:
- Tabla comparativa de accuracy, precision, recall, F1
- Gráfica visual de métricas por clase
- JSON con resumen de resultados

---

## 🔍 Inferencia (Predicción)

```powershell
# Con imagen específica
python scripts/predict.py ruta/a/imagen_ojo.jpg

# Con imagen aleatoria del test set
python scripts/predict.py
```

Muestra predicciones de **ambos modelos** con probabilidades por clase.

---

## ⚡ Optimizaciones para RTX 5060

- **Mixed Precision (AMP)**: Activo por defecto — 2x velocidad
- **cuDNN Benchmark**: Auto-tuning de kernels
- **Batch size automático**: Ajuste según VRAM disponible
- **Gradient clipping**: Estabilidad en entrenamiento
- **Cosine annealing LR**: Convergencia suave
- **Pin memory**: Transferencia GPU optimizada

---

## 📈 Métricas Generadas

- Accuracy global
- Precision, Recall, F1-score por clase
- Confusion Matrix (PNG alta resolución)
- Curvas de entrenamiento (loss/accuracy)
- Reportes en formato texto y JSON
- Tiempo de inferencia promedio

---

## 🛠️ Tecnologías

- **PyTorch** 2.2+ con CUDA
- **Ultralytics** YOLOv8
- **ResNet50** (ImageNet pretrained)
- **scikit-learn** para métricas
- **matplotlib + seaborn** para visualización

---

## 📄 Licencia

Proyecto de investigación — Uso educativo y médico.
