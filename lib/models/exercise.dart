class Exercise {
  final String id;
  final String name;
  final String description;
  final int targetReps;
  final int targetSets;
  final Map<String, AngleThreshold> jointThresholds;
  final String category;

  const Exercise({
    required this.id,
    required this.name,
    required this.description,
    required this.targetReps,
    required this.targetSets,
    required this.jointThresholds,
    required this.category,
  });
}

class AngleThreshold {
  final double minAngle;
  final double maxAngle;
  const AngleThreshold(this.minAngle, this.maxAngle);
}

final List<Exercise> defaultExercises = [
  Exercise(
    id: 'knee_extension',
    name: 'Knee Extension',
    description: 'Straighten your knee fully while seated',
    targetReps: 10,
    targetSets: 3,
    category: 'Knee',
    jointThresholds: {
      'leftKnee': AngleThreshold(160, 180),
      'rightKnee': AngleThreshold(160, 180),
    },
  ),
  Exercise(
    id: 'shoulder_abduction',
    name: 'Shoulder Abduction',
    description: 'Raise your arm sideways to shoulder height',
    targetReps: 10,
    targetSets: 3,
    category: 'Shoulder',
    jointThresholds: {
      'leftShoulder': AngleThreshold(80, 100),
      'rightShoulder': AngleThreshold(80, 100),
    },
  ),
  Exercise(
    id: 'hip_flexion',
    name: 'Hip Flexion',
    description: 'Lift your knee toward your chest while standing',
    targetReps: 10,
    targetSets: 3,
    category: 'Hip',
    jointThresholds: {
      'leftHip': AngleThreshold(80, 100),
      'rightHip': AngleThreshold(80, 100),
    },
  ),
  Exercise(
    id: 'elbow_flexion',
    name: 'Elbow Flexion',
    description: 'Curl your arm from straight to fully bent',
    targetReps: 12,
    targetSets: 3,
    category: 'Elbow',
    jointThresholds: {
      'leftElbow': AngleThreshold(30, 50),
      'rightElbow': AngleThreshold(30, 50),
    },
  ),
];
