import '../../../models/wishlist_item.dart';
import '../api_client.dart';

class WishlistRepository {
  WishlistRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<List<WishlistItem>> list() async {
    final response = await _apiClient.get('/wishlist');

    return (response['data'] as List<dynamic>).map((w) => WishlistItem.fromJson(w as Map<String, dynamic>)).toList();
  }

  /// Returns true if the product is now wishlisted, false if it was
  /// just removed — a toggle, mirroring WishlistService::toggle().
  Future<bool> toggle(int productId) async {
    final response = await _apiClient.post('/wishlist/$productId');

    return (response['data'] as Map<String, dynamic>)['wishlisted'] as bool;
  }
}
