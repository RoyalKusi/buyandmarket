import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/cart.dart';
import 'app_providers.dart';

/// Single shared cart instance for the whole app — the badge in the app
/// bar, the cart screen, and checkout's "what am I paying for" summary
/// all read this one notifier rather than each re-fetching `/carts`.
class CartNotifier extends StateNotifier<AsyncValue<Cart>> {
  CartNotifier(this._ref) : super(const AsyncValue.loading()) {
    refresh();
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
