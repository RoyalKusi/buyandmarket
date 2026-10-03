import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../models/wishlist_item.dart';
import '../../providers/app_providers.dart';

final _currency = NumberFormat.currency(symbol: '\$', decimalDigits: 2);

class WishlistScreen extends ConsumerStatefulWidget {
  const WishlistScreen({super.key});

  @override
  ConsumerState<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends ConsumerState<WishlistScreen> {
  late Future<List<WishlistItem>> _future;

  @override
  void initState() {
    super.initState();
    _future = ref.read(wishlistRepositoryProvider).list();
  }

  Future<void> _refresh() async {
    final future = ref.read(wishlistRepositoryProvider).list();
    setState(() => _future = future);
    await future;
  }

  Future<void> _remove(WishlistItem item) async {
    await ref.read(wishlistRepositoryProvider).toggle(item.productId);
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Text('Wishlist', style: Theme.of(context).textTheme.headlineSmall),
          ),
        ),
        Expanded(
          child: FutureBuilder<List<WishlistItem>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text('Could not load your wishlist.'),
                      const SizedBox(height: 8),
                      OutlinedButton(onPressed: _refresh, child: const Text('Retry')),
                    ],
                  ),
                );
              }

              final items = snapshot.data!;
              if (items.isEmpty) {
                return const Center(child: Text('Your wishlist is empty.'));
              }

              return RefreshIndicator(
                onRefresh: _refresh,
                child: ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  separatorBuilder: (context, index) => const Divider(),
                  itemBuilder: (context, index) {
                    final item = items[index];
                    final product = item.product;
                    return ListTile(
                      leading: const Icon(Icons.inventory_2_outlined),
                      title: Text(product?.title ?? 'Product'),
                      subtitle: product != null ? Text(_currency.format(product.displayPrice)) : null,
                      trailing: IconButton(icon: const Icon(Icons.favorite), onPressed: () => _remove(item)),
                      onTap: product != null ? () => context.push('/products/${product.id}') : null,
                    );
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
