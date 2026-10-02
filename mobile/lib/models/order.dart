import 'order_group.dart';

class Order {
  Order({
    required this.id,
    required this.orderNumber,
    required this.total,
    required this.status,
    required this.createdAt,
    this.orderGroups = const [],
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'] as int,
      orderNumber: json['order_number'] as String,
      total: double.parse(json['total'].toString()),
      status: json['status'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
      orderGroups: (json['order_groups'] as List<dynamic>?)
              ?.map((g) => OrderGroup.fromJson(g as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  final int id;
  final String orderNumber;
  final double total;
  final String status;
  final DateTime createdAt;
  final List<OrderGroup> orderGroups;
}
