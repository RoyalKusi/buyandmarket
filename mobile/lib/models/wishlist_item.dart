import 'product.dart';

class WishlistItem {
  WishlistItem({required this.id, required this.productId, this.product});

  factory WishlistItem.fromJson(Map<String, dynamic> json) {
    return WishlistItem(
      id: json['id'] as int,
      productId: json['product_id'] as int,
      product: json['product'] != null ? Product.fromJson(json['product'] as Map<String, dynamic>) : null,
    );
  }

  final int id;
  final int productId;
  final Product? product;
}
