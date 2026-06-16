class LocalPrediction {
  final int? id;
  final String imagePath;
  final String predictedClass;
  final double confidence;
  final String modelUsed;
  final Map<String, double> allPredictions;
  final double processingTime;
  final String createdAt;
  final Map<String, dynamic> clinicalData;

  LocalPrediction({
    this.id,
    required this.imagePath,
    required this.predictedClass,
    required this.confidence,
    required this.modelUsed,
    required this.allPredictions,
    required this.processingTime,
    required this.createdAt,
    required this.clinicalData,
  });

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'imagePath': imagePath,
      'predictedClass': predictedClass,
      'confidence': confidence,
      'modelUsed': modelUsed,
      'allPredictions': allPredictions,
      'processingTime': processingTime,
      'createdAt': createdAt,
      'clinicalData': clinicalData,
    };
  }

  factory LocalPrediction.fromMap(Map<dynamic, dynamic> map) {
    return LocalPrediction(
      id: map['id'] as int?,
      imagePath: map['imagePath'] as String,
      predictedClass: map['predictedClass'] as String,
      confidence: (map['confidence'] as num).toDouble(),
      modelUsed: map['modelUsed'] as String,
      allPredictions: (map['allPredictions'] as Map<dynamic, dynamic>).map(
        (key, value) => MapEntry(key as String, (value as num).toDouble()),
      ),
      processingTime: (map['processingTime'] as num).toDouble(),
      createdAt: map['createdAt'] as String,
      clinicalData: Map<String, dynamic>.from(map['clinicalData'] as Map),
    );
  }
}
