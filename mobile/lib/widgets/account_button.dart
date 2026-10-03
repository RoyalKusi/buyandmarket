import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/theme/app_theme.dart';
import '../providers/auth_provider.dart';

/// Account entry point in [AppTopBar] — a real profile photo when a
/// social sign-in provided one, an initial-letter avatar for a signed-in
/// buyer otherwise, or a plain person icon for a guest. Always routes to
/// `/account`, which itself handles the signed-in/guest split.
class AccountButton extends ConsumerWidget {
  const AccountButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final user = authState.user;

    return IconButton(
      tooltip: 'Account',
      onPressed: () => context.push('/account'),
      icon: user == null
          ? const Icon(Icons.account_circle_outlined)
          : CircleAvatar(
              radius: 14,
              backgroundColor: AppColors.blue50,
              backgroundImage: user.avatarUrl != null ? NetworkImage(user.avatarUrl!) : null,
              child: user.avatarUrl == null
                  ? Text(user.initial, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.blue600))
                  : null,
            ),
    );
  }
}
