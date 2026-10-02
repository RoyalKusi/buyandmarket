import 'product.dart';
import 'product_variant.dart';

class OrderItem {
  OrderItem({required this.id, required this.quantity, required this.priceAtPurchase, this.variant, this.product});

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    final variantJson = json['variant'] as Map<String, dynamic>?;

    return OrderItem(
      id: json['id'] as int,
      quantity: json['quantity'] as int,
      priceAtPurchase: double.parse(json['price_at_purchase'].toString()),
      variant: variantJson != null ? ProductVariant.fromJson(variantJson) : null,
      product: variantJson?['product'] != null ? Product.fromJson(variantJson!['product'] as Map<String, dynamic>) : null,
    );
  }

  final int id;
  final int quantity;
  final double priceAtPurchase;
  final ProductVariant? variant;
  final Product? product;
}
