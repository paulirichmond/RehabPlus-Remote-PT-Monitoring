// lib/widgets/pose_painter.dart
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:google_mlkit_pose_detection/google_mlkit_pose_detection.dart';

import '../services/form_checker.dart';

class PosePainter extends CustomPainter {
  final List<Pose> poses;

  /// Size of the analysis frame in PIXELS, already rotated to display
  /// orientation (portrait phone => swapped W/H). Landmarks are mapped with
  /// a uniform cover scale + centre-crop offset so the skeleton matches an
  /// undistorted preview.
  final Size absoluteImageSize;
  final InputImageRotation rotation;
  final bool isExerciseCorrect;
  final CameraLensDirection cameraLensDirection;

  /// When true the painter mirrors landmarks horizontally for the front
  /// camera. The screen layer must then render the video UNMIRRORED
  /// (ignoreFrontCameraOrientation = false) so picture and skeleton agree.
  final bool mirrorFrontCamera;

  /// Joints the form checker wants highlighted (drawn larger with a red glow).
  final Set<PoseLandmarkType> flaggedLandmarks;

  /// Faults to explain with a callout bubble next to the offending joint.
  final List<FormIssue> issues;

  PosePainter(
    this.poses,
    this.absoluteImageSize,
    this.rotation,
    this.isExerciseCorrect,
    this.cameraLensDirection, {
    this.mirrorFrontCamera = false,
    this.flaggedLandmarks = const {},
    this.issues = const [],
  });

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

  static const List<List<PoseLandmarkType>> _bones = [
    // Arms
    [PoseLandmarkType.leftShoulder, PoseLandmarkType.leftElbow],
    [PoseLandmarkType.leftElbow, PoseLandmarkType.leftWrist],
    [PoseLandmarkType.rightShoulder, PoseLandmarkType.rightElbow],
    [PoseLandmarkType.rightElbow, PoseLandmarkType.rightWrist],
    // Legs
    [PoseLandmarkType.leftHip, PoseLandmarkType.leftKnee],
    [PoseLandmarkType.leftKnee, PoseLandmarkType.leftAnkle],
    [PoseLandmarkType.rightHip, PoseLandmarkType.rightKnee],
    [PoseLandmarkType.rightKnee, PoseLandmarkType.rightAnkle],
    // Torso
    [PoseLandmarkType.leftShoulder, PoseLandmarkType.rightShoulder],
    [PoseLandmarkType.leftHip, PoseLandmarkType.rightHip],
    [PoseLandmarkType.leftShoulder, PoseLandmarkType.leftHip],
    [PoseLandmarkType.rightShoulder, PoseLandmarkType.rightHip],
  ];

  @override
  void paint(Canvas canvas, Size size) {
    if (poses.isEmpty) return;

    final wrong = !isExerciseCorrect;
    final lineColor = wrong ? Colors.redAccent : Colors.greenAccent;
    // Matches the mockup: red bones with yellow joints when the form is wrong.
    final dotColor = wrong ? Colors.yellowAccent : Colors.greenAccent;

    final dotPaint = Paint()
      ..style = PaintingStyle.fill
      ..color = dotColor;

    final linePaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4.0
      ..strokeCap = StrokeCap.round
      ..color = lineColor;

    final hotLinePaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 6.5
      ..strokeCap = StrokeCap.round
      ..color = Colors.red;

    // --- Aspect-correct mapping ------------------------------------------
    // The preview is drawn COVER-first (uniform scale + centre crop), never
    // stretched, so landmarks must be mapped with the same uniform scale.
    // absoluteImageSize is in analysis-image pixels already rotated to
    // display orientation (portrait phone => swapped W/H).
    final iw = absoluteImageSize.width;
    final ih = absoluteImageSize.height;
    if (iw <= 0 || ih <= 0) return;
    final scale = max(size.width / iw, size.height / ih);
    final drawW = iw * scale;
    final drawH = ih * scale;
    final offX = (size.width - drawW) / 2.0;
    final offY = (size.height - drawH) / 2.0;
    final mirror = mirrorFrontCamera;

    for (final pose in poses) {
      final Map<PoseLandmarkType, Offset> points = {};

      pose.landmarks.forEach((type, landmark) {
        if (landmark.likelihood > 0.5) {
          var x = offX + landmark.x * scale;
          final y = offY + landmark.y * scale;

          // Flip X axis for front camera mirroring (same axis the video is
          // mirrored on, so skeleton and picture always agree).
          if (mirror) x = size.width - x;
          points[type] = Offset(x, y);
        }
      });

      // Bones (thicker when they touch a flagged joint)
      for (final bone in _bones) {
        final p1 = points[bone[0]];
        final p2 = points[bone[1]];
        if (p1 == null || p2 == null) continue;
        final hot = wrong &&
            (flaggedLandmarks.contains(bone[0]) ||
                flaggedLandmarks.contains(bone[1]));
        canvas.drawLine(p1, p2, hot ? hotLinePaint : linePaint);
      }

      // Joint dots (never on the face)
      points.forEach((type, p) {
        if (!_faceLandmarks.contains(type)) {
          canvas.drawCircle(p, 5, dotPaint);
        }
      });

      if (wrong) {
        _drawFlaggedJoints(canvas, points);
        _drawCallouts(canvas, size, points);
      }
    }
  }

  void _drawFlaggedJoints(Canvas canvas, Map<PoseLandmarkType, Offset> points) {
    final glow = Paint()..color = Colors.redAccent.withValues(alpha: 0.35);
    final ring = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.5
      ..color = Colors.white;
    final core = Paint()..color = Colors.redAccent;

    for (final type in flaggedLandmarks) {
      final p = points[type];
      if (p == null) continue;
      canvas.drawCircle(p, 18, glow);
      canvas.drawCircle(p, 11, ring);
      canvas.drawCircle(p, 6, core);
    }
  }

  Offset? _anchorPoint(FormIssue issue, Map<PoseLandmarkType, Offset> points) {
    final a = issue.anchor;
    if (a != null && points[a] != null) return points[a];
    for (final t in issue.landmarks) {
      final p = points[t];
      if (p != null) return p;
    }
    return null;
  }

  /// White bubble with a red border and red text, joined to the joint by a line
  /// (same look as the "leg's low" / "bend your back" notes in the mockup).
  void _drawCallouts(
    Canvas canvas,
    Size size,
    Map<PoseLandmarkType, Offset> points,
  ) {
    const maxTextWidth = 150.0;
    const pad = 8.0;
    const red = Color(0xFFD50000);

    final fill = Paint()..color = Colors.white.withValues(alpha: 0.95);
    final border = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.6
      ..color = red;
    final leader = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.6
      ..color = red;

    var slot = 0;
    for (final issue in issues) {
      if (issue.code == FormIssueCode.notInFrame) continue;
      if (slot >= 2) break; // keep the screen readable
      final target = _anchorPoint(issue, points);
      if (target == null) continue;

      final tp = TextPainter(
        text: TextSpan(
          text: issue.message,
          style: const TextStyle(
            color: red,
            fontSize: 11,
            fontWeight: FontWeight.bold,
            height: 1.2,
          ),
        ),
        textDirection: TextDirection.ltr,
        maxLines: 4,
        ellipsis: '…',
      )..layout(maxWidth: maxTextWidth);

      final w = tp.width + pad * 2;
      final h = tp.height + pad * 2;

      // Put the bubble on whichever side of the joint has more room, the first
      // one above it and the second below so two callouts never overlap.
      final onLeftHalf = target.dx < size.width / 2;
      final left = (onLeftHalf ? target.dx + 28 : target.dx - 28 - w)
          .clamp(8.0, size.width - w - 8.0)
          .toDouble();
      final top = (slot == 0 ? target.dy - h - 24 : target.dy + 24)
          .clamp(8.0, size.height - h - 8.0)
          .toDouble();

      final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(left, top, w, h),
        const Radius.circular(8),
      );

      final from = Offset(onLeftHalf ? left : left + w, top + h / 2);
      canvas.drawLine(from, target, leader);
      canvas.drawRRect(rect, fill);
      canvas.drawRRect(rect, border);
      tp.paint(canvas, Offset(left + pad, top + pad));

      slot++;
    }
  }

  @override
  bool shouldRepaint(PosePainter oldDelegate) => true;
}
