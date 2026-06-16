import 'package:hive_flutter/hive_flutter.dart';
import '../models/local_prediction.dart';

class HiveHelper {
  static const String _boxName = 'predictions_box';

  static Future<void> init() async {
    await Hive.initFlutter();
    await Hive.openBox(_boxName);
  }

  static Box _getBox() {
    return Hive.box(_boxName);
  }

  // Guardar predicción en base de datos local
  static Future<void> savePrediction(LocalPrediction prediction) async {
    final box = _getBox();
    
    // Obtener ID incremental
    int nextId = 1;
    if (box.isNotEmpty) {
      final keys = box.keys.map((k) => k as int).toList();
      keys.sort();
      nextId = keys.last + 1;
    }
    
    final pMap = prediction.toMap();
    pMap['id'] = nextId; // Asignar ID autoincremental local
    
    await box.put(nextId, pMap);
  }

  // Obtener historial de predicciones locales
  static List<LocalPrediction> getPredictions() {
    final box = _getBox();
    final List<LocalPrediction> list = [];
    
    for (var key in box.keys) {
      final value = box.get(key);
      if (value is Map) {
        list.add(LocalPrediction.fromMap(value));
      }
    }
    
    // Devolver ordenado de más reciente a más antiguo
    list.sort((a, b) => b.createdAt.compareTo(a.createdAt));
    return list;
  }

  // Eliminar un reporte
  static Future<void> deletePrediction(int id) async {
    final box = _getBox();
    await box.delete(id);
  }

  // Limpiar todo el historial
  static Future<void> clearAll() async {
    final box = _getBox();
    await box.clear();
  }
}
