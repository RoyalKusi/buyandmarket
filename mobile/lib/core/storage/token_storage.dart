import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// The Sanctum bearer token is a live credential, not a UI preference —
/// stored in the platform keychain/keystore (flutter_secure_storage),
/// never SharedPreferences/plain files.
class TokenStorage {
  TokenStorage({FlutterSecureStorage? storage}) : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'buyandmarket_auth_token';

  final FlutterSecureStorage _storage;

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> saveToken(String token) => _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);
}
