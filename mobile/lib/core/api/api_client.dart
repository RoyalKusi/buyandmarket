import 'package:dio/dio.dart';

import '../storage/token_storage.dart';
import 'api_exception.dart';

/// Thin wrapper over the /api/v1 surface App\Http\Controllers\Api\V1\*
/// already exposes (TDD §7.1) — every call site goes through this one
/// class so the base URL, auth header, and error-shape translation
/// never drift between screens. Mirrors the web client's own "shared
/// authorization codepath" design: this app is just another Bearer-
/// token consumer of the exact same endpoints the Livewire frontend's
/// AJAX calls and any future third-party integration would use.
class ApiClient {
  ApiClient({required String baseUrl, TokenStorage? tokenStorage})
    : _tokenStorage = tokenStorage ?? TokenStorage(),
      _dio = Dio(
        BaseOptions(
          baseUrl: baseUrl,
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 15),
          headers: {'Accept': 'application/json'},
        ),
      ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStorage.readToken();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
  }

  final Dio _dio;
  final TokenStorage _tokenStorage;

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) async {
    return _unwrap(await _run(() => _dio.get(path, queryParameters: query)));
  }

  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) async {
    return _unwrap(await _run(() => _dio.post(path, data: data)));
  }

  Future<Map<String, dynamic>> patch(String path, {Map<String, dynamic>? data}) async {
    return _unwrap(await _run(() => _dio.patch(path, data: data)));
  }

  Future<void> delete(String path) async {
    await _run(() => _dio.delete(path));
  }

  Future<Response<dynamic>> _run(Future<Response<dynamic>> Function() call) async {
    try {
      return await call();
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  Map<String, dynamic> _unwrap(Response<dynamic> response) {
    final body = response.data;
    if (body is Map<String, dynamic>) {
      return body;
    }

    // A 204 No Content (e.g. cart-item delete) has no JSON body.
    return <String, dynamic>{};
  }

  ApiException _toApiException(DioException e) {
    final status = e.response?.statusCode;
    final body = e.response?.data;

    if (body is Map<String, dynamic>) {
      final message = body['message'] as String? ?? 'Something went wrong. Please try again.';
      final rawErrors = body['errors'];
      final fieldErrors = <String, List<String>>{};

      if (rawErrors is Map) {
        rawErrors.forEach((key, value) {
          if (value is List) {
            fieldErrors[key.toString()] = value.map((v) => v.toString()).toList();
          }
        });
      }

      return ApiException(statusCode: status, message: message, fieldErrors: fieldErrors);
    }

    if (e.type == DioExceptionType.connectionTimeout || e.type == DioExceptionType.receiveTimeout) {
      return ApiException(statusCode: status, message: 'The connection timed out. Check your internet and try again.');
    }

    if (e.type == DioExceptionType.connectionError) {
      return ApiException(statusCode: status, message: 'Could not reach BuyAndMarket. Check your connection.');
    }

    return ApiException(statusCode: status, message: 'Something went wrong. Please try again.');
  }
}
