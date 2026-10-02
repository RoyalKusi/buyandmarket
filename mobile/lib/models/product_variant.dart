class ProductVariant {
  ProductVariant({
    required this.id,
    required this.productId,
    required this.sku,
    required this.stockQuantity,
    this.priceOverride,
  });

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    return ProductVariant(
      id: json['id'] as int,
      productId: json['product_id'] as int,
      sku: json['sku'] as String,
      stockQuantity: json['stock_quantity'] as int,
      // Laravel's decimal:2 cast always serialises as a string, never a
      // float — parsed here rather than trusted as num to avoid a
      // silent type crash if a future API change widens the cast.
      priceOverride: json['price_override'] == null ? null : double.tryParse(json['price_override'].toString()),
    );
  }

  final int id;
  final int productId;
  final String sku;
  final int stockQuantity;
  final double? priceOverride;

  bool get inStock => stockQuantity > 0;
}
