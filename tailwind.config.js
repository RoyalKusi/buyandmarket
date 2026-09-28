import defaultTheme from 'tailwindcss/defaultTheme';

// Every value below is sourced verbatim from the BuyAndMarket Design System
// (§1 colour system, §2 typography, §3 layout, §5.2 motion, §9 implementation
// tokens). This file is the single source of truth for design tokens on the
// web client — no arbitrary Tailwind values (`p-[13px]`, inline hex) are
// permitted anywhere else in the codebase.

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        // Design System §3.2: breakpoints are the theme's screens, not an
        // extension — mobile-first, each breakpoint adds rather than overrides.
        screens: {
            xs: '0px',
            sm: '375px',
            md: '640px',
            lg: '1024px',
            xl: '1280px',
            '2xl': '1536px',
        },
        extend: {
            colors: {
                // §1.5 Brand — Royal Blue
                blue: {
                    50: '#EEF3FC',
                    100: '#DCE6F8',
                    200: '#B9CDF1',
                    300: '#8AAAE4',
                    400: '#5580D0',
                    500: '#1F53B5',
                    600: '#003594',
                    700: '#002B78',
                    800: '#00215C',
                    900: '#001742',
                    950: '#000F2B',
                },
                // §1.5 Accent — Marketplace Gold (never for primary CTAs)
                gold: {
                    400: '#FFC94D',
                    500: '#FFB81C',
                    600: '#E6A000',
                    700: '#B87A00',
                },
                // §1.5 Semantic
                success: {
                    50: '#E8F6EE',
                    600: '#0B7A3B',
                    700: '#095F2E',
                },
                danger: {
                    50: '#FDECEE',
                    600: '#C8102E',
                    700: '#9E0C24',
                },
                warning: {
                    50: '#FFF6E0',
                    700: '#8A5A00',
                },
                info: {
                    50: '#E6F8FA',
                    500: '#12B5C9',
                    700: '#0A7C8A',
                },
                // §1.5 Neutrals — cool-toned Slate scale
                slate: {
                    0: '#FFFFFF',
                    25: '#FAFBFD',
                    50: '#F4F6FA',
                    100: '#E9EDF4',
                    200: '#D5DBE6',
                    300: '#B6BFCF',
                    400: '#8B96AB',
                    500: '#66718A',
                    600: '#4B566C',
                    700: '#363F52',
                    800: '#232B3B',
                    900: '#131926',
                    950: '#0A0E17',
                },
            },
            // §2.1 Font families — self-hosted WOFF2, Latin + Latin Extended
            fontFamily: {
                display: ['"Space Grotesk"', 'Inter', 'system-ui', 'sans-serif'],
                sans: ['Inter', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'],
                mono: ['ui-monospace', '"JetBrains Mono"', 'monospace'],
            },
            // §2.2 Type scale — base 16px, ratio 1.25 above / 1.125 below
            fontSize: {
                'display-xl': ['3rem', { lineHeight: '3.5rem', letterSpacing: '-0.02em', fontWeight: '700' }],
                'display-lg': ['2.25rem', { lineHeight: '2.75rem', letterSpacing: '-0.015em', fontWeight: '700' }],
                'display-md': ['1.75rem', { lineHeight: '2.25rem', letterSpacing: '-0.01em', fontWeight: '600' }],
                'heading-lg': ['1.5rem', { lineHeight: '2rem', letterSpacing: '-0.01em', fontWeight: '600' }],
                'heading-md': ['1.25rem', { lineHeight: '1.75rem', fontWeight: '600' }],
                'heading-sm': ['1.0625rem', { lineHeight: '1.5rem', fontWeight: '600' }],
                'body-lg': ['1rem', { lineHeight: '1.5rem', fontWeight: '400' }],
                'body-md': ['0.875rem', { lineHeight: '1.4286rem', fontWeight: '400' }],
                'body-sm': ['0.8125rem', { lineHeight: '1.3846rem', letterSpacing: '0.005em', fontWeight: '400' }],
                caption: ['0.75rem', { lineHeight: '1.3333rem', letterSpacing: '0.01em', fontWeight: '500' }],
                micro: ['0.6875rem', { lineHeight: '1.2727rem', letterSpacing: '0.03em', fontWeight: '600' }],
                'price-lg': ['1.5rem', { lineHeight: '1.1667rem', letterSpacing: '-0.01em', fontWeight: '700' }],
                'price-md': ['1rem', { lineHeight: '1.25rem', fontWeight: '700' }],
                button: ['0.9375rem', { lineHeight: '1.3333rem', letterSpacing: '0.01em', fontWeight: '600' }],
            },
            // §3.1 Spacing scale — 4px base unit, 8px rhythm
            spacing: {
                1: '0.25rem',
                2: '0.5rem',
                3: '0.75rem',
                4: '1rem',
                5: '1.25rem',
                6: '1.5rem',
                8: '2rem',
                10: '2.5rem',
                12: '3rem',
                16: '4rem',
                20: '5rem',
            },
            // §1.6 Radii scale
            borderRadius: {
                xs: '4px',
                sm: '8px',
                md: '12px',
                lg: '16px',
                xl: '24px',
                full: '9999px',
            },
            // §1.6 Shadow/elevation scale — cool-toned, tinted with slate-900
            boxShadow: {
                1: '0 1px 2px rgba(19,25,38,.06), 0 1px 1px rgba(19,25,38,.04)',
                2: '0 2px 8px rgba(19,25,38,.08)',
                3: '0 8px 24px rgba(19,25,38,.12)',
                4: '0 16px 48px rgba(19,25,38,.16)',
            },
            // §5.2 Durations and easing
            transitionDuration: {
                fast: '150ms',
                base: '200ms',
                settle: '250ms',
            },
            transitionTimingFunction: {
                settle: 'cubic-bezier(0.16,1,0.3,1)',
            },
        },
    },
    plugins: [],
};
