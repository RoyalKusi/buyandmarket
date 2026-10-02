import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_exception.dart';
import '../../models/address.dart';
import '../../models/cart_item.dart';
import '../../models/checkout_session.dart';
import '../../models/delivery_rate_card.dart';
import '../../providers/app_providers.dart';
import '../../providers/cart_provider.dart';

final _currency = NumberFormat.currency(symbol: '\$', decimalDigits: 2);

/// A single multi-step checkout: address -> delivery (per store) ->
/// payment, mirroring the web's three-page flow
/// (Storefront\CheckoutController) but as one screen with a stepper,
/// since a mobile buyer expects to stay on one page rather than
/// navigate between three.
class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  int _step = 0;
  bool _isBusy = true;
  String? _errorMessage;

  CheckoutSession? _session;
  List<Address> _addresses = const [];
  int? _selectedAddressId;

  Map<int, List<CartItem>> _itemsByStore = const {};
  final Map<int, List<DeliveryRateCard>> _rateCardsByStore = {};
  final Map<int, DeliveryRateCard> _selectedRateCardByStore = {};

  String _provider = 'pesepay';

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    setState(() {
      _isBusy = true;
      _errorMessage = null;
    });

    try {
      final cart = await ref.read(cartRepositoryProvider).show();
      _itemsByStore = {};
      for (final item in cart.items) {
        final storeId = item.product?.store?.id;
        if (storeId == null) continue;
        _itemsByStore.putIfAbsent(storeId, () => []).add(item);
      }

      final session = await ref.read(checkoutRepositoryProvider).start();
      final addresses = await ref.read(addressRepositoryProvider).list();

      final defaultAddress = addresses.where((a) => a.isDefault);

      setState(() {
        _session = session;
        _addresses = addresses;
        _selectedAddressId = defaultAddress.isNotEmpty ? defaultAddress.first.id : (addresses.isNotEmpty ? addresses.first.id : null);
      });

      for (final storeId in _itemsByStore.keys) {
        _rateCardsByStore[storeId] = await ref.read(checkoutRepositoryProvider).deliveryRateCards(storeId);
      }
    } on ApiException catch (e) {
      setState(() => _errorMessage = e.message);
    } finally {
      if (mounted) setState(() => _isBusy = false);
    }
  }

  double get _subtotal => _itemsByStore.values.expand((items) => items).fold(0, (sum, item) => sum + item.lineTotal);

  double get _deliveryTotal => _selectedRateCardByStore.values.fold(0, (sum, card) => sum + card.baseFee);

  Future<void> _continueFromAddress() async {
    if (_selectedAddressId == null || _session == null) return;

    setState(() {
      _isBusy = true;
      _errorMessage = null;
    });
    try {
      await ref.read(checkoutRepositoryProvider).setAddress(sessionId: _session!.id, addressId: _selectedAddressId!);
      setState(() => _step = 1);
    } on ApiException catch (e) {
      setState(() => _errorMessage = e.message);
    } finally {
      if (mounted) setState(() => _isBusy = false);
    }
  }

  Future<void> _continueFromDelivery() async {
    if (_session == null || _selectedRateCardByStore.length != _itemsByStore.length) {
      setState(() => _errorMessage = 'Pick a delivery method for every store in your cart.');
      return;
    }

    setState(() {
      _isBusy = true;
      _errorMessage = null;
    });
    try {
      final selection = _selectedRateCardByStore.map(
        (storeId, card) => MapEntry(storeId.toString(), {'fee': card.baseFee.toStringAsFixed(2)}),
      );
      await ref.read(checkoutRepositoryProvider).setDelivery(sessionId: _session!.id, selectionByStoreId: selection);
      setState(() => _step = 2);
    } on ApiException catch (e) {
      setState(() => _errorMessage = e.message);
    } finally {
      if (mounted) setState(() => _isBusy = false);
    }
  }

  Future<void> _pay() async {
    if (_session == null) return;

    setState(() {
      _isBusy = true;
      _errorMessage = null;
    });
    try {
      final result = await ref.read(checkoutRepositoryProvider).initiatePayment(sessionId: _session!.id, provider: _provider);
      await ref.read(cartProvider.notifier).refresh();

      if (!mounted) return;

      if (result.redirectUrl != null) {
        await launchUrl(Uri.parse(result.redirectUrl!), mode: LaunchMode.externalApplication);
      } else if (result.instructions != null) {
        await showDialog<void>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Complete your payment'),
            content: Text(result.instructions!),
            actions: [TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('OK'))],
          ),
        );
      }

      if (!mounted) return;
      context.go('/orders');
    } on ApiException catch (e) {
      setState(() => _errorMessage = e.message);
    } finally {
      if (mounted) setState(() => _isBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: _isBusy && _session == null
          ? const Center(child: CircularProgressIndicator())
          : SafeArea(
              child: Column(
                children: [
                  if (_errorMessage != null)
                    Container(
                      width: double.infinity,
                      margin: const EdgeInsets.all(16),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: Theme.of(context).colorScheme.errorContainer, borderRadius: BorderRadius.circular(8)),
                      child: Text(_errorMessage!, style: TextStyle(color: Theme.of(context).colorScheme.onErrorContainer)),
                    ),
                  Expanded(
                    child: switch (_step) {
                      0 => _buildAddressStep(),
                      1 => _buildDeliveryStep(),
                      _ => _buildPaymentStep(),
                    },
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildAddressStep() {
    return Column(
      children: [
        Expanded(
          child: _addresses.isEmpty
              ? const Center(child: Text('Add a delivery address first, from your profile.'))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: _addresses
                      .map(
                        (address) => RadioListTile<int>(
                          value: address.id,
                          groupValue: _selectedAddressId,
                          onChanged: (value) => setState(() => _selectedAddressId = value),
                          title: Text(address.label),
                          subtitle: Text(address.oneLine),
                        ),
                      )
                      .toList(),
                ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: ElevatedButton(onPressed: _isBusy || _selectedAddressId == null ? null : _continueFromAddress, child: const Text('Continue to delivery')),
        ),
      ],
    );
  }

  Widget _buildDeliveryStep() {
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: _itemsByStore.entries.map((entry) {
              final storeId = entry.key;
              final storeName = entry.value.first.product?.store?.name ?? 'Store';
              final rateCards = _rateCardsByStore[storeId] ?? const [];

              return Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(storeName, style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      if (rateCards.isEmpty)
                        const Text('This seller has no delivery options configured yet.')
                      else
                        ...rateCards.map(
                          (card) => RadioListTile<DeliveryRateCard>(
                            value: card,
                            groupValue: _selectedRateCardByStore[storeId],
                            onChanged: (value) => setState(() => _selectedRateCardByStore[storeId] = value!),
                            title: Text('${card.method[0].toUpperCase()}${card.method.substring(1)} · ${card.etaLabel}'),
                            subtitle: card.zoneName != null ? Text(card.zoneName!) : null,
                            secondary: Text(_currency.format(card.baseFee)),
                          ),
                        ),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: ElevatedButton(onPressed: _isBusy ? null : _continueFromDelivery, child: const Text('Continue to payment')),
        ),
      ],
    );
  }

  Widget _buildPaymentStep() {
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    children: [
                      _SummaryRow(label: 'Subtotal', value: _currency.format(_subtotal)),
                      _SummaryRow(label: 'Delivery', value: _currency.format(_deliveryTotal)),
                      const Divider(),
                      _SummaryRow(label: 'Total', value: _currency.format(_subtotal + _deliveryTotal), isTotal: true),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text('Payment method', style: Theme.of(context).textTheme.titleMedium),
              RadioListTile<String>(
                value: 'pesepay',
                groupValue: _provider,
                onChanged: (value) => setState(() => _provider = value!),
                title: const Text('Pesepay'),
              ),
              RadioListTile<String>(
                value: 'paynow',
                groupValue: _provider,
                onChanged: (value) => setState(() => _provider = value!),
                title: const Text('Paynow'),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: ElevatedButton(
            onPressed: _isBusy ? null : _pay,
            child: _isBusy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Pay now'),
          ),
        ),
      ],
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.label, required this.value, this.isTotal = false});

  final String label;
  final String value;
  final bool isTotal;

  @override
  Widget build(BuildContext context) {
    final style = isTotal ? Theme.of(context).textTheme.titleLarge : Theme.of(context).textTheme.bodyMedium;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(label, style: style), Text(value, style: style)],
      ),
    );
  }
}
