import 'package:flutter/material.dart';

import '../core/api/repositories/catalogue_repository.dart';
import '../models/product.dart';
import 'product_card.dart';

/// Shared infinite-scroll product grid backing the home, search, and
/// category screens — each just supplies a different [fetchPage].
class ProductGridView extends StatefulWidget {
  const ProductGridView({required this.fetchPage, super.key});

  final Future<ProductPage> Function(int page) fetchPage;

  @override
  State<ProductGridView> createState() => _ProductGridViewState();
}

class _ProductGridViewState extends State<ProductGridView> {
  final _scrollController = ScrollController();
  final List<Product> _products = [];
  int _currentPage = 1;
  int _lastPage = 1;
  bool _isLoading = true;
  bool _isLoadingMore = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _load();
    _scrollController.addListener(_onScroll);
  }

  @override
  void didUpdateWidget(ProductGridView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.fetchPage != widget.fetchPage) {
      _products.clear();
      _currentPage = 1;
      _lastPage = 1;
      _isLoading = true;
      _error = null;
      _load();
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_isLoadingMore || _currentPage >= _lastPage) return;
    if (_scrollController.position.pixels > _scrollController.position.maxScrollExtent - 300) {
      _loadMore();
    }
  }

  Future<void> _load() async {
    try {
      final page = await widget.fetchPage(1);
      setState(() {
        _products
          ..clear()
          ..addAll(page.products);
        _currentPage = page.currentPage;
        _lastPage = page.lastPage;
        _isLoading = false;
      });
    } catch (error) {
      setState(() {
        _error = error;
        _isLoading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    setState(() => _isLoadingMore = true);
    try {
      final page = await widget.fetchPage(_currentPage + 1);
      setState(() {
        _products.addAll(page.products);
        _currentPage = page.currentPage;
        _lastPage = page.lastPage;
      });
    } finally {
      if (mounted) setState(() => _isLoadingMore = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Center(child: CircularProgressIndicator());

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Could not load products.'),
            const SizedBox(height: 8),
            OutlinedButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }

    if (_products.isEmpty) {
      return const Center(child: Text('No products found.'));
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: GridView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.all(16),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 0.68),
        itemCount: _products.length + (_isLoadingMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index >= _products.length) return const Center(child: CircularProgressIndicator());
          return ProductCard(product: _products[index]);
        },
      ),
    );
  }
}
