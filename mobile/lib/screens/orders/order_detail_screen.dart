import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../models/order.dart';
import '../../models/order_group.dart';
import '../../providers/app_providers.dart';

final _currency = NumberFormat.currency(symbol: '\$', decimalDigits: 2);
final _date = DateFormat.yMMMd().add_jm();

class OrderDetailScreen extends ConsumerStatefulWidget {
  const OrderDetailScreen({required this.orderId, super.key});

  final int orderId;

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  late Future<Order> _future;

  @override
  void initState() {
    super.initState();
    _future = ref.read(orderRepositoryProvider).show(widget.orderId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Order details')),
      body: FutureBuilder<Order>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return const Center(child: Text('Could not load this order.'));
          }

          final order = snapshot.data!;

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(order.orderNumber, style: Theme.of(context).textTheme.headlineSmall),
              Text(_date.format(order.createdAt), style: Theme.of(context).textTheme.bodySmall),
              const SizedBox(height: 4),
              Chip(label: Text(order.status)),
              const SizedBox(height: 16),
              ...order.orderGroups.map((group) => _OrderGroupCard(group: group)),
              const Divider(height: 32),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Total', style: Theme.of(context).textTheme.titleLarge),
                  Text(_currency.format(order.total), style: Theme.of(context).textTheme.titleLarge),
                ],
              ),
            ],
          );
        },
      ),
    );
  }
}

class _OrderGroupCard extends StatelessWidget {
  const _OrderGroupCard({required this.group});

  final OrderGroup group;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Shipment', style: Theme.of(context).textTheme.titleMedium),
                Chip(label: Text(group.status)),
              ],
            ),
            const SizedBox(height: 8),
            ...group.items.map(
              (item) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(child: Text(item.product?.title ?? item.variant?.sku ?? 'Item')),
                    Text('×${item.quantity}'),
                    const SizedBox(width: 12),
                    Text(_currency.format(item.priceAtPurchase * item.quantity)),
                  ],
                ),
              ),
            ),
            const Divider(),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [const Text('Delivery'), Text(_currency.format(group.deliveryFee))],
            ),
          ],
        ),
      ),
    );
  }
}
