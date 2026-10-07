// lib/services/form_checker.dart
//
// Frame-by-frame "is this good form?" judge that sits next to the rep logic
// in PoseDetectorScreen. It never counts reps; it only answers:
//   - is the patient in frame?
//   - is anything moving that should be still (flailing arms, swaying body)?
//   - is the working limb moving too fast / uncontrolled?
//   - is the trunk leaning when the exercise needs an upright back?
//   - exercise-specific faults (other leg not bent, knee bent on a leg raise)
//
// All motion is measured in TORSO LENGTHS (shoulder-mid to hip-mid distance),
// so thresholds don't depend on how far the patient stands from the phone.

import 'dart:collection';
import 'dart:math';
import 'dart:ui' show Offset;

import 'package:google_mlkit_pose_detection/google_mlkit_pose_detection.dart';

import '../models/exercise_model.dart';

// Declared in priority order: lower index is shown first in the banner.
enum FormIssueCode {
  armsMoving,
  legsMoving,
  bodySway,
  trunkLean,
  tooFast,
  kneeBent,
  otherLeg,
  armCircling,
  elbowDrifting,
  shortRange,
  notInFrame,
}

class FormIssue {
  final FormIssueCode code;

  /// Patient-facing text, shown in the banner and in the on-skeleton callout.
  final String message;

  /// Landmarks to highlight in red.
  final Set<PoseLandmarkType> landmarks;

  /// Landmark the callout bubble points at (falls back to any of [landmarks]).
  final PoseLandmarkType? anchor;

  const FormIssue({
    required this.code,
    required this.message,
    required this.landmarks,
    this.anchor,
  });

  FormIssue withMessage(String m) =>
      FormIssue(code: code, message: m, landmarks: landmarks, anchor: anchor);
}

enum FormStatus { ok, wrongForm, notInFrame }

class FormResult {
  final FormStatus status;
  final List<FormIssue> issues;

  const FormResult(this.status, this.issues);

  static const FormResult ok = FormResult(FormStatus.ok, []);

  bool get isOk => status == FormStatus.ok;
  bool get isWrong => status == FormStatus.wrongForm;
  bool get isNotInFrame => status == FormStatus.notInFrame;
  FormIssue? get primary => issues.isEmpty ? null : issues.first;

  Set<PoseLandmarkType> get flagged => {
        for (final i in issues) ...i.landmarks,
      };

  /// Returns a copy with [issue] put first (used for one-off events such as
  /// "rep not counted" that outlive the frame that caused them).
  FormResult plus(FormIssue issue) {
    if (isNotInFrame) return this;
    return FormResult(FormStatus.wrongForm, [
      issue,
      ...issues.where((i) => i.code != issue.code),
    ]);
  }
}

/// All tunable numbers in one place. [strict] is what the app uses; loosen
/// via [standard] (or build your own) after testing with real patients.
class FormThresholds {
  /// Landmarks below this ML Kit confidence are ignored.
  final double minLikelihood;

  /// Max wander (in torso lengths, over [window]) for limbs that should be still.
  final double stationaryRange;

  /// Max wander for the shoulders/hips when the body should stay put.
  final double torsoSwayRange;

  /// Max speed (torso lengths per second) for limbs that are meant to move.
  final double maxWorkingSpeed;

  /// Max trunk lean from vertical, in degrees, for upright exercises.
  final double maxTrunkLeanDeg;

  /// How much recent history is used for the wander measurement.
  final Duration window;

  /// A fault must persist this long before it's reported (kills flicker).
  final Duration confirm;

  /// A reported fault stays on screen this long after it stops.
  final Duration hold;

  /// Fraction of the rest->target movement that counts as an honest attempt.
  /// Used by the screen to detect partial reps.
  final double attemptProgress;

  const FormThresholds({
    required this.minLikelihood,
    required this.stationaryRange,
    required this.torsoSwayRange,
    required this.maxWorkingSpeed,
    required this.maxTrunkLeanDeg,
    required this.window,
    required this.confirm,
    required this.hold,
    required this.attemptProgress,
  });

  static const FormThresholds strict = FormThresholds(
    minLikelihood: 0.5,
    stationaryRange: 0.25,
    torsoSwayRange: 0.15,
    maxWorkingSpeed: 2.2,
    maxTrunkLeanDeg: 20,
    window: Duration(milliseconds: 600),
    confirm: Duration(milliseconds: 120),
    hold: Duration(milliseconds: 700),
    attemptProgress: 0.3,
  );

  static const FormThresholds standard = FormThresholds(
    minLikelihood: 0.5,
    stationaryRange: 0.40,
    torsoSwayRange: 0.25,
    maxWorkingSpeed: 3.5,
    maxTrunkLeanDeg: 30,
    window: Duration(milliseconds: 600),
    confirm: Duration(milliseconds: 200),
    hold: Duration(milliseconds: 700),
    attemptProgress: 0.4,
  );
}

// ---------------------------------------------------------------------------
// Landmark groups
// ---------------------------------------------------------------------------

final Set<PoseLandmarkType> _shoulders = {
  PoseLandmarkType.leftShoulder,
  PoseLandmarkType.rightShoulder,
};
final Set<PoseLandmarkType> _hips = {
  PoseLandmarkType.leftHip,
  PoseLandmarkType.rightHip,
};
final Set<PoseLandmarkType> _elbows = {
  PoseLandmarkType.leftElbow,
  PoseLandmarkType.rightElbow,
};
final Set<PoseLandmarkType> _wrists = {
  PoseLandmarkType.leftWrist,
  PoseLandmarkType.rightWrist,
};
final Set<PoseLandmarkType> _knees = {
  PoseLandmarkType.leftKnee,
  PoseLandmarkType.rightKnee,
};
final Set<PoseLandmarkType> _ankles = {
  PoseLandmarkType.leftAnkle,
  PoseLandmarkType.rightAnkle,
};
final Set<PoseLandmarkType> _arms = {..._elbows, ..._wrists};
final Set<PoseLandmarkType> _legs = {..._knees, ..._ankles};
final Set<PoseLandmarkType> _torso = {..._shoulders, ..._hips};

/// What each exercise expects from the body besides the working joint.
class _Profile {
  /// Limbs that must stay put (checked by how far they wander).
  final Set<PoseLandmarkType> stationary;

  /// Limbs that are supposed to move (checked by speed only).
  final Set<PoseLandmarkType> working;

  /// Shoulders/hips must not sway around.
  final bool torsoStill;

  /// Back must stay upright (seated / standing exercises).
  final bool upright;

  /// Which limbs must be in frame before tracking starts.
  final bool needsLegs;
  final bool needsArms;

  /// When true (arm exercises), one complete left OR right arm chain is
  /// enough to start tracking. A side-on phone routinely loses the far arm,
  /// and demanding both sides paired kept the app stuck on "notInFrame" —
  /// which froze the form state (stuck green) instead of updating it.
  final bool armsPairwise;

  _Profile({
    required this.stationary,
    required this.working,
    required this.torsoStill,
    required this.upright,
    this.needsLegs = false,
    this.needsArms = false,
    this.armsPairwise = false,
  });
}

_Profile _profileFor(ExerciseType type) {
  switch (type) {
    case ExerciseType.kneeExtension: // seated
      return _Profile(
        stationary: _arms,
        working: _legs,
        torsoStill: true,
        upright: true,
        needsLegs: true,
      );

    // Lying-down leg work. Flip `upright` to true for kneeFlexion if your
    // clinic performs it standing (hamstring curl) instead of lying prone.
    case ExerciseType.straightLegRaise:
    case ExerciseType.heelSlide:
    case ExerciseType.kneeFlexion:
      return _Profile(
        stationary: _arms,
        working: _legs,
        torsoStill: true,
        upright: false,
        needsLegs: true,
      );

    case ExerciseType.sitToStand:
    case ExerciseType.gluteBridge:
      // The torso legitimately travels in these two.
      return _Profile(
        stationary: _arms,
        working: _legs,
        torsoStill: false,
        upright: false,
        needsLegs: true,
      );

    case ExerciseType.bicepCurl:
      return _Profile(
        // Elbows must stay pinned at the sides — that's what separates a
        // real curl from arm circles. The wrist is the travelling joint.
        stationary: {..._elbows, ..._legs},
        working: _wrists,
        torsoStill: true,
        upright: true,
        needsArms: true,
        armsPairwise: true,
      );

    case ExerciseType.forwardRaise:
    case ExerciseType.sideRaise:
    case ExerciseType.forwardPush:
      return _Profile(
        stationary: _legs, // feet stay planted
        working: _arms,
        torsoStill: true,
        upright: true,
        needsArms: true,
        armsPairwise: true,
      );

    case ExerciseType.singleLegBalance:
      return _Profile(
        stationary: _arms,
        working: _legs,
        torsoStill: true,
        upright: true,
        needsLegs: true,
      );

    case ExerciseType.wallSit:
      return _Profile(
        stationary: {..._arms, ..._legs},
        working: <PoseLandmarkType>{},
        torsoStill: true,
        upright: true,
        needsLegs: true,
      );
  }
}

class _Sample {
  final int t; // ms since epoch
  final Map<PoseLandmarkType, Offset> points;
  _Sample(this.t, this.points);
}

class FormChecker {
  final ExerciseConfig exercise;
  final FormThresholds th;
  final _Profile _profile;

  FormChecker(
    this.exercise, {
    FormThresholds thresholds = FormThresholds.strict,
  })  : th = thresholds,
        _profile = _profileFor(exercise.type);

  static const double _smoothing = 0.6; // EMA weight of the newest frame
  static const int _gapMs = 300; // a fault that vanishes this long restarts

  final Queue<_Sample> _history = Queue<_Sample>();
  final Map<PoseLandmarkType, Offset> _smooth = {};
  double? _torsoLen;
  int _now = 0;

  final Map<FormIssueCode, int> _firstSeen = {};
  final Map<FormIssueCode, int> _lastSeen = {};
  final Map<FormIssueCode, FormIssue> _latest = {};

  static final FormIssue _notInFrameIssue = FormIssue(
    code: FormIssueCode.notInFrame,
    message: 'Please step into the frame and center your body to begin tracking.',
    landmarks: <PoseLandmarkType>{},
  );

  FormResult get _notInFrame =>
      FormResult(FormStatus.notInFrame, [_notInFrameIssue]);

  void reset() {
    _history.clear();
    _smooth.clear();
    _torsoLen = null;
    _firstSeen.clear();
    _lastSeen.clear();
    _latest.clear();
  }

  /// Call when ML Kit found nobody in the frame.
  FormResult evaluateNoPose() {
    reset();
    return _notInFrame;
  }

  FormResult evaluate(
    Map<PoseLandmarkType, PoseLandmark> lm, {
    DateTime? now,
  }) {
    _now = (now ?? DateTime.now()).millisecondsSinceEpoch;

    if (!_requiredVisible(lm)) {
      reset();
      return _notInFrame;
    }

    _ingest(lm);

    final raw = <FormIssue>[];
    final torso = _torsoLen;

    if (torso != null) {
      _checkStationary(raw, torso);
      _checkTorsoSway(raw, torso);
      _checkSpeed(raw, torso);
    }
    _checkTrunkLean(raw);
    _checkExerciseSpecific(raw, lm);

    return _stabilise(raw);
  }

  // -------------------------------------------------------------------------
  // Visibility
  // -------------------------------------------------------------------------

  bool _ok(PoseLandmark? p) => p != null && p.likelihood >= th.minLikelihood;

  bool _requiredVisible(Map<PoseLandmarkType, PoseLandmark> lm) {
    // At least one side of each pair must be visible (a side-on camera
    // usually hides the far limb).
    bool pair(PoseLandmarkType l, PoseLandmarkType r) =>
        _ok(lm[l]) || _ok(lm[r]);

    if (!pair(PoseLandmarkType.leftShoulder, PoseLandmarkType.rightShoulder) ||
        !pair(PoseLandmarkType.leftHip, PoseLandmarkType.rightHip)) {
      return false;
    }
    if (_profile.needsLegs &&
        !(pair(PoseLandmarkType.leftKnee, PoseLandmarkType.rightKnee) &&
            pair(PoseLandmarkType.leftAnkle, PoseLandmarkType.rightAnkle))) {
      return false;
    }
    if (_profile.needsArms) {
      if (_profile.armsPairwise) {
        // Arm exercises: one complete same-side chain (elbow + wrist) is
        // enough. The old paired check demanded BOTH elbows AND both
        // wrists, which failed constantly with a front camera and left the
        // app stuck reporting "notInFrame" instead of judging the form.
        final leftArm =
            _ok(lm[PoseLandmarkType.leftElbow]) &&
            _ok(lm[PoseLandmarkType.leftWrist]);
        final rightArm =
            _ok(lm[PoseLandmarkType.rightElbow]) &&
            _ok(lm[PoseLandmarkType.rightWrist]);
        if (!leftArm && !rightArm) return false;
      } else if (!(pair(PoseLandmarkType.leftElbow, PoseLandmarkType.rightElbow) &&
          pair(PoseLandmarkType.leftWrist, PoseLandmarkType.rightWrist))) {
        return false;
      }
    }
    return true;
  }

  // -------------------------------------------------------------------------
  // History / smoothing
  // -------------------------------------------------------------------------

  void _ingest(Map<PoseLandmarkType, PoseLandmark> lm) {
    final sample = <PoseLandmarkType, Offset>{};
    for (final e in lm.entries) {
      if (!_ok(e.value)) {
        _smooth.remove(e.key);
        continue;
      }
      final raw = Offset(e.value.x, e.value.y);
      final prev = _smooth[e.key];
      final s = prev == null ? raw : prev + (raw - prev) * _smoothing;
      _smooth[e.key] = s;
      sample[e.key] = s;
    }
    _history.add(_Sample(_now, sample));

    final cutoff = _now - th.window.inMilliseconds - 300;
    while (_history.isNotEmpty && _history.first.t < cutoff) {
      _history.removeFirst();
    }

    final s = _meanOf(_shoulders);
    final h = _meanOf(_hips);
    if (s != null && h != null) {
      final len = (s - h).distance;
      if (len > 20) {
        // slow EMA so one noisy frame can't change the yardstick
        _torsoLen = _torsoLen == null ? len : _torsoLen! * 0.9 + len * 0.1;
      }
    }
  }

  Offset? _meanOf(Iterable<PoseLandmarkType> types) {
    var sum = Offset.zero;
    var n = 0;
    for (final t in types) {
      final p = _smooth[t];
      if (p != null) {
        sum += p;
        n++;
      }
    }
    return n == 0 ? null : sum / n.toDouble();
  }

  /// How far [type] wandered inside the window, in torso lengths.
  double? _range(PoseLandmarkType type, double torso) {
    final cutoff = _now - th.window.inMilliseconds;
    double? minX, maxX, minY, maxY;
    var n = 0;
    for (final s in _history) {
      if (s.t < cutoff) continue;
      final p = s.points[type];
      if (p == null) continue;
      minX = minX == null ? p.dx : min(minX, p.dx);
      maxX = maxX == null ? p.dx : max(maxX, p.dx);
      minY = minY == null ? p.dy : min(minY, p.dy);
      maxY = maxY == null ? p.dy : max(maxY, p.dy);
      n++;
    }
    if (n < 4) return null; // not enough evidence yet
    return Offset(maxX! - minX!, maxY! - minY!).distance / torso;
  }

  /// Speed of [type] over roughly the last 150-400 ms, in torso lengths / s.
  double? _speed(PoseLandmarkType type, double torso) {
    if (_history.isEmpty) return null;
    final newest = _history.last.points[type];
    if (newest == null) return null;
    for (final s in _history) {
      final dt = _now - s.t;
      final p = s.points[type];
      if (dt >= 150 && dt <= 400 && p != null) {
        return (newest - p).distance / torso / (dt / 1000.0);
      }
    }
    return null;
  }

  // -------------------------------------------------------------------------
  // Checks
  // -------------------------------------------------------------------------

  PoseLandmarkType _firstOf(Set<PoseLandmarkType> set, [PoseLandmarkType? prefer]) {
    if (prefer != null && set.contains(prefer)) return prefer;
    return set.first;
  }

  /// Limbs that should stay put but wander (flailing arms, stepping feet).
  void _checkStationary(List<FormIssue> out, double torso) {
    final armsBad = <PoseLandmarkType>{};
    final legsBad = <PoseLandmarkType>{};
    for (final t in _profile.stationary) {
      final r = _range(t, torso);
      if (r == null || r <= th.stationaryRange) continue;
      (_arms.contains(t) ? armsBad : legsBad).add(t);
    }
    if (armsBad.isNotEmpty) {
      out.add(FormIssue(
        code: FormIssueCode.armsMoving,
        message: 'Arms are moving too much. Keep them still.',
        landmarks: armsBad,
        anchor: _firstOf(armsBad, PoseLandmarkType.rightWrist),
      ));
    }
    if (legsBad.isNotEmpty) {
      out.add(FormIssue(
        code: FormIssueCode.legsMoving,
        message: 'Keep your feet and legs still.',
        landmarks: legsBad,
        anchor: _firstOf(legsBad, PoseLandmarkType.rightAnkle),
      ));
    }
  }

  void _checkTorsoSway(List<FormIssue> out, double torso) {
    if (!_profile.torsoStill) return;
    final ranges = <double>[];
    for (final t in _torso) {
      final r = _range(t, torso);
      if (r != null) ranges.add(r);
    }
    if (ranges.length < 2) return;
    ranges.sort();
    // Median, so one jittery landmark can't trigger it on its own.
    final median = ranges[(ranges.length - 1) ~/ 2];
    if (median > th.torsoSwayRange) {
      out.add(FormIssue(
        code: FormIssueCode.bodySway,
        message: 'Body is swaying. Stay steady.',
        landmarks: Set.of(_torso),
        anchor: _smooth.containsKey(PoseLandmarkType.leftShoulder)
            ? PoseLandmarkType.leftShoulder
            : PoseLandmarkType.rightShoulder,
      ));
    }
  }

  void _checkSpeed(List<FormIssue> out, double torso) {
    final fast = <PoseLandmarkType>{};
    for (final t in _profile.working) {
      final v = _speed(t, torso);
      if (v != null && v > th.maxWorkingSpeed) fast.add(t);
    }
    if (fast.isNotEmpty) {
      out.add(FormIssue(
        code: FormIssueCode.tooFast,
        message: 'Too fast. Slow down and control the movement.',
        landmarks: fast,
        anchor: fast.first,
      ));
    }
  }

  void _checkTrunkLean(List<FormIssue> out) {
    if (!_profile.upright) return;
    final s = _meanOf(_shoulders);
    final h = _meanOf(_hips);
    if (s == null || h == null) return;
    final d = s - h; // image y grows downward, so "up" is negative dy
    final lean = atan2(d.dx.abs(), -d.dy) * 180 / pi;
    if (lean > th.maxTrunkLeanDeg) {
      out.add(FormIssue(
        code: FormIssueCode.trunkLean,
        message: 'Your back is bent. Straighten your body.',
        landmarks: Set.of(_torso),
        anchor: _smooth.containsKey(PoseLandmarkType.leftShoulder)
            ? PoseLandmarkType.leftShoulder
            : PoseLandmarkType.rightShoulder,
      ));
    }
  }

  double? _angle(
    Map<PoseLandmarkType, PoseLandmark> lm,
    PoseLandmarkType a,
    PoseLandmarkType b,
    PoseLandmarkType c,
  ) {
    final p1 = lm[a], p2 = lm[b], p3 = lm[c];
    if (!_ok(p1) || !_ok(p2) || !_ok(p3)) return null;
    final rad = atan2(p3!.y - p2!.y, p3.x - p2.x) -
        atan2(p1!.y - p2.y, p1.x - p2.x);
    var deg = (rad * 180 / pi).abs();
    if (deg > 180) deg = 360 - deg;
    return deg;
  }

  void _checkExerciseSpecific(
    List<FormIssue> out,
    Map<PoseLandmarkType, PoseLandmark> lm,
  ) {
    switch (exercise.type) {
      case ExerciseType.bicepCurl:
        // Catch the "arm circles instead of curls" cheat directly. A curl
        // takes the wrist UP toward the shoulder in a vertical arc with the
        // elbow pinned at the side; circling sweeps the wrist through a wide
        // horizontal loop that drags the elbow away from the torso.
        final sh = _meanOf(_shoulders);
        if (sh != null) {
          for (final side in const [
            (PoseLandmarkType.leftElbow, PoseLandmarkType.leftWrist),
            (PoseLandmarkType.rightElbow, PoseLandmarkType.rightWrist),
          ]) {
            final elbow = _smooth[side.$1];
            final wrist = _smooth[side.$2];
            if (elbow == null || wrist == null) continue;

            final elbowRange = _range(side.$1, _torsoLen ?? 1.0);
            final wristRange = _range(side.$2, _torsoLen ?? 1.0);
            final torso = _torsoLen;
            if (elbowRange == null || wristRange == null || torso == null) {
              continue;
            }

            // Elbow wandered far from its spot while the arm was "active".
            if (elbowRange > th.stationaryRange * 0.8 &&
                wristRange > th.stationaryRange) {
              out.add(FormIssue(
                code: FormIssueCode.armCircling,
                message: 'That is an arm circle, not a curl. Keep your elbow '
                    'at your side and fold your forearm up.',
                landmarks: {side.$1, side.$2},
                anchor: side.$1,
              ));
            } else if (elbowRange > th.stationaryRange) {
              out.add(FormIssue(
                code: FormIssueCode.elbowDrifting,
                message: 'Elbow is drifting. Pin it to your side.',
                landmarks: {side.$1},
                anchor: side.$1,
              ));
            }

            // Wrist must rise ABOVE the elbow during the rep (curling up),
            // never orbit around it horizontally.
            final wristAboveElbow = wrist.dy < elbow.dy - 0.05 * torso;
            final nearShoulderHeight = (wrist - sh).distance < 0.45 * torso;
            if (wristRange > th.stationaryRange &&
                !wristAboveElbow &&
                !nearShoulderHeight) {
              out.add(FormIssue(
                code: FormIssueCode.armCircling,
                message: 'Curl straight up toward your shoulder, '
                    'do not swing in circles.',
                landmarks: {side.$2},
                anchor: side.$2,
              ));
            }
          }
        }
        break;

      case ExerciseType.kneeExtension:
        final l = _angle(lm, PoseLandmarkType.leftHip, PoseLandmarkType.leftKnee,
            PoseLandmarkType.leftAnkle);
        final r = _angle(lm, PoseLandmarkType.rightHip,
            PoseLandmarkType.rightKnee, PoseLandmarkType.rightAnkle);
        if (l != null && r != null) {
          final working = max(l, r);
          final resting = min(l, r);
          // Both legs straightened = cheating the rep.
          if (working >= exercise.targetAngle && resting >= 120) {
            final leftIsResting = l < r;
            out.add(FormIssue(
              code: FormIssueCode.otherLeg,
              message: 'Keep your other leg bent and still.',
              landmarks: {
                leftIsResting
                    ? PoseLandmarkType.leftKnee
                    : PoseLandmarkType.rightKnee,
                leftIsResting
                    ? PoseLandmarkType.leftAnkle
                    : PoseLandmarkType.rightAnkle,
              },
              anchor: leftIsResting
                  ? PoseLandmarkType.leftKnee
                  : PoseLandmarkType.rightKnee,
            ));
          }
        }
        break;

      case ExerciseType.straightLegRaise:
        // Raised leg must stay straight.
        for (final side in const [true, false]) {
          final sh = side ? PoseLandmarkType.leftShoulder : PoseLandmarkType.rightShoulder;
          final hp = side ? PoseLandmarkType.leftHip : PoseLandmarkType.rightHip;
          final kn = side ? PoseLandmarkType.leftKnee : PoseLandmarkType.rightKnee;
          final an = side ? PoseLandmarkType.leftAnkle : PoseLandmarkType.rightAnkle;
          final hipAngle = _angle(lm, sh, hp, kn);
          final kneeAngle = _angle(lm, hp, kn, an);
          if (hipAngle == null || kneeAngle == null) continue;
          final lifted = hipAngle < exercise.restAngle - 10;
          if (lifted && kneeAngle < 150) {
            out.add(FormIssue(
              code: FormIssueCode.kneeBent,
              message: 'Keep your knee straight.',
              landmarks: {kn, an},
              anchor: kn,
            ));
          }
          break; // first side with a full chain is enough
        }
        break;

      default:
        break;
    }
  }

  // -------------------------------------------------------------------------
  // Debounce: confirm faults before showing, hold them briefly after
  // -------------------------------------------------------------------------

  FormResult _stabilise(List<FormIssue> raw) {
    for (final i in raw) {
      final last = _lastSeen[i.code];
      if (last == null || _now - last > _gapMs) _firstSeen[i.code] = _now;
      _lastSeen[i.code] = _now;
      _latest[i.code] = i;
    }

    final holdMs = th.hold.inMilliseconds;
    final confirmMs = th.confirm.inMilliseconds;
    final active = <FormIssue>[];

    for (final code in _lastSeen.keys.toList()) {
      final last = _lastSeen[code]!;
      if (_now - last > holdMs) {
        _lastSeen.remove(code);
        _firstSeen.remove(code);
        _latest.remove(code);
        continue;
      }
      if (last - _firstSeen[code]! >= confirmMs) {
        active.add(_latest[code]!);
      }
    }

    if (active.isEmpty) return FormResult.ok;
    active.sort((a, b) => a.code.index.compareTo(b.code.index));
    return FormResult(FormStatus.wrongForm, active);
  }
}
