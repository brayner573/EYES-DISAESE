import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'config/theme.dart';
import 'database/hive_helper.dart';
import 'models/local_prediction.dart';
import 'services/ml_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Inicializar base de datos local Hive
  await HiveHelper.init();
  
  runApp(const EyeDiseaseApp());
}

class EyeDiseaseApp extends StatelessWidget {
  const EyeDiseaseApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Eye Disease AI',
      theme: AppTheme.darkTheme,
      debugShowCheckedModeBanner: false,
      home: const DashboardScreen(),
    );
  }
}

// ─── PANTALLA PRINCIPAL: DASHBOARD & HISTORIAL LOCAL ────────────────
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({Key? key}) : super(key: key);

  @override
  _DashboardScreenState createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  List<LocalPrediction> _history = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadHistory();
  }

  // Cargar historial desde Hive
  void _loadHistory() {
    setState(() {
      _isLoading = true;
    });
    try {
      final list = HiveHelper.getPredictions();
      setState(() {
        _history = list;
      });
    } catch (e) {
      print("Error al cargar historial local: $e");
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  // Seleccionar fuente de imagen (Cámara o Galería)
  Future<void> _pickImage(ImageSource source) async {
    final picker = ImagePicker();
    final pickedFile = await picker.pickImage(source: source);
    if (pickedFile != null && mounted) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ImagePreviewScreen(imageFile: File(pickedFile.path)),
        ),
      ).then((_) {
        if (mounted) _loadHistory();
      }); // Recargar historial tras escanear
    }
  }

  void _showImageSourceDialog() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppTheme.cardColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 10),
            ListTile(
              leading: const Icon(Icons.camera_alt, color: AppTheme.primaryBlue),
              title: const Text('Tomar Foto', style: TextStyle(fontWeight: FontWeight.bold)),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library, color: AppTheme.accentGreen),
              title: const Text('Elegir de Galería', style: TextStyle(fontWeight: FontWeight.bold)),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.gallery);
              },
            ),
            const SizedBox(height: 10),
          ],
        ),
      ),
    );
  }

  // Eliminar un diagnóstico del historial
  Future<void> _deleteItem(int? id) async {
    if (id == null) return;
    await HiveHelper.deletePrediction(id);
    _loadHistory();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Diagnóstico eliminado del historial.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Eye Disease AI — Offline'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadHistory,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _history.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.remove_red_eye_outlined, size: 80, color: AppTheme.textSecondary),
                      const SizedBox(height: 16),
                      Text('No tienes diagnósticos guardados',
                          style: Theme.of(context).textTheme.titleLarge?.copyWith(color: AppTheme.textSecondary)),
                      const SizedBox(height: 8),
                      const Text('Presiona el botón para realizar un nuevo escaneo',
                          style: TextStyle(color: AppTheme.textSecondary)),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _history.length,
                  itemBuilder: (context, index) {
                    final item = _history[index];
                    final String label = item.clinicalData['label'] ?? item.predictedClass;
                    final double confidence = item.confidence;
                    final String date = item.createdAt;
                    final String urgency = item.clinicalData['urgency'] ?? 'Baja';

                    Color badgeColor = AppTheme.accentGreen;
                    if (urgency.toLowerCase().contains('urgencia')) {
                      badgeColor = AppTheme.dangerRed;
                    } else if (urgency.toLowerCase().contains('alta')) {
                      badgeColor = AppTheme.warningOrange;
                    } else if (urgency.toLowerCase().contains('media')) {
                      badgeColor = AppTheme.primaryBlue;
                    }

                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Dismissible(
                        key: Key(item.id.toString()),
                        direction: DismissDirection.endToStart,
                        background: Container(
                          alignment: Alignment.centerRight,
                          padding: const EdgeInsets.symmetric(horizontal: 20),
                          color: AppTheme.dangerRed.withOpacity(0.8),
                          child: const Icon(Icons.delete, color: Colors.white),
                        ),
                        onDismissed: (_) => _deleteItem(item.id),
                        child: ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: AppTheme.primaryBlue,
                            child: Icon(Icons.analytics_outlined, color: Colors.white),
                          ),
                          title: Text(
                            label,
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          subtitle: Text('Confianza: ${confidence.toStringAsFixed(1)}% • $date'),
                          trailing: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: badgeColor.withOpacity(0.2),
                              border: Border.all(color: badgeColor),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              urgency,
                              style: TextStyle(color: badgeColor, fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ),
                          onTap: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => ResultDetailScreen(prediction: item),
                              ),
                            );
                          },
                        ),
                      ),
                    );
                  },
                ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showImageSourceDialog,
        label: const Text('Nuevo Análisis'),
        icon: const Icon(Icons.add_a_photo_outlined),
        backgroundColor: AppTheme.primaryBlue,
      ),
    );
  }
}

// ─── PREVISUALIZACIÓN E INFERENCIA DE MODELO LOCAL ─────────────────
class ImagePreviewScreen extends StatefulWidget {
  final File imageFile;

  const ImagePreviewScreen({Key? key, required this.imageFile}) : super(key: key);

  @override
  _ImagePreviewScreenState createState() => _ImagePreviewScreenState();
}

class _ImagePreviewScreenState extends State<ImagePreviewScreen> {
  bool _isAnalyzing = false;
  String _selectedModel = 'yolov8';

  final Map<String, String> _models = {
    'yolov8': 'YOLOv8 (FP16)',
    'resnet50': 'ResNet50 (INT8)',
  };

  Future<void> _runInference() async {
    setState(() {
      _isAnalyzing = true;
    });

    try {
      // Inferencia con ONNX Runtime local
      final result = await MLService().runInference(widget.imageFile, _selectedModel);

      setState(() {
        _isAnalyzing = false;
      });

      if (result != null && result['status'] == 'success') {
        // Guardar resultado localmente en la base de datos de Hive
        final localPrediction = LocalPrediction(
          imagePath: widget.imageFile.path,
          predictedClass: result['predicted_class'],
          confidence: result['confidence'],
          modelUsed: result['model_used'],
          allPredictions: Map<String, double>.from(result['all_predictions']),
          processingTime: result['processing_time'],
          createdAt: DateTime.now().toString().substring(0, 19),
          clinicalData: Map<String, dynamic>.from(result['clinical_data']),
        );
        
        await HiveHelper.savePrediction(localPrediction);

        if (!mounted) return;

        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => ResultDetailScreen(prediction: localPrediction),
          ),
        );
      } else {
        _showErrorDialog('El análisis local falló. Por favor intente de nuevo.');
      }
    } catch (e) {
      setState(() {
        _isAnalyzing = false;
      });
      _showErrorDialog('Error del motor de IA: $e');
    }
  }

  void _showErrorDialog(String msg) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Error de Inferencia'),
        content: Text(msg),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Aceptar'),
          ),
        ],
      ),
    );
  }

  double _scale = 1.0;
  Offset _offset = Offset.zero;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Confirmar Imagen')),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: Container(
                  color: Colors.black,
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      Positioned.fill(
                        child: InteractiveViewer(
                          panEnabled: true,
                          scaleEnabled: true,
                          minScale: 0.1,
                          maxScale: 10.0,
                          boundaryMargin: const EdgeInsets.all(2000),
                          child: Center(
                            child: Image.file(
                              widget.imageFile,
                              fit: BoxFit.contain,
                            ),
                          ),
                        ),
                      ),
                      // Guía de encuadre ocular circular translúcida
                      IgnorePointer(
                        child: Container(
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: Colors.white.withOpacity(0.9),
                              width: 3,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.7),
                                spreadRadius: 3000,
                              ),
                            ],
                          ),
                          width: 260,
                          height: 260,
                        ),
                      ),
                      const IgnorePointer(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            SizedBox(height: 300),
                            Text(
                              "AJUSTE EL OJO EN EL CÍRCULO",
                              style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                                letterSpacing: 1.5,
                                shadows: [Shadow(color: Colors.black, blurRadius: 10)],
                              ),
                            ),
                            Text(
                              "(Pellizque para zoom o arrastre)",
                              style: TextStyle(
                                color: Colors.white70,
                                fontSize: 12,
                                shadows: [Shadow(color: Colors.black, blurRadius: 10)],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Seleccione el modelo para inferencia en el dispositivo:',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            DropdownButton<String>(
              value: _selectedModel,
              isExpanded: true,
              items: _models.entries.map((entry) {
                return DropdownMenuItem(value: entry.key, child: Text(entry.value));
              }).toList(),
              onChanged: (val) {
                if (val != null) setState(() => _selectedModel = val);
              },
            ),
            const SizedBox(height: 24),
            _isAnalyzing
                ? const Column(
                    children: [
                      CircularProgressIndicator(),
                      SizedBox(height: 12),
                      Text('Ejecutando inferencia local en NPU/GPU...'),
                    ],
                  )
                : ElevatedButton(
                    onPressed: _runInference,
                    child: const Text('Analizar en este dispositivo'),
                  ),
          ],
        ),
      ),
    );
  }
}

// ─── PANTALLA DE DETALLE DE DIAGNÓSTICO LOCAL ──────────────────────
class ResultDetailScreen extends StatelessWidget {
  final LocalPrediction prediction;

  const ResultDetailScreen({Key? key, required this.prediction}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final String label = prediction.clinicalData['label'] ?? prediction.predictedClass;
    final String description = prediction.clinicalData['description'] ?? 'No disponible';
    final String urgency = prediction.clinicalData['urgency'] ?? 'Baja';
    final String severity = prediction.clinicalData['severity'] ?? 'Ninguna';
    final String advice = prediction.clinicalData['advice'] ?? '';
    final String treatment = prediction.clinicalData['treatment'] ?? '';
    final String symptoms = prediction.clinicalData['symptoms'] ?? '';
    final String warning = prediction.clinicalData['warning'] ?? '';

    Color scoreColor = AppTheme.accentGreen;
    if (urgency.toLowerCase().contains('urgencia')) {
      scoreColor = AppTheme.dangerRed;
    } else if (urgency.toLowerCase().contains('alta')) {
      scoreColor = AppTheme.warningOrange;
    } else if (urgency.toLowerCase().contains('media')) {
      scoreColor = AppTheme.primaryBlue;
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Diagnóstico Ocular')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Tarjeta de Clase y Nivel de Confianza
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20.0),
                child: Column(
                  children: [
                    Text(
                      label,
                      style: Theme.of(context).textTheme.displayLarge?.copyWith(
                            color: scoreColor,
                            fontSize: 28,
                          ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Confianza: ${prediction.confidence.toStringAsFixed(1)}%',
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 12),
                    // Barra de progreso con color dinámico
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: LinearProgressIndicator(
                        value: prediction.confidence / 100,
                        minHeight: 12,
                        backgroundColor: AppTheme.darkBackground,
                        valueColor: AlwaysStoppedAnimation<Color>(scoreColor),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Motor: ${prediction.modelUsed} • Tiempo: ${prediction.processingTime.toStringAsFixed(0)} ms',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            // Nivel de Riesgo
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: scoreColor.withOpacity(0.15),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: scoreColor, width: 1.5),
              ),
              child: Row(
                children: [
                  Icon(Icons.health_and_safety_outlined, color: scoreColor),
                  const SizedBox(width: 12),
                  Text(
                    'Gravedad: $severity ($urgency)',
                    style: TextStyle(color: scoreColor, fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            // Descripción Médica
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text('Descripción General:', style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Text(description, style: const TextStyle(height: 1.4)),
                    if (symptoms.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      const Text('Síntomas Frecuentes:', style: TextStyle(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text(symptoms, style: const TextStyle(height: 1.4)),
                    ],
                    if (treatment.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      const Text('Tratamientos sugeridos:', style: TextStyle(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text(treatment, style: const TextStyle(height: 1.4)),
                    ],
                    if (advice.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      const Text('Consejo Clínico:', style: TextStyle(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text(advice, style: const TextStyle(height: 1.4)),
                    ],
                  ],
                ),
              ),
            ),
            if (warning.isNotEmpty) ...[
              const SizedBox(height: 16),
              Card(
                color: Colors.redAccent.withOpacity(0.1),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: const BorderSide(color: Colors.redAccent),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.warning_amber_outlined, color: Colors.redAccent),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          warning,
                          style: const TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold, height: 1.4),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Volver al Historial'),
            ),
          ],
        ),
      ),
    );
  }
}
