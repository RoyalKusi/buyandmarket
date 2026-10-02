import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api/api_exception.dart';
import '../models/user.dart';
import 'app_providers.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState {
  const AuthState({required this.status, this.user});

  const AuthState.unknown() : this(status: AuthStatus.unknown);

  final AuthStatus status;
  final AppUser? user;

  bool get isAuthenticated => status == AuthStatus.authenticated;
}

/// Resolves who's logged in once at app start (by probing the stored
/// token against `/auth/user`) and then owns every subsequent login/
/// logout/register transition, so the router and every screen read one
/// shared source of truth instead of each polling the token store.
class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier(this._ref) : super(const AuthState.unknown()) {
    _restore();
  }

  final Ref _ref;

  Future<void> _restore() async {
    final user = await _ref.read(authRepositoryProvider).currentUser();
    state = user != null ? AuthState(status: AuthStatus.authenticated, user: user) : const AuthState(status: AuthStatus.unauthenticated);
  }

  Future<void> login({required String email, required String password}) async {
    final user = await _ref.read(authRepositoryProvider).login(email: email, password: password);
    state = AuthState(status: AuthStatus.authenticated, user: user);
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final user = await _ref.read(authRepositoryProvider).register(
      name: name,
      email: email,
      password: password,
      passwordConfirmation: passwordConfirmation,
    );
    state = AuthState(status: AuthStatus.authenticated, user: user);
  }

  Future<void> logout() async {
    try {
      await _ref.read(authRepositoryProvider).logout();
    } on ApiException {
      // Token may already be invalid server-side; still clear it locally.
    }
    state = const AuthState(status: AuthStatus.unauthenticated);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) => AuthNotifier(ref));
