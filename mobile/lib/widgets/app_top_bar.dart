import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../core/theme/app_theme.dart';
import 'account_button.dart';

/// The persistent top bar shown across every main tab (Home, Cart,
/// Orders, Wishlist) — logo, a tappable search pill, and an account
/// icon, mirroring the web storefront's own header (logo, search,
/// account) so the app reads as the same product. The Search tab keeps
/// its own richer app bar (a real editable field, a sort menu) instead
/// of this one — see AppShell.
class AppTopBar extends StatelessWidget implements PreferredSizeWidget {
  const AppTopBar({this.showBackButton = false, super.key});

  /// True for a tab-root screen reached by pushing deeper within the
  /// shell (e.g. a category page) rather than one of the five bottom-nav
  /// destinations — a back arrow stands in for the logo there, since
  /// removing each screen's own app bar also removed its back affordance.
  final bool showBackButton;

  @override
  Widget build(BuildContext context) {
    return AppBar(
      titleSpacing: 12,
      title: Row(
        children: [
          if (showBackButton)
            IconButton(
              icon: const Icon(Icons.arrow_back),
              tooltip: 'Back',
              onPressed: () => Navigator.of(context).canPop() ? Navigator.of(context).pop() : context.go('/'),
            )
          else
            InkWell(
              borderRadius: BorderRadius.circular(8),
              onTap: () => context.go('/'),
              child: Padding(
                padding: const EdgeInsets.all(4),
                child: Image.asset('assets/images/logo.png', height: 28, semanticLabel: 'BuyAndMarket home'),
              ),
            ),
          const SizedBox(width: 8),
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(999),
              onTap: () => context.push('/search'),
              child: Container(
                height: 40,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(color: AppColors.slate50, borderRadius: BorderRadius.circular(999)),
                child: Row(
                  children: [
                    Icon(Icons.search, size: 20, color: AppColors.slate400),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Search products, stores...',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.slate400),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
      actions: const [AccountButton(), SizedBox(width: 4)],
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);
}
