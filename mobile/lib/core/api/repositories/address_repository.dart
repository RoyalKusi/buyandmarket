import '../../../models/address.dart';
import '../api_client.dart';

class AddressRepository {
  AddressRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<List<Address>> list() async {
    final response = await _apiClient.get('/addresses');

    return (response['data'] as List<dynamic>).map((a) => Address.fromJson(a as Map<String, dynamic>)).toList();
  }

  Future<Address> create({
    required String label,
    required String recipientName,
    required String phone,
    required String province,
    required String city,
    String? area,
    required String streetAddress,
    bool isDefault = false,
  }) async {
    final response = await _apiClient.post(
      '/addresses',
      data: {
        'label': label,
        'recipient_name': recipientName,
        'phone': phone,
        'province': province,
        'city': city,
        if (area != null) 'area': area,
        'street_address': streetAddress,
        'is_default': isDefault,
      },
    );

    return Address.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<void> delete(int id) async {
    await _apiClient.delete('/addresses/$id');
  }
}
