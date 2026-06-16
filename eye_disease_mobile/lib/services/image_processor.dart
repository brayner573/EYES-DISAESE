import 'dart:io';
import 'dart:typed_data';
import 'package:image/image.dart' as img;

class ImageProcessor {
  // Redimensiona y normaliza la imagen a Float32List en formato BCHW (PyTorch)
  static Future<Float32List> preprocess(File imageFile) async {
    final bytes = await imageFile.readAsBytes();
    final image = img.decodeImage(bytes);
    
    if (image == null) {
      throw Exception('No se pudo decodificar la imagen.');
    }

    // 1. Center Crop (para enfocar el ojo/retina)
    int size = image.width < image.height ? image.width : image.height;
    int x = (image.width - size) ~/ 2;
    int y = (image.height - size) ~/ 2;
    final cropped = img.copyCrop(image, x: x, y: y, width: size, height: size);

    // 2. Resize a 224x224
    final resized = img.copyResize(cropped, width: 224, height: 224);

    // 3. Crear Float32List en formato BCHW (1 * 3 * 224 * 224 = 150,528 elementos)
    final buffer = Float32List(1 * 3 * 224 * 224);
    
    // Medias y desviaciones estándar de ImageNet (usados en ResNet50)
    const mean = [0.485, 0.456, 0.406];
    const std = [0.229, 0.224, 0.225];

    int channelStride = 224 * 224;

    for (int y = 0; y < 224; y++) {
      for (int x = 0; x < 224; x++) {
        final pixel = resized.getPixel(x, y);
        
        // Obtener valores normalizados de los canales R, G, B
        double r = pixel.r / 255.0;
        double g = pixel.g / 255.0;
        double b = pixel.b / 255.0;

        // Normalizar
        double rNorm = (r - mean[0]) / std[0];
        double gNorm = (g - mean[1]) / std[1];
        double bNorm = (b - mean[2]) / std[2];

        int pixelIndex = y * 224 + x;
        buffer[pixelIndex] = rNorm;                   // Canal Red
        buffer[pixelIndex + channelStride] = gNorm;   // Canal Green
        buffer[pixelIndex + 2 * channelStride] = bNorm; // Canal Blue
      }
    }

    return buffer;
  }
}
