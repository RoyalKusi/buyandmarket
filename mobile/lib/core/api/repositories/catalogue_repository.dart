import '../../../models/category.dart';
import '../../../models/product.dart';
import '../api_client.dart';

class ProductPage {
  ProductPage({required this.products, required this.currentPage, required this.lastPage});

  final List<Product> products;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}

/// Backs the home, search, and category screens — all three are "the
/// same browse experience, pre-scoped differently," mirroring the web
/// storefront's own ProductGrid component (one component, three
/// mount contexts).
class CatalogueRepository {
  CatalogueRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<List<Category>> categories() async {
    final response = await _apiClient.get('/categories');

    return (response['data'] as List<dynamic>).map((c) => Category.fromJson(c as Map<String, dynamic>)).toList();
  }

  Future<ProductPage> products({
    String? query,
    int? categoryId,
    int? storeId,
    double? minPrice,
    double? maxPrice,
    String sort = 'relevance',
    int page = 1,
  }) async {
    final response = await _apiClient.get(
      '/products',
      query: {
        if (query != null && query.isNotEmpty) 'q': query,
        if (categoryId != null) 'category_id': categoryId,
        if (storeId != null) 'store_id': storeId,
        if (minPrice != null) 'min_price': minPrice,
        if (maxPrice != null) 'max_price': maxPrice,
        'sort': sort,
        'page': page,
      },
    );

    final meta = response['meta'] as Map<String, dynamic>;

    return ProductPage(
      products: (response['data'] as List<dynamic>).map((p) => Product.fromJson(p as Map<String, dynamic>)).toList(),
      currentPage: meta['current_page'] as int,
      lastPage: meta['last_page'] as int,
    );
  }

  Future<Product> product(int id) async {
    final response = await _apiClient.get('/products/$id');

    return Product.fromJson(response['data'] as Map<String, dynamic>);
  }
}
