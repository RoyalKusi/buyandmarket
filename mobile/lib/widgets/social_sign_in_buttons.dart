import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/theme/app_theme.dart';
import '../providers/auth_provider.dart';

/// "Continue with Google" / "Continue with Facebook", shared by the
/// login and register screens — the backend treats a social sign-in as
/// both at once (find-or-create), so there's nothing separate to offer
/// on the register screen beyond this.
class SocialSignInButtons extends ConsumerStatefulWidget {
  const SocialSignInButtons({required this.onSignedIn, required this.onError, super.key});

  final VoidCallback onSignedIn;
  final void Function(String message) onError;

  @override
  ConsumerState<SocialSignInButtons> createState() => _SocialSignInButtonsState();
}

class _SocialSignInButtonsState extends ConsumerState<SocialSignInButtons> {
  bool _isGoogleBusy = false;
  bool _isFacebookBusy = false;

  Future<void> _withGoogle() async {
    setState(() => _isGoogleBusy = true);
    try {
      final signedIn = await ref.read(authProvider.notifier).loginWithGoogle();
      if (signedIn && mounted) widget.onSignedIn();
    } catch (e) {
      widget.onError('Could not sign in with Google. Please try again.');
    } finally {
      if (mounted) setState(() => _isGoogleBusy = false);
    }
  }

  Future<void> _withFacebook() async {
    setState(() => _isFacebookBusy = true);
    try {
      final signedIn = await ref.read(authProvider.notifier).loginWithFacebook();
      if (signedIn && mounted) widget.onSignedIn();
    } catch (e) {
      widget.onError('Could not sign in with Facebook. Please try again.');
    } finally {
      if (mounted) setState(() => _isFacebookBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        OutlinedButton.icon(
          onPressed: _isGoogleBusy ? null : _withGoogle,
          icon: _isGoogleBusy
              ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const _GoogleGlyph(),
          label: const Text('Continue with Google'),
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.slate900,
            side: const BorderSide(color: AppColors.slate200),
          ),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: _isFacebookBusy ? null : _withFacebook,
          icon: _isFacebookBusy
              ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Icon(Icons.facebook, color: Color(0xFF1877F2)),
          label: const Text('Continue with Facebook'),
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.slate900,
            side: const BorderSide(color: AppColors.slate200),
          ),
        ),
      ],
    );
  }
}

/// A plain-Material-Icons substitute for Google's "G" mark — there is no
/// official multi-colour Google logo in the Material Icons font, and
/// this avoids pulling in a brand-asset SVG package for one glyph.
class _GoogleGlyph extends StatelessWidget {
  const _GoogleGlyph();

  @override
  Widget build(BuildContext context) {
    return const SizedBox(
      height: 18,
      width: 18,
      child: Center(
        child: Text(
          'G',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Color(0xFF4285F4)),
        ),
      ),
    );
  }
}

/// A thin horizontal "or" divider separating password fields from the
/// social sign-in options below them.
class OrDivider extends StatelessWidget {
  const OrDivider({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 20),
      child: Row(
        children: [
          const Expanded(child: Divider()),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Text('or', style: Theme.of(context).textTheme.bodySmall),
          ),
          const Expanded(child: Divider()),
        ],
      ),
    );
  }
}
