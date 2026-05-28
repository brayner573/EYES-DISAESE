import sys
import json
import time
from pathlib import Path
from PIL import Image
import torch
from torchvision import models, transforms
import torch.nn as nn

# ─── Herramientas de IA Adicionales ───────────────────────────────────────────
sys.path.append(r"D:\MODELO_EYES\eye_disease_ai\scripts")
try:
    from utils_eye_crop import crop_eye_from_image
except ImportError:
    crop_eye_from_image = None

# ─── Configuración ────────────────────────────────────────────────────────────
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

cnn_transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize([0.5, 0.5, 0.5], [0.5, 0.5, 0.5]),
])

# ─── Loaders ──────────────────────────────────────────────────────────────────
def load_resnet(num_classes):
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

def load_sunet(num_classes):
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

def load_effnet(num_classes):
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

def load_densenet(num_classes):
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

# ─── Predictors ───────────────────────────────────────────────────────────────
def predict_pytorch(img_path, model_type):
    weights_path = {
        "resnet50": RESNET_WEIGHTS, "sunet": SUNET_WEIGHTS,
        "densenet": CNN_WEIGHTS, "efficientnet": EFFNET_WEIGHTS
    }.get(model_type)
    
    classes_path = {
        "resnet50": RESNET_CLASSES, "sunet": SUNET_CLASSES,
        "densenet": CNN_CLASSES, "efficientnet": EFFNET_CLASSES
    }.get(model_type)

    if not weights_path.exists() or not classes_path.exists():
        return None

    with open(classes_path) as f:
        cmap = json.load(f)
    cnames = [cmap[str(i)] for i in range(len(cmap))]

    loaders = {"resnet50": load_resnet, "sunet": load_sunet, "densenet": load_densenet, "efficientnet": load_effnet}
    model = loaders[model_type](len(cnames))
    
    img = crop_eye_from_image(img_path).convert("RGB") if crop_eye_from_image else Image.open(img_path).convert("RGB")
    transform = infer_transform
    tensor = transform(img).unsqueeze(0).to(DEVICE)
    
    with torch.no_grad():
        probs = torch.softmax(model(tensor), dim=1).squeeze().cpu().numpy()
        
    idx = probs.argmax()
    names_map = {"resnet50": "ResNet50", "sunet": "Swin Transformer (SUNet)", "densenet": "DenseNet121", "efficientnet": "EfficientNetV2"}
    
    return {
        "class": cnames[idx],
        "confidence": round(float(probs[idx]) * 100, 2),
        "model": names_map[model_type],
        "all_predictions": {cnames[i]: round(float(p) * 100, 2) for i, p in enumerate(probs)}
    }

def predict_yolo_base(img_path, model_type):
    weights_path = YOLO11_WEIGHTS if model_type == "yolo11" else YOLO_WEIGHTS
    if not weights_path.exists(): return None
    
    from ultralytics import YOLO
    model = YOLO(str(weights_path))
    img = crop_eye_from_image(img_path).convert("RGB") if crop_eye_from_image else str(img_path)
    
    results = model.predict(img, verbose=False)
    probs, names = results[0].probs, results[0].names
    idx = probs.top1
    
    model_name = "YOLO11" if model_type == "yolo11" else "YOLOv8"
    return {
        "class": names[idx],
        "confidence": round(float(probs.top1conf) * 100, 2),
        "model": model_name,
        "all_predictions": {names[i]: round(float(p) * 100, 2) for i, p in enumerate(probs.data.cpu().numpy())}
    }

def main():
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Missing arguments"}))
        sys.exit(1)
        
    img_path, model_type = Path(sys.argv[1]), sys.argv[2].lower()
    
    if not img_path.exists():
        print(json.dumps({"error": "Image not found"}))
        sys.exit(1)
        
    try:
        if model_type in ["resnet50", "sunet", "densenet", "efficientnet"]:
            result = predict_pytorch(img_path, model_type)
        elif model_type in ["yolov8", "yolo11"]:
            result = predict_yolo_base(img_path, model_type)
        else:
            print(json.dumps({"error": f"Unknown model type: {model_type}"}))
            sys.exit(1)
            
        if result is None:
            print(json.dumps({"error": f"Model weights not found for {model_type}. Train it first."}))
            sys.exit(1)
            
        print(json.dumps(result))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()
