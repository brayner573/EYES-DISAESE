import numpy as np
import pandas as pd
from scipy.stats import mannwhitneyu, chi2

def main():
    # Load files
    yolo_file = "D:/MODELO_EYES/eye_disease_ai/results/yolo11/probs_seed42.csv"
    resnet_file = "D:/MODELO_EYES/eye_disease_ai/results/resnet/probs_seed42.csv"

    yolo_df = pd.read_csv(yolo_file)
    resnet_df = pd.read_csv(resnet_file)

    # Columns: cataract, diabetic_retinopathy, glaucoma, normal, retina_disease
    classes = ["cataract", "diabetic_retinopathy", "glaucoma", "normal", "retina_disease"]

    yolo_probs = yolo_df[classes].values
    resnet_probs = resnet_df[classes].values

    yolo_preds = np.argmax(yolo_probs, axis=1)
    resnet_preds = np.argmax(resnet_probs, axis=1)

    yolo_labels = yolo_df["real_label"].values
    resnet_labels = resnet_df["real_label"].values

    # Assert labels match
    assert np.array_equal(yolo_labels, resnet_labels), "Labels do not match!"

    # Calculate correct predictions
    yolo_correct = (yolo_preds == yolo_labels)
    resnet_correct = (resnet_preds == resnet_labels)

    # Contingency table
    #           ResNet Correct    ResNet Incorrect
    # YOLO Corr        a                 b
    # YOLO Inco        c                 d
    a = np.sum(yolo_correct & resnet_correct)
    b = np.sum(yolo_correct & ~resnet_correct)
    c = np.sum(~yolo_correct & resnet_correct)
    d = np.sum(~yolo_correct & ~resnet_correct)

    print(f"Contingency Table:")
    print(f"a (both correct): {a}")
    print(f"b (YOLO correct, ResNet incorrect): {b}")
    print(f"c (YOLO incorrect, ResNet correct): {c}")
    print(f"d (both incorrect): {d}")

    # McNemar with Yates correction
    # chi2 = (|b - c| - 1)^2 / (b + c)
    if b + c > 0:
        chi2_stat = ((abs(b - c) - 1.0) ** 2) / (b + c)
        p_val = chi2.sf(chi2_stat, 1)
    else:
        chi2_stat = 0.0
        p_val = 1.0

    print(f"\nMcNemar test (Yates correction):")
    print(f"chi2: {chi2_stat:.4f}")
    print(f"p-value: {p_val:.6f}")

    # Mann-Whitney U test for YOLO11 correct vs incorrect confidences
    yolo_conf = np.max(yolo_probs, axis=1)
    correct_conf = yolo_conf[yolo_correct]
    incorrect_conf = yolo_conf[~yolo_correct]

    print(f"\nConfidences for YOLO11:")
    print(f"Mean correct confidence: {np.mean(correct_conf)*100:.2f}% (std: {np.std(correct_conf)*100:.2f}%)")
    print(f"Mean incorrect confidence: {np.mean(incorrect_conf)*100:.2f}% (std: {np.std(incorrect_conf)*100:.2f}%)")

    mw_stat, mw_p = mannwhitneyu(correct_conf, incorrect_conf, alternative='two-sided')
    print(f"Mann-Whitney U statistic: {mw_stat}, p-value: {mw_p}")

    # Expected Calibration Error (ECE) for YOLO11
    # Bins: 15
    n_bins = 15
    bin_boundaries = np.linspace(0, 1, n_bins + 1)
    ece = 0.0
    
    bin_accs = []
    bin_confs = []
    bin_sizes = []

    for i in range(n_bins):
        bin_lower = bin_boundaries[i]
        bin_upper = bin_boundaries[i + 1]
        
        # Find predictions in this bin
        in_bin = (yolo_conf > bin_lower) & (yolo_conf <= bin_upper)
        prop_in_bin = np.mean(in_bin)
        
        if prop_in_bin > 0:
            accuracy_in_bin = np.mean(yolo_correct[in_bin])
            avg_confidence_in_bin = np.mean(yolo_conf[in_bin])
            ece += prop_in_bin * np.abs(avg_confidence_in_bin - accuracy_in_bin)
            
            bin_accs.append(accuracy_in_bin)
            bin_confs.append(avg_confidence_in_bin)
            bin_sizes.append(np.sum(in_bin))

    print(f"\nExpected Calibration Error (ECE) with 15 bins: {ece*100:.2f}%")

if __name__ == "__main__":
    main()
