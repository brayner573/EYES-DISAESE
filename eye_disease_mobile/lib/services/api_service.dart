import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_routes.dart';

class ApiService {
  static const String _tokenKey = 'auth_token';

  // Guardar token en caché local
  static Future<void> saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
  }

  // Leer token guardado
  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  // Eliminar token (Cerrar sesión)
  static Future<void> clearToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
  }

  // Cabeceras HTTP comunes con Bearer Token
  static Future<Map<String, String>> _getHeaders() async {
    final token = await getToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  // Login
  static Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse(ApiRoutes.login),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'email': email, 'password': password}),
      );
      
      final data = jsonDecode(response.body);
      if (response.statusCode == 200 && data['status'] == 'success') {
        await saveToken(data['token']);
      }
      return data;
    } catch (e) {
      return {'status': 'error', 'message': 'Error de conexión: $e'};
    }
  }

  // Registro
  static Future<Map<String, dynamic>> register(String name, String email, String password, String confirmPassword) async {
    try {
      final response = await http.post(
        Uri.parse(ApiRoutes.register),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'name': name,
          'email': email,
          'password': password,
          'password_confirm': confirmPassword
        }),
      );
      return jsonDecode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': 'Error de conexión: $e'};
    }
  }

  // Obtener Historial
  static Future<Map<String, dynamic>> getHistory() async {
    try {
      final headers = await _getHeaders();
      final response = await http.get(Uri.parse(ApiRoutes.history), headers: headers);
      return jsonDecode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': 'Error al cargar historial: $e'};
    }
  }

  // Realizar Predicción (Subir imagen de la cámara)
  static Future<Map<String, dynamic>> uploadPrediction(File imageFile, String modelKey) async {
    try {
      final token = await getToken();
      final uri = Uri.parse(ApiRoutes.predict);
      
      final request = http.MultipartRequest('POST', uri);
      request.headers.addAll({
        if (token != null) 'Authorization': 'Bearer $token',
      });

      // Adjuntar archivo
      request.files.add(await http.MultipartFile.fromPath('image', imageFile.path));
      
      // Adjuntar parámetros POST
      request.fields['model'] = modelKey;

      final streamedResponse = await request.send();
      final response = await http.Response.fromStream(streamedResponse);
      
      return jsonDecode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': 'Error al enviar imagen: $e'};
    }
  }
}
