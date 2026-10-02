import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../models/order.dart';
import '../../providers/app_providers.dart';

final _currency = NumberFormat.currency(symbol: '\$', decimalDigits: 2);
final _date = DateFormat.yMMMd();

class OrderHistoryScreen extends ConsumerStatefulWidget {
  const OrderHistoryScreen({super.key});

  @override
  ConsumerState<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends ConsumerState<OrderHistoryScreen> {
  late Future<List<Order>> _future;

  @override
  void initState() {
    super.initState();
    _future = ref.read(orderRepositoryProvider).list();
  }

  Future<void> _refresh() async {
    final future = ref.read(orderRepositoryProvider).list();
    setState(() => _future = future);
    await future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Your orders')),
      body: FutureBuilder<List<Order>>(
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
                  const Text('Could not load your orders.'),
                  const SizedBox(height: 8),
                  OutlinedButton(onPressed: _refresh, child: const Text('Retry')),
                ],
              ),
            );
          }

          final orders = snapshot.data!;
          if (orders.isEmpty) {
            return const Center(child: Text("You haven't placed any orders yet."));
          }

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: orders.length,
              separatorBuilder: (context, index) => const Divider(),
              itemBuilder: (context, index) {
                final order = orders[index];
                return ListTile(
                  title: Text(order.orderNumber),
                  subtitle: Text(_date.format(order.createdAt)),
                  trailing: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(_currency.format(order.total)),
                      Text(order.status, style: Theme.of(context).textTheme.bodySmall),
                    ],
                  ),
                  onTap: () => context.push('/orders/${order.id}'),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
