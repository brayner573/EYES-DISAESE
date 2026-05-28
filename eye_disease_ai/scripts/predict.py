"""
predict.py — Inferencia con imagen nueva
==========================================
Usa AMBOS modelos (ResNet50 + YOLOv8) para predecir la enfermedad
en una imagen nueva de oftalmoscopio.

Uso:
    python scripts/predict.py <ruta_imagen>
    python scripts/predict.py foto_ojo.jpg
    python scripts/predict.py              # usa imagen aleatoria del test set
"""

import sys, json, random, time
from pathlib import Path

import torch
import torch.nn as nn
from torchvision import models, transforms
from PIL import Image

# Importar el nuevo módulo de recorte inteligente
try:
    from utils_eye_crop import crop_eye_from_image
except ImportError:
    import sys
    sys.path.append(str(Path(__file__).parent))
    from utils_eye_crop import crop_eye_from_image

PROJECT_ROOT = Path(__file__).resolve().parent.parent
MODEL_DIR    = PROJECT_ROOT / "models"
DATASET_DIR  = PROJECT_ROOT / "dataset_split"

RESNET_WEIGHTS = MODEL_DIR / "resnet" / "resnet50_eye_disease_best.pth"
RESNET_CLASSES = MODEL_DIR / "resnet" / "resnet50_classes.json"
YOLO_WEIGHTS   = MODEL_DIR / "yolo" / "yolov8_eye_disease_best.pt"

DEVICE = torch.device("cuda" if torch.cuda.is_available() else "cpu")
IMG_SIZE = 224

# Colores ANSI
G, Y, R, C, W, B, RST = "\033[92m", "\033[93m", "\033[91m", "\033[96m", "\033[97m", "\033[1m", "\033[0m"

infer_transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225]),
])


def load_resnet(num_classes):
    """Carga modelo ResNet50 entrenado."""
    model = models.resnet50(weights=None)
    model.fc = nn.Sequential(
        nn.Dropout(0.4), nn.Linear(model.fc.in_features, 512),
        nn.BatchNorm1d(512), nn.ReLU(inplace=True),
        nn.Dropout(0.3), nn.Linear(512, 256), nn.ReLU(inplace=True),
        nn.Dropout(0.2), nn.Linear(256, num_classes),
    )
    checkpoint = torch.load(RESNET_WEIGHTS, map_location=DEVICE, weights_only=True)
    if isinstance(checkpoint, dict) and "model_state_dict" in checkpoint:
        model.load_state_dict(checkpoint["model_state_dict"])
    else:
        model.load_state_dict(checkpoint)
    model.eval().to(DEVICE)
    return model


def predict_resnet(model, class_names, img_path):
    # Usar el recorte automático con MediaPipe (o imagen original si falla)
    img = crop_eye_from_image(img_path).convert("RGB")
    tensor = infer_transform(img).unsqueeze(0).to(DEVICE)
    t0 = time.time()
    with torch.no_grad():
        logits = model(tensor)
        probs = torch.softmax(logits, dim=1).squeeze().cpu().numpy()
    elapsed = (time.time() - t0) * 1000
    idx = probs.argmax()
    return class_names[idx], float(probs[idx]), {class_names[i]: float(p) for i, p in enumerate(probs)}, elapsed


def predict_yolo(img_path):
    from ultralytics import YOLO
    model = YOLO(str(YOLO_WEIGHTS))
    
    # Usar el recorte automático con MediaPipe
    img = crop_eye_from_image(img_path).convert("RGB")
    
    t0 = time.time()
    results = model.predict(img, verbose=False)
    elapsed = (time.time() - t0) * 1000
    probs = results[0].probs
    names = results[0].names
    idx = probs.top1
    return names[idx], float(probs.top1conf), {names[i]: float(p) for i, p in enumerate(probs.data.cpu().numpy())}, elapsed


def print_result(model_name, cls, prob, class_probs, time_ms):
    bar_len = 25
    color = G if prob >= 0.7 else (Y if prob >= 0.4 else R)
    print(f"\n{B}{'─'*50}{RST}")
    print(f"  {C}{B}{model_name}{RST} ({time_ms:.1f} ms)")
    print(f"{'─'*50}")
    print(f"  Predicción : {B}{color}{cls.upper()}{RST}")
    print(f"  Confianza  : {color}{prob*100:.1f}%{RST}\n")
    for c, p in sorted(class_probs.items(), key=lambda x: -x[1]):
        filled = int(p * bar_len)
        bar = "█" * filled + "░" * (bar_len - filled)
        m = " ←" if c == cls else ""
        print(f"    {c:<22} {bar} {p*100:5.1f}%{m}")


def main():
    if len(sys.argv) > 1:
        img_path = Path(sys.argv[1])
        if not img_path.exists():
            print(f"{R}❌ No se encontró: {img_path}{RST}"); sys.exit(1)
    else:
        test_imgs = list((DATASET_DIR / "test").rglob("*.jpg")) + \
                    list((DATASET_DIR / "test").rglob("*.png"))
        if not test_imgs:
            print(f"{R}❌ No hay imágenes en test set{RST}"); sys.exit(1)
        img_path = random.choice(test_imgs)
        print(f"  🎲 Imagen aleatoria: {img_path}")

    print(f"\n{B}{'═'*50}")
    print(f"  🔬 INFERENCIA — ENFERMEDADES OCULARES")
    print(f"{'═'*50}{RST}")
    print(f"  📸 Imagen : {img_path.name}")
    print(f"  📂 Clase  : {img_path.parent.name}")
    print(f"  🖥️  Device: {DEVICE}")

    # ResNet50
    if RESNET_WEIGHTS.exists() and RESNET_CLASSES.exists():
        with open(RESNET_CLASSES) as f:
            cmap = json.load(f)
        cnames = [cmap[str(i)] for i in range(len(cmap))]
        model = load_resnet(len(cnames))
        cls, prob, probs, ms = predict_resnet(model, cnames, img_path)
        print_result("ResNet50", cls, prob, probs, ms)
    else:
        print(f"\n{Y}⚠️  ResNet50 no encontrado. Ejecuta train_resnet.py{RST}")

    # YOLOv8
    if YOLO_WEIGHTS.exists():
        cls, prob, probs, ms = predict_yolo(img_path)
        print_result("YOLOv8-cls", cls, prob, probs, ms)
    else:
        print(f"\n{Y}⚠️  YOLOv8 no encontrado. Ejecuta train_yolov8.py{RST}")

    print(f"\n{B}{'═'*50}{RST}\n")


if __name__ == "__main__":
    main()
