// lib/models/exercise_model.dart
enum ExerciseType {
  kneeExtension,
  straightLegRaise,
  kneeFlexion,
  heelSlide,
  forwardRaise,
  sideRaise,
  forwardPush,
  // --- new ---
  bicepCurl,
  sitToStand,
  gluteBridge,
  // hold exercises (timed)
  singleLegBalance,
  wallSit,
}

class ExerciseConfig {
  final ExerciseType type;
  final String title;
  final String category;
  final String repsText;
  final double targetAngle;
  final double restAngle;

  /// For hold exercises: how many seconds one hold must last.
  /// 0 means a normal rep-based exercise.
  final int holdSeconds;

  /// Hard limit for the session: how many reps (or, for hold exercises,
  /// how many completed holds) finish the exercise.
  final int targetReps;

  const ExerciseConfig({
    required this.type,
    required this.title,
    required this.category,
    required this.repsText,
    required this.targetAngle,
    required this.restAngle,
    required this.targetReps,
    this.holdSeconds = 0,
  });

  bool get isHold => holdSeconds > 0;
}

final List<ExerciseConfig> availableExercises = [
  // ---------------- Lower body ----------------
  const ExerciseConfig(
    type: ExerciseType.kneeExtension,
    title: 'Knee Extension',
    category: 'MCL Recovery',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 125.0, // extended (lowered from 150: pose model rarely reads 180)
    restAngle: 110.0, // seated, knee relaxed
  ),
  const ExerciseConfig(
    type: ExerciseType.straightLegRaise,
    title: 'Straight Leg Raise',
    category: 'MCL Recovery',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 140.0, // Hip flexion target
    restAngle: 170.0,
  ),
  const ExerciseConfig(
    type: ExerciseType.kneeFlexion,
    title: 'Knee Flexion',
    category: 'MCL Recovery',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 90.0, // Fully bent knee
    restAngle: 160.0,
  ),
  const ExerciseConfig(
    type: ExerciseType.heelSlide,
    title: 'Heel Slides',
    category: 'MCL Recovery',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 90.0,
    restAngle: 160.0,
  ),
  const ExerciseConfig(
    type: ExerciseType.sitToStand,
    title: 'Sit-to-Stand',
    category: 'Lower Body',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 165.0, // standing, knees straight
    restAngle: 110.0, // seated
  ),
  const ExerciseConfig(
    type: ExerciseType.gluteBridge,
    title: 'Glute Bridge',
    category: 'Core & Hips',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 160.0, // hips lifted, body in a line
    restAngle: 135.0, // lying, knees bent
  ),

  // ---------------- Upper body ----------------
  const ExerciseConfig(
    type: ExerciseType.bicepCurl,
    title: 'Bicep Curl',
    category: 'Arm Rehabilitation',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 70.0, // elbow bent
    restAngle: 140.0, // arm nearly straight
  ),
  const ExerciseConfig(
    type: ExerciseType.forwardRaise,
    title: 'Forward Raise',
    category: 'Arm Rehabilitation',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 90.0, // Arm parallel to ground
    restAngle: 20.0,
  ),
  const ExerciseConfig(
    type: ExerciseType.sideRaise,
    title: 'Side Raise',
    category: 'Arm Rehabilitation',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 90.0,
    restAngle: 20.0,
  ),
  const ExerciseConfig(
    type: ExerciseType.forwardPush,
    title: 'Forward Push',
    category: 'Arm Rehabilitation',
    repsText: '1 Set | 10 Reps',
    targetReps: 10,
    targetAngle: 155.0, // Extended elbow
    restAngle: 80.0, // Bent elbow near chest
  ),

  // ---------------- Timed holds ----------------
  const ExerciseConfig(
    type: ExerciseType.singleLegBalance,
    title: 'Single-Leg Balance',
    category: 'Balance',
    repsText: '3 Holds | 10 sec',
    targetReps: 3,
    targetAngle: 0.0, // not used for holds
    restAngle: 0.0, // not used for holds
    holdSeconds: 10,
  ),
  const ExerciseConfig(
    type: ExerciseType.wallSit,
    title: 'Wall Sit',
    category: 'Lower Body',
    repsText: '3 Holds | 20 sec',
    targetReps: 3,
    targetAngle: 90.0, // knee angle to hold (+/- 20 degrees)
    restAngle: 0.0, // not used for holds
    holdSeconds: 20,
  ),
];
