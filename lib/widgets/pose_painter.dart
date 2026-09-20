import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:google_mlkit_pose_detection/google_mlkit_pose_detection.dart';

class PosePainter extends CustomPainter {
  final List<Pose> poses;
  final Size absoluteImageSize;
  final InputImageRotation rotation;
  final bool isExerciseCorrect;
  final CameraLensDirection cameraLensDirection;

  PosePainter(
    this.poses,
    this.absoluteImageSize,
    this.rotation,
    this.isExerciseCorrect,
    this.cameraLensDirection,
  );

  // Facial landmarks to exclude from drawing dots
  static const Set<PoseLandmarkType> _faceLandmarks = {
    PoseLandmarkType.nose,
    PoseLandmarkType.leftEyeInner,
    PoseLandmarkType.leftEye,
    PoseLandmarkType.leftEyeOuter,
    PoseLandmarkType.rightEyeInner,
    PoseLandmarkType.rightEye,
    PoseLandmarkType.rightEyeOuter,
    PoseLandmarkType.leftEar,
    PoseLandmarkType.rightEar,
    PoseLandmarkType.leftMouth,
    PoseLandmarkType.rightMouth,
  };

  @override
  void paint(Canvas canvas, Size size) {
    if (poses.isEmpty) return;

    final paintColor = isExerciseCorrect
        ? Colors.greenAccent
        : Colors.redAccent;

    final pointPaint = Paint()
      ..style = PaintingStyle.fill
      ..strokeWidth = 6.0
      ..color = paintColor;

    final linePaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4.0
      ..color = paintColor;

    for (final pose in poses) {
      final Map<PoseLandmarkType, Offset> points = {};

      pose.landmarks.forEach((type, landmark) {
        if (landmark.likelihood > 0.5) {
          double x = landmark.x * size.width / absoluteImageSize.width;
          double y = landmark.y * size.height / absoluteImageSize.height;

          // Flip X axis for front camera mirroring
          if (cameraLensDirection == CameraLensDirection.front) {
            x = size.width - x;
          }

          points[type] = Offset(x, y);

          // Draw joint circles ONLY if the landmark is not part of the face
          if (!_faceLandmarks.contains(type)) {
            canvas.drawCircle(Offset(x, y), 5, pointPaint);
          }
        }
      });

      void drawLine(PoseLandmarkType type1, PoseLandmarkType type2) {
        final p1 = points[type1];
        final p2 = points[type2];
        if (p1 != null && p2 != null) {
          canvas.drawLine(p1, p2, linePaint);
        }
      }

      // Arm connections
      drawLine(PoseLandmarkType.leftShoulder, PoseLandmarkType.leftElbow);
      drawLine(PoseLandmarkType.leftElbow, PoseLandmarkType.leftWrist);
      drawLine(PoseLandmarkType.rightShoulder, PoseLandmarkType.rightElbow);
      drawLine(PoseLandmarkType.rightElbow, PoseLandmarkType.rightWrist);

      // Leg connections
      drawLine(PoseLandmarkType.leftHip, PoseLandmarkType.leftKnee);
      drawLine(PoseLandmarkType.leftKnee, PoseLandmarkType.leftAnkle);
      drawLine(PoseLandmarkType.rightHip, PoseLandmarkType.rightKnee);
      drawLine(PoseLandmarkType.rightKnee, PoseLandmarkType.rightAnkle);

      // Upper body context lines (Torso)
      drawLine(PoseLandmarkType.leftShoulder, PoseLandmarkType.rightShoulder);
      drawLine(PoseLandmarkType.leftHip, PoseLandmarkType.rightHip);
      drawLine(PoseLandmarkType.leftShoulder, PoseLandmarkType.leftHip);
      drawLine(PoseLandmarkType.rightShoulder, PoseLandmarkType.rightHip);
    }
  }

  @override
  bool shouldRepaint(PosePainter oldDelegate) => true;
}
