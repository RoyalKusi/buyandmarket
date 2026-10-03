import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api/api_client.dart';
import '../core/api/repositories/address_repository.dart';
import '../core/api/repositories/auth_repository.dart';
import '../core/api/repositories/cart_repository.dart';
import '../core/api/repositories/catalogue_repository.dart';
import '../core/api/repositories/checkout_repository.dart';
import '../core/api/repositories/order_repository.dart';
import '../core/api/repositories/wishlist_repository.dart';
import '../core/auth/social_auth_service.dart';
import '../core/config.dart';
import '../core/storage/token_storage.dart';

final tokenStorageProvider = Provider<TokenStorage>((ref) => TokenStorage());

final socialAuthServiceProvider = Provider<SocialAuthService>((ref) => SocialAuthService());

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(baseUrl: AppConfig.apiBaseUrl, tokenStorage: ref.watch(tokenStorageProvider));
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(apiClient: ref.watch(apiClientProvider), tokenStorage: ref.watch(tokenStorageProvider));
});

final catalogueRepositoryProvider = Provider<CatalogueRepository>((ref) {
  return CatalogueRepository(apiClient: ref.watch(apiClientProvider));
});

final cartRepositoryProvider = Provider<CartRepository>((ref) {
  return CartRepository(apiClient: ref.watch(apiClientProvider));
});

final checkoutRepositoryProvider = Provider<CheckoutRepository>((ref) {
  return CheckoutRepository(apiClient: ref.watch(apiClientProvider));
});

final orderRepositoryProvider = Provider<OrderRepository>((ref) {
  return OrderRepository(apiClient: ref.watch(apiClientProvider));
});

final wishlistRepositoryProvider = Provider<WishlistRepository>((ref) {
  return WishlistRepository(apiClient: ref.watch(apiClientProvider));
});

final addressRepositoryProvider = Provider<AddressRepository>((ref) {
  return AddressRepository(apiClient: ref.watch(apiClientProvider));
});
