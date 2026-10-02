import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme/app_theme.dart';
import '../../providers/auth_provider.dart';

class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final user = authState.user;

    return Scaffold(
      appBar: AppBar(title: const Text('Account')),
      body: user == null ? const _GuestAccountView() : _SignedInAccountView(userName: user.name, userEmail: user.email, avatarUrl: user.avatarUrl, initial: user.initial),
    );
  }
}

class _GuestAccountView extends StatelessWidget {
  const _GuestAccountView();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Image.asset('assets/images/logo.png', height: 40),
            const SizedBox(height: 24),
            Text('Sign in to manage your account', style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(
              'Track orders, save addresses, and build your wishlist.',
              style: Theme.of(context).textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            ElevatedButton(onPressed: () => context.push('/login'), child: const Text('Sign in')),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: () => context.push('/register'), child: const Text('Create an account')),
          ],
        ),
      ),
    );
  }
}

class _SignedInAccountView extends ConsumerWidget {
  const _SignedInAccountView({required this.userName, required this.userEmail, required this.avatarUrl, required this.initial});

  final String userName;
  final String userEmail;
  final String? avatarUrl;
  final String initial;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return ListView(
      padding: const EdgeInsets.symmetric(vertical: 16),
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: AppColors.blue50,
                backgroundImage: avatarUrl != null ? NetworkImage(avatarUrl!) : null,
                child: avatarUrl == null
                    ? Text(initial, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppColors.blue600))
                    : null,
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(userName, style: Theme.of(context).textTheme.titleLarge),
                    Text(userEmail, style: Theme.of(context).textTheme.bodyMedium),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const Divider(height: 1),
        ListTile(
          leading: const Icon(Icons.receipt_long_outlined),
          title: const Text('Orders'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => context.go('/orders'),
        ),
        ListTile(
          leading: const Icon(Icons.favorite_outline),
          title: const Text('Wishlist'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => context.go('/wishlist'),
        ),
        ListTile(
          leading: const Icon(Icons.location_on_outlined),
          title: const Text('Addresses'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => context.push('/account/addresses'),
        ),
        const Divider(height: 1),
        ListTile(
          leading: Icon(Icons.logout, color: Theme.of(context).colorScheme.error),
          title: Text('Sign out', style: TextStyle(color: Theme.of(context).colorScheme.error)),
          onTap: () async {
            await ref.read(authProvider.notifier).logout();
            if (context.mounted) context.go('/');
          },
        ),
      ],
    );
  }
}
