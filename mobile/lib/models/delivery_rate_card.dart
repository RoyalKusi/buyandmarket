class DeliveryRateCard {
  DeliveryRateCard({
    required this.id,
    required this.method,
    required this.baseFee,
    required this.etaMinDays,
    required this.etaMaxDays,
    this.zoneName,
  });

  factory DeliveryRateCard.fromJson(Map<String, dynamic> json) {
    final zone = json['zone'] as Map<String, dynamic>?;

    return DeliveryRateCard(
      id: json['id'] as int,
      method: json['method'] as String,
      baseFee: double.parse(json['base_fee'].toString()),
      etaMinDays: json['eta_min_days'] as int,
      etaMaxDays: json['eta_max_days'] as int,
      zoneName: zone?['name'] as String?,
    );
  }

  final int id;
  final String method;
  final double baseFee;
  final int etaMinDays;
  final int etaMaxDays;
  final String? zoneName;

  String get etaLabel => '$etaMinDays–$etaMaxDays days';
}
