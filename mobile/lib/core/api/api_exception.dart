/// Wraps a failed API call with whatever Laravel's response actually
/// said — a validation 422's per-field `errors` map when present, else
/// just `message`. UI code reads [fieldErrors] to show inline form
/// errors and falls back to [message] for a flash/toast.
class ApiException implements Exception {
  ApiException({
    required this.statusCode,
    required this.message,
    this.fieldErrors = const {},
  });

  final int? statusCode;
  final String message;
  final Map<String, List<String>> fieldErrors;

  String? firstErrorFor(String field) => fieldErrors[field]?.first;

  bool get isUnauthorized => statusCode == 401;

  bool get isValidationError => statusCode == 422;

  @override
  String toString() => message;
}
