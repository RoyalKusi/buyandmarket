# BuyAndMarket mobile

Flutter app for the buyer-facing side of BuyAndMarket: browse/search,
product detail, cart, checkout (Pesepay/Paynow), order history,
wishlist, addresses, and account — talking to the Laravel app's
`/api/v1` surface (see the repo root `README.md` and `CHANGELOG.md`).

## Running it

```
flutter pub get
flutter run --dart-define=API_BASE_URL=https://your-backend/api/v1
```

`API_BASE_URL` defaults to `http://10.0.2.2:8000/api/v1` (the Android
emulator's alias for the host machine), so `flutter run` against a local
`php artisan serve` works without any flags on the most common setup.
See `lib/core/config.dart`.

## Google / Facebook sign-in setup

The app and backend both have the Google/Facebook sign-in code wired up
and tested, but **neither provider works until you register real apps
and drop in the credentials below** — there is nothing to build, only
configuration.

### 1. Google

1. In [Google Cloud Console](https://console.cloud.google.com/) →
   APIs & Services → Credentials, create an **OAuth 2.0 Client ID** of
   type **Web application**. Copy its Client ID and Client Secret.
2. Set these in the backend's `.env` (never commit real values):
   ```
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   ```
3. Create a second OAuth client of type **Android**, using this app's
   package name (`android/app/build.gradle`'s `applicationId`) and your
   signing certificate's SHA-1 fingerprint (`keytool -list -v
   -keystore <your keystore>`). No code change needed for this one —
   Google Play Services on the device matches it automatically.
4. Create a third OAuth client of type **iOS**, using this app's bundle
   ID. Put its client ID into `ios/Runner/Info.plist`'s `GIDClientID`
   key, and its reversed form (swap the dot-separated parts, e.g.
   `123-abc.apps.googleusercontent.com` → `com.googleusercontent.apps.123-abc`)
   into the same file's `CFBundleURLTypes`, replacing
   `com.googleusercontent.apps.YOUR_IOS_REVERSED_CLIENT_ID`.

### 2. Facebook

1. At [developers.facebook.com](https://developers.facebook.com/apps),
   create an app with the **Facebook Login** product added.
2. From Settings → Basic, copy the **App ID** and **Client Token**
   (not the App Secret).
3. Set these in the backend's `.env`:
   ```
   FACEBOOK_CLIENT_ID=<App ID>
   FACEBOOK_CLIENT_SECRET=<App Secret>
   ```
4. Replace the placeholders in:
   - `android/app/src/main/res/values/facebook_strings.xml`
     (`facebook_app_id`, `facebook_client_token`,
     `fb_login_protocol_scheme`)
   - `ios/Runner/Info.plist` (`FacebookAppID`, `FacebookClientToken`,
     and the `fbYOUR_FACEBOOK_APP_ID` URL scheme)
5. In the Facebook app's own Login settings, add this app's Android
   package name + key hash, and its iOS bundle ID, under Settings →
   Basic → Add Platform.

### How it works

The app performs sign-in natively on-device
(`google_sign_in`/`flutter_facebook_auth`), then POSTs the resulting
provider access token to `POST /api/v1/auth/google` or `/facebook`
(`App\Http\Controllers\Api\V1\SocialAuthController`), which verifies it
against the provider's own API and returns the same `{data, token}`
shape as email/password login. A returning social user is matched by
provider ID; a first-time one is linked to an existing email/password
account by email, or a new buyer account is created.
