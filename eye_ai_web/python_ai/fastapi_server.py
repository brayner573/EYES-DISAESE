import sys
import json
import time
import logging
from pathlib import Path
from contextlib import asynccontextmanager
from typing import Optional, Dict, Any

from fastapi import FastAPI, HTTPException, Body
from pydantic import BaseModel
from PIL import Image
import torch
from torchvision import models, transforms
import torch.nn as nn

# Configurar logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("EyeAI-FastAPI")

# ─── Herramientas de IA Adicionales ───────────────────────────────────────────
sys.path.append(r"D:\MODELO_EYES\eye_disease_ai\scripts")
try:
    from utils_eye_crop import crop_eye_from_image
except ImportError:
    logger.warning("utils_eye_crop no encontrado. Se usará carga de imagen estándar.")
    crop_eye_from_image = None

# ─── Rutas y Configuración ────────────────────────────────────────────────────
PROJECT_ROOT = Path(r"D:\MODELO_EYES\eye_disease_ai")
MODEL_DIR    = PROJECT_ROOT / "models"

RESNET_WEIGHTS = MODEL_DIR / "resnet" / "resnet50_eye_disease_best.pth"
RESNET_CLASSES = MODEL_DIR / "resnet" / "resnet50_classes.json"
YOLO_WEIGHTS   = MODEL_DIR / "yolo" / "yolov8_eye_disease_best.pt"
YOLO11_WEIGHTS = MODEL_DIR / "yolo11" / "yolo11_eye_disease_best.pt"
SUNET_WEIGHTS  = MODEL_DIR / "sunet" / "sunet_best.pth"
SUNET_CLASSES  = MODEL_DIR / "sunet" / "sunet_classes.json"
CNN_WEIGHTS    = MODEL_DIR / "densenet" / "densenet_best.pth"
CNN_CLASSES    = MODEL_DIR / "densenet" / "densenet_classes.json"
EFFNET_WEIGHTS = MODEL_DIR / "efficientnet" / "efficientnet_v2_best.pth"
EFFNET_CLASSES = MODEL_DIR / "efficientnet" / "efficientnet_classes.json"

DEVICE = torch.device("cuda" if torch.cuda.is_available() else "cpu")
IMG_SIZE = 224

infer_transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225]),
])

# Almacenamiento de modelos precargados en memoria
LOADED_MODELS: Dict[str, Dict[str, Any]] = {}

# ─── Loaders de PyTorch ───────────────────────────────────────────────────────
def load_resnet_model(num_classes: int):
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
    checkpoint = torch.load(RESNET_WEIGHTS, map_location=DEVICE, weights_only=True)
    if isinstance(checkpoint, dict) and "model_state_dict" in checkpoint:
        model.load_state_dict(checkpoint["model_state_dict"])
    else:
        model.load_state_dict(checkpoint)
    model.eval().to(DEVICE)
    return model

def load_sunet_model(num_classes: int):
    model = models.swin_t(weights=None)
    in_features = model.head.in_features
    model.head = nn.Sequential(
        nn.Dropout(p=0.5),
        nn.Linear(in_features, 512),
        nn.LayerNorm(512),
        nn.ReLU(),
        nn.Dropout(p=0.3),
        nn.Linear(512, num_classes)
    )
    checkpoint = torch.load(SUNET_WEIGHTS, map_location=DEVICE, weights_only=True)
    if isinstance(checkpoint, dict) and "model_state_dict" in checkpoint:
        model.load_state_dict(checkpoint["model_state_dict"])
    else:
        model.load_state_dict(checkpoint)
    model.eval().to(DEVICE)
    return model

def load_effnet_model(num_classes: int):
    model = models.efficientnet_v2_s(weights=None)
    in_features = model.classifier[1].in_features
    model.classifier = nn.Sequential(
        nn.Dropout(p=0.4),
        nn.Linear(in_features, 256),
        nn.BatchNorm1d(256),
        nn.ReLU(),
        nn.Dropout(p=0.3),
        nn.Linear(256, num_classes)
    )
    checkpoint = torch.load(EFFNET_WEIGHTS, map_location=DEVICE, weights_only=True)
    if isinstance(checkpoint, dict) and "model_state_dict" in checkpoint:
        model.load_state_dict(checkpoint["model_state_dict"])
    else:
        model.load_state_dict(checkpoint)
    model.eval().to(DEVICE)
    return model

def load_densenet_model(num_classes: int):
    model = models.densenet121(weights=None)
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
    checkpoint = torch.load(CNN_WEIGHTS, map_location=DEVICE, weights_only=True)
    if isinstance(checkpoint, dict) and "model_state_dict" in checkpoint:
        model.load_state_dict(checkpoint["model_state_dict"])
    else:
        model.load_state_dict(checkpoint)
    model.eval().to(DEVICE)
    return model

# ─── Inicialización de Modelos al Arrancar ────────────────────────────────────
def preload_all_models():
    logger.info(f"Iniciando precarga de modelos en dispositivo: {DEVICE}")
    
    # 1. ResNet50
    if RESNET_WEIGHTS.exists() and RESNET_CLASSES.exists():
        try:
            with open(RESNET_CLASSES) as f: cmap = json.load(f)
            cnames = [cmap[str(i)] for i in range(len(cmap))]
            LOADED_MODELS["resnet50"] = {
                "model": load_resnet_model(len(cnames)),
                "classes": cnames,
                "display_name": "ResNet50",
                "type": "pytorch"
            }
            logger.info("ResNet50 cargado exitosamente en VRAM/RAM.")
        except Exception as e:
            logger.error(f"Error cargando ResNet50: {e}")

    # 2. SUNet
    if SUNET_WEIGHTS.exists() and SUNET_CLASSES.exists():
        try:
            with open(SUNET_CLASSES) as f: cmap = json.load(f)
            cnames = [cmap[str(i)] for i in range(len(cmap))]
            LOADED_MODELS["sunet"] = {
                "model": load_sunet_model(len(cnames)),
                "classes": cnames,
                "display_name": "Swin Transformer (SUNet)",
                "type": "pytorch"
            }
            logger.info("SUNet cargado exitosamente en VRAM/RAM.")
        except Exception as e:
            logger.error(f"Error cargando SUNet: {e}")

    # 3. DenseNet121
    if CNN_WEIGHTS.exists() and CNN_CLASSES.exists():
        try:
            with open(CNN_CLASSES) as f: cmap = json.load(f)
            cnames = [cmap[str(i)] for i in range(len(cmap))]
            LOADED_MODELS["densenet"] = {
                "model": load_densenet_model(len(cnames)),
                "classes": cnames,
                "display_name": "DenseNet121",
                "type": "pytorch"
            }
            logger.info("DenseNet121 cargado exitosamente en VRAM/RAM.")
        except Exception as e:
            logger.error(f"Error cargando DenseNet121: {e}")

    # 4. EfficientNetV2
    if EFFNET_WEIGHTS.exists() and EFFNET_CLASSES.exists():
        try:
            with open(EFFNET_CLASSES) as f: cmap = json.load(f)
            cnames = [cmap[str(i)] for i in range(len(cmap))]
            LOADED_MODELS["efficientnet"] = {
                "model": load_effnet_model(len(cnames)),
                "classes": cnames,
                "display_name": "EfficientNetV2",
                "type": "pytorch"
            }
            logger.info("EfficientNetV2 cargado exitosamente en VRAM/RAM.")
        except Exception as e:
            logger.error(f"Error cargando EfficientNetV2: {e}")

    # 5. YOLOv8 & YOLO11
    try:
        from ultralytics import YOLO
        if YOLO_WEIGHTS.exists():
            LOADED_MODELS["yolov8"] = {
                "model": YOLO(str(YOLO_WEIGHTS)),
                "display_name": "YOLOv8",
                "type": "yolo"
            }
            logger.info("YOLOv8 cargado exitosamente.")
        if YOLO11_WEIGHTS.exists():
            LOADED_MODELS["yolo11"] = {
                "model": YOLO(str(YOLO11_WEIGHTS)),
                "display_name": "YOLO11",
                "type": "yolo"
            }
            logger.info("YOLO11 cargado exitosamente.")
    except Exception as e:
        logger.error(f"Error cargando modelos YOLO: {e}")

    logger.info(f"Precarga completada. Total modelos listos: {len(LOADED_MODELS)} ({list(LOADED_MODELS.keys())})")

@asynccontextmanager
async def lifespan(app: FastAPI):
    preload_all_models()
    yield
    LOADED_MODELS.clear()
    logger.info("Modelos liberados de memoria.")

app = FastAPI(
    title="Eye Disease AI Inference Microservice",
    version="2.0.0",
    lifespan=lifespan
)

# ─── Esquema de Petición ──────────────────────────────────────────────────────
class PredictionRequest(BaseModel):
    image_path: str
    model: str = "resnet50"

# ─── Endpoints ────────────────────────────────────────────────────────────────
@app.get("/health")
def health_check():
    return {
        "status": "online",
        "device": str(DEVICE),
        "loaded_models": list(LOADED_MODELS.keys()),
        "total_models": len(LOADED_MODELS)
    }

@app.post("/predict")
def predict(req: PredictionRequest):
    model_key = req.model.lower()
    img_path = Path(req.image_path)

    if not img_path.exists():
        raise HTTPException(status_code=400, detail=f"Imagen no encontrada en ruta: {req.image_path}")

    if model_key not in LOADED_MODELS:
        raise HTTPException(
            status_code=404,
            detail=f"El modelo '{model_key}' no está disponible o no se han encontrado sus pesos entrenados."
        )

    model_info = LOADED_MODELS[model_key]
    start_time = time.time()

    try:
        if model_info["type"] == "pytorch":
            model = model_info["model"]
            cnames = model_info["classes"]

            if crop_eye_from_image:
                img = crop_eye_from_image(img_path).convert("RGB")
            else:
                img = Image.open(img_path).convert("RGB")

            tensor = infer_transform(img).unsqueeze(0).to(DEVICE)
            with torch.no_grad():
                probs = torch.softmax(model(tensor), dim=1).squeeze().cpu().numpy()

            idx = probs.argmax()
            confidence = round(float(probs[idx]) * 100, 2)
            all_preds = {cnames[i]: round(float(p) * 100, 2) for i, p in enumerate(probs)}
            predicted_class = cnames[idx]

        elif model_info["type"] == "yolo":
            model = model_info["model"]
            img_input = crop_eye_from_image(img_path).convert("RGB") if crop_eye_from_image else str(img_path)
            results = model.predict(img_input, verbose=False)
            probs, names = results[0].probs, results[0].names
            idx = probs.top1
            confidence = round(float(probs.top1conf) * 100, 2)
            predicted_class = names[idx]
            all_preds = {names[i]: round(float(p) * 100, 2) for i, p in enumerate(probs.data.cpu().numpy())}

        processing_time = round((time.time() - start_time) * 1000, 2)

        return {
            "class": predicted_class,
            "confidence": confidence,
            "model": model_info["display_name"],
            "all_predictions": all_preds,
            "processing_time": processing_time
        }
    except Exception as e:
        logger.error(f"Error procesando predicción ({model_key}): {e}")
        raise HTTPException(status_code=500, detail=f"Error durante la inferencia: {str(e)}")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8000)
