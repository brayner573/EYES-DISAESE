# 👁️ EYES-DISEASE — Sistema Inteligente de Diagnóstico Ocular

[![Licencia](https://img.shields.io/badge/Licencia-Investigaci%C3%B3n%20%2F%20Educativa-blue.svg)](LICENSE)
[![Python](https://img.shields.io/badge/Python-3.10-blue.svg?logo=python&logoColor=white)](https://python.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg?logo=php&logoColor=white)](https://php.net)
[![PyTorch](https://img.shields.io/badge/PyTorch-2.2%2B-EE4C2C.svg?logo=pytorch&logoColor=white)](https://pytorch.org)
[![YOLO](https://img.shields.io/badge/Ultralytics-YOLOv8%20%2F%20YOLOv11-008080.svg)](https://ultralytics.com)
[![Database](https://img.shields.io/badge/Database-MySQL-blue.svg?logo=mysql&logoColor=white)](https://mysql.com)

Un sistema profesional end-to-end de clasificación y detección de patologías oculares a partir de imágenes de fondo de ojo (oftalmoscopio). Este ecosistema integra modelos de Deep Learning de última generación con una plataforma web interactiva y segura para la gestión de pacientes y diagnósticos en tiempo real.

---

## 🏗️ Arquitectura del Sistema

El ecosistema se divide en dos componentes principales perfectamente integrados:

```mermaid
graph TD
    subgraph AI Core [🔬 eye_disease_ai]
        A[Original Images] --> B[scripts/split_data.py]
        B --> C[Dataset Train/Val/Test]
        C --> D[YOLOv8 / YOLOv11 Classifiers]
        C --> E[ResNet50 / DenseNet / EfficientNet]
        D --> F[Results & Evaluation Metrics]
        E --> F
    end

    subgraph Web App [💻 eye_ai_web]
        G[PHP MVC Web Portal] -->|Upload Image| H[PredictionController]
        H -->|Execute Inference| I[python_ai/predict_web.py]
        I -->|Load Best Weights| D
        I -->|Return JSON Predictions| H
        H -->|Save Results| J[(MySQL Database)]
        H -->|Display Side-by-Side| K[User View]
    end
```

### 1. `eye_disease_ai` (AI Core & Scripts de Entrenamiento)
Módulo encargado del procesamiento, entrenamiento, validación y comparación de modelos de Deep Learning.
- **Modelos Soportados**: YOLOv8, YOLOv11, ResNet50, DenseNet, EfficientNet, SUNet.
- **Flujo de Trabajo**: Preparación de datos ➔ Aumento de datos ➔ Entrenamiento en GPU ➔ Evaluación de métricas avanzadas (Accuracy, F1-Score, Matriz de Confusión).

### 2. `eye_ai_web` (Portal de Gestión y Diagnóstico Web)
Plataforma web profesional estructurada bajo el patrón **MVC (Modelo-Vista-Controlador)** en PHP vainilla, sin dependencias externas pesadas.
- **Módulos**: Autenticación segura, Panel de Usuario, Historial Clínico de Predicciones, Comparador de Modelos, Panel Administrativo completo para gestionar usuarios y monitorear logs.
- **Inferencia en Caliente**: Conexión directa mediante llamadas a scripts en segundo plano con PyTorch para la predicción inmediata de imágenes subidas por el usuario.

---

## 📋 Clases Clínicas Detectadas

El sistema analiza imágenes fundoscópicas clasificándolas en 5 categorías fundamentales:

| Icono | Patología | Descripción Médica |
|:---:|---|---|
| 👁️‍🗨️ | **Cataract** | Pérdida de transparencia del cristalino que disminuye la visión. |
| 🩸 | **Diabetic Retinopathy** | Daño microvascular en la retina debido a complicaciones de la diabetes. |
| 🩺 | **Glaucoma** | Neuropatía óptica progresiva asociada a elevación de la presión intraocular. |
| 🩹 | **Retina Disease** | Otras patologías y desprendimientos en el tejido retinal. |
| ✅ | **Normal** | Ojo sano con estructuras vasculares, mácula y disco óptico normales. |

---

## 🚀 Requisitos Técnicos

### Requisitos de Hardware (Entrenamiento AI)
* **GPU**: NVIDIA RTX 5060 (16 GB VRAM recomendado, soporte CUDA 12.4).
* **RAM**: Mínimo 16 GB.
* **CPU**: Procesador moderno de 6+ núcleos.

### Requisitos de Servidor (Portal Web)
* **PHP**: Versión 8.1 o superior.
* **Base de Datos**: MySQL o MariaDB.
* **Servidor Web**: Apache (con módulo `rewrite` activo) o Nginx.
* **Entorno Local**: Compatible con XAMPP / Laragon.

---

## 📦 Instalación y Configuración del AI Core (`eye_disease_ai`)

### 1. Preparar el Entorno Virtual
Asegúrate de estar en el directorio raíz de la inteligencia artificial:
```powershell
cd D:\MODELO_EYES\eye_disease_ai
python -m venv venv
.\venv\Scripts\Activate.ps1
```

### 2. Instalar Dependencias con Aceleración CUDA
Instala PyTorch optimizado para tarjetas NVIDIA RTX y luego instala el resto de dependencias de visión artificial:
```powershell
# Instalar PyTorch con CUDA 12.4
pip install torch torchvision --index-url https://download.pytorch.org/whl/cu124

# Instalar dependencias del proyecto
pip install -r requirements.txt
```

### 3. Preparación y División del Dataset
Para entrenar los modelos, coloca tus imágenes en `raw_data/` en subcarpetas según cada clase, luego ejecuta:
```powershell
# Crear estructura de carpetas
python scripts/setup_folders.py

# Dividir dataset en conjuntos de entrenamiento, validación y prueba (70/15/15)
python scripts/split_data.py
```

### 4. Entrenamiento y Evaluación
Puedes entrenar los clasificadores con un solo comando:
```powershell
# Entrenar YOLOv8 Classification
python scripts/train_yolov8.py

# Entrenar YOLOv11 Classification
python scripts/train_yolo11.py

# Entrenar ResNet50
python scripts/train_resnet.py

# Evaluar y comparar modelos generados
python scripts/evaluate.py
```

---

## 💻 Instalación y Configuración del Portal Web (`eye_ai_web`)

### 1. Configurar Base de Datos MySQL
1. Inicia tu servidor local de base de datos (por ejemplo MySQL en XAMPP).
2. Importa el archivo SQL para inicializar el esquema de tablas, roles y el usuario administrador inicial:
   ```bash
   mysql -u root -p < eye_ai_web/database/eye_ai.sql
   ```
3. La configuración por defecto se encuentra en `eye_ai_web/config/database.php`. Modifica el usuario y contraseña si es necesario.

### 2. Acceso Inicial a la Plataforma
El script SQL creará por defecto una cuenta de Administrador con los siguientes datos de acceso:
* **URL**: `http://localhost/eye_ai_web/`
* **Correo Electrónico**: `admin@eyeai.com`
* **Contraseña**: `Admin123!`

---

## 📊 Resultados Estadísticos Consolidados (3-Seeds & TTA)

El rendimiento global de cada modelo se evaluó rigurosamente bajo tres semillas independientes (`42`, `123`, `2024`) sobre el conjunto completo de prueba de **1,301 imágenes**. Las métricas se expresan como **Media ± Desviación Estándar ($\mu \pm \sigma$)** tras aplicar un ensemble de **Test-Time Augmentation (TTA)** geométrico de 5 vistas:

| Arquitectura de Red | Exactitud (Accuracy) | F1-Score (Ponderado) | AUC-ROC (Macro OvR) | Latencia TTA (ms/imagen) |
| :--- | :---: | :---: | :---: | :---: |
| **YOLOv8m-cls** | $0.7569 \pm 0.1079$ | $0.7586 \pm 0.1068$ | $0.9286 \pm 0.0440$ | $\mathbf{26.6 \pm 0.0\ ms}$ |
| **YOLO11m-cls** | $\mathbf{0.7584 \pm 0.1192}$ | $0.7434 \pm 0.1312$ | $0.8987 \pm 0.0627$ | $28.3 \pm 0.2\ ms}$ |
| **ResNet50** | $0.7525 \pm 0.1039$ | $\mathbf{0.7667 \pm 0.0944}$ | $\mathbf{0.9335 \pm 0.0354}$ | $65.1 \pm 4.9\ ms}$ |
| **Swin Transformer (SUNet)**| $0.7382 \pm 0.0893$ | $0.7469 \pm 0.0848$ | $0.9269 \pm 0.0343$ | $32.4 \pm 0.2\ ms}$ |
| **DenseNet121** | $0.6920 \pm 0.0995$ | $0.7163 \pm 0.0819$ | $0.9256 \pm 0.0335$ | $39.3 \pm 0.3\ ms}$ |
| **EfficientNetV2-S** | $0.6144 \pm 0.0377$ | $0.5813 \pm 0.0578$ | $0.8590 \pm 0.0240$ | $41.7 \pm 0.1\ ms}$ |

* **AUC-ROC Sobresaliente**: Las redes basadas en **ResNet50** ($\mathbf{0.9335}$), **YOLOv8m-cls** ($0.9286$) y **Swin Transformer** ($0.9269$) obtuvieron áreas bajo la curva extremadamente sólidas para discriminar patologías de manera robusta y generalizable.
* **Eficiencia de Inferencia**: Los clasificadores de la familia **YOLO** demostraron una velocidad excepcional, completando el análisis de 5 vistas geométricas (TTA) en menos de **29 milisegundos por paciente** en GPU.

---

## ⚡ Optimizaciones Aplicadas
* **Mixed Precision (AMP)**: Reducción del uso de VRAM de GPU en un 50% con aceleración en operaciones de punto flotante de 16-bits.
* **cuDNN Auto-Tuning**: Benchmark automático para encontrar los kernels de convolución más rápidos en la GPU disponible.
* **Estructura MVC Limpia**: Separación estricta de responsabilidades en PHP que garantiza rapidez y facilidad de mantenimiento.
* **Seguridad Incorporada**: Prevención de inyección SQL mediante consultas preparadas PDO, sanitización de entradas, protección contra ataques CSRF y hashes seguros para contraseñas (`PASSWORD_BCRYPT`).

---

## 📄 Licencia y Uso
Este software se distribuye exclusivamente bajo licencia de **Investigación Científica y Uso Educativo**. No se recomienda su empleo como diagnóstico clínico único sin la supervisión y validación de un profesional oftalmólogo matriculado.