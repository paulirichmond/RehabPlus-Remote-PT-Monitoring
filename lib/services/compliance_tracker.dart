import '../models/exercise.dart';
import '../models/pose_data.dart';

enum RepPhase { idle, inProgress, completed }

class ComplianceTracker {
  final Exercise exercise;

  ComplianceTracker({required this.exercise});

  RepPhase _phase = RepPhase.idle;
  int _completedReps = 0;
  int _completedSets = 0;
  double _currentRepScore = 0;
  int _frameCount = 0;
  int _compliantFrames = 0;
  bool _peakReached = false;

  final List<double> repScores = [];

  int get completedReps => _completedReps;
  int get completedSets => _completedSets;
  double get currentRepScore => _currentRepScore;
  bool get isSetComplete => _completedReps >= exercise.targetReps;
  bool get isSessionComplete => _completedSets >= exercise.targetSets;

  /// Returns true if a rep was just completed
  bool processFrame(PoseData poseData) {
    final angles = poseData.jointAngles;

    _frameCount++;
    if (_computeFrameCompliance(angles) > 0.7) _compliantFrames++;

    final atPeak = _isAtPeak(angles);
    final atRest = _isAtRest(angles);

    if (!_peakReached && atPeak) {
      _peakReached = true;
      _phase = RepPhase.inProgress;
    }

    if (_peakReached && atRest && _phase == RepPhase.inProgress) {
      _peakReached = false;
      final repScore = _frameCount > 0 ? _compliantFrames / _frameCount : 0.0;
      _currentRepScore = repScore;
      repScores.add(repScore);
      _completedReps++;
      _frameCount = 0;
      _compliantFrames = 0;

      if (_completedReps >= exercise.targetReps) {
        _completedSets++;
        _completedReps = 0;
      }
      _phase = RepPhase.idle;
      return true;
    }

    return false;
  }

  double _computeFrameCompliance(Map<String, double> angles) {
    if (exercise.jointThresholds.isEmpty) return 1.0;
    int compliant = 0;
    for (final entry in exercise.jointThresholds.entries) {
      final angle = angles[entry.key];
      if (angle != null && angle >= entry.value.minAngle && angle <= entry.value.maxAngle) {
        compliant++;
      }
    }
    return compliant / exercise.jointThresholds.length;
  }

  bool _isAtPeak(Map<String, double> angles) {
    for (final entry in exercise.jointThresholds.entries) {
      final angle = angles[entry.key];
      if (angle != null && angle >= entry.value.minAngle && angle <= entry.value.maxAngle) {
        return true;
      }
    }
    return false;
  }

  bool _isAtRest(Map<String, double> angles) {
    for (final entry in exercise.jointThresholds.entries) {
      final angle = angles[entry.key];
      if (angle != null) {
        final midpoint = (entry.value.minAngle + entry.value.maxAngle) / 2;
        if ((angle - midpoint).abs() > 30) return true;
      }
    }
    return false;
  }

  double get overallCompliance {
    if (repScores.isEmpty) return 0;
    return repScores.reduce((a, b) => a + b) / repScores.length;
  }

  void reset() {
    _phase = RepPhase.idle;
    _completedReps = 0;
    _completedSets = 0;
    _currentRepScore = 0;
    _frameCount = 0;
    _compliantFrames = 0;
    _peakReached = false;
    repScores.clear();
  }
}
