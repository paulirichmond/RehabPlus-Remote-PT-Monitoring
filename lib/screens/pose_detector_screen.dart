// lib/screens/pose_detector_screen.dart
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';

import 'package:camera/camera.dart';
import 'package:vibration/vibration.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:provider/provider.dart';

import 'package:google_mlkit_pose_detection/google_mlkit_pose_detection.dart';

import '../models/exercise_model.dart';
import '../models/session.dart';
import '../services/app_provider.dart';
import '../services/form_checker.dart';
import '../widgets/pose_painter.dart';

class PoseDetectorScreen extends StatefulWidget {
  final ExerciseConfig selectedExercise;
  const PoseDetectorScreen({super.key, required this.selectedExercise});

  @override
  State<PoseDetectorScreen> createState() => _PoseDetectorScreenState();
}

class _PoseDetectorScreenState extends State<PoseDetectorScreen> {
  CameraController? _cameraController;
  List<CameraDescription> _availableCameras = [];
  late PoseDetector _poseDetector;
  bool _isProcessing = false;

  final AudioPlayer _audioPlayer = AudioPlayer();

  // pubspec registers assets/successSound.mp3 (the old code asked for
  // chime.mp3, which isn't bundled and threw on every rep).
  void _triggerFeedback() async {
    try {
      await _audioPlayer.play(AssetSource('successSound.mp3'));
    } catch (e) {
      debugPrint('Audio error: $e');
    }
    if (await Vibration.hasVibrator() ?? false) {
      Vibration.vibrate(duration: 150);
    }
  }

  /// Double buzz for wrong form / rejected rep (throttled so it can't spam).
  DateTime _lastBuzz = DateTime.fromMillisecondsSinceEpoch(0);
  Future<void> _triggerWrongFeedback() async {
    final now = DateTime.now();
    if (now.difference(_lastBuzz).inMilliseconds < 1500) return;
    _lastBuzz = now;
    if (await Vibration.hasVibrator() ?? false) {
      Vibration.vibrate(pattern: [0, 120, 80, 120]);
    }
  }

  int _repCounter = 0;
  String _stage = "down";
  double _currentAngle = 0.0;

  // ---- Form checking ----
  late final FormChecker _checker;
  FormResult _form = FormResult.ok;
  bool _wasWrong = false;

  // One-off events ("rep not counted", "leg too low") that should stay on
  // screen for a moment after the frame that caused them.
  FormIssue? _flash;
  DateTime? _flashUntil;

  // ---- Rep cycle (a rep = leave rest, reach target, come back to rest) ----
  bool _cycleActive = false;
  DateTime? _cycleStart;
  FormIssue? _cycleViolation; // first form fault seen during this rep
  double _maxProgress = 0.0; // furthest toward target this rep (0..1+)
  double? _baseline; // the patient's own resting angle
  int _rejectedReps = 0;
  final List<double> _repScores = []; // 1.0 = clean rep, 0.0 = rejected rep

  // ---- Session completion state ----
  bool _isSessionComplete = false;
  late final DateTime _sessionStart;

  // ---- Hold-timer state (single-leg balance, wall sit, ...) ----
  DateTime? _holdStart; // when the current hold began
  DateTime? _lostSince; // when the position was last lost
  bool _holdCounted = false; // this hold already earned its point
  double _holdElapsed = 0.0; // seconds held so far
  static const Duration _holdGrace = Duration(milliseconds: 700);
  static const double _wallSitTolerance = 20.0; // degrees either side of target

  // Visual Overlay states
  List<Pose> _detectedPoses = [];
  Size? _imageSize;
  InputImageRotation _rotation = InputImageRotation.rotation0deg;
  CameraLensDirection _lensDirection = CameraLensDirection.front;

  /// Live faults plus any recent one-off event.
  FormResult get _effectiveForm {
    final flash = _flash;
    final until = _flashUntil;
    if (flash == null || until == null || DateTime.now().isAfter(until)) {
      return _form;
    }
    return _form.plus(flash);
  }

  void _flashIssue(FormIssue issue) {
    _flash = issue;
    _flashUntil = DateTime.now().add(const Duration(milliseconds: 1800));
  }

  @override
  void initState() {
    super.initState();
    _sessionStart = DateTime.now();
    _checker = FormChecker(widget.selectedExercise);
    _initPoseDetector();
    _initCamera();
  }

  void _initPoseDetector() {
    final options = PoseDetectorOptions(
      mode: PoseDetectionMode.stream,
      model: PoseDetectionModel.accurate,
    );
    _poseDetector = PoseDetector(options: options);
  }

  Future<void> _initCamera() async {
    _availableCameras = await availableCameras();
    final camera = _availableCameras.firstWhere(
      (c) => c.lensDirection == _lensDirection,
      orElse: () => _availableCameras.first,
    );

    _lensDirection = camera.lensDirection;

    _cameraController = CameraController(
      camera,
      ResolutionPreset.medium,
      enableAudio: false,
      imageFormatGroup: ImageFormatGroup.nv21,
    );

    await _cameraController!.initialize();
    _cameraController!.startImageStream(
      (image) => _processFrame(image, camera),
    );
    if (mounted) setState(() {});
  }

  Future<void> _switchCamera() async {
    if (_availableCameras.length < 2) return;

    _lensDirection = _lensDirection == CameraLensDirection.front
        ? CameraLensDirection.back
        : CameraLensDirection.front;

    if (_cameraController != null) {
      await _cameraController!.stopImageStream();
      await _cameraController!.dispose();
      _cameraController = null;
    }

    _checker.reset();
    _cancelCycle();
    await _initCamera();
  }

  void _processFrame(CameraImage image, CameraDescription camera) async {
    if (_isProcessing || _isSessionComplete) return;
    _isProcessing = true;

    final inputImage = _inputImageFromCameraImage(image, camera);
    if (inputImage != null) {
      try {
        final List<Pose> poses = await _poseDetector.processImage(inputImage);

        if (mounted) {
          // Judge the form first so the overlay and the rep logic agree.
          final form = poses.isEmpty
              ? _checker.evaluateNoPose()
              : _checker.evaluate(poses.first.landmarks);

          setState(() {
            _detectedPoses = poses;
            _imageSize = Size(image.height.toDouble(), image.width.toDouble());
            _rotation =
                inputImage.metadata?.rotation ??
                InputImageRotation.rotation0deg;
            _form = form;
          });

          if (form.isWrong && !_wasWrong) _triggerWrongFeedback();
          _wasWrong = form.isWrong;

          if (poses.isNotEmpty) {
            _analyzeMotion(poses.first.landmarks, form);
          } else {
            // Nobody in frame: drop any half-finished rep, and let the hold
            // timer reset after its grace period.
            _cancelCycle();
            if (widget.selectedExercise.isHold) _updateHold(false);
          }
        }
      } catch (e) {
        debugPrint("ML Kit Detection Error: $e");
      }
    }

    _isProcessing = false;
  }

  // ---------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------

  /// Angle at landmark [b] between [a] and [c], or null if any is missing.
  double? _jointAngle(
    Map<PoseLandmarkType, PoseLandmark> lm,
    PoseLandmarkType a,
    PoseLandmarkType b,
    PoseLandmarkType c,
  ) {
    final p1 = lm[a];
    final p2 = lm[b];
    final p3 = lm[c];
    if (p1 == null || p2 == null || p3 == null) return null;
    return _calculateAngle(p1, p2, p3);
  }

  double _average(List<double> values) =>
      values.reduce((a, b) => a + b) / values.length;

  double _distance(PoseLandmark a, PoseLandmark b) {
    final dx = a.x - b.x;
    final dy = a.y - b.y;
    return sqrt(dx * dx + dy * dy);
  }

  // ---------------------------------------------------------------------
  // Rep-based exercises
  // ---------------------------------------------------------------------

  void _analyzeMotion(
    Map<PoseLandmarkType, PoseLandmark> landmarks,
    FormResult form,
  ) {
    final exercise = widget.selectedExercise;

    // Patient isn't properly framed: nothing counts until they are.
    if (form.isNotInFrame) {
      _cancelCycle();
      if (exercise.isHold) _updateHold(false);
      return;
    }

    // Timed holds use their own logic.
    if (exercise.isHold) {
      _analyzeHold(landmarks, form);
      return;
    }

    // Extract key landmarks (left side first, right side as fallback)
    final shoulder =
        landmarks[PoseLandmarkType.leftShoulder] ??
        landmarks[PoseLandmarkType.rightShoulder];
    final hip =
        landmarks[PoseLandmarkType.leftHip] ??
        landmarks[PoseLandmarkType.rightHip];
    final knee =
        landmarks[PoseLandmarkType.leftKnee] ??
        landmarks[PoseLandmarkType.rightKnee];
    final ankle =
        landmarks[PoseLandmarkType.leftAnkle] ??
        landmarks[PoseLandmarkType.rightAnkle];
    final elbow =
        landmarks[PoseLandmarkType.leftElbow] ??
        landmarks[PoseLandmarkType.rightElbow];
    final wrist =
        landmarks[PoseLandmarkType.leftWrist] ??
        landmarks[PoseLandmarkType.rightWrist];

    double calculatedAngle = 0.0;
    bool isValidRep = false;
    bool isRestPosition = false;

    switch (exercise.type) {
      case ExerciseType.kneeExtension:
        {
          // One leg straightens while the other stays bent.
          final left = _jointAngle(
            landmarks,
            PoseLandmarkType.leftHip,
            PoseLandmarkType.leftKnee,
            PoseLandmarkType.leftAnkle,
          );
          final right = _jointAngle(
            landmarks,
            PoseLandmarkType.rightHip,
            PoseLandmarkType.rightKnee,
            PoseLandmarkType.rightAnkle,
          );
          if (left != null && right != null) {
            final working = max(left, right); // leg being extended
            final resting = min(left, right); // must stay bent
            calculatedAngle = working;
            isValidRep = working >= exercise.targetAngle && resting < 120.0;
            isRestPosition = working < exercise.restAngle;
          }
        }
        break;

      case ExerciseType.straightLegRaise:
        if (shoulder != null && hip != null && knee != null && ankle != null) {
          final kneeAngle = _calculateAngle(hip, knee, ankle);
          final hipAngle = _calculateAngle(shoulder, hip, knee);
          calculatedAngle = hipAngle;

          if (kneeAngle >= 150.0) {
            isValidRep = hipAngle <= exercise.targetAngle;
            isRestPosition = hipAngle >= exercise.restAngle;
          }
        }
        break;

      case ExerciseType.kneeFlexion:
      case ExerciseType.heelSlide:
        if (hip != null && knee != null && ankle != null) {
          calculatedAngle = _calculateAngle(hip, knee, ankle);
          isValidRep = calculatedAngle <= exercise.targetAngle;
          isRestPosition = calculatedAngle >= exercise.restAngle;
        }
        break;

      case ExerciseType.forwardRaise:
      case ExerciseType.sideRaise:
        if (hip != null && shoulder != null && wrist != null) {
          calculatedAngle = _calculateAngle(hip, shoulder, wrist);
          isValidRep = calculatedAngle >= exercise.targetAngle;
          isRestPosition = calculatedAngle <= exercise.restAngle;
        }
        break;

      case ExerciseType.forwardPush:
        if (shoulder != null && elbow != null && wrist != null) {
          calculatedAngle = _calculateAngle(shoulder, elbow, wrist);
          isValidRep = calculatedAngle >= exercise.targetAngle;
          isRestPosition = calculatedAngle <= exercise.restAngle;
        }
        break;

      case ExerciseType.bicepCurl:
        {
          // Use whichever arm is more bent (the working arm). The resting
          // arm hangs straight, so it never triggers a rep by itself.
          final angles = <double?>[
            _jointAngle(
              landmarks,
              PoseLandmarkType.leftShoulder,
              PoseLandmarkType.leftElbow,
              PoseLandmarkType.leftWrist,
            ),
            _jointAngle(
              landmarks,
              PoseLandmarkType.rightShoulder,
              PoseLandmarkType.rightElbow,
              PoseLandmarkType.rightWrist,
            ),
          ].whereType<double>().toList();

          if (angles.isNotEmpty) {
            calculatedAngle = angles.reduce(min);
            isValidRep = calculatedAngle <= exercise.targetAngle;
            isRestPosition = calculatedAngle >= exercise.restAngle;
          }
        }
        break;

      case ExerciseType.sitToStand:
        {
          // Average knee angle of both legs: bent when seated, straight standing.
          final angles = <double?>[
            _jointAngle(
              landmarks,
              PoseLandmarkType.leftHip,
              PoseLandmarkType.leftKnee,
              PoseLandmarkType.leftAnkle,
            ),
            _jointAngle(
              landmarks,
              PoseLandmarkType.rightHip,
              PoseLandmarkType.rightKnee,
              PoseLandmarkType.rightAnkle,
            ),
          ].whereType<double>().toList();

          if (angles.isNotEmpty) {
            calculatedAngle = _average(angles);
            isValidRep = calculatedAngle >= exercise.targetAngle;
            isRestPosition = calculatedAngle <= exercise.restAngle;
          }
        }
        break;

      case ExerciseType.gluteBridge:
        {
          // Hip angle (shoulder-hip-knee): opens up as the hips lift.
          final angles = <double?>[
            _jointAngle(
              landmarks,
              PoseLandmarkType.leftShoulder,
              PoseLandmarkType.leftHip,
              PoseLandmarkType.leftKnee,
            ),
            _jointAngle(
              landmarks,
              PoseLandmarkType.rightShoulder,
              PoseLandmarkType.rightHip,
              PoseLandmarkType.rightKnee,
            ),
          ].whereType<double>().toList();

          if (angles.isNotEmpty) {
            calculatedAngle = _average(angles);
            isValidRep = calculatedAngle >= exercise.targetAngle;
            isRestPosition = calculatedAngle <= exercise.restAngle;
          }
        }
        break;

      case ExerciseType.singleLegBalance:
      case ExerciseType.wallSit:
        break; // handled by _analyzeHold above
    }

    // 0.0 means "couldn't measure" (a needed landmark was missing). Don't let
    // that look like the patient leaving the rest position.
    if (calculatedAngle == 0.0) return;

    setState(() {
      _currentAngle = calculatedAngle;
      _advanceRepCycle(
        angle: calculatedAngle,
        isValidRep: isValidRep,
        isRest: isRestPosition,
        form: form,
      );
    });

    _checkCompletion();
  }

  // ---------------------------------------------------------------------
  // Strict rep accounting
  //
  // A rep only counts if the patient (1) leaves rest, (2) reaches the target
  // angle, (3) returns to rest, AND (4) the form checker raised no fault at
  // any point in between. Anything else is rejected and recorded as a 0.0
  // compliance score, so history/therapist stats reflect real quality.
  // ---------------------------------------------------------------------

  void _advanceRepCycle({
    required double angle,
    required bool isValidRep,
    required bool isRest,
    required FormResult form,
  }) {
    if (isRest) {
      if (_cycleActive) _finishCycle();
      _cancelCycle();
      // Learn the patient's own resting angle (used to judge partial reps).
      _baseline = _baseline == null ? angle : _baseline! * 0.8 + angle * 0.2;
      return;
    }

    if (!_cycleActive) {
      _cycleActive = true;
      _cycleStart = DateTime.now();
      _cycleViolation = null;
      _maxProgress = 0.0;
    }

    if (form.isWrong) _cycleViolation ??= form.primary;
    _maxProgress = max(_maxProgress, _progress(angle));

    if (isValidRep && _stage == "down") {
      _stage = "up"; // target reached; the rep is judged on the way back
    }
  }

  void _finishCycle() {
    final exercise = widget.selectedExercise;
    final ms = DateTime.now()
        .difference(_cycleStart ?? DateTime.now())
        .inMilliseconds;

    if (_stage == "up") {
      final bad = _cycleViolation;
      if (bad == null) {
        _repCounter++;
        _repScores.add(1.0);
        _triggerFeedback();
      } else {
        _rejectRep(bad.withMessage('Rep not counted. ${bad.message}'));
      }
    } else if (_maxProgress >= FormThresholds.strict.attemptProgress &&
        ms >= 500) {
      // They started the movement but never got to the target angle.
      final fb = _rangeFeedback(exercise.type);
      _rejectRep(FormIssue(
        code: FormIssueCode.shortRange,
        message: 'Rep not counted. ${fb.message}',
        landmarks: fb.landmarks,
      ));
    }
  }

  void _rejectRep(FormIssue issue) {
    _rejectedReps++;
    _repScores.add(0.0);
    _flashIssue(issue);
    _triggerWrongFeedback();
  }

  void _cancelCycle() {
    _cycleActive = false;
    _cycleStart = null;
    _cycleViolation = null;
    _maxProgress = 0.0;
    _stage = "down";
  }

  /// 0 = at the patient's resting angle, 1 = at the target angle.
  double _progress(double angle) {
    final e = widget.selectedExercise;
    final base = _baseline ?? e.restAngle;
    final span = e.targetAngle - base;
    if (span.abs() < 10) return 0.0; // degenerate range, don't guess
    return (angle - base) / span;
  }

  ({String message, Set<PoseLandmarkType> landmarks}) _rangeFeedback(
    ExerciseType type,
  ) {
    const ankles = {PoseLandmarkType.leftAnkle, PoseLandmarkType.rightAnkle};
    const wrists = {PoseLandmarkType.leftWrist, PoseLandmarkType.rightWrist};
    const hips = {PoseLandmarkType.leftHip, PoseLandmarkType.rightHip};

    switch (type) {
      case ExerciseType.kneeExtension:
        return (
          message: 'Leg is too low. Raise it higher to straighten your knee.',
          landmarks: ankles,
        );
      case ExerciseType.straightLegRaise:
        return (message: 'Leg is too low. Lift it higher.', landmarks: ankles);
      case ExerciseType.kneeFlexion:
      case ExerciseType.heelSlide:
        return (message: 'Bend your knee further.', landmarks: ankles);
      case ExerciseType.forwardRaise:
      case ExerciseType.sideRaise:
        return (
          message: 'Arm is too low. Raise it to shoulder height.',
          landmarks: wrists,
        );
      case ExerciseType.forwardPush:
        return (message: 'Push all the way out.', landmarks: wrists);
      case ExerciseType.bicepCurl:
        return (message: 'Curl all the way up.', landmarks: wrists);
      case ExerciseType.sitToStand:
        return (message: 'Stand up fully.', landmarks: hips);
      case ExerciseType.gluteBridge:
        return (message: 'Lift your hips higher.', landmarks: hips);
      case ExerciseType.singleLegBalance:
      case ExerciseType.wallSit:
        return (message: 'Hold the full position.', landmarks: ankles);
    }
  }

  // ---------------------------------------------------------------------
  // Timed holds
  // ---------------------------------------------------------------------

  void _analyzeHold(
    Map<PoseLandmarkType, PoseLandmark> landmarks,
    FormResult form,
  ) {
    final exercise = widget.selectedExercise;
    bool inPosition = false;

    switch (exercise.type) {
      case ExerciseType.singleLegBalance:
        {
          final lHip = landmarks[PoseLandmarkType.leftHip];
          final lKnee = landmarks[PoseLandmarkType.leftKnee];
          final lAnkle = landmarks[PoseLandmarkType.leftAnkle];
          final rHip = landmarks[PoseLandmarkType.rightHip];
          final rKnee = landmarks[PoseLandmarkType.rightKnee];
          final rAnkle = landmarks[PoseLandmarkType.rightAnkle];

          if (lHip != null &&
              lKnee != null &&
              lAnkle != null &&
              rHip != null &&
              rKnee != null &&
              rAnkle != null) {
            // Image y grows downward, so the standing foot has the larger y.
            final leftIsStanding = lAnkle.y > rAnkle.y;
            final standHip = leftIsStanding ? lHip : rHip;
            final standKnee = leftIsStanding ? lKnee : rKnee;
            final standAnkle = leftIsStanding ? lAnkle : rAnkle;

            final standingKneeAngle = _calculateAngle(
              standHip,
              standKnee,
              standAnkle,
            );
            final legLength = _distance(standHip, standAnkle);
            final footLift = (lAnkle.y - rAnkle.y).abs();

            _currentAngle = standingKneeAngle;
            // Standing leg mostly straight AND other foot lifted by at
            // least 15% of the standing leg's length.
            inPosition = standingKneeAngle >= 150.0 && footLift >= 0.15 * legLength;
          }
        }
        break;

      case ExerciseType.wallSit:
        {
          final angles = <double?>[
            _jointAngle(
              landmarks,
              PoseLandmarkType.leftHip,
              PoseLandmarkType.leftKnee,
              PoseLandmarkType.leftAnkle,
            ),
            _jointAngle(
              landmarks,
              PoseLandmarkType.rightHip,
              PoseLandmarkType.rightKnee,
              PoseLandmarkType.rightAnkle,
            ),
          ].whereType<double>().toList();

          if (angles.isNotEmpty) {
            final kneeAngle = _average(angles);
            _currentAngle = kneeAngle;
            inPosition =
                (kneeAngle - exercise.targetAngle).abs() <= _wallSitTolerance;
          }
        }
        break;

      default:
        break;
    }

    // Strict: the clock only runs while the position is right AND the form
    // checker is happy. Flailing or swaying pauses it, and after the grace
    // period it resets.
    _updateHold(inPosition && !form.isWrong);
  }

  /// Call once per processed frame. Counts up while [inPosition] is true and
  /// awards one point when the hold reaches holdSeconds. A short loss of the
  /// position (grace period) does not reset the timer.
  void _updateHold(bool inPosition) {
    final now = DateTime.now();
    final needed = widget.selectedExercise.holdSeconds;

    if (inPosition) {
      _lostSince = null;
      _holdStart ??= now;
      final elapsed = now.difference(_holdStart!).inMilliseconds / 1000.0;

      setState(() {
        _holdElapsed = elapsed > needed ? needed.toDouble() : elapsed;
      });

      if (elapsed >= needed && !_holdCounted) {
        _holdCounted = true;
        _repScores.add(1.0);
        setState(() => _repCounter++);
        _triggerFeedback();
        _checkCompletion();
      }
    } else {
      _lostSince ??= now;
      if (now.difference(_lostSince!) > _holdGrace) {
        if (_holdStart != null || _holdElapsed != 0.0) {
          setState(() {
            _holdStart = null;
            _holdCounted = false; // release, then hold again for the next one
            _holdElapsed = 0.0;
          });
        }
      }
    }
  }

  // ---------------------------------------------------------------------
  // Session completion
  // ---------------------------------------------------------------------

  /// Call after every place _repCounter is incremented. Once the target is
  /// reached, locks in the session, persists it to history, and shows the
  /// congratulations banner. Safe to call repeatedly — only fires once.
  void _checkCompletion() {
    if (_isSessionComplete) return;
    if (_repCounter < widget.selectedExercise.targetReps) return;

    setState(() => _isSessionComplete = true);
    _cameraController?.stopImageStream();
    _saveSession();
    _showCompletionBanner();
  }

  Future<void> _saveSession() async {
    final exercise = widget.selectedExercise;
    final session = ExerciseSession(
      id: DateTime.now().microsecondsSinceEpoch.toString(),
      exerciseId: exercise.type.name,
      exerciseName: exercise.title,
      startTime: _sessionStart,
      endTime: DateTime.now(),
      completedReps: _repCounter,
      completedSets: 1,
      targetReps: exercise.targetReps,
      targetSets: 1,
      // One score per attempt: 1.0 for a clean rep/hold, 0.0 for a rep that
      // was rejected for form or range. overallCompliance = clean / attempts.
      repComplianceScores: List<double>.from(_repScores),
      notes: _rejectedReps > 0
          ? '$_rejectedReps rep(s) rejected for poor form or range'
          : null,
    );

    if (!mounted) return;
    await context.read<AppProvider>().recordCompletedSession(session);
  }

  void _showCompletionBanner() {
    final exercise = widget.selectedExercise;
    if (!mounted) return;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.check_circle_rounded, color: Colors.teal, size: 64),
            const SizedBox(height: 12),
            const Text(
              'Session Complete!',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              exercise.isHold
                  ? 'You finished ${exercise.targetReps} holds of ${exercise.title}.'
                  : 'You finished ${exercise.targetReps} reps of ${exercise.title}.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.black54),
            ),
            if (_rejectedReps > 0) ...[
              const SizedBox(height: 8),
              Text(
                '$_rejectedReps attempt(s) were not counted because of form.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.redAccent, fontSize: 12),
              ),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.of(dialogContext).pop(); // close the dialog
              Navigator.of(context).pop(); // back to the exercise list
            },
            child: const Text('Done'),
          ),
        ],
      ),
    );
  }

  double _calculateAngle(PoseLandmark p1, PoseLandmark p2, PoseLandmark p3) {
    double radians =
        atan2(p3.y - p2.y, p3.x - p2.x) - atan2(p1.y - p2.y, p1.x - p2.x);
    double angle = (radians * 180.0 / pi).abs();
    if (angle > 180.0) angle = 360.0 - angle;
    return angle;
  }

  InputImage? _inputImageFromCameraImage(
    CameraImage image,
    CameraDescription camera,
  ) {
    final sensorOrientation = camera.sensorOrientation;
    final rotation =
        InputImageRotationValue.fromRawValue(sensorOrientation) ??
        InputImageRotation.rotation0deg;
    final format =
        InputImageFormatValue.fromRawValue(image.format.raw) ??
        InputImageFormat.nv21;

    final WriteBuffer allBytes = WriteBuffer();
    for (final Plane plane in image.planes) {
      allBytes.putUint8List(plane.bytes);
    }
    final bytes = allBytes.done().buffer.asUint8List();

    final metadata = InputImageMetadata(
      size: Size(image.width.toDouble(), image.height.toDouble()),
      rotation: rotation,
      format: format,
      bytesPerRow: image.planes[0].bytesPerRow,
    );

    return InputImage.fromBytes(bytes: bytes, metadata: metadata);
  }

  @override
  void dispose() {
    _audioPlayer.dispose();
    _cameraController?.dispose();
    _poseDetector.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_cameraController == null || !_cameraController!.value.isInitialized) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final exercise = widget.selectedExercise;
    final form = _effectiveForm;
    final isCorrect = !form.isWrong;

    return Scaffold(
      appBar: AppBar(
        title: Text(exercise.title),
        backgroundColor: isCorrect ? Colors.teal : Colors.red,
        actions: [
          IconButton(
            icon: const Icon(Icons.cameraswitch),
            tooltip: "Switch Camera",
            onPressed: _switchCamera,
          ),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          CameraPreview(_cameraController!),

          if (_imageSize != null && _detectedPoses.isNotEmpty)
            CustomPaint(
              painter: PosePainter(
                _detectedPoses,
                _imageSize!,
                _rotation,
                isCorrect,
                _lensDirection,
                flaggedLandmarks: form.flagged,
                issues: form.issues,
              ),
            ),

          // "Keep Centered: ..." / "Wrong Form: ..." message
          Positioned(
            top: 8,
            left: 16,
            right: 16,
            child: _FormBanner(form: form),
          ),

          Positioned(
            bottom: 20,
            left: 20,
            right: 20,
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: (isCorrect ? Colors.black87 : Colors.red.shade900)
                    .withValues(alpha: 0.85),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Progress bar for timed holds
                  if (exercise.isHold) ...[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: LinearProgressIndicator(
                        value: (_holdElapsed / exercise.holdSeconds)
                            .clamp(0.0, 1.0)
                            .toDouble(),
                        minHeight: 8,
                        backgroundColor: Colors.white24,
                        valueColor: AlwaysStoppedAnimation<Color>(
                          isCorrect ? Colors.greenAccent : Colors.redAccent,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            exercise.isHold ? "HOLDS" : "REPS",
                            style: const TextStyle(
                              color: Colors.white54,
                              fontSize: 12,
                            ),
                          ),
                          Text(
                            "$_repCounter / ${exercise.targetReps}",
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 28,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                      Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            exercise.isHold ? "HOLD" : "ANGLE",
                            style: const TextStyle(
                              color: Colors.white54,
                              fontSize: 12,
                            ),
                          ),
                          Text(
                            exercise.isHold
                                ? "${_holdElapsed.toStringAsFixed(1)} / ${exercise.holdSeconds}s"
                                : "${_currentAngle.round()}°",
                            style: TextStyle(
                              color: isCorrect
                                  ? Colors.greenAccent
                                  : Colors.redAccent,
                              fontSize: 28,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  if (_rejectedReps > 0) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Not counted: $_rejectedReps',
                      style: const TextStyle(
                        color: Colors.redAccent,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Top-of-screen message: red bold label + the current fault, like the mockup.
class _FormBanner extends StatelessWidget {
  final FormResult form;
  const _FormBanner({required this.form});

  @override
  Widget build(BuildContext context) {
    if (form.isOk) return const SizedBox.shrink();

    final label = form.isNotInFrame ? 'Keep Centered: ' : 'Wrong Form: ';
    final message = form.primary?.message ?? '';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.6),
        borderRadius: BorderRadius.circular(12),
      ),
      child: RichText(
        textAlign: TextAlign.center,
        text: TextSpan(
          children: [
            TextSpan(
              text: label,
              style: const TextStyle(
                color: Colors.redAccent,
                fontWeight: FontWeight.bold,
                fontSize: 13,
              ),
            ),
            TextSpan(
              text: message,
              style: const TextStyle(
                color: Colors.white70,
                fontWeight: FontWeight.w600,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
