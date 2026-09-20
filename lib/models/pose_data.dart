import 'dart:math';
import 'dart:ui' show Size;
import 'package:google_mlkit_pose_detection/google_mlkit_pose_detection.dart';

class PoseData {
  final Pose pose;
  final Map<String, double> jointAngles;
  final Size imageSize;

  PoseData({required this.pose, required this.jointAngles, required this.imageSize});
}

class JointAngleCalculator {
  static double angleBetweenPoints(PoseLandmark a, PoseLandmark b, PoseLandmark c) {
    final abx = a.x - b.x, aby = a.y - b.y;
    final cbx = c.x - b.x, cby = c.y - b.y;
    final dot = abx * cbx + aby * cby;
    final magAB = sqrt(abx * abx + aby * aby);
    final magCB = sqrt(cbx * cbx + cby * cby);
    if (magAB == 0 || magCB == 0) return 0;
    return acos((dot / (magAB * magCB)).clamp(-1.0, 1.0)) * 180 / pi;
  }

  static Map<String, double> computeAngles(Pose pose) {
    final lm = pose.landmarks;
    final angles = <String, double>{};

    PoseLandmark? get(PoseLandmarkType t) => lm[t];

    final lHip = get(PoseLandmarkType.leftHip);
    final lKnee = get(PoseLandmarkType.leftKnee);
    final lAnkle = get(PoseLandmarkType.leftAnkle);
    if (lHip != null && lKnee != null && lAnkle != null) {
      angles['leftKnee'] = angleBetweenPoints(lHip, lKnee, lAnkle);
    }

    final rHip = get(PoseLandmarkType.rightHip);
    final rKnee = get(PoseLandmarkType.rightKnee);
    final rAnkle = get(PoseLandmarkType.rightAnkle);
    if (rHip != null && rKnee != null && rAnkle != null) {
      angles['rightKnee'] = angleBetweenPoints(rHip, rKnee, rAnkle);
    }

    final lShoulder = get(PoseLandmarkType.leftShoulder);
    final lElbow = get(PoseLandmarkType.leftElbow);
    final lWrist = get(PoseLandmarkType.leftWrist);
    if (lShoulder != null && lElbow != null && lHip != null) {
      angles['leftShoulder'] = angleBetweenPoints(lElbow, lShoulder, lHip);
    }
    if (lShoulder != null && lElbow != null && lWrist != null) {
      angles['leftElbow'] = angleBetweenPoints(lShoulder, lElbow, lWrist);
    }

    final rShoulder = get(PoseLandmarkType.rightShoulder);
    final rElbow = get(PoseLandmarkType.rightElbow);
    final rWrist = get(PoseLandmarkType.rightWrist);
    if (rShoulder != null && rElbow != null && rHip != null) {
      angles['rightShoulder'] = angleBetweenPoints(rElbow, rShoulder, rHip);
    }
    if (rShoulder != null && rElbow != null && rWrist != null) {
      angles['rightElbow'] = angleBetweenPoints(rShoulder, rElbow, rWrist);
    }

    if (lShoulder != null && lHip != null && lKnee != null) {
      angles['leftHip'] = angleBetweenPoints(lShoulder, lHip, lKnee);
    }
    if (rShoulder != null && rHip != null && rKnee != null) {
      angles['rightHip'] = angleBetweenPoints(rShoulder, rHip, rKnee);
    }

    return angles;
  }
}
