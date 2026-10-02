import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../models/cart.dart';
import '../../models/cart_item.dart';
import '../../providers/cart_provider.dart';

final _currency = NumberFormat.currency(symbol: '\$', decimalDigits: 2);

class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cartState = ref.watch(cartProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Your cart')),
      body: cartState.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stackTrace) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('Could not load your cart.'),
              const SizedBox(height: 8),
              OutlinedButton(onPressed: () => ref.read(cartProvider.notifier).refresh(), child: const Text('Retry')),
            ],
          ),
        ),
        data: (Cart cart) {
          if (cart.isEmpty) {
            return const Center(child: Text('Your cart is empty.'));
          }

          return Column(
            children: [
              Expanded(
                child: ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: cart.items.length,
                  separatorBuilder: (context, index) => const Divider(),
                  itemBuilder: (context, index) => _CartItemTile(item: cart.items[index]),
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Subtotal', style: Theme.of(context).textTheme.titleMedium),
                          Text(_currency.format(cart.subtotal), style: Theme.of(context).textTheme.titleLarge),
                        ],
                      ),
                      const SizedBox(height: 12),
                      ElevatedButton(onPressed: () => context.push('/checkout'), child: const Text('Proceed to checkout')),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _CartItemTile extends ConsumerWidget {
  const _CartItemTile({required this.item});

  final CartItem item;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(item.product?.title ?? item.variant?.sku ?? 'Item', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 4),
              Text(_currency.format(item.priceSnapshot), style: Theme.of(context).textTheme.bodyMedium),
            ],
          ),
        ),
        IconButton(
          onPressed: item.quantity > 1 ? () => ref.read(cartProvider.notifier).updateQuantity(itemId: item.id, quantity: item.quantity - 1) : null,
          icon: const Icon(Icons.remove_circle_outline),
        ),
        Text('${item.quantity}'),
        IconButton(
          onPressed: () => ref.read(cartProvider.notifier).updateQuantity(itemId: item.id, quantity: item.quantity + 1),
          icon: const Icon(Icons.add_circle_outline),
        ),
        IconButton(
          onPressed: () => ref.read(cartProvider.notifier).removeItem(item.id),
          icon: const Icon(Icons.delete_outline),
        ),
      ],
    );
  }
}
