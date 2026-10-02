import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../providers/auth_provider.dart';
import '../../screens/auth/login_screen.dart';
import '../../screens/auth/register_screen.dart';
import '../../screens/cart/cart_screen.dart';
import '../../screens/catalogue/category_screen.dart';
import '../../screens/catalogue/home_screen.dart';
import '../../screens/catalogue/product_detail_screen.dart';
import '../../screens/catalogue/search_screen.dart';
import '../../screens/checkout/checkout_screen.dart';
import '../../screens/orders/order_detail_screen.dart';
import '../../screens/orders/order_history_screen.dart';
import '../../screens/shell/app_shell.dart';
import '../../screens/wishlist/wishlist_screen.dart';

/// Screens behind this only make sense for a signed-in buyer; an
/// unauthenticated visitor hitting any of them is bounced to login
/// with `redirect` set so they land back where they meant to go.
const _authGatedPaths = ['/cart', '/checkout', '/orders', '/wishlist'];

final routerProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: '/',
    refreshListenable: _AuthRefreshListenable(ref),
    redirect: (context, state) {
      // Read, not watch: this must be the *current* auth state at the
      // moment each navigation is evaluated, not a value captured when
      // this provider was first built. Watching here previously made
      // every auth change rebuild the whole routerProvider — recreating
      // GoRouter from scratch and snapping the app back to
      // initialLocation ('/'), discarding whatever navigation (e.g. the
      // post-login redirect to the page that gated the user) was
      // already in flight. refreshListenable below is what re-runs this
      // callback on an auth change instead, without tearing the router
      // down.
      final authState = ref.read(authProvider);
      if (authState.status == AuthStatus.unknown) return null;

      final goingToAuthGated = _authGatedPaths.any((p) => state.matchedLocation.startsWith(p));
      final goingToAuthScreen = state.matchedLocation == '/login' || state.matchedLocation == '/register';

      if (!authState.isAuthenticated && goingToAuthGated) {
        return '/login?redirect=${Uri.encodeComponent(state.matchedLocation)}';
      }
      if (authState.isAuthenticated && goingToAuthScreen) {
        return '/';
      }
      return null;
    },
    routes: [
      ShellRoute(
        builder: (context, state, child) => AppShell(child: child),
        routes: [
          GoRoute(path: '/', builder: (context, state) => const HomeScreen()),
          GoRoute(path: '/search', builder: (context, state) => SearchScreen(initialQuery: state.uri.queryParameters['q'])),
          GoRoute(
            path: '/categories/:id',
            builder: (context, state) => CategoryScreen(
              categoryId: int.parse(state.pathParameters['id']!),
              categoryName: state.uri.queryParameters['name'],
            ),
          ),
          GoRoute(path: '/cart', builder: (context, state) => const CartScreen()),
          GoRoute(path: '/orders', builder: (context, state) => const OrderHistoryScreen()),
          GoRoute(path: '/wishlist', builder: (context, state) => const WishlistScreen()),
        ],
      ),
      GoRoute(path: '/products/:id', builder: (context, state) => ProductDetailScreen(productId: int.parse(state.pathParameters['id']!))),
      GoRoute(path: '/orders/:id', builder: (context, state) => OrderDetailScreen(orderId: int.parse(state.pathParameters['id']!))),
      GoRoute(path: '/checkout', builder: (context, state) => const CheckoutScreen()),
      GoRoute(
        path: '/login',
        builder: (context, state) => LoginScreen(redirectTo: state.uri.queryParameters['redirect']),
      ),
      GoRoute(path: '/register', builder: (context, state) => const RegisterScreen()),
    ],
  );
});

/// Bridges Riverpod's [authProvider] into go_router's `Listenable`-based
/// refresh hook, so a login/logout re-evaluates `redirect` immediately
/// instead of only on the next navigation call.
class _AuthRefreshListenable extends ChangeNotifier {
  _AuthRefreshListenable(Ref ref) {
    ref.listen(authProvider, (previous, next) {
      if (previous?.status != next.status) notifyListeners();
    });
  }
}
