import 'package:flutter_facebook_auth/flutter_facebook_auth.dart';
import 'package:google_sign_in/google_sign_in.dart';

/// Thrown when the person cancels the native sign-in sheet — distinct
/// from a real failure, so the caller can quietly do nothing instead of
/// showing an error.
class SocialSignInCancelled implements Exception {}

/// Wraps the two native sign-in SDKs behind one shape: perform the
/// on-device sign-in, return the provider's own access token. The
/// resulting token is sent to the backend's `/api/v1/auth/{google,
/// facebook}` endpoints (AuthRepository) for verification and exchange
/// for a Sanctum token — this service never talks to our backend
/// itself.
class SocialAuthService {
  final GoogleSignIn _googleSignIn = GoogleSignIn(scopes: ['email']);

  Future<String> signInWithGoogle() async {
    final account = await _googleSignIn.signIn();
    if (account == null) throw SocialSignInCancelled();

    final auth = await account.authentication;
    final token = auth.accessToken;
    if (token == null) throw SocialSignInCancelled();

    return token;
  }

  Future<String> signInWithFacebook() async {
    final result = await FacebookAuth.instance.login(permissions: const ['email', 'public_profile']);

    if (result.status == LoginStatus.cancelled) throw SocialSignInCancelled();
    if (result.status != LoginStatus.success || result.accessToken == null) {
      throw Exception(result.message ?? 'Facebook sign-in failed.');
    }

    return result.accessToken!.tokenString;
  }
}
