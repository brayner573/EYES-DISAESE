import sys
import os
import json
import subprocess
from pathlib import Path

# Asegurar que las dependencias necesarias de ONNX estén instaladas
def install_dependencies():
    packages = ["onnx", "onnxruntime", "onnxscript"]
    for package in packages:
        try:
            __import__(package.replace("-", "_"))
        except ImportError:
            print(f"Instalando {package}...")
            subprocess.check_call([sys.executable, "-m", "pip", "install", package])

print("Comprobando dependencias...")
install_dependencies()

import torch
import torch.nn as nn
import onnx
from torchvision import models
from onnxruntime.quantization import quantize_dynamic, QuantType

# ─── Configuración de rutas ───────────────────────────────────────────────────
PROJECT_ROOT = Path(__file__).resolve().parent.parent
RESNET_WEIGHTS = PROJECT_ROOT / "models" / "resnet" / "resnet50_eye_disease_best.pth"
RESNET_CLASSES = PROJECT_ROOT / "models" / "resnet" / "resnet50_classes.json"
YOLO_WEIGHTS = PROJECT_ROOT / "models" / "yolo" / "yolov8_eye_disease_best.pt"

OUTPUT_DIR = PROJECT_ROOT.parent / "eye_disease_mobile" / "assets" / "models"
os.makedirs(OUTPUT_DIR, exist_ok=True)

# ─── 1. Exportar y Cuantizar ResNet50 ──────────────────────────────────────────
def export_resnet():
    print("\n--- Iniciando conversión de ResNet50 a ONNX ---")
    if not RESNET_WEIGHTS.exists():
        print(f"Error: Pesos de ResNet no encontrados en {RESNET_WEIGHTS}")
        return False
        
    with open(RESNET_CLASSES) as f:
        cmap = json.load(f)
    num_classes = len(cmap)
    print(f"Clases detectadas ({num_classes}): {list(cmap.values())}")

    # Reconstruir arquitectura exacta del modelo
    model = models.resnet50(weights=None)
    model.fc = nn.Sequential(
        nn.Dropout(0.5),
        nn.Linear(model.fc.in_features, 512),
        nn.BatchNorm1d(512),
        nn.ReLU(inplace=True),
        nn.Dropout(0.3),
        nn.Linear(512, 256),
        nn.BatchNorm1d(256),
        nn.ReLU(inplace=True),
        nn.Linear(256, num_classes)
    )

    # Cargar pesos
    checkpoint = torch.load(RESNET_WEIGHTS, map_location="cpu", weights_only=True)
    if isinstance(checkpoint, dict) and "model_state_dict" in checkpoint:
        model.load_state_dict(checkpoint["model_state_dict"])
    else:
        model.load_state_dict(checkpoint)
    model.eval()

    # Inferencia dummy para trazado
    dummy_input = torch.randn(1, 3, 224, 224)
    onnx_fp32_path = OUTPUT_DIR / "resnet50_eye_disease.onnx"

    print("Exportando a ONNX FP32...")
    torch.onnx.export(
        model,
        dummy_input,
        str(onnx_fp32_path),
        export_params=True,
        opset_version=18,
        do_constant_folding=True,
        input_names=['input'],
        output_names=['output']
    )
    print(f"ResNet50 FP32 guardado en: {onnx_fp32_path}")

    # Limpiar definiciones redundantes de pesos para evitar fallos en replace_gemm_with_matmul
    print("Limpiando inicializadores redundantes del grafo ONNX...")
    model_onnx = onnx.load(str(onnx_fp32_path))
    init_names = {i.name for i in model_onnx.graph.initializer}
    # Remover de inputs
    inputs_to_remove = [ipt for ipt in model_onnx.graph.input if ipt.name in init_names]
    for ipt in inputs_to_remove:
        model_onnx.graph.input.remove(ipt)
    # Remover de value_info
    vi_to_remove = [vi for vi in model_onnx.graph.value_info if vi.name in init_names]
    for vi in vi_to_remove:
        model_onnx.graph.value_info.remove(vi)
    onnx.save(model_onnx, str(onnx_fp32_path))

    # Cuantización dinámica a INT8
    onnx_int8_path = OUTPUT_DIR / "resnet50_quant.onnx"
    print("Cuantizando a INT8...")
    quantize_dynamic(
        model_input=str(onnx_fp32_path),
        model_output=str(onnx_int8_path),
        weight_type=QuantType.QUInt8
    )
    print(f"ResNet50 INT8 guardado en: {onnx_int8_path}")
    
    # Limpiar archivo FP32 para ahorrar espacio si se desea
    if onnx_fp32_path.exists():
        os.remove(onnx_fp32_path)
        print("Eliminado modelo FP32 temporal.")

    return True

# ─── 2. Exportar YOLOv8 ───────────────────────────────────────────────────────
def export_yolo():
    print("\n--- Iniciando conversión de YOLOv8 a ONNX ---")
    if not YOLO_WEIGHTS.exists():
        print(f"Error: Pesos de YOLOv8 no encontrados en {YOLO_WEIGHTS}")
        return False

    try:
        from ultralytics import YOLO
    except ImportError:
        print("Instalando ultralytics...")
        subprocess.check_call([sys.executable, "-m", "pip", "install", "ultralytics"])
        from ultralytics import YOLO

    print("Cargando modelo YOLOv8...")
    model = YOLO(str(YOLO_WEIGHTS))
    
    print("Exportando YOLOv8 a ONNX...")
    # Ultralytics exporta directamente al directorio del modelo original
    exported_path_str = model.export(format="onnx", half=True, dynamic=False)
    
    if exported_path_str:
        src_path = Path(exported_path_str)
        dest_path = OUTPUT_DIR / "yolov8_quant.onnx"
        if dest_path.exists():
            os.remove(dest_path)
        os.rename(src_path, dest_path)
        print(f"YOLOv8 ONNX FP16 movido y guardado en: {dest_path}")
        return True
    else:
        print("Error al exportar YOLOv8.")
        return False

if __name__ == "__main__":
    r_success = export_resnet()
    y_success = export_yolo()
    
    print("\n--- Resumen del Proceso ---")
    print(f"ResNet50 Exportación y Cuantización: {'EXITOSA' if r_success else 'FALLIDA'}")
    print(f"YOLOv8 Exportación y Cuantización: {'EXITOSA' if y_success else 'FALLIDA'}")
    print(f"Los modelos resultantes se encuentran en: {OUTPUT_DIR}")
