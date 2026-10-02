import '../../../models/order.dart';
import '../api_client.dart';

class OrderRepository {
  OrderRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<List<Order>> list() async {
    final response = await _apiClient.get('/orders');

    return (response['data'] as List<dynamic>).map((o) => Order.fromJson(o as Map<String, dynamic>)).toList();
  }

  Future<Order> show(int id) async {
    final response = await _apiClient.get('/orders/$id');

    return Order.fromJson(response['data'] as Map<String, dynamic>);
  }
}
