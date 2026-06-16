class ApiRoutes {
  // Para pruebas en emulador Android, usar 10.0.2.2. Para iOS o dispositivo real usar la IP local.
  static const String baseUrl = 'http://10.0.2.2/eye_ai_web';

  static const String login = '$baseUrl/api/auth/login';
  static const String register = '$baseUrl/api/auth/register';
  static const String predict = '$baseUrl/api/prediction/run';
  static const String history = '$baseUrl/api/prediction/history';
  
  static String viewPrediction(int id) => '$baseUrl/api/prediction/view/$id';
}
