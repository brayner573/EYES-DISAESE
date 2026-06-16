import 'dart:io';
import 'dart:math' as math;
import 'dart:typed_data';
import 'package:flutter/services.dart';
import 'package:onnxruntime/onnxruntime.dart';
import 'image_processor.dart';

class MLService {
  static final MLService _instance = MLService._internal();
  factory MLService() => _instance;
  MLService._internal();

  OrtSession? _session;
  String? _loadedModelKey;
  bool _isInitialized = false;

  final List<String> _classes = [
    'cataract',
    'diabetic_retinopathy',
    'glaucoma',
    'normal',
    'retina_disease'
  ];

  final Map<String, Map<String, dynamic>> _clinicalClasses = {
    'cataract': {
      'label': 'Catarata',
      'description': 'Opacidad del cristalino del ojo que causa visión borrosa y disminución de la agudeza visual.',
      'urgency': 'Media',
      'severity': 'Moderada',
      'advice': 'Consulte a un oftalmólogo para evaluar el nivel de opacidad. El tratamiento no siempre es inmediato a menos que afecte la calidad de vida.',
      'treatment': 'Cirugía ambulatoria para extraer el cristalino opaco y reemplazarlo por un lente intraocular artificial.',
      'symptoms': 'Visión borrosa o nublada, dificultad con la visión nocturna, sensibilidad a la luz y resplandores.',
      'warning': 'La cirugía es altamente efectiva y segura. No deje que progrese hasta la ceguera.'
    },
    'diabetic_retinopathy': {
      'label': 'Retinopatía Diabética',
      'description': 'Complicación de la diabetes que daña los vasos sanguíneos del tejido sensible a la luz en la parte posterior del ojo (retina).',
      'urgency': 'Alta',
      'severity': 'Grave',
      'advice': 'Debe acudir con urgencia al oftalmólogo y a su endocrinólogo. El control del azúcar en sangre es crítico e inmediato.',
      'treatment': 'Control estricto de la diabetes, terapia con láser (fotocoagulación), inyecciones intravítreas (anti-VEGF) o vitrectomía.',
      'symptoms': 'Manchas o hebras oscuras flotando en la visión, visión fluctuante, áreas de visión oscuras o vacías.',
      'warning': 'Si no se trata a tiempo, la retinopatía diabética puede causar ceguera irreversible.'
    },
    'glaucoma': {
      'label': 'Glaucoma',
      'description': 'Grupo de afecciones oculares que dañan el nervio óptico, a menudo asociadas con una presión intraocular anormalmente alta.',
      'urgency': 'Urgencia Médica',
      'severity': 'Muy Grave',
      'advice': 'Acuda a un especialista inmediatamente. El daño al nervio óptico es irreversible, pero el progreso se puede detener con medicación rápida.',
      'treatment': 'Gotas oftalmológicas diarias para reducir la presión intraocular, medicamentos orales, tratamiento con láser o microcirugía.',
      'symptoms': 'A menudo asintomático en etapas tempranas. Luego: pérdida de visión periférica, halos alrededor de las luces, dolor ocular severo.',
      'warning': '¡El Glaucoma es conocido como "el ladrón silencioso de la vista"! El tratamiento diario es estricto y de por vida.'
    },
    'normal': {
      'label': 'Normal (Sano)',
      'description': 'No se han detectado anomalías significativas en la retina o el fondo de ojo. El globo ocular parece estar en condiciones saludables.',
      'urgency': 'Baja',
      'severity': 'Ninguna',
      'advice': '¡Excelente noticia! Mantenga sus buenos hábitos. Un resultado de IA normal es un buen indicador, pero no reemplaza un chequeo clínico.',
      'treatment': 'Ninguno requerido. Mantener hábitos de higiene visual.',
      'symptoms': 'Visión clara y sin molestias crónicas.',
      'warning': 'Recuerde que el análisis por IA es complementario. Si presenta dolor ocular, pérdida súbita de visión o destellos, acuda a urgencias.'
    },
    'retina_disease': {
      'label': 'Enfermedad Retiniana',
      'description': 'Anomalía inespecífica en la retina (como degeneración macular o desgarros) que afecta el tejido sensible a la luz.',
      'urgency': 'Alta',
      'severity': 'Grave',
      'advice': 'Se requiere un diagnóstico diferencial urgente. Las enfermedades de la retina pueden avanzar rápidamente y poner en riesgo la visión.',
      'treatment': 'Inyecciones antiangiogénicas (anti-VEGF), terapia fotodinámica láser o cirugía vitreorretiniana.',
      'symptoms': 'Visión central distorsionada o borrosa, punto ciego en el centro del campo visual, destellos de luz repentinos.',
      'warning': '¡Importante! No se frote los ojos ni realice esfuerzos físicos fuertes hasta ser evaluado por un retinólogo.'
    },
  };

  // Inicializar variables de ONNX
  Future<void> _initEnv() async {
    if (_isInitialized) return;
    OrtEnv.instance.init();
    _isInitialized = true;
  }

  // Cargar modelo dinámicamente según elección
  Future<void> loadModel(String modelKey) async {
    if (_session != null && _loadedModelKey == modelKey) return; // Ya está cargado

    await _initEnv();

    // Liberar sesión previa si existe
    _session?.release();
    _session = null;

    final String assetPath = modelKey == 'yolov8'
        ? 'assets/models/yolov8_quant.onnx'
        : 'assets/models/resnet50_quant.onnx';

    try {
      final modelData = await rootBundle.load(assetPath);
      final modelBytes = modelData.buffer.asUint8List();

      final sessionOptions = OrtSessionOptions();
      sessionOptions.setSessionGraphOptimizationLevel(GraphOptimizationLevel.ortEnableAll);

      _session = OrtSession.fromBuffer(modelBytes, sessionOptions);
      _loadedModelKey = modelKey;
      print("Modelo local ONNX '$modelKey' cargado correctamente.");
    } catch (e) {
      print("Error al cargar el modelo local '$modelKey': $e");
      rethrow;
    }
  }

  // Ejecutar inferencia localmente
  Future<Map<String, dynamic>?> runInference(File imageFile, String modelKey) async {
    // 1. Asegurar que el modelo esté cargado
    await loadModel(modelKey);

    if (_session == null) {
      throw Exception("No se pudo iniciar la sesión del modelo de IA.");
    }

    final stopwatch = Stopwatch()..start();

    // 2. Preprocesar imagen
    final Float32List inputData = await ImageProcessor.preprocess(imageFile);

    // 3. Crear tensor de entrada [1, 3, 224, 224]
    final shape = [1, 3, 224, 224];
    final inputTensor = OrtValueTensor.createTensorWithDataList(inputData, shape);

    // Mapear nombres según el modelo (YOLOv8 vs PyTorch original)
    final inputName = modelKey == 'yolov8' ? 'images' : 'input';
    final inputs = {inputName: inputTensor};

    final runOptions = OrtRunOptions();
    List<OrtValue?> outputs;

    try {
      outputs = _session!.run(runOptions, inputs);
    } catch (e) {
      inputTensor.release();
      runOptions.release();
      print("Error durante la inferencia local: $e");
      return null;
    }

    if (outputs.isEmpty || outputs.first == null) {
      inputTensor.release();
      runOptions.release();
      throw Exception("Inferencia fallida: salida vacía.");
    }

    // 4. Procesar salida [1, 5]
    final outputValue = outputs.first!.value;
    List<double> logits;

    if (outputValue is List<List<double>>) {
      logits = outputValue[0];
    } else if (outputValue is List<List<dynamic>>) {
      logits = outputValue[0].map((e) => (e as num).toDouble()).toList();
    } else {
      inputTensor.release();
      for (var element in outputs) {
        element?.release();
      }
      runOptions.release();
      throw Exception("Tipo de salida no soportado: ${outputValue.runtimeType}");
    }

    // Calcular probabilidades aplicando Softmax
    final List<double> probabilities = _softmax(logits);
    stopwatch.stop();

    // Obtener la clase con mayor confianza
    double maxVal = -1.0;
    int maxIdx = 0;
    for (int i = 0; i < probabilities.length; i++) {
      if (probabilities[i] > maxVal) {
        maxVal = probabilities[i];
        maxIdx = i;
      }
    }

    final String predictedClass = _classes[maxIdx];
    final double confidence = maxVal * 100;

    // Crear mapa de todas las predicciones
    final Map<String, double> allPredictions = {};
    for (int i = 0; i < _classes.length; i++) {
      allPredictions[_classes[i]] = probabilities[i] * 100;
    }

    // Liberar memoria nativa
    inputTensor.release();
    for (var element in outputs) {
      element?.release();
    }
    runOptions.release();

    final String modelNameFormatted = modelKey == 'yolov8' ? 'YOLOv8 (Offline)' : 'ResNet50 (Offline)';

    return {
      'status': 'success',
      'predicted_class': predictedClass,
      'confidence': confidence,
      'model_used': modelNameFormatted,
      'all_predictions': allPredictions,
      'processing_time': stopwatch.elapsedMilliseconds.toDouble(),
      'clinical_data': _clinicalClasses[predictedClass] ?? {}
    };
  }

  // Softmax
  List<double> _softmax(List<double> logits) {
    double maxVal = logits.reduce(math.max);
    List<double> expScores = logits.map((val) => math.exp(val - maxVal)).toList();
    double sumExp = expScores.reduce((a, b) => a + b);
    return expScores.map((score) => score / sumExp).toList();
  }

  // Liberar recursos
  void dispose() {
    _session?.release();
    _session = null;
    _loadedModelKey = null;
  }
}
