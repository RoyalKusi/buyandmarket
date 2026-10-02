import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../models/address.dart';
import '../../providers/app_providers.dart';

class AddressesScreen extends ConsumerStatefulWidget {
  const AddressesScreen({super.key});

  @override
  ConsumerState<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends ConsumerState<AddressesScreen> {
  late Future<List<Address>> _future;

  @override
  void initState() {
    super.initState();
    _future = ref.read(addressRepositoryProvider).list();
  }

  Future<void> _refresh() async {
    final future = ref.read(addressRepositoryProvider).list();
    setState(() => _future = future);
    await future;
  }

  Future<void> _delete(Address address) async {
    try {
      await ref.read(addressRepositoryProvider).delete(address.id);
      await _refresh();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _openAddSheet() async {
    final added = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => const _AddAddressSheet(),
    );
    if (added == true) await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Your addresses')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openAddSheet,
        icon: const Icon(Icons.add),
        label: const Text('Add address'),
      ),
      body: FutureBuilder<List<Address>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('Could not load your addresses.'),
                  const SizedBox(height: 8),
                  OutlinedButton(onPressed: _refresh, child: const Text('Retry')),
                ],
              ),
            );
          }

          final addresses = snapshot.data!;
          if (addresses.isEmpty) {
            return const Center(child: Text("You haven't saved an address yet."));
          }

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
              itemCount: addresses.length,
              separatorBuilder: (context, index) => const Divider(),
              itemBuilder: (context, index) {
                final address = addresses[index];
                return ListTile(
                  leading: Icon(address.isDefault ? Icons.location_on : Icons.location_on_outlined),
                  title: Row(
                    children: [
                      Text(address.label),
                      if (address.isDefault) ...[
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: Theme.of(context).colorScheme.primaryContainer,
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text('Default', style: Theme.of(context).textTheme.bodySmall),
                        ),
                      ],
                    ],
                  ),
                  subtitle: Text(address.oneLine),
                  trailing: IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _delete(address)),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _AddAddressSheet extends ConsumerStatefulWidget {
  const _AddAddressSheet();

  @override
  ConsumerState<_AddAddressSheet> createState() => _AddAddressSheetState();
}

class _AddAddressSheetState extends ConsumerState<_AddAddressSheet> {
  final _formKey = GlobalKey<FormState>();
  final _label = TextEditingController(text: 'Home');
  final _recipientName = TextEditingController();
  final _phone = TextEditingController();
  final _province = TextEditingController();
  final _city = TextEditingController();
  final _area = TextEditingController();
  final _streetAddress = TextEditingController();
  bool _isDefault = false;
  bool _isSubmitting = false;
  String? _errorMessage;

  @override
  void dispose() {
    _label.dispose();
    _recipientName.dispose();
    _phone.dispose();
    _province.dispose();
    _city.dispose();
    _area.dispose();
    _streetAddress.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
    });

    try {
      await ref.read(addressRepositoryProvider).create(
        label: _label.text.trim(),
        recipientName: _recipientName.text.trim(),
        phone: _phone.text.trim(),
        province: _province.text.trim(),
        city: _city.text.trim(),
        area: _area.text.trim().isEmpty ? null : _area.text.trim(),
        streetAddress: _streetAddress.text.trim(),
        isDefault: _isDefault,
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _errorMessage = e.message);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Add an address', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 16),
                if (_errorMessage != null) ...[
                  Text(_errorMessage!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  const SizedBox(height: 12),
                ],
                TextFormField(
                  controller: _label,
                  decoration: const InputDecoration(labelText: 'Label (e.g. Home)'),
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _recipientName,
                  decoration: const InputDecoration(labelText: 'Recipient name'),
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'Phone'),
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _province,
                  decoration: const InputDecoration(labelText: 'Province'),
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _city,
                  decoration: const InputDecoration(labelText: 'City'),
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(controller: _area, decoration: const InputDecoration(labelText: 'Area (optional)')),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _streetAddress,
                  decoration: const InputDecoration(labelText: 'Street address'),
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                ),
                CheckboxListTile(
                  value: _isDefault,
                  onChanged: (value) => setState(() => _isDefault = value ?? false),
                  title: const Text('Set as default'),
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                const SizedBox(height: 8),
                ElevatedButton(
                  onPressed: _isSubmitting ? null : _submit,
                  child: _isSubmitting
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Save address'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
