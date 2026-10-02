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

    return Scaffold(
      appBar: AppBar(title: Text(categoryName ?? 'Category')),
      body: ProductGridView(fetchPage: (page) => catalogue.products(categoryId: categoryId, sort: 'newest', page: page)),
    );
  }
}
