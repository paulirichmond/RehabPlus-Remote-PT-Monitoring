// lib/screens/pose_detector_screen.dart
//
// SINGLE-FILE BUILD. Replace lib/screens/pose_detector_screen.dart with this.
// No other file needs to change (form_checker.dart, exercise_model.dart and
// pubspec.yaml stay as they are).
//
// What changed in this version
//   1. Per-exercise fault checks for the exercises that had none (raises,
//      forward push, sit-to-stand, single-leg balance, wall sit), plus an
//      un-debounced elbow-swing check for the bicep curl.
//   2. Flailing no longer counts as a rep. A rep is rejected if it was too
//      fast, if the arm/body faulted for several frames, or if the joint
//      crossed the target more than once (back-and-forth).
//   3. Looser "down" position: a nearly straight arm (elbow ~170 deg) counts
//      as down for the curl, and the raise exercises accept the arm within
//      ~48 deg of the body. Elbow-swing limit relaxed to 55 deg.
//   4. Success sound is preloaded once in low-latency mode, the vibrator check
//      is cached, and the rep is now committed on the way back down (past 60%
//      of the return) instead of at the very end, so feedback fires sooner.
//   5. One setState per processed frame (less rebuilding => less lag).
//
// Tuning knobs are all in the "TUNING" block below.

import 'dart:async';
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

  // ---------------------------------------------------------------------
  // TUNING
  // ---------------------------------------------------------------------

  /// Pose analysis cap (ms between processed frames). ~15 fps.
  static const int _minFrameIntervalMs = 66;

  /// false = whole frame visible (letterboxed). true = fill and crop.
  static const bool _coverPreview = false;

  /// Same numbers as FormThresholds.standard, except the elbow-swing limit
  /// is relaxed (was 45) so the arms don't have to be glued to your sides.
  static const FormThresholds _thresholds = FormThresholds(
    minLikelihood: 0.5,
    stationaryRange: 0.40,
    torsoSwayRange: 0.25,
    maxWorkingSpeed: 3.5,
    maxTrunkLeanDeg: 30,
    maxUpperArmSwingDeg: 55,
    minStraightArmDeg: 140,
    window: Duration(milliseconds: 800),
    confirm: Duration(milliseconds: 200),
    hold: Duration(milliseconds: 700),
    attemptProgress: 0.4,
  );

  /// Rest zone: a rep is "back at rest" once the joint has returned through
  /// this fraction of the range.
  static const double _restZone = 0.3;

  /// The rep is judged (counted/rejected, sound plays) as soon as the joint
  /// has come back this far toward rest after reaching the target
  /// (1.0 = at target, 0.0 = at rest). Higher = earlier feedback.
  static const double _commitProgress = 0.6;

  /// "Down" positions. The curl's elbow is ~170 deg when the arm hangs
  /// straight; the raises measure the arm against the body (hip-shoulder-wrist).
  static const double _curlRestAngle = 170.0;
  static const double _raiseRestAngle = 30.0;

  /// Anti-flail rules for rep-based exercises.
  static const int _minRepMs = 700; // leave-rest -> back toward rest
  static const int _faultFrameLimit = 3; // faulty frames allowed in one rep
  static const double _reArmProgress =
      0.7; // must drop below this to re-hit target

  /// Extra per-exercise limits.
  static const double _raiseMaxDeg = 125.0; // arm above this = too high
  static const double _sitToStandMaxLeanDeg = 60.0;
  static const double _wallSitFaultDeg = 30.0; // off-target by this = red
  static const double _wallSitTolerance = 20.0; // counts as "in position"

  // ---------------------------------------------------------------------
  // Audio / haptics
  // ---------------------------------------------------------------------

  final AudioPlayer _audioPlayer = AudioPlayer();
  bool _audioReady = false;
  bool _hasVibrator = false;

  /// Loads the sound once so a rep never waits on disk/decoding.
  Future<void> _initAudio() async {
    try {
      await _audioPlayer.setAudioContext(
        AudioContextConfig(
          focus: AudioContextConfigFocus.mixWithOthers,
        ).build(),
      );
      await _audioPlayer.setPlayerMode(PlayerMode.lowLatency);
      await _audioPlayer.setReleaseMode(ReleaseMode.stop);
      await _audioPlayer.setSource(AssetSource('successSound.mp3'));
      _audioReady = true;
    } catch (e) {
      debugPrint('Audio init error: $e');
    }
  }

  void _playSuccessSound() {
    if (!_audioReady) return;
    // Fire and forget: never block the frame pipeline on audio.
    unawaited(
      _audioPlayer
          .stop()
          .then((_) => _audioPlayer.resume())
          .catchError((Object e) => debugPrint('Audio error: $e')),
    );
  }

  void _triggerFeedback() {
    _playSuccessSound();
    if (_hasVibrator) Vibration.vibrate(duration: 100);
  }

  /// Double buzz for wrong form / rejected rep (throttled so it can't spam).
  DateTime _lastBuzz = DateTime.fromMillisecondsSinceEpoch(0);
  void _triggerWrongFeedback() {
    final now = DateTime.now();
    if (now.difference(_lastBuzz).inMilliseconds < 1500) return;
    _lastBuzz = now;
    if (_hasVibrator) Vibration.vibrate(pattern: [0, 120, 80, 120]);
  }

  // ---------------------------------------------------------------------
  // State
  // ---------------------------------------------------------------------

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

  // Debounce for the extra per-exercise faults defined in this file.
  final Map<FormIssueCode, int> _xFirst = {};
  final Map<FormIssueCode, int> _xLast = {};
  final Map<FormIssueCode, FormIssue> _xIssue = {};

  // ---- Rep cycle (a rep = leave rest, reach target, come back) ----
  bool _cycleActive = false;
  bool _awaitRest = false; // rep already judged; wait until fully at rest
  DateTime? _cycleStart;
  FormIssue? _cycleViolation; // first debounced form fault seen during the rep
  FormIssue? _cycleFaultIssue; // first raw (instant) fault seen during the rep
  int _cycleFaultFrames = 0; // frames with a raw fault during the rep
  int _targetHits = 0; // times the target was reached during the rep
  bool _atTarget = false;
  double _maxProgress = 0.0; // furthest toward target this rep (0..1+)
  double? _baseline; // the patient's own resting angle
  int _rejectedReps = 0;
  final List<double> _repScores = []; // 1.0 = clean rep, 0.0 = rejected rep

  // ---- Session completion state ----
  bool _isSessionComplete = false;
  late final DateTime _sessionStart;

  // ---- Hold-timer state (single-leg balance, wall sit, ...) ----
  DateTime? _holdStart;
  DateTime? _lostSince;
  bool _holdCounted = false;
  double _holdElapsed = 0.0;
  static const Duration _holdGrace = Duration(milliseconds: 700);

  // Visual Overlay states
  List<Pose> _detectedPoses = [];
  Size? _imageSize;
  InputImageRotation _rotation = InputImageRotation.rotation0deg;
  CameraLensDirection _lensDirection = CameraLensDirection.front;

  /// Front camera preview is a selfie mirror while ML Kit data is not, so the
  /// skeleton is flipped. The app-bar button is an on-device override.
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
    Vibration.hasVibrator().then((v) => _hasVibrator = v ?? false);
    _initAudio();
    _initPoseDetector();
    _initCamera();
  }

  void _initPoseDetector() {
    // "base" is the lightweight model: same 33 landmarks as "accurate" at a
    // fraction of the cost.
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
      _mirrorSkeleton = _lensDirection == CameraLensDirection.front;

      final controller = CameraController(
        camera,
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

  void _resetTracking() {
    _checker.reset();
    _xFirst.clear();
    _xLast.clear();
    _xIssue.clear();
    _cancelCycle();
  }

  Future<void> _switchCamera() async {
    if (_availableCameras.length < 2 || _initializingCamera) return;

    _lensDirection = _lensDirection == CameraLensDirection.front
        ? CameraLensDirection.back
        : CameraLensDirection.front;

    await _disposeCamera();

    _resetTracking();
    if (mounted) setState(() => _detectedPoses = []);
    await _initCamera();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (_isSessionComplete) return;
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused) {
      _resetTracking();
      _disposeCamera().then((_) {
        if (mounted) setState(() => _detectedPoses = []);
      });
    } else if (state == AppLifecycleState.resumed &&
        _cameraController == null) {
      _initCamera();
    }
  }

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
      if (!mounted || _isSessionComplete) return;

      // Judge the form first so the overlay and the rep logic agree.
      final FormResult form;
      if (poses.isEmpty) {
        form = _filterForm(_checker.evaluateNoPose());
      } else {
        final lm = poses.first.landmarks;
        form = _filterForm(_mergeExtraFaults(_checker.evaluate(lm), lm));
      }

      if (form.isWrong && !_wasWrong) _triggerWrongFeedback();
      _wasWrong = form.isWrong;

      // Run all logic BEFORE the single setState below.
      if (poses.isNotEmpty) {
        _analyzeMotion(poses.first.landmarks, form);
      } else {
        _cancelCycle();
        if (widget.selectedExercise.isHold) _updateHold(false);
      }

      final rotation =
          inputImage.metadata?.rotation ?? InputImageRotation.rotation0deg;
      final swap =
          rotation == InputImageRotation.rotation90deg ||
          rotation == InputImageRotation.rotation270deg;
      final w = image.width.toDouble();
      final h = image.height.toDouble();

      if (!mounted) return;
      setState(() {
        _detectedPoses = poses;
        _imageSize = swap ? Size(h, w) : Size(w, h);
        _rotation = rotation;
        _form = form;
      });
    } catch (e) {
      debugPrint("ML Kit Detection Error: $e");
    } finally {
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

  bool _vis(PoseLandmark? p) =>
      p != null && p.likelihood >= _thresholds.minLikelihood;

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

  /// Like [_jointAngle] but also requires every landmark to be confidently
  /// visible (used by the fault checks so guessed points don't cause red).
  double? _visAngle(
    Map<PoseLandmarkType, PoseLandmark> lm,
    PoseLandmarkType a,
    PoseLandmarkType b,
    PoseLandmarkType c,
  ) {
    final p1 = lm[a];
    final p2 = lm[b];
    final p3 = lm[c];
    if (!_vis(p1) || !_vis(p2) || !_vis(p3)) return null;
    return _calculateAngle(p1!, p2!, p3!);
  }

  double _average(List<double> values) =>
      values.reduce((a, b) => a + b) / values.length;

  double _distance(PoseLandmark a, PoseLandmark b) {
    final dx = a.x - b.x;
    final dy = a.y - b.y;
    return sqrt(dx * dx + dy * dy);
  }

  /// Shoulder-mid to hip-mid distance in pixels, or null if not visible.
  double? _torsoPx(Map<PoseLandmarkType, PoseLandmark> lm) {
    final s = [
      lm[PoseLandmarkType.leftShoulder],
      lm[PoseLandmarkType.rightShoulder],
    ].where(_vis).cast<PoseLandmark>().toList();
    final h = [
      lm[PoseLandmarkType.leftHip],
      lm[PoseLandmarkType.rightHip],
    ].where(_vis).cast<PoseLandmark>().toList();
    if (s.isEmpty || h.isEmpty) return null;
    final sx = s.map((p) => p.x).reduce((a, b) => a + b) / s.length;
    final sy = s.map((p) => p.y).reduce((a, b) => a + b) / s.length;
    final hx = h.map((p) => p.x).reduce((a, b) => a + b) / h.length;
    final hy = h.map((p) => p.y).reduce((a, b) => a + b) / h.length;
    return sqrt((sx - hx) * (sx - hx) + (sy - hy) * (sy - hy));
  }

  /// The angle the "down" position is judged against (see TUNING).
  double get _restAngle {
    switch (widget.selectedExercise.type) {
      case ExerciseType.bicepCurl:
        return _curlRestAngle;
      case ExerciseType.forwardRaise:
      case ExerciseType.sideRaise:
        return _raiseRestAngle;
      default:
        return widget.selectedExercise.restAngle;
    }
  }

  // ---------------------------------------------------------------------
  // Extra per-exercise faults (live in this file; reuse existing issue codes)
  //
  // _rawFaults  : instantaneous, used for rep validation (catches flailing)
  // _mergeExtraFaults : debounced, used for the red skeleton / banner
  // ---------------------------------------------------------------------

  List<FormIssue> _rawFaults(Map<PoseLandmarkType, PoseLandmark> lm) {
    final out = <FormIssue>[];
    final exercise = widget.selectedExercise;

    switch (exercise.type) {
      case ExerciseType.bicepCurl:
        {
          // Upper arm must stay beside the trunk; only the elbow bends.
          final swung = <PoseLandmarkType>{};
          const sides = [
            (
              PoseLandmarkType.leftHip,
              PoseLandmarkType.leftShoulder,
              PoseLandmarkType.leftElbow,
            ),
            (
              PoseLandmarkType.rightHip,
              PoseLandmarkType.rightShoulder,
              PoseLandmarkType.rightElbow,
            ),
          ];
          for (final s in sides) {
            final a = _visAngle(lm, s.$1, s.$2, s.$3);
            if (a != null && a > _thresholds.maxUpperArmSwingDeg) {
              swung.add(s.$3);
            }
          }
          if (swung.isNotEmpty) {
            out.add(
              FormIssue(
                code: FormIssueCode.elbowDrift,
                message: 'Keep your elbows by your sides. Only bend the elbow.',
                landmarks: swung,
                anchor: swung.first,
              ),
            );
          }
        }
        break;

      case ExerciseType.forwardRaise:
      case ExerciseType.sideRaise:
        {
          // Raise to shoulder height, not above it.
          final high = <PoseLandmarkType>{};
          const sides = [
            (
              PoseLandmarkType.leftHip,
              PoseLandmarkType.leftShoulder,
              PoseLandmarkType.leftWrist,
            ),
            (
              PoseLandmarkType.rightHip,
              PoseLandmarkType.rightShoulder,
              PoseLandmarkType.rightWrist,
            ),
          ];
          for (final s in sides) {
            final a = _visAngle(lm, s.$1, s.$2, s.$3);
            if (a != null && a > _raiseMaxDeg) high.add(s.$3);
          }
          if (high.isNotEmpty) {
            out.add(
              FormIssue(
                code: FormIssueCode.shortRange,
                message: 'Too high. Stop at shoulder height.',
                landmarks: high,
                anchor: high.first,
              ),
            );
          }
        }
        break;

      case ExerciseType.forwardPush:
        {
          // Extended hands should be at chest height, not drifting up/down.
          final torso = _torsoPx(lm);
          if (torso != null) {
            final off = <PoseLandmarkType>{};
            const sides = [
              (
                PoseLandmarkType.leftShoulder,
                PoseLandmarkType.leftElbow,
                PoseLandmarkType.leftWrist,
              ),
              (
                PoseLandmarkType.rightShoulder,
                PoseLandmarkType.rightElbow,
                PoseLandmarkType.rightWrist,
              ),
            ];
            for (final s in sides) {
              final elbowAngle = _visAngle(lm, s.$1, s.$2, s.$3);
              final sh = lm[s.$1];
              final wr = lm[s.$3];
              if (elbowAngle == null || !_vis(sh) || !_vis(wr)) continue;
              if (elbowAngle > 130 && (wr!.y - sh!.y).abs() > 0.6 * torso) {
                off.add(s.$3);
              }
            }
            if (off.isNotEmpty) {
              out.add(
                FormIssue(
                  code: FormIssueCode.shortRange,
                  message: 'Push straight ahead at chest height.',
                  landmarks: off,
                  anchor: off.first,
                ),
              );
            }
          }
        }
        break;

      case ExerciseType.sitToStand:
        {
          // Some forward lean is normal; too much means collapsing forward.
          final ls = lm[PoseLandmarkType.leftShoulder];
          final rs = lm[PoseLandmarkType.rightShoulder];
          final lh = lm[PoseLandmarkType.leftHip];
          final rh = lm[PoseLandmarkType.rightHip];
          final shoulders = [ls, rs].where(_vis).cast<PoseLandmark>().toList();
          final hips = [lh, rh].where(_vis).cast<PoseLandmark>().toList();
          if (shoulders.isNotEmpty && hips.isNotEmpty) {
            final sx =
                shoulders.map((p) => p.x).reduce((a, b) => a + b) /
                shoulders.length;
            final sy =
                shoulders.map((p) => p.y).reduce((a, b) => a + b) /
                shoulders.length;
            final hx =
                hips.map((p) => p.x).reduce((a, b) => a + b) / hips.length;
            final hy =
                hips.map((p) => p.y).reduce((a, b) => a + b) / hips.length;
            final lean = atan2((sx - hx).abs(), hy - sy) * 180 / pi;
            if (lean > _sitToStandMaxLeanDeg) {
              out.add(
                FormIssue(
                  code: FormIssueCode.trunkLean,
                  message: "Don't lean too far forward. Keep your chest up.",
                  landmarks: {
                    PoseLandmarkType.leftShoulder,
                    PoseLandmarkType.rightShoulder,
                    PoseLandmarkType.leftHip,
                    PoseLandmarkType.rightHip,
                  },
                  anchor: _vis(ls)
                      ? PoseLandmarkType.leftShoulder
                      : PoseLandmarkType.rightShoulder,
                ),
              );
            }
          }
        }
        break;

      case ExerciseType.singleLegBalance:
        {
          final lAnkle = lm[PoseLandmarkType.leftAnkle];
          final rAnkle = lm[PoseLandmarkType.rightAnkle];
          if (_vis(lAnkle) && _vis(rAnkle)) {
            // Image y grows downward: the standing foot has the larger y.
            final leftStands = lAnkle!.y > rAnkle!.y;
            final hip = leftStands
                ? PoseLandmarkType.leftHip
                : PoseLandmarkType.rightHip;
            final knee = leftStands
                ? PoseLandmarkType.leftKnee
                : PoseLandmarkType.rightKnee;
            final ankle = leftStands
                ? PoseLandmarkType.leftAnkle
                : PoseLandmarkType.rightAnkle;
            final a = _visAngle(lm, hip, knee, ankle);
            if (a != null && a < 150) {
              out.add(
                FormIssue(
                  code: FormIssueCode.kneeBent,
                  message: 'Keep your standing leg straight.',
                  landmarks: {knee, ankle},
                  anchor: knee,
                ),
              );
            }
          }
        }
        break;

      case ExerciseType.wallSit:
        {
          final angles = <double?>[
            _visAngle(
              lm,
              PoseLandmarkType.leftHip,
              PoseLandmarkType.leftKnee,
              PoseLandmarkType.leftAnkle,
            ),
            _visAngle(
              lm,
              PoseLandmarkType.rightHip,
              PoseLandmarkType.rightKnee,
              PoseLandmarkType.rightAnkle,
            ),
          ].whereType<double>().toList();
          if (angles.isNotEmpty) {
            final knee = _average(angles);
            final diff = knee - exercise.targetAngle;
            if (diff.abs() > _wallSitFaultDeg) {
              out.add(
                FormIssue(
                  code: FormIssueCode.shortRange,
                  message: diff > 0
                      ? 'Slide lower until your knees are at 90°.'
                      : 'Too low. Rise until your knees are at 90°.',
                  landmarks: {
                    PoseLandmarkType.leftKnee,
                    PoseLandmarkType.rightKnee,
                  },
                  anchor: PoseLandmarkType.leftKnee,
                ),
              );
            }
          }
        }
        break;

      default:
        break;
    }
    return out;
  }

  /// Debounces [_rawFaults] (confirm 150 ms, hold 700 ms) and merges the
  /// result into the checker's own result.
  FormResult _mergeExtraFaults(
    FormResult base,
    Map<PoseLandmarkType, PoseLandmark> lm,
  ) {
    if (base.isNotInFrame) return base;

    final now = DateTime.now().millisecondsSinceEpoch;
    for (final i in _rawFaults(lm)) {
      final last = _xLast[i.code];
      if (last == null || now - last > 300) _xFirst[i.code] = now;
      _xLast[i.code] = now;
      _xIssue[i.code] = i;
    }

    final baseCodes = base.issues.map((i) => i.code).toSet();
    final extra = <FormIssue>[];
    for (final code in _xLast.keys.toList()) {
      final last = _xLast[code]!;
      if (now - last > 700) {
        _xLast.remove(code);
        _xFirst.remove(code);
        _xIssue.remove(code);
        continue;
      }
      if (last - _xFirst[code]! >= 150 && !baseCodes.contains(code)) {
        extra.add(_xIssue[code]!);
      }
    }
    if (extra.isEmpty) return base;

    final all = [...base.issues, ...extra]
      ..sort((a, b) => a.code.index.compareTo(b.code.index));
    return FormResult(FormStatus.wrongForm, all);
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

    double calculatedAngle = 0.0;
    bool isValidRep = false;
    bool isRestPosition = false;

    switch (exercise.type) {
      case ExerciseType.kneeExtension:
        {
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
            isRestPosition = calculatedAngle <= _restAngle;
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

            final shoulderY =
                landmarks[PoseLandmarkType.leftShoulder]?.y ??
                landmarks[PoseLandmarkType.rightShoulder]?.y;
            // Landmark coordinates are PIXELS, so tolerances scale with body.
            final torsoPx = (shoulder != null && hip != null)
                ? _distance(shoulder, hip)
                : 100.0;
            final elbowPinned =
                shoulderY == null || bestElbow.y > shoulderY - 0.15 * torsoPx;
            final wristNearShoulder =
                shoulderY != null && bestWrist.y <= shoulderY + 0.35 * torsoPx;

            isValidRep =
                calculatedAngle <= exercise.targetAngle &&
                elbowPinned &&
                wristNearShoulder;
            isRestPosition = calculatedAngle >= _restAngle;
          }
        }
        break;

      case ExerciseType.sitToStand:
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
            calculatedAngle = _average(angles);
            isValidRep = calculatedAngle >= exercise.targetAngle;
            isRestPosition = calculatedAngle <= exercise.restAngle;
          }
        }
        break;

      case ExerciseType.gluteBridge:
        {
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
    // first part of the range, rather than at the exact rest angle.
    if (calculatedAngle != 0.0) {
      final restA = _restAngle;
      final span = exercise.targetAngle - restA;
      final returnAngle = restA + span * _restZone;
      isRestPosition = span >= 0
          ? calculatedAngle <= returnAngle
          : calculatedAngle >= returnAngle;
    }

    // 0.0 means "couldn't measure" (a needed landmark was missing).
    if (calculatedAngle == 0.0) return;

    // No setState here: _processFrame does a single setState per frame.
    _currentAngle = calculatedAngle;
    _advanceRepCycle(
      angle: calculatedAngle,
      isValidRep: isValidRep,
      isRest: isRestPosition,
      form: form,
      instantFaults: _rawFaults(landmarks),
    );

    _checkCompletion();
  }

  // ---------------------------------------------------------------------
  // Strict rep accounting
  //
  // A rep counts only if the patient (1) leaves rest, (2) reaches the target
  // once, (3) comes back toward rest, AND (4) took long enough, AND (5) no
  // fault was raised along the way (debounced OR instantaneous for more than
  // a couple of frames). Anything else is rejected and scored 0.0.
  //
  // The rep is judged as soon as the joint is _commitProgress of the way
  // back, so the sound/vibration don't wait for a full return to rest.
  // ---------------------------------------------------------------------

  void _advanceRepCycle({
    required double angle,
    required bool isValidRep,
    required bool isRest,
    required FormResult form,
    required List<FormIssue> instantFaults,
  }) {
    // Rep already judged: wait for a full return before the next one starts.
    if (_awaitRest) {
      if (isRest) {
        _awaitRest = false;
        _cancelCycle();
        _learnBaseline(angle);
      }
      return;
    }

    if (isRest) {
      if (_cycleActive) _finishCycle();
      _cancelCycle();
      _learnBaseline(angle);
      return;
    }

    if (!_cycleActive) {
      _cycleActive = true;
      _cycleStart = DateTime.now();
      _cycleViolation = null;
      _cycleFaultIssue = null;
      _cycleFaultFrames = 0;
      _targetHits = 0;
      _atTarget = false;
      _maxProgress = 0.0;
    }

    if (form.isWrong) _cycleViolation ??= form.primary;
    if (instantFaults.isNotEmpty) {
      _cycleFaultFrames++;
      _cycleFaultIssue ??= instantFaults.first;
    }

    final p = _progress(angle);
    _maxProgress = max(_maxProgress, p);

    // Count how many separate times the target was reached (hysteresis so
    // jitter around the target isn't counted as a new hit).
    if (isValidRep) {
      if (!_atTarget) {
        _atTarget = true;
        _targetHits++;
      }
      if (_stage == "down") _stage = "up";
    } else if (p < _reArmProgress) {
      _atTarget = false;
    }

    // Judge the rep on the way back, without waiting for full rest.
    if (_stage == "up" && p <= _commitProgress) {
      _finishCycle();
      _awaitRest = true;
    }
  }

  void _learnBaseline(double angle) {
    _baseline = _baseline == null ? angle : _baseline! * 0.8 + angle * 0.2;
  }

  void _finishCycle() {
    final exercise = widget.selectedExercise;
    final ms = DateTime.now()
        .difference(_cycleStart ?? DateTime.now())
        .inMilliseconds;

    if (_stage == "up") {
      final bad =
          _cycleViolation ??
          (_cycleFaultFrames >= _faultFrameLimit ? _cycleFaultIssue : null);

      if (bad != null) {
        _rejectRep(bad.withMessage('Rep not counted. ${bad.message}'));
      } else if (_targetHits > 1) {
        _rejectRep(
          const FormIssue(
            code: FormIssueCode.tooFast,
            message:
                'Rep not counted. Use one smooth movement, not back and forth.',
            landmarks: <PoseLandmarkType>{},
          ),
        );
      } else if (ms < _minRepMs) {
        _rejectRep(
          const FormIssue(
            code: FormIssueCode.tooFast,
            message: 'Rep not counted. Too fast. Slow down and control it.',
            landmarks: <PoseLandmarkType>{},
          ),
        );
      } else {
        _repCounter++;
        _repScores.add(1.0);
        _triggerFeedback();
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
    _awaitRest = false;
    _cycleStart = null;
    _cycleViolation = null;
    _cycleFaultIssue = null;
    _cycleFaultFrames = 0;
    _targetHits = 0;
    _atTarget = false;
    _maxProgress = 0.0;
    _stage = "down";
  }

  /// 0 = at the patient's resting angle, 1 = at the target angle.
  double _progress(double angle) {
    final e = widget.selectedExercise;
    final base = _baseline ?? _restAngle;
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

    // The clock only runs while the position is right AND form is clean.
    _updateHold(inPosition && !form.isWrong);
  }

  /// Call once per processed frame. State is plain fields; the frame's single
  /// setState in _processFrame repaints them.
  void _updateHold(bool inPosition) {
    final now = DateTime.now();
    final needed = widget.selectedExercise.holdSeconds;

    if (inPosition) {
      _lostSince = null;
      _holdStart ??= now;
      final elapsed = now.difference(_holdStart!).inMilliseconds / 1000.0;
      _holdElapsed = elapsed > needed ? needed.toDouble() : elapsed;

      if (elapsed >= needed && !_holdCounted) {
        _holdCounted = true;
        _repScores.add(1.0);
        _repCounter++;
        _triggerFeedback();
        _checkCompletion();
      }
    } else {
      _lostSince ??= now;
      if (now.difference(_lostSince!) > _holdGrace) {
        if (_holdStart != null || _holdElapsed != 0.0) {
          _holdStart = null;
          _holdCounted = false; // release, then hold again for the next one
          _holdElapsed = 0.0;
        }
      }
    }
  }

  // ---------------------------------------------------------------------
  // Session completion
  // ---------------------------------------------------------------------

  void _checkCompletion() {
    if (_isSessionComplete) return;
    if (_repCounter < widget.selectedExercise.targetReps) return;

    setState(() => _isSessionComplete = true);
    _disposeCamera();
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
              Navigator.of(dialogContext).pop();
              if (mounted) Navigator.of(context).pop();
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
          const ColoredBox(color: Colors.black),
          RepaintBoundary(
            child: _AspectCoverPreview(
              controller: controller,
              cover: _coverPreview,
            ),
          ),

          if (_imageSize != null && _detectedPoses.isNotEmpty)
            RepaintBoundary(
              child: CustomPaint(
                painter: PosePainter(
                  _detectedPoses,
                  _imageSize!,
                  _rotation,
                  isCorrect,
                  _lensDirection,
                  mirrorX: _mirrorSkeleton,
                  cover: _coverPreview,
                  flaggedLandmarks: form.flagged,
                  issues: form.issues,
                ),
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
///  - cover=false: the whole frame is visible (letterboxed).
///  - cover=true : the frame fills the screen and the overflow is cropped.
/// PosePainter uses the identical mapping, so video and skeleton line up.
/// This widget must NOT apply its own horizontal flip.
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
// PosePainter (lives here; lib/widgets/pose_painter.dart is not needed)
// ===========================================================================

class PosePainter extends CustomPainter {
  final List<Pose> poses;

  /// Size of the analysis frame in PIXELS, already rotated to display
  /// orientation (portrait phone => swapped W/H).
  final Size absoluteImageSize;
  final InputImageRotation rotation;
  final bool isExerciseCorrect;
  final CameraLensDirection cameraLensDirection;

  /// Flip landmarks horizontally (true for the front camera).
  final bool mirrorX;

  /// Same choice as the preview: false = whole frame visible, true = crop.
  final bool cover;

  /// Joints the form checker wants highlighted.
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
          if (mirrorX) x = size.width - x;
          points[type] = Offset(x, y);
        }
      });

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

  /// White bubble with a red border and red text, joined to the joint.
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
