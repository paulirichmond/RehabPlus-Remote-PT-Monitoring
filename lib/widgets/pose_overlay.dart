import 'package:flutter/material.dart';
import 'package:google_mlkit_pose_detection/google_mlkit_pose_detection.dart';
import '../models/pose_data.dart';

class PoseOverlayPainter extends CustomPainter {
  final PoseData poseData;
  final bool isFrontCamera;

  PoseOverlayPainter({required this.poseData, this.isFrontCamera = true});

  static const _connections = [
    [PoseLandmarkType.leftShoulder, PoseLandmarkType.rightShoulder],
    [PoseLandmarkType.leftShoulder, PoseLandmarkType.leftElbow],
    [PoseLandmarkType.leftElbow, PoseLandmarkType.leftWrist],
    [PoseLandmarkType.rightShoulder, PoseLandmarkType.rightElbow],
    [PoseLandmarkType.rightElbow, PoseLandmarkType.rightWrist],
    [PoseLandmarkType.leftShoulder, PoseLandmarkType.leftHip],
    [PoseLandmarkType.rightShoulder, PoseLandmarkType.rightHip],
    [PoseLandmarkType.leftHip, PoseLandmarkType.rightHip],
    [PoseLandmarkType.leftHip, PoseLandmarkType.leftKnee],
    [PoseLandmarkType.leftKnee, PoseLandmarkType.leftAnkle],
    [PoseLandmarkType.rightHip, PoseLandmarkType.rightKnee],
    [PoseLandmarkType.rightKnee, PoseLandmarkType.rightAnkle],
  ];

  @override
  void paint(Canvas canvas, Size size) {
    final landmarks = poseData.pose.landmarks;
    final imgW = poseData.imageSize.width;
    final imgH = poseData.imageSize.height;

    Offset toScreen(PoseLandmark lm) {
      double x = lm.x / imgW * size.width;
      double y = lm.y / imgH * size.height;
      if (isFrontCamera) x = size.width - x;
      return Offset(x, y);
    }

    final bonePaint = Paint()
      ..color = Colors.greenAccent
      ..strokeWidth = 2.5
      ..style = PaintingStyle.stroke;

    for (final conn in _connections) {
      final a = landmarks[conn[0]];
      final b = landmarks[conn[1]];
      if (a != null && b != null && a.likelihood > 0.5 && b.likelihood > 0.5) {
        canvas.drawLine(toScreen(a), toScreen(b), bonePaint);
      }
    }

    final dotPaint = Paint()..color = Colors.yellowAccent;
    for (final lm in landmarks.values) {
      if (lm.likelihood > 0.5) {
        canvas.drawCircle(toScreen(lm), 4, dotPaint);
      }
    }

    final textStyle = const TextStyle(color: Colors.white, fontSize: 11, backgroundColor: Colors.black54);
    for (final entry in poseData.jointAngles.entries) {
      final lmType = _jointToLandmark(entry.key);
      if (lmType == null) continue;
      final lm = landmarks[lmType];
      if (lm == null || lm.likelihood < 0.5) continue;
      final pos = toScreen(lm);
      final tp = TextPainter(
        text: TextSpan(text: '${entry.value.toStringAsFixed(0)}°', style: textStyle),
        textDirection: TextDirection.ltr,
      )..layout();
      tp.paint(canvas, Offset(pos.dx + 6, pos.dy - 8));
    }
  }

  PoseLandmarkType? _jointToLandmark(String joint) {
    switch (joint) {
      case 'leftKnee': return PoseLandmarkType.leftKnee;
      case 'rightKnee': return PoseLandmarkType.rightKnee;
      case 'leftShoulder': return PoseLandmarkType.leftShoulder;
      case 'rightShoulder': return PoseLandmarkType.rightShoulder;
      case 'leftElbow': return PoseLandmarkType.leftElbow;
      case 'rightElbow': return PoseLandmarkType.rightElbow;
      case 'leftHip': return PoseLandmarkType.leftHip;
      case 'rightHip': return PoseLandmarkType.rightHip;
      default: return null;
    }
  }

  @override
  bool shouldRepaint(PoseOverlayPainter old) => true;
}
