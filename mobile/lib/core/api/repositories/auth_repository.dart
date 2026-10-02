import '../../storage/token_storage.dart';
import '../api_client.dart';
import '../../../models/user.dart';

class AuthRepository {
  AuthRepository({required ApiClient apiClient, TokenStorage? tokenStorage})
    : _apiClient = apiClient,
      _tokenStorage = tokenStorage ?? TokenStorage();

  final ApiClient _apiClient;
  final TokenStorage _tokenStorage;

  Future<AppUser> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await _apiClient.post(
      '/auth/register',
      data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );

    await _tokenStorage.saveToken(response['token'] as String);

    return AppUser.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<AppUser> login({required String email, required String password, String deviceName = 'mobile'}) async {
    final response = await _apiClient.post(
      '/auth/login',
      data: {'email': email, 'password': password, 'device_name': deviceName},
    );

    await _tokenStorage.saveToken(response['token'] as String);

    return AppUser.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<AppUser> loginWithGoogle(String accessToken) => _loginWithProvider('google', accessToken);

  Future<AppUser> loginWithFacebook(String accessToken) => _loginWithProvider('facebook', accessToken);

  Future<AppUser> _loginWithProvider(String provider, String accessToken) async {
    final response = await _apiClient.post('/auth/$provider', data: {'access_token': accessToken});

    await _tokenStorage.saveToken(response['token'] as String);

    return AppUser.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<void> logout() async {
    try {
      await _apiClient.post('/auth/logout');
    } finally {
      await _tokenStorage.clearToken();
    }
  }

  Future<AppUser?> currentUser() async {
    final token = await _tokenStorage.readToken();
    if (token == null) return null;

    try {
      final response = await _apiClient.get('/auth/user');

      return AppUser.fromJson(response['data'] as Map<String, dynamic>);
    } on Exception {
      // An expired/revoked token looks like "not logged in" to the UI,
      // not an error to surface.
      await _tokenStorage.clearToken();

      return null;
    }
  }

  Future<void> forgotPassword(String email) async {
    await _apiClient.post('/auth/forgot-password', data: {'email': email});
  }

  Future<void> resetPassword({
    required String token,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    await _apiClient.post(
      '/auth/reset-password',
      data: {
        'token': token,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
  }
}
