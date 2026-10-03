import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';

import 'package:buyandmarket_mobile/core/api/api_client.dart';
import 'package:buyandmarket_mobile/core/api/repositories/auth_repository.dart';
import 'package:buyandmarket_mobile/core/api/repositories/cart_repository.dart';
import 'package:buyandmarket_mobile/core/api/repositories/catalogue_repository.dart';
import 'package:buyandmarket_mobile/main.dart';
import 'package:buyandmarket_mobile/models/cart.dart';
import 'package:buyandmarket_mobile/models/category.dart';
import 'package:buyandmarket_mobile/models/user.dart';
import 'package:buyandmarket_mobile/providers/app_providers.dart';

/// Stands in for the real, network-backed repositories the home
/// screen's shell reads on first frame (categories, the cart badge
/// count) — without this, [CartRepository.show]/
/// [CatalogueRepository.categories] would fire a real Dio request with
/// no backend to answer it, leaving a pending timer the test framework
/// flags as a leak.
class _FakeCatalogueRepository extends CatalogueRepository {
  _FakeCatalogueRepository() : super(apiClient: _unusedApiClient);

  @override
  Future<List<Category>> categories() async => const [];

  @override
  Future<ProductPage> products({
    String? query,
    int? categoryId,
    int? storeId,
    double? minPrice,
    double? maxPrice,
    String sort = 'relevance',
    int page = 1,
  }) async => ProductPage(products: const [], currentPage: 1, lastPage: 1);
}

class _FakeCartRepository extends CartRepository {
  _FakeCartRepository() : super(apiClient: _unusedApiClient);

  @override
  Future<Cart> show() async => Cart(id: 0);
}

/// Avoids a real flutter_secure_storage platform-channel read (no
/// plugin implementation is registered in a plain widget test) and any
/// real Dio call the real [AuthRepository] would make while resolving
/// who's logged in at app start.
class _FakeAuthRepository extends AuthRepository {
  _FakeAuthRepository() : super(apiClient: _unusedApiClient);

  @override
  Future<AppUser?> currentUser() async => null;
}

final _unusedApiClient = ApiClient(baseUrl: 'http://localhost');

void main() {
  testWidgets('the app boots to the home screen without crashing', (WidgetTester tester) async {
    // Without this, GoogleFonts tries to fetch the font over the
    // network on first use, leaving a pending HTTP timer the test
    // framework flags as a leak since there's no backend to answer it.
    GoogleFonts.config.allowRuntimeFetching = false;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          catalogueRepositoryProvider.overrideWithValue(_FakeCatalogueRepository()),
          cartRepositoryProvider.overrideWithValue(_FakeCartRepository()),
          authRepositoryProvider.overrideWithValue(_FakeAuthRepository()),
        ],
        child: const BuyAndMarketApp(),
      ),
    );
    await tester.pump();

    expect(find.byType(Image), findsWidgets);
    expect(find.byType(NavigationBar), findsOneWidget);
  });
}
