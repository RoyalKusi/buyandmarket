class Address {
  Address({
    required this.id,
    required this.label,
    required this.recipientName,
    required this.phone,
    required this.province,
    required this.city,
    required this.streetAddress,
    this.area,
    this.isDefault = false,
  });

  factory Address.fromJson(Map<String, dynamic> json) {
    return Address(
      id: json['id'] as int,
      label: json['label'] as String,
      recipientName: json['recipient_name'] as String,
      phone: json['phone'] as String,
      province: json['province'] as String,
      city: json['city'] as String,
      area: json['area'] as String?,
      streetAddress: json['street_address'] as String,
      isDefault: json['is_default'] as bool? ?? false,
    );
  }

  final int id;
  final String label;
  final String recipientName;
  final String phone;
  final String province;
  final String city;
  final String? area;
  final String streetAddress;
  final bool isDefault;

  String get oneLine => [streetAddress, area, city, province].where((p) => p != null && p.isNotEmpty).join(', ');
}
