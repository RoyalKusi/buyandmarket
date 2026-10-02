class AppUser {
  AppUser({required this.id, required this.name, required this.email, this.emailVerifiedAt, this.avatarUrl});

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      emailVerifiedAt: json['email_verified_at'] as String?,
      avatarUrl: json['avatar_url'] as String?,
    );
  }

  final int id;
  final String name;
  final String email;
  final String? emailVerifiedAt;
  final String? avatarUrl;

  bool get hasVerifiedEmail => emailVerifiedAt != null;

  String get initial => name.trim().isNotEmpty ? name.trim()[0].toUpperCase() : '?';
}
