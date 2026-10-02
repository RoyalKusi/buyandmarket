import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/cart.dart';
import 'app_providers.dart';
import 'auth_provider.dart';

/// Single shared cart instance for the whole app — the badge in the app
/// bar, the cart screen, and checkout's "what am I paying for" summary
/// all read this one notifier rather than each re-fetching `/carts`.
class CartNotifier extends StateNotifier<AsyncValue<Cart>> {
  CartNotifier(this._ref) : super(const AsyncValue.loading()) {
    refresh();

    // The cart backing this notifier is resolved server-side by acting
    // user (authenticated) or session (guest) — without this, logging in
    // or out leaves the cart badge/screen showing whatever was fetched
    // before that transition (e.g. an empty pre-login guest cart) until
    // some unrelated add/update/remove call happens to trigger a refresh.
    _ref.listen(authProvider, (previous, next) {
      if (previous?.status != next.status) refresh();
    });
  }

  final Ref _ref;

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    try {
      final cart = await _ref.read(cartRepositoryProvider).show();
      state = AsyncValue.data(cart);
    } catch (error, stackTrace) {
      state = AsyncValue.error(error, stackTrace);
    }
  }

  Future<void> addItem({required int variantId, required int quantity}) async {
    await _ref.read(cartRepositoryProvider).addItem(variantId: variantId, quantity: quantity);
    await refresh();
  }

  Future<void> updateQuantity({required int itemId, required int quantity}) async {
    await _ref.read(cartRepositoryProvider).updateQuantity(itemId: itemId, quantity: quantity);
    await refresh();
  }

  Future<void> removeItem(int itemId) async {
    await _ref.read(cartRepositoryProvider).removeItem(itemId);
    await refresh();
  }
}

final cartProvider = StateNotifierProvider<CartNotifier, AsyncValue<Cart>>((ref) => CartNotifier(ref));
