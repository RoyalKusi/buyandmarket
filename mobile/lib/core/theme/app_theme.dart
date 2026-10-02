import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Design tokens mirrored 1:1 from the web client's tailwind.config.js
/// (Design System §1/§2) so the app looks like the same product, not a
/// reskin. Royal Blue is the brand primary; Marketplace Gold is an
/// accent only — per the web design system's own rule, it is never
/// used for a primary call-to-action.
class AppColors {
  AppColors._();

  static const blue50 = Color(0xFFEEF3FC);
  static const blue100 = Color(0xFFDCE6F8);
  static const blue500 = Color(0xFF1F53B5);
  static const blue600 = Color(0xFF003594);
  static const blue700 = Color(0xFF002B78);

  static const gold400 = Color(0xFFFFC94D);
  static const gold500 = Color(0xFFFFB81C);

  static const success50 = Color(0xFFE8F6EE);
  static const success600 = Color(0xFF0B7A3B);

  static const danger50 = Color(0xFFFDECEE);
  static const danger600 = Color(0xFFC8102E);

  static const warning50 = Color(0xFFFFF6E0);
  static const warning700 = Color(0xFF8A5A00);

  static const slate0 = Color(0xFFFFFFFF);
  static const slate25 = Color(0xFFFAFBFD);
  static const slate50 = Color(0xFFF4F6FA);
  static const slate100 = Color(0xFFE9EDF4);
  static const slate200 = Color(0xFFD5DBE6);
  static const slate300 = Color(0xFFB6BFCF);
  static const slate400 = Color(0xFF8B96AB);
  static const slate500 = Color(0xFF66718A);
  static const slate600 = Color(0xFF4B566C);
  static const slate700 = Color(0xFF363F52);
  static const slate900 = Color(0xFF131926);
}

class AppTheme {
  AppTheme._();

  static ThemeData light() {
    final displayFont = GoogleFonts.spaceGrotesk();
    final bodyFont = GoogleFonts.inter();

    final colorScheme = ColorScheme.fromSeed(
      seedColor: AppColors.blue600,
      primary: AppColors.blue600,
      secondary: AppColors.gold500,
      error: AppColors.danger600,
      surface: AppColors.slate0,
      brightness: Brightness.light,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: AppColors.slate50,
      fontFamily: bodyFont.fontFamily,
      textTheme: TextTheme(
        displaySmall: displayFont.copyWith(fontSize: 28, fontWeight: FontWeight.w700, letterSpacing: -0.015 * 28, color: AppColors.slate900),
        headlineSmall: displayFont.copyWith(fontSize: 24, fontWeight: FontWeight.w600, color: AppColors.slate900),
        titleLarge: displayFont.copyWith(fontSize: 20, fontWeight: FontWeight.w600, color: AppColors.slate900),
        titleMedium: displayFont.copyWith(fontSize: 17, fontWeight: FontWeight.w600, color: AppColors.slate900),
        bodyLarge: bodyFont.copyWith(fontSize: 16, color: AppColors.slate700),
        bodyMedium: bodyFont.copyWith(fontSize: 14, color: AppColors.slate700),
        bodySmall: bodyFont.copyWith(fontSize: 13, color: AppColors.slate500),
        labelLarge: bodyFont.copyWith(fontSize: 15, fontWeight: FontWeight.w600),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.slate0,
        foregroundColor: AppColors.slate900,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        titleTextStyle: displayFont.copyWith(fontSize: 18, fontWeight: FontWeight.w600, color: AppColors.blue600),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.blue600,
          foregroundColor: AppColors.slate0,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          textStyle: bodyFont.copyWith(fontWeight: FontWeight.w600, fontSize: 15),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.blue600,
          side: const BorderSide(color: AppColors.blue600),
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          textStyle: bodyFont.copyWith(fontWeight: FontWeight.w600, fontSize: 15),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.slate0,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.slate200),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.slate200),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.blue600, width: 2),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),
      cardTheme: CardThemeData(
        color: AppColors.slate0,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: AppColors.slate100),
        ),
      ),
      dividerTheme: const DividerThemeData(color: AppColors.slate100, thickness: 1),
    );
  }
}
