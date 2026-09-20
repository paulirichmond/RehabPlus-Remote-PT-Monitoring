import 'package:flutter/foundation.dart';
import 'package:flutter/scheduler.dart';
import 'package:camera/camera.dart';
import 'package:uuid/uuid.dart';
import 'package:provider/provider.dart';
import '../models/exercise.dart';
import '../models/session.dart';
import '../models/pose_data.dart';
import '../services/pose_estimation_service.dart';
import '../services/compliance_tracker.dart';
import '../services/session_storage.dart';

class AppProvider extends ChangeNotifier {
  final SessionStorage _storage = SessionStorage();
  final PoseEstimationService _poseService = PoseEstimationService();
  final _uuid = const Uuid();

  List<CameraDescription> cameras = [];
  List<Exercise> availableExercises = [];
  CameraController? cameraController;
  PoseData? currentPose;
  ComplianceTracker? tracker;
  ExerciseSession? activeSession;
  List<ExerciseSession> sessionHistory = [];
  bool isSessionActive = false;
  bool isCameraInitialized = false;
  String? feedbackMessage;

  Future<void> init() async {
    cameras = await availableCameras();
    await loadHistory();
    loadExercises();
  }

  Future<void> loadHistory() async {
    sessionHistory = await _storage.loadSessions();
    notifyListeners();
  }

  void loadExercises() {
    availableExercises = defaultExercises;
    notifyListeners();
  }

  Future<void> startSession(Exercise exercise) async {
    tracker = ComplianceTracker(exercise: exercise);
    activeSession = ExerciseSession(
      id: _uuid.v4(),
      exerciseId: exercise.id,
      exerciseName: exercise.name,
      startTime: DateTime.now(),
      targetReps: exercise.targetReps,
      targetSets: exercise.targetSets,
    );
    isSessionActive = true;
    await _initCamera();
    notifyListeners();
  }

  Future<void> _initCamera() async {
    if (cameras.isEmpty) return;
    final front = cameras.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => cameras.first,
    );
    cameraController = CameraController(
      front,
      ResolutionPreset.medium,
      enableAudio: false,
    );
    await cameraController!.initialize();
    isCameraInitialized = true;
    cameraController!.startImageStream(_onFrame);
    notifyListeners();
  }

  void _onFrame(CameraImage image) {
    if (!isSessionActive || tracker == null || cameraController == null) return;
    final camera = cameras.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => cameras.first,
    );
    _poseService.processFrame(image, camera).then((pose) {
      if (pose == null) return;
      currentPose = pose;
      final repCompleted = tracker!.processFrame(pose);
      if (repCompleted) {
        final score = tracker!.currentRepScore;
        activeSession!.repComplianceScores = List.from(tracker!.repScores);
        activeSession!.completedReps = tracker!.completedReps;
        activeSession!.completedSets = tracker!.completedSets;
        feedbackMessage = score > 0.8
            ? '✅ Great form!'
            : score > 0.5
            ? '⚠️ Watch your form'
            : '❌ Adjust position';
      }
      SchedulerBinding.instance.addPostFrameCallback((_) => notifyListeners());
    });
  }

  Future<void> endSession() async {
    if (activeSession == null) return;
    activeSession!.endTime = DateTime.now();
    activeSession!.completedReps = tracker?.completedReps ?? 0;
    activeSession!.completedSets = tracker?.completedSets ?? 0;
    activeSession!.repComplianceScores = List.from(tracker?.repScores ?? []);
    await _storage.saveSession(activeSession!);
    await loadHistory();
    await _disposeCamera();
    isSessionActive = false;
    activeSession = null;
    tracker = null;
    currentPose = null;
    feedbackMessage = null;
    notifyListeners();
  }

  /// Persists a session that was tracked outside this provider's own
  /// camera/tracker pipeline (e.g. from PoseDetectorScreen's
  /// ExerciseConfig-based flow) and refreshes sessionHistory so it shows
  /// up in HistoryScreen / ProfileScreen immediately.
  Future<void> recordCompletedSession(ExerciseSession session) async {
    await _storage.saveSession(session);
    await loadHistory();
  }

  Future<void> _disposeCamera() async {
    await cameraController?.stopImageStream();
    await cameraController?.dispose();
    cameraController = null;
    isCameraInitialized = false;
  }

  @override
  void dispose() {
    _disposeCamera();
    _poseService.dispose();
    super.dispose();
  }
}
