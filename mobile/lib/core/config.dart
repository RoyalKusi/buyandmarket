/// API base URL. Overridable at build/run time via
/// `--dart-define=API_BASE_URL=https://buyandmarket.com/api/v1` for a
/// real device; defaults to the Android emulator's host-loopback
/// alias so `flutter run` against a local `php artisan serve` works
/// out of the box on the most common dev setup.
class AppConfig {
  AppConfig._();

  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
}
