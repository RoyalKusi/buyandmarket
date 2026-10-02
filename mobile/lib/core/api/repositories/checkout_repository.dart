import '../../../models/checkout_session.dart';
import '../../../models/delivery_rate_card.dart';
import '../api_client.dart';

class PaymentInitiationResult {
  PaymentInitiationResult({this.redirectUrl, this.instructions});

  factory PaymentInitiationResult.fromJson(Map<String, dynamic> json) {
    return PaymentInitiationResult(
      redirectUrl: json['redirect_url'] as String?,
      instructions: json['instructions'] as String?,
    );
  }

  final String? redirectUrl;
  final String? instructions;
}

class CheckoutRepository {
  CheckoutRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  Future<CheckoutSession> start({String? guestEmail, String? guestPhone}) async {
    final response = await _apiClient.post(
      '/checkout/session',
      data: {
        if (guestEmail != null) 'guest_email': guestEmail,
        if (guestPhone != null) 'guest_phone': guestPhone,
      },
    );

    return CheckoutSession.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<List<DeliveryRateCard>> deliveryRateCards(int storeId) async {
    final response = await _apiClient.get('/stores/$storeId/delivery-rate-cards');

    return (response['data'] as List<dynamic>).map((c) => DeliveryRateCard.fromJson(c as Map<String, dynamic>)).toList();
  }

  Future<CheckoutSession> setAddress({required int sessionId, required int addressId}) async {
    final response = await _apiClient.patch('/checkout/session/$sessionId/address', data: {'address_id': addressId});

    return CheckoutSession.fromJson(response['data'] as Map<String, dynamic>);
  }

  /// [selectionByStoreId] mirrors the web's own shape exactly: store id
  /// (as a string key, since it travels as JSON) to `{'fee': '3.00'}`.
  Future<CheckoutSession> setDelivery({
    required int sessionId,
    required Map<String, Map<String, String>> selectionByStoreId,
  }) async {
    final response = await _apiClient.patch(
      '/checkout/session/$sessionId/delivery',
      data: {'selection': selectionByStoreId},
    );

    return CheckoutSession.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<PaymentInitiationResult> initiatePayment({required int sessionId, required String provider}) async {
    final response = await _apiClient.post('/checkout/session/$sessionId/payment', data: {'provider': provider});

    return PaymentInitiationResult.fromJson(response['data'] as Map<String, dynamic>);
  }
}
