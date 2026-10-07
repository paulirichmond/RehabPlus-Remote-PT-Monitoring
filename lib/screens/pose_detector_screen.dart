// lib/screens/pose_detector_screen.dart
//
// SINGLE-FILE BUILD: PosePainter now lives at the bottom of this file, so you
// can DELETE lib/widgets/pose_painter.dart (this screen was its only user).
//
// What changed vs. the previous version
//   1. Skeleton mirroring FIXED (see _initCamera + PosePainter.mirrorX).
//   2. Added a "Flip skeleton" button in the app bar as an on-device override.
//   3. _processFrame can no longer get stuck busy (try/finally).
//   4. Camera init handles permission denial / failures with a retry screen.
//   5. Camera + detector are shut down safely (stop stream -> dispose -> close)
//      and released when the app goes to the background.
//   6. Image size for the painter is derived from the rotation, not assumed.
//   7. "Always red" fixes: looser form thresholds, leg checks skipped for arm
//      exercises, a forgiving "back at rest" zone, both arms tracked, and a
//      "Why red" line on screen that names the fault that is firing.
//   8. Preview shows the full 4:3 frame (no crop, no stretch); the skeleton
//      uses the same mapping. Flip _coverPreview to true for full-screen crop.

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

class PoseDetectorScreen extends StatefulWidget {
  final ExerciseConfig selectedExercise;
  const PoseDetectorScreen({super.key, required this.selectedExercise});

  @override
  State<PoseDetectorScreen> createState() => _PoseDetectorScreenState();
}

class _PoseDetectorScreenState extends State<PoseDetectorScreen>
    with WidgetsBindingObserver {
  CameraController? _cameraController;
  List<CameraDescription> _availableCameras = [];
  late PoseDetector _poseDetector;
  bool _isProcessing = false;
  bool _initializingCamera = false;
  String? _cameraError;

  final AudioPlayer _audioPlayer = AudioPlayer();

  // pubspec registers assets/successSound.mp3.
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

  /// Whether the skeleton is flipped horizontally to match the preview.
  ///
  /// WHY THIS IS TRUE FOR THE FRONT CAMERA:
  /// ML Kit analyses the raw (unmirrored) sensor frames, so its landmark X
  /// coordinates are in "camera's point of view" space: the patient's RIGHT
  /// hand has a SMALL x (left side of the frame). But the `camera` plugin
  /// shows the front-camera PREVIEW as a selfie mirror, so that same right
  /// hand is drawn on the RIGHT side of the screen. Painting the landmarks
  /// without flipping them puts the right-hand dots on the left of the
  /// screen -> "I raise my right hand and the skeleton's left hand rises".
  /// Flipping X for the front camera lines the skeleton back up.
  ///
  /// If a particular phone renders its front preview unmirrored, tap the
  /// flip icon in the app bar to correct it on the spot.
  bool _mirrorSkeleton = true;

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
    WidgetsBinding.instance.addObserver(this);
    _sessionStart = DateTime.now();
    _checker = FormChecker(widget.selectedExercise, thresholds: _thresholds);
    _initPoseDetector();
    _initCamera();
  }

  /// Pose analysis is capped to roughly this rate (ms between processed
  /// frames). ~15 fps is plenty for slow rehab movements.
  static const int _minFrameIntervalMs = 66;

  /// false = show the whole camera frame at its native ratio (letterboxed,
  /// nothing cropped, nothing stretched). true = fill the screen and crop.
  static const bool _coverPreview = false;

  /// The "strict" preset flagged normal movement as bad form (jittery
  /// landmarks at 320x240 easily exceed 0.25 torso lengths). "standard" is
  /// the forgiving preset that already ships in form_checker.dart, so no
  /// custom constructor call is needed (that call was what showed red).
  static const FormThresholds _thresholds = FormThresholds.standard;

  /// A rep is "back at rest" once the joint has returned through the first
  /// 30% of the range, instead of having to hit the exact rest angle.
  static const double _restZone = 0.3;

  void _initPoseDetector() {
    // "base" is the lightweight model: same 33 landmarks as "accurate" at a
    // fraction of the cost. Switch to PoseDetectionModel.accurate if you need
    // more precision and the device can keep up.
    final options = PoseDetectorOptions(
      mode: PoseDetectionMode.stream,
      model: PoseDetectionModel.base,
    );
    _poseDetector = PoseDetector(options: options);
  }

  Future<void> _initCamera() async {
    if (_initializingCamera || _isSessionComplete) return;
    _initializingCamera = true;
    if (mounted) setState(() => _cameraError = null);

    try {
      _availableCameras = await availableCameras();
      if (_availableCameras.isEmpty) {
        throw CameraException('NoCamera', 'No camera found on this device.');
      }

      final camera = _availableCameras.firstWhere(
        (c) => c.lensDirection == _lensDirection,
        orElse: () => _availableCameras.first,
      );
      _lensDirection = camera.lensDirection;

      // Front camera preview = selfie mirror, ML Kit input = unmirrored, so
      // the skeleton has to be flipped. Back camera: both unmirrored.
      _mirrorSkeleton = _lensDirection == CameraLensDirection.front;

      final controller = CameraController(
        camera,
        // Low resolution: ML Kit downscales internally anyway.
        ResolutionPreset.low,
        enableAudio: false,
        imageFormatGroup: ImageFormatGroup.nv21,
      );
      _cameraController = controller;

      await controller.initialize();
      if (!mounted) {
        await _disposeCamera();
        return;
      }
      await controller.startImageStream(
        (image) => _processFrame(image, camera),
      );
      if (mounted) setState(() {});
    } on CameraException catch (e) {
      debugPrint('Camera error: ${e.code} ${e.description}');
      await _disposeCamera();
      if (mounted) {
        setState(() {
          _cameraError = switch (e.code) {
            'CameraAccessDenied' ||
            'CameraAccessDeniedWithoutPrompt' ||
            'CameraAccessRestricted' =>
              'Camera permission is required to track your exercise. '
                  'Please allow camera access in your phone settings.',
            'NoCamera' => 'No camera was found on this device.',
            _ => 'Could not start the camera (${e.code}).',
          };
        });
      }
    } catch (e) {
      debugPrint('Camera init failed: $e');
      await _disposeCamera();
      if (mounted) {
        setState(() => _cameraError = 'Could not start the camera.');
      }
    } finally {
      _initializingCamera = false;
    }
  }

  /// Stops the stream before disposing (disposing a streaming controller can
  /// throw or keep delivering frames to a dead screen).
  Future<void> _disposeCamera() async {
    final controller = _cameraController;
    _cameraController = null;
    if (controller == null) return;
    try {
      if (controller.value.isStreamingImages) {
        await controller.stopImageStream();
      }
    } catch (e) {
      debugPrint('stopImageStream: $e');
    }
    try {
      await controller.dispose();
    } catch (e) {
      debugPrint('camera dispose: $e');
    }
  }

  Future<void> _switchCamera() async {
    if (_availableCameras.length < 2 || _initializingCamera) return;

    _lensDirection = _lensDirection == CameraLensDirection.front
        ? CameraLensDirection.back
        : CameraLensDirection.front;

    await _disposeCamera();

    _checker.reset();
    _cancelCycle();
    if (mounted) setState(() => _detectedPoses = []);
    await _initCamera();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (_isSessionComplete) return;
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused) {
      // Release the camera while backgrounded (privacy + battery).
      _checker.reset();
      _cancelCycle();
      _disposeCamera().then((_) {
        if (mounted) setState(() => _detectedPoses = []);
      });
    } else if (state == AppLifecycleState.resumed &&
        _cameraController == null) {
      _initCamera();
    }
  }

  /// Timestamp of the last processed frame — used to cap the analysis rate.
  int _lastFrameMs = 0;

  void _processFrame(CameraImage image, CameraDescription camera) async {
    // Back-pressure: skip frames while ML Kit is still busy.
    if (_isProcessing || _isSessionComplete || !mounted) return;

    final nowMs = DateTime.now().millisecondsSinceEpoch;
    if (nowMs - _lastFrameMs < _minFrameIntervalMs) return;
    _lastFrameMs = nowMs;

    _isProcessing = true;
    try {
      final inputImage = _inputImageFromCameraImage(image, camera);
      if (inputImage == null) return;

      final List<Pose> poses = await _poseDetector.processImage(inputImage);

      if (mounted && !_isSessionComplete) {
        // Judge the form first so the overlay and the rep logic agree.
        final form = _filterForm(
          poses.isEmpty
              ? _checker.evaluateNoPose()
              : _checker.evaluate(poses.first.landmarks),
        );

        final rotation =
            inputImage.metadata?.rotation ?? InputImageRotation.rotation0deg;
        // The painter wants the frame size AFTER rotation to upright.
        final swap =
            rotation == InputImageRotation.rotation90deg ||
            rotation == InputImageRotation.rotation270deg;
        final w = image.width.toDouble();
        final h = image.height.toDouble();

        setState(() {
          _detectedPoses = poses;
          _imageSize = swap ? Size(h, w) : Size(w, h);
          _rotation = rotation;
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
    } finally {
      // Always released, even if something above throws.
      _isProcessing = false;
    }
  }

  /// Arm exercises: legs are usually cropped or half out of frame, and their
  /// guessed landmarks jitter, which kept "legs moving" red permanently.
  FormResult _filterForm(FormResult f) {
    if (!f.isWrong) return f;
    final armExercise = switch (widget.selectedExercise.type) {
      ExerciseType.forwardRaise ||
      ExerciseType.sideRaise ||
      ExerciseType.forwardPush ||
      ExerciseType.bicepCurl => true,
      _ => false,
    };
    if (!armExercise) return f;
    final kept = f.issues
        .where((i) => i.code != FormIssueCode.legsMoving)
        .toList();
    return kept.isEmpty
        ? FormResult.ok
        : FormResult(FormStatus.wrongForm, kept);
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
        {
          // Use whichever arm is raised (the old code only watched the left).
          final raised = <double?>[
            _jointAngle(
              landmarks,
              PoseLandmarkType.leftHip,
              PoseLandmarkType.leftShoulder,
              PoseLandmarkType.leftWrist,
            ),
            _jointAngle(
              landmarks,
              PoseLandmarkType.rightHip,
              PoseLandmarkType.rightShoulder,
              PoseLandmarkType.rightWrist,
            ),
          ].whereType<double>().toList();
          if (raised.isNotEmpty) {
            calculatedAngle = raised.reduce(max);
            isValidRep = calculatedAngle >= exercise.targetAngle;
            isRestPosition = calculatedAngle <= exercise.restAngle;
          }
        }
        break;

      case ExerciseType.forwardPush:
        {
          final extended = <double?>[
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
          if (extended.isNotEmpty) {
            calculatedAngle = extended.reduce(max);
            isValidRep = calculatedAngle >= exercise.targetAngle;
            isRestPosition = calculatedAngle <= exercise.restAngle;
          }
        }
        break;

      case ExerciseType.bicepCurl:
        {
          // Track BOTH arms independently; a real curl needs the wrist to
          // travel toward the shoulder while the elbow stays pinned low.
          final left = _jointAngle(
            landmarks,
            PoseLandmarkType.leftShoulder,
            PoseLandmarkType.leftElbow,
            PoseLandmarkType.leftWrist,
          );
          final right = _jointAngle(
            landmarks,
            PoseLandmarkType.rightShoulder,
            PoseLandmarkType.rightElbow,
            PoseLandmarkType.rightWrist,
          );

          double? best;
          PoseLandmark? bestElbow;
          PoseLandmark? bestWrist;
          for (final entry in [
            (left, PoseLandmarkType.leftElbow, PoseLandmarkType.leftWrist),
            (right, PoseLandmarkType.rightElbow, PoseLandmarkType.rightWrist),
          ]) {
            final angle = entry.$1;
            if (angle == null) continue;
            if (best == null || angle < best) {
              best = angle;
              bestElbow = landmarks[entry.$2];
              bestWrist = landmarks[entry.$3];
            }
          }

          if (best != null && bestElbow != null && bestWrist != null) {
            calculatedAngle = best;

            // Elbow must hang below the shoulder line (image y grows down).
            final shoulderY =
                landmarks[PoseLandmarkType.leftShoulder]?.y ??
                landmarks[PoseLandmarkType.rightShoulder]?.y;
            // Landmark coordinates are PIXELS, so tolerances must scale with
            // the body (the old +/-0.05 / 0.12 were fractions of a pixel).
            final torsoPx = (shoulder != null && hip != null)
                ? _distance(shoulder, hip)
                : 100.0;
            final elbowPinned =
                shoulderY == null || bestElbow.y > shoulderY - 0.15 * torsoPx;

            // Wrist must be near shoulder height — the top of a curl.
            final wristNearShoulder =
                shoulderY != null && bestWrist.y <= shoulderY + 0.35 * torsoPx;

            isValidRep =
                calculatedAngle <= exercise.targetAngle &&
                elbowPinned &&
                wristNearShoulder;
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

    // Forgiving rest zone: count the rep once the joint is back through the
    // first part of the range, rather than at the exact rest angle (arms
    // that hang a bit away from the body never hit 20 degrees).
    if (calculatedAngle != 0.0) {
      final span = exercise.targetAngle - exercise.restAngle;
      final returnAngle = exercise.restAngle + span * _restZone;
      isRestPosition = span >= 0
          ? calculatedAngle <= returnAngle
          : calculatedAngle >= returnAngle;
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
    } else if (_maxProgress >= _thresholds.attemptProgress && ms >= 500) {
      // They started the movement but never got to the target angle.
      final fb = _rangeFeedback(exercise.type);
      _rejectRep(
        FormIssue(
          code: FormIssueCode.shortRange,
          message: 'Rep not counted. ${fb.message}',
          landmarks: fb.landmarks,
        ),
      );
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
            inPosition =
                standingKneeAngle >= 150.0 && footLift >= 0.15 * legLength;
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
    // checker is happy.
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
    _disposeCamera(); // session is over: stop the stream and free the camera
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
            const Icon(
              Icons.check_circle_rounded,
              color: Colors.teal,
              size: 64,
            ),
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
              if (mounted) Navigator.of(context).pop(); // back to the list
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
    WidgetsBinding.instance.removeObserver(this);
    _audioPlayer.dispose();
    // Order matters: stop the stream and camera first, then close ML Kit.
    _disposeCamera().whenComplete(() => _poseDetector.close());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final exercise = widget.selectedExercise;

    if (_cameraError != null) {
      return Scaffold(
        appBar: AppBar(title: Text(exercise.title)),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.videocam_off_rounded,
                  size: 56,
                  color: Colors.black38,
                ),
                const SizedBox(height: 16),
                Text(
                  _cameraError!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 14, height: 1.4),
                ),
                const SizedBox(height: 20),
                ElevatedButton.icon(
                  onPressed: _initCamera,
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Try again'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final controller = _cameraController;
    if (controller == null || !controller.value.isInitialized) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final form = _effectiveForm;
    final isCorrect = !form.isWrong;

    return Scaffold(
      appBar: AppBar(
        title: Text(exercise.title),
        backgroundColor: isCorrect ? Colors.teal : Colors.red,
        actions: [
          IconButton(
            icon: Icon(
              Icons.flip,
              color: _mirrorSkeleton ? Colors.white : Colors.white54,
            ),
            tooltip: "Flip skeleton (use if it moves opposite to you)",
            onPressed: () => setState(() => _mirrorSkeleton = !_mirrorSkeleton),
          ),
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
          // Native-ratio preview (see _coverPreview). The plugin itself mirrors
          // the FRONT preview like a selfie; this widget adds no flip.
          const ColoredBox(color: Colors.black),
          _AspectCoverPreview(controller: controller, cover: _coverPreview),

          if (_imageSize != null && _detectedPoses.isNotEmpty)
            CustomPaint(
              painter: PosePainter(
                _detectedPoses,
                _imageSize!,
                _rotation,
                isCorrect,
                _lensDirection,
                // Front camera: preview is mirrored, ML Kit data is not, so
                // the skeleton is flipped to match (see _mirrorSkeleton).
                mirrorX: _mirrorSkeleton,
                cover: _coverPreview,
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
                  if (!isCorrect) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Why red: ${form.issues.map((i) => i.code.name).join(', ')}',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white70,
                        fontSize: 11,
                      ),
                    ),
                  ],
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

/// Camera preview drawn at the sensor's native aspect ratio.
///  - cover=false: the whole frame is visible (letterboxed), so nothing is
///    cropped or stretched.
///  - cover=true : the frame fills the screen and the overflow is cropped.
/// PosePainter uses the identical mapping, so video and skeleton line up.
///
/// This widget must NOT apply its own horizontal flip: the `camera` plugin
/// already mirrors the front preview, and PosePainter.mirrorX compensates
/// for that on the skeleton side.
class _AspectCoverPreview extends StatelessWidget {
  final CameraController controller;
  final bool cover;

  const _AspectCoverPreview({required this.controller, this.cover = false});

  @override
  Widget build(BuildContext context) {
    final size = controller.value.previewSize;
    if (size == null || size.width <= 0 || size.height <= 0) {
      return const SizedBox.shrink();
    }

    // Plugins disagree on whether previewSize is landscape or portrait, so
    // don't trust its orientation: take the long/short sides and orient the
    // frame to match the screen (portrait phone => tall frame).
    final longSide = max(size.width, size.height);
    final shortSide = min(size.width, size.height);
    final isLandscape =
        MediaQuery.of(context).orientation == Orientation.landscape;
    final frame = isLandscape
        ? Size(longSide, shortSide)
        : Size(shortSide, longSide);

    return LayoutBuilder(
      builder: (context, constraints) {
        final sx = constraints.maxWidth / frame.width;
        final sy = constraints.maxHeight / frame.height;
        final scale = cover ? max(sx, sy) : min(sx, sy);
        final w = frame.width * scale;
        final h = frame.height * scale;

        return ClipRect(
          child: Center(
            child: SizedBox(
              width: w,
              height: h,
              child: CameraPreview(controller),
            ),
          ),
        );
      },
    );
  }
}

/// Top-of-screen message: red bold label + the current fault.
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

// ===========================================================================
// PosePainter (merged in from lib/widgets/pose_painter.dart)
// ===========================================================================

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

  /// Flip landmarks horizontally. True for the front camera: ML Kit sees the
  /// unmirrored frame while the on-screen preview is a selfie mirror, so
  /// without this flip the patient's right hand drives the skeleton's
  /// left-side limb on screen.
  final bool mirrorX;

  /// Same choice as the preview: false = whole frame visible (min scale),
  /// true = fill and crop (max scale).
  final bool cover;

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
    this.mirrorX = false,
    this.cover = false,
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
    // Red bones with yellow joints when the form is wrong.
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

    // Aspect-correct mapping: uniform cover scale + centre crop, matching
    // _AspectCoverPreview.
    final iw = absoluteImageSize.width;
    final ih = absoluteImageSize.height;
    if (iw <= 0 || ih <= 0) return;
    final scale = cover
        ? max(size.width / iw, size.height / ih)
        : min(size.width / iw, size.height / ih);
    final drawW = iw * scale;
    final drawH = ih * scale;
    final offX = (size.width - drawW) / 2.0;
    final offY = (size.height - drawH) / 2.0;

    for (final pose in poses) {
      final Map<PoseLandmarkType, Offset> points = {};

      pose.landmarks.forEach((type, landmark) {
        if (landmark.likelihood > 0.5) {
          var x = offX + landmark.x * scale;
          final y = offY + landmark.y * scale;

          // Mirror about the screen's vertical centre line. The preview is
          // flipped around the same line (it is centred in the screen), so
          // the skeleton and the video stay glued together.
          if (mirrorX) x = size.width - x;
          points[type] = Offset(x, y);
        }
      });

      // Bones (thicker when they touch a flagged joint)
      for (final bone in _bones) {
        final p1 = points[bone[0]];
        final p2 = points[bone[1]];
        if (p1 == null || p2 == null) continue;
        final hot =
            wrong &&
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

  /// White bubble with a red border and red text, joined to the joint by a line.
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

      // Bubble goes on whichever side of the joint has more room; the first
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
