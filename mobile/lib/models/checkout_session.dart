class CheckoutSession {
  CheckoutSession({
    required this.id,
    required this.status,
    this.addressId,
    this.orderId,
  });

  factory CheckoutSession.fromJson(Map<String, dynamic> json) {
    return CheckoutSession(
      id: json['id'] as int,
      status: json['status'] as String,
      addressId: json['address_id'] as int?,
      orderId: json['order_id'] as int?,
    );
  }

  final int id;
  final String status;
  final int? addressId;
  final int? orderId;
}
