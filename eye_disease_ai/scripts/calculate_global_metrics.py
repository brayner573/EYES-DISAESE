import json
from pathlib import Path
import numpy as np

def main():
    results_dir = Path("D:/MODELO_EYES/eye_disease_ai/results")
    seeds = [42, 123, 2024]
    
    models = {
        "yolo11": "YOLO11m-cls",
        "yolov8": "YOLOv8m-cls",
        "resnet50": "ResNet50",
        "sunet": "Swin Transformer (SUNet)",
        "densenet121": "DenseNet121",
        "efficientnet": "EfficientNetV2-S"
    }
    
    classes = ["cataract", "diabetic_retinopathy", "glaucoma", "normal", "retina_disease"]
    
    print("| Modelo | Accuracy (%) | Precision Macro (%) | Recall Macro (%) | F1 Macro (%) | F1 Weighted (%) | AUC-ROC Macro | Latency (ms) |")
    print("|---|---|---|---|---|---|---|---|")
    
    for m_key, m_name in models.items():
        m_dir = results_dir / m_key
        if m_key == "yolo11":
            m_dir = results_dir / "yolo11"
        elif m_key == "yolov8":
            m_dir = results_dir / "yolo"
        elif m_key == "resnet50":
            m_dir = results_dir / "resnet"
        elif m_key == "densenet121":
            m_dir = results_dir / "densenet"
            
        accs, prec_macros, rec_macros, f1_macros, f1_weights, aucs, latencies = [], [], [], [], [], [], []
        
        # We also want per-class F1 for seed 42 (or mean per-class F1)
        per_class_f1s = {cls: [] for cls in classes}
        per_class_precs = {cls: [] for cls in classes}
        per_class_recs = {cls: [] for cls in classes}
        
        for seed in seeds:
            json_path = m_dir / f"metricas_seed{seed}.json"
            if not json_path.exists():
                print(f"Path not found: {json_path}")
                continue
            with open(json_path) as f:
                data = json.load(f)
            
            accs.append(data["accuracy"])
            f1_weights.append(data["f1_weighted"])
            aucs.append(data["auc_roc_macro"])
            latencies.append(data["avg_latency_ms"])
            
            # Calculate macro metrics
            cls_precs = [data["classes"][cls]["precision"] for cls in classes]
            cls_recs = [data["classes"][cls]["recall"] for cls in classes]
            cls_f1s = [data["classes"][cls]["f1_score"] for cls in classes]
            
            prec_macros.append(np.mean(cls_precs))
            rec_macros.append(np.mean(cls_recs))
            f1_macros.append(np.mean(cls_f1s))
            
            for cls in classes:
                per_class_f1s[cls].append(data["classes"][cls]["f1_score"])
                per_class_precs[cls].append(data["classes"][cls]["precision"])
                per_class_recs[cls].append(data["classes"][cls]["recall"])
                
        # Format string
        def fmt(vals):
            return f"{np.mean(vals)*100:.2f}±{np.std(vals)*100:.2f}"
            
        def fmt_raw(vals):
            return f"{np.mean(vals):.3f}±{np.std(vals):.3f}"
            
        print(f"| **{m_name}** | {fmt(accs)} | {fmt(prec_macros)} | {fmt(rec_macros)} | {fmt(f1_macros)} | {fmt(f1_weights)} | {fmt_raw(aucs)} | {np.mean(latencies):.2f}±{np.std(latencies):.2f} |")
        
        # Let's print the per-class metrics for seed 42 specifically, or the mean per-class metrics
        print(f"  Class F1-scores (mean±std):")
        for cls in classes:
            print(f"    - {cls}: F1={np.mean(per_class_f1s[cls])*100:.2f}±{np.std(per_class_f1s[cls])*100:.2f}%, Prec={np.mean(per_class_precs[cls])*100:.2f}%, Rec={np.mean(per_class_recs[cls])*100:.2f}%")

if __name__ == "__main__":
    main()
