import cv2
import numpy as np
import mediapipe as mp
from PIL import Image

# Inicializar MediaPipe Face Mesh
mp_face_mesh = mp.solutions.face_mesh
face_mesh = mp_face_mesh.FaceMesh(
    static_image_mode=True,
    max_num_faces=1,
    refine_landmarks=True,
    min_detection_confidence=0.5
)

# Índices de los puntos (landmarks) de los ojos en MediaPipe
LEFT_EYE_INDICES = [33, 7, 163, 144, 145, 153, 154, 155, 133, 173, 157, 158, 159, 160, 161, 246]
RIGHT_EYE_INDICES = [362, 382, 381, 380, 374, 373, 390, 249, 263, 466, 388, 387, 386, 385, 384, 398]

def crop_eye_from_image(img_path, margin=0.5):
    """
    Toma una imagen (ruta), detecta el rostro, y recorta el ojo.
    Si hay dos ojos, recorta el que se vea más grande o central.
    Si no detecta rostro (ej: la foto ya es de un ojo), devuelve la original.
    
    Args:
        img_path (str or Path): Ruta de la imagen.
        margin (float): Margen extra alrededor del ojo (0.5 = 50% extra).
        
    Returns:
        PIL.Image: Imagen recortada (o la original si no hay rostro).
    """
    image = cv2.imread(str(img_path))
    if image is None:
        raise ValueError(f"No se pudo leer la imagen: {img_path}")
        
    image_rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    results = face_mesh.process(image_rgb)
    
    # Si no detecta rostro, asumimos que ya es una foto macro del ojo
    if not results.multi_face_landmarks:
        return Image.fromarray(image_rgb)
        
    h, w, _ = image.shape
    landmarks = results.multi_face_landmarks[0].landmark
    
    def get_bbox(indices):
        x_coords = [landmarks[i].x * w for i in indices]
        y_coords = [landmarks[i].y * h for i in indices]
        
        xmin, xmax = int(min(x_coords)), int(max(x_coords))
        ymin, ymax = int(min(y_coords)), int(max(y_coords))
        
        # Calcular ancho y alto del bounding box
        width = xmax - xmin
        height = ymax - ymin
        
        # Añadir margen
        xmin = max(0, int(xmin - width * margin))
        xmax = min(w, int(xmax + width * margin))
        ymin = max(0, int(ymin - height * margin))
        ymax = min(h, int(ymax + height * margin))
        
        # Hacer el recorte cuadrado (opcional pero ayuda a la IA)
        box_w = xmax - xmin
        box_h = ymax - ymin
        diff = abs(box_w - box_h)
        if box_w > box_h:
            ymin = max(0, ymin - diff // 2)
            ymax = min(h, ymax + diff // 2)
        else:
            xmin = max(0, xmin - diff // 2)
            xmax = min(w, xmax + diff // 2)
            
        return xmin, ymin, xmax, ymax, (xmax - xmin) * (ymax - ymin)

    # Obtener bounding boxes de ambos ojos
    left_bbox = get_bbox(LEFT_EYE_INDICES)
    right_bbox = get_bbox(RIGHT_EYE_INDICES)
    
    # Seleccionar el ojo más grande (por si la persona está de lado)
    if left_bbox[4] > right_bbox[4]:
        best_bbox = left_bbox
    else:
        best_bbox = right_bbox
        
    xmin, ymin, xmax, ymax, _ = best_bbox
    
    # Prevenir errores de dimensiones cero
    if xmax <= xmin or ymax <= ymin:
        return Image.fromarray(image_rgb)
        
    cropped_eye = image_rgb[ymin:ymax, xmin:xmax]
    return Image.fromarray(cropped_eye)

if __name__ == "__main__":
    # Prueba rápida si se ejecuta directamente
    print("Módulo utils_eye_crop.py listo.")
