import 'product.dart';
import 'product_variant.dart';

class CartItem {
  CartItem({
    required this.id,
    required this.cartId,
    required this.variantId,
    required this.quantity,
    required this.priceSnapshot,
    this.variant,
    this.product,
  });

  factory CartItem.fromJson(Map<String, dynamic> json) {
    final variantJson = json['variant'] as Map<String, dynamic>?;

    return CartItem(
      id: json['id'] as int,
      cartId: json['cart_id'] as int,
      variantId: json['variant_id'] as int,
      quantity: json['quantity'] as int,
      priceSnapshot: double.parse(json['price_snapshot'].toString()),
      variant: variantJson != null ? ProductVariant.fromJson(variantJson) : null,
      product: variantJson?['product'] != null ? Product.fromJson(variantJson!['product'] as Map<String, dynamic>) : null,
    );
  }

  final int id;
  final int cartId;
  final int variantId;
  final int quantity;
  final double priceSnapshot;
  final ProductVariant? variant;
  final Product? product;

  double get lineTotal => priceSnapshot * quantity;
}
