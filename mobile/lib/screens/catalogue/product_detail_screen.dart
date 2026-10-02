import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/api/api_exception.dart';
import '../../models/product.dart';
import '../../providers/app_providers.dart';
import '../../providers/auth_provider.dart';
import '../../providers/cart_provider.dart';

final _currency = NumberFormat.currency(symbol: '\$', decimalDigits: 2);

class ProductDetailScreen extends ConsumerStatefulWidget {
  const ProductDetailScreen({required this.productId, super.key});

  final int productId;

  @override
  ConsumerState<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends ConsumerState<ProductDetailScreen> {
  late Future<Product> _future;
  int _quantity = 1;
  bool _isAddingToCart = false;

  @override
  void initState() {
    super.initState();
    _future = ref.read(catalogueRepositoryProvider).product(widget.productId);
  }

  Future<void> _addToCart(Product product) async {
    if (!ref.read(authProvider).isAuthenticated) {
      context.push('/login?redirect=${Uri.encodeComponent('/products/${widget.productId}')}');
      return;
    }

    final variant = product.variants.isNotEmpty ? product.variants.first : null;
    if (variant == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('This product is not available right now.')));
      return;
    }

    setState(() => _isAddingToCart = true);
    try {
      await ref.read(cartProvider.notifier).addItem(variantId: variant.id, quantity: _quantity);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Added to cart')));
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _isAddingToCart = false);
    }
  }

  Future<void> _toggleWishlist(Product product) async {
    if (!ref.read(authProvider).isAuthenticated) {
      context.push('/login?redirect=${Uri.encodeComponent('/products/${widget.productId}')}');
      return;
    }

    try {
      final wishlisted = await ref.read(wishlistRepositoryProvider).toggle(product.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(wishlisted ? 'Added to wishlist' : 'Removed from wishlist')));
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        actions: [
          FutureBuilder<Product>(
            future: _future,
            builder: (context, snapshot) {
              final product = snapshot.data;
              return IconButton(
                icon: const Icon(Icons.favorite_border),
                onPressed: product == null ? null : () => _toggleWishlist(product),
              );
            },
          ),
        ],
      ),
      body: FutureBuilder<Product>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return const Center(child: Text('Could not load this product.'));
          }

          final product = snapshot.data!;

          return SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (product.store != null)
                  Text(product.store!.name, style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 4),
                Text(product.title, style: Theme.of(context).textTheme.headlineSmall),
                const SizedBox(height: 8),
                Text(_currency.format(product.displayPrice), style: Theme.of(context).textTheme.displaySmall),
                const SizedBox(height: 16),
                if (!product.isInStock)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(color: Theme.of(context).colorScheme.errorContainer, borderRadius: BorderRadius.circular(6)),
                    child: const Text('Out of stock'),
                  ),
                if (product.description != null) ...[
                  const SizedBox(height: 16),
                  Text(product.description!, style: Theme.of(context).textTheme.bodyLarge),
                ],
                const SizedBox(height: 24),
                Row(
                  children: [
                    Text('Quantity', style: Theme.of(context).textTheme.titleMedium),
                    const Spacer(),
                    IconButton(onPressed: _quantity > 1 ? () => setState(() => _quantity--) : null, icon: const Icon(Icons.remove_circle_outline)),
                    Text('$_quantity', style: Theme.of(context).textTheme.titleMedium),
                    IconButton(onPressed: () => setState(() => _quantity++), icon: const Icon(Icons.add_circle_outline)),
                  ],
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: (!product.isInStock || _isAddingToCart) ? null : () => _addToCart(product),
                  child: _isAddingToCart
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Add to cart'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
