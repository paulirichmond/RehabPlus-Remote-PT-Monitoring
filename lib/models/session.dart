class ExerciseSession {
  final String id;
  final String exerciseId;
  final String exerciseName;
  final DateTime startTime;
  DateTime? endTime;
  int completedReps;
  int completedSets;
  final int targetReps;
  final int targetSets;
  List<double> repComplianceScores;
  String? notes;

  ExerciseSession({
    required this.id,
    required this.exerciseId,
    required this.exerciseName,
    required this.startTime,
    required this.targetReps,
    required this.targetSets,
    this.endTime,
    this.completedReps = 0,
    this.completedSets = 0,
    this.repComplianceScores = const [],
    this.notes,
  });

  double get overallCompliance {
    if (repComplianceScores.isEmpty) return 0;
    return repComplianceScores.reduce((a, b) => a + b) / repComplianceScores.length;
  }

  double get repCompletionRate => targetReps > 0 ? (completedReps / targetReps).clamp(0, 1) : 0;

  Map<String, dynamic> toJson() => {
        'id': id,
        'exerciseId': exerciseId,
        'exerciseName': exerciseName,
        'startTime': startTime.toIso8601String(),
        'endTime': endTime?.toIso8601String(),
        'completedReps': completedReps,
        'completedSets': completedSets,
        'targetReps': targetReps,
        'targetSets': targetSets,
        'repComplianceScores': repComplianceScores,
        'notes': notes,
      };

  factory ExerciseSession.fromJson(Map<String, dynamic> json) => ExerciseSession(
        id: json['id'],
        exerciseId: json['exerciseId'],
        exerciseName: json['exerciseName'],
        startTime: DateTime.parse(json['startTime']),
        endTime: json['endTime'] != null ? DateTime.parse(json['endTime']) : null,
        completedReps: json['completedReps'],
        completedSets: json['completedSets'],
        targetReps: json['targetReps'],
        targetSets: json['targetSets'],
        repComplianceScores: List<double>.from(json['repComplianceScores']),
        notes: json['notes'],
      );
}
