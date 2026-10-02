import 'product_variant.dart';
import 'store.dart';

class Product {
  Product({
    required this.id,
    required this.title,
    required this.slug,
    required this.basePrice,
    required this.status,
    this.description,
    this.store,
    this.variants = const [],
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'] as int,
      title: json['title'] as String,
      slug: json['slug'] as String,
      basePrice: double.parse(json['base_price'].toString()),
      status: json['status'] as String,
      description: json['description'] as String?,
      store: json['store'] != null ? Store.fromJson(json['store'] as Map<String, dynamic>) : null,
      variants: (json['variants'] as List<dynamic>?)
              ?.map((v) => ProductVariant.fromJson(v as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  final int id;
  final String title;
  final String slug;
  final double basePrice;
  final String status;
  final String? description;
  final Store? store;
  final List<ProductVariant> variants;

  /// The price actually charged — a variant's override if present,
  /// otherwise the product's own base price. Mirrors
  /// App\Models\ProductVariant::price() on the backend exactly.
  double get displayPrice => variants.isNotEmpty ? (variants.first.priceOverride ?? basePrice) : basePrice;

  bool get isInStock => variants.isEmpty || variants.any((v) => v.inStock);
}
