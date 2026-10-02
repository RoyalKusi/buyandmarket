import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/product.dart';

final _currency = NumberFormat.currency(symbol: '\$', decimalDigits: 2);

class ProductCard extends StatelessWidget {
  const ProductCard({required this.product, super.key});

  final Product product;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/products/${product.id}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1,
              child: Container(
                color: Theme.of(context).colorScheme.surfaceContainerHighest,
                alignment: Alignment.center,
                // The browse/search API doesn't expose a product image URL
                // yet (ProductSearchController returns raw Product JSON,
                // no images relation) — a placeholder icon stands in until
                // that's added.
                child: Icon(Icons.inventory_2_outlined, size: 32, color: Theme.of(context).colorScheme.onSurfaceVariant),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 4),
                  Text(_currency.format(product.displayPrice), style: Theme.of(context).textTheme.titleMedium),
                  if (!product.isInStock)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text('Out of stock', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.error)),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
