import 'cart_item.dart';

class Cart {
  Cart({required this.id, this.userId, this.items = const []});

  factory Cart.fromJson(Map<String, dynamic> json) {
    return Cart(
      id: json['id'] as int,
      userId: json['user_id'] as int?,
      items: (json['items'] as List<dynamic>?)?.map((i) => CartItem.fromJson(i as Map<String, dynamic>)).toList() ??
          const [],
    );
  }

  final int id;
  final int? userId;
  final List<CartItem> items;

  int get itemCount => items.fold(0, (sum, item) => sum + item.quantity);

  double get subtotal => items.fold(0, (sum, item) => sum + item.lineTotal);

  bool get isEmpty => items.isEmpty;
}
