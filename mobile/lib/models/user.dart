class AppUser {
  AppUser({required this.id, required this.name, required this.email, this.emailVerifiedAt});

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      emailVerifiedAt: json['email_verified_at'] as String?,
    );
  }

  final int id;
  final String name;
  final String email;
  final String? emailVerifiedAt;

  bool get hasVerifiedEmail => emailVerifiedAt != null;
}
