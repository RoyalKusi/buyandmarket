import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../models/category.dart';
import '../../providers/app_providers.dart';
import '../../widgets/product_grid_view.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final catalogue = ref.watch(catalogueRepositoryProvider);

    return Column(
      children: [
        SizedBox(
          height: 48,
          child: FutureBuilder<List<Category>>(
            future: catalogue.categories(),
            builder: (context, snapshot) {
              final categories = snapshot.data ?? const <Category>[];
              if (categories.isEmpty) return const SizedBox.shrink();

              return ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                itemCount: categories.length,
                separatorBuilder: (context, index) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final category = categories[index];
                  return ActionChip(
                    label: Text(category.name),
                    onPressed: () => context.push('/categories/${category.id}?name=${Uri.encodeComponent(category.name)}'),
                  );
                },
              );
            },
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: ProductGridView(fetchPage: (page) => catalogue.products(sort: 'newest', page: page)),
        ),
      ],
    );
  }
}
