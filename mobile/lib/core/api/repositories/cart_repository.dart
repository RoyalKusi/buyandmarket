import '../../../models/cart.dart';
import '../api_client.dart';

class CartRepository {
  CartRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<Cart> show() async {
    final response = await _apiClient.get('/carts');

    return Cart.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<void> addItem({required int variantId, required int quantity}) async {
    await _apiClient.post('/carts/items', data: {'variant_id': variantId, 'quantity': quantity});
  }

  Future<void> updateQuantity({required int itemId, required int quantity}) async {
    await _apiClient.patch('/carts/items/$itemId', data: {'quantity': quantity});
  }

  Future<void> removeItem(int itemId) async {
    await _apiClient.delete('/carts/items/$itemId');
  }
}
