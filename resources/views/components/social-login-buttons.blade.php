{{--
    "Continue with Google/Facebook" — shared by auth.login and
    auth.register (App\Http\Controllers\Auth\SocialLoginController's
    find-or-create means there's nothing separate to offer on register).
--}}
<div class="mt-6">
    <div class="flex items-center gap-3">
        <div class="h-px flex-1 bg-slate-100"></div>
        <span class="text-body-sm text-slate-500">or</span>
        <div class="h-px flex-1 bg-slate-100"></div>
    </div>

    <div class="mt-6 space-y-3">
        <a href="{{ route('social.redirect', 'google') }}"
            class="flex h-12 items-center justify-center gap-3 rounded-sm border border-slate-200 text-body-md font-semibold text-slate-900 hover:bg-slate-50 transition-colors duration-fast">
            <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
                <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.9c1.7-1.57 2.7-3.88 2.7-6.62Z"/>
                <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.9-2.26c-.8.54-1.84.86-3.06.86-2.35 0-4.34-1.59-5.05-3.72H.95v2.33A9 9 0 0 0 9 18Z"/>
                <path fill="#FBBC05" d="M3.95 10.7a5.4 5.4 0 0 1 0-3.4V4.97H.95a9 9 0 0 0 0 8.06l3-2.33Z"/>
                <path fill="#EA4335" d="M9 3.58c1.32 0 2.51.46 3.44 1.35l2.58-2.58A8.98 8.98 0 0 0 9 0 9 9 0 0 0 .95 4.97l3 2.33C4.66 5.17 6.65 3.58 9 3.58Z"/>
            </svg>
            Continue with Google
        </a>

        <a href="{{ route('social.redirect', 'facebook') }}"
            class="flex h-12 items-center justify-center gap-3 rounded-sm border border-slate-200 text-body-md font-semibold text-slate-900 hover:bg-slate-50 transition-colors duration-fast">
            <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" fill="#1877F2">
                <path d="M18 9a9 9 0 1 0-10.41 8.89v-6.29H5.31V9h2.28V7.02c0-2.25 1.34-3.49 3.39-3.49.98 0 2.01.18 2.01.18v2.21h-1.13c-1.11 0-1.46.69-1.46 1.4V9h2.49l-.4 2.6h-2.09v6.29A9 9 0 0 0 18 9Z"/>
            </svg>
            Continue with Facebook
        </a>
    </div>
</div>
