class Store {
  Store({required this.id, required this.name, required this.slug});

  factory Store.fromJson(Map<String, dynamic> json) {
    return Store(
      id: json['id'] as int,
      name: json['name'] as String,
      slug: json['slug'] as String,
    );
  }

  final int id;
  final String name;
  final String slug;
}
