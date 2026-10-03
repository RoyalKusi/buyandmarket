import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../models/cart.dart';
import '../../providers/cart_provider.dart';
import '../../widgets/app_top_bar.dart';

/// Bottom nav shell for the four top-level buyer destinations, plus the
/// persistent top bar (logo, search, account) shown on every tab except
/// Search — that tab supplies its own richer app bar (a real editable
/// field, a sort menu), so showing this one too would double up. Search
/// has no bottom-nav tab of its own: the top bar's search pill (on
/// every other tab) is its one entry point, so there's no second way to
/// reach it duplicating that affordance. Cart carries a live item-count
/// badge since "what's in my cart" is the one piece of state a buyer
/// checks constantly while browsing.
class AppShell extends ConsumerWidget {
  const AppShell({required this.child, super.key});

  final Widget child;

  static const _tabs = ['/', '/cart', '/orders', '/wishlist'];

  int _indexFor(String location) {
    for (var i = _tabs.length - 1; i >= 0; i--) {
      if (location.startsWith(_tabs[i]) && (_tabs[i] != '/' || location == '/')) return i;
    }
    return 0;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final location = GoRouterState.of(context).matchedLocation;
    final currentIndex = _indexFor(location);
    final cartCount = ref.watch(cartProvider).maybeWhen(data: (Cart cart) => cart.itemCount, orElse: () => 0);

    return Scaffold(
      appBar: location.startsWith('/search') ? null : AppTopBar(showBackButton: location.startsWith('/categories/')),
      body: child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: currentIndex,
        onDestinationSelected: (index) => context.go(_tabs[index]),
        destinations: [
          const NavigationDestination(icon: Icon(Icons.storefront_outlined), selectedIcon: Icon(Icons.storefront), label: 'Home'),
          NavigationDestination(
            icon: Badge(
              label: Text('$cartCount'),
              isLabelVisible: cartCount > 0,
              child: const Icon(Icons.shopping_bag_outlined),
            ),
            selectedIcon: const Icon(Icons.shopping_bag),
            label: 'Cart',
          ),
          const NavigationDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long), label: 'Orders'),
          const NavigationDestination(icon: Icon(Icons.favorite_outline), selectedIcon: Icon(Icons.favorite), label: 'Wishlist'),
        ],
      ),
    );
  }
}
