import 'order_item.dart';

class OrderGroup {
  OrderGroup({required this.id, required this.status, required this.subtotal, required this.deliveryFee, this.items = const []});

  factory OrderGroup.fromJson(Map<String, dynamic> json) {
    return OrderGroup(
      id: json['id'] as int,
      status: json['status'] as String,
      subtotal: double.parse(json['subtotal'].toString()),
      deliveryFee: double.parse(json['delivery_fee'].toString()),
      items: (json['items'] as List<dynamic>?)?.map((i) => OrderItem.fromJson(i as Map<String, dynamic>)).toList() ??
          const [],
    );
  }

  final int id;
  final String status;
  final double subtotal;
  final double deliveryFee;
  final List<OrderItem> items;
}
