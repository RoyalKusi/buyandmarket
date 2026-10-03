import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers/app_providers.dart';
import '../../widgets/product_grid_view.dart';

class CategoryScreen extends ConsumerWidget {
  const CategoryScreen({required this.categoryId, this.categoryName, super.key});

  final int categoryId;
  final String? categoryName;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final catalogue = ref.watch(catalogueRepositoryProvider);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Text(categoryName ?? 'Category', style: Theme.of(context).textTheme.headlineSmall),
          ),
        ),
        Expanded(
          child: ProductGridView(fetchPage: (page) => catalogue.products(categoryId: categoryId, sort: 'newest', page: page)),
        ),
      ],
    );
  }
}
