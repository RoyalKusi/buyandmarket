import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers/app_providers.dart';
import '../../widgets/product_grid_view.dart';

class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({this.initialQuery, super.key});

  final String? initialQuery;

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  late final _controller = TextEditingController(text: widget.initialQuery);
  String _query = '';
  String _sort = 'relevance';

  @override
  void initState() {
    super.initState();
    _query = widget.initialQuery ?? '';
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final catalogue = ref.watch(catalogueRepositoryProvider);

    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _controller,
          autofocus: widget.initialQuery == null,
          decoration: const InputDecoration(hintText: 'Search products...', border: InputBorder.none),
          onSubmitted: (value) => setState(() => _query = value.trim()),
        ),
        actions: [
          PopupMenuButton<String>(
            icon: const Icon(Icons.sort),
            onSelected: (value) => setState(() => _sort = value),
            itemBuilder: (context) => const [
              PopupMenuItem(value: 'relevance', child: Text('Relevance')),
              PopupMenuItem(value: 'newest', child: Text('Newest')),
              PopupMenuItem(value: 'price_low_high', child: Text('Price: low to high')),
              PopupMenuItem(value: 'price_high_low', child: Text('Price: high to low')),
            ],
          ),
        ],
      ),
      body: _query.isEmpty
          ? const Center(child: Text('Search for products by name or description.'))
          : ProductGridView(
              key: ValueKey('$_query-$_sort'),
              fetchPage: (page) => catalogue.products(query: _query, sort: _sort, page: page),
            ),
    );
  }
}
