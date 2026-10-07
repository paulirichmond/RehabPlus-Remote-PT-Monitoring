// lib/screens/exercise_page.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/exercise_model.dart';
import '../services/app_provider.dart';
import 'pose_detector_screen.dart';

class ExercisePage extends StatefulWidget {
  const ExercisePage({super.key});

  @override
  State<ExercisePage> createState() => _ExercisePageState();
}

class _ExercisePageState extends State<ExercisePage> {
  static const Color primaryTeal = Color(0xFF23A8AA);
  static const Color lightTealBg = Color(0xFFE8F7F7);

  late Future<List<ExerciseConfig>> _exercisesFuture;

  @override
  void initState() {
    super.initState();
    _exercisesFuture = _loadAssignedExercises();
  }

  // TODO: this was wired to a therapist-assigned plan via Firestore
  // (PlanService/AuthService) — reverted to the static list for now. See
  // plan_service.dart/auth_service.dart if you want to reconnect it later.
  Future<List<ExerciseConfig>> _loadAssignedExercises() async {
    return availableExercises;
  }

  Future<void> _refresh() async {
    setState(() => _exercisesFuture = _loadAssignedExercises());
    await _exercisesFuture;
  }

  // How close the patient is to a default goal of 3x/week for this
  // exercise, based on sessions actually saved in the last 7 days.
  // NOTE: uses a fixed assumption of 3x/week rather than the plan's real
  // frequencyPerWeek — wire that through if you want it exact per plan.
  double _weeklyProgress(AppProvider provider, ExerciseConfig config) {
    final weekAgo = DateTime.now().subtract(const Duration(days: 7));
    final count = provider.sessionHistory
        .where((s) => s.exerciseId == config.type.name && s.startTime.isAfter(weekAgo))
        .length;
    const assumedWeeklyGoal = 3;
    return (count / assumedWeeklyGoal).clamp(0.0, 1.0);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      // No bottomNavigationBar here: DashboardScreen already has one.
      body: Stack(
        children: [
          // Background decorative teal circles
          Positioned(
            top: -100,
            left: -80,
            child: Container(
              width: 300,
              height: 300,
              decoration: const BoxDecoration(
                color: lightTealBg,
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            bottom: -60,
            right: -80,
            child: Container(
              width: 280,
              height: 280,
              decoration: const BoxDecoration(
                color: lightTealBg,
                shape: BoxShape.circle,
              ),
            ),
          ),

          SafeArea(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 8),

                // Top Bar Header
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20.0),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Text(
                            'Rehab',
                            style: TextStyle(
                              fontSize: 28,
                              fontWeight: FontWeight.bold,
                              color: primaryTeal,
                            ),
                          ),
                          const SizedBox(width: 4),
                          IconButton(
                            onPressed: () {},
                            icon: const Icon(
                              Icons.add,
                              color: primaryTeal,
                              size: 28,
                            ),
                          ),
                        ],
                      ),
                      Row(
                        children: [
                          _buildTopCircleButton(Icons.mail_rounded, true),
                          const SizedBox(width: 10),
                          _buildTopCircleButton(
                            Icons.assignment_turned_in_rounded,
                            false,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),

                // Date & Subtitle
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: const [
                      Text(
                        'SUNDAY, 17 MAY',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: Colors.black54,
                          letterSpacing: 0.5,
                        ),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'Select an\nExercise',
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                          height: 1.15,
                          color: Colors.black,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),

                // Exercise list, driven by the patient's assigned plan.
                Expanded(
                  child: FutureBuilder<List<ExerciseConfig>>(
                    future: _exercisesFuture,
                    builder: (context, snapshot) {
                      if (snapshot.connectionState == ConnectionState.waiting) {
                        return const Center(
                          child: CircularProgressIndicator(color: primaryTeal),
                        );
                      }

                      if (snapshot.hasError) {
                        return _buildMessage(
                          icon: Icons.wifi_off_rounded,
                          text: "Couldn't load your plan. Check your connection and try again.",
                          onRetry: _refresh,
                        );
                      }

                      final exercises = snapshot.data ?? const [];
                      if (exercises.isEmpty) {
                        return _buildMessage(
                          icon: Icons.event_note_rounded,
                          text: 'No therapy plan assigned yet.\nCheck back once your therapist sets one up.',
                          onRetry: _refresh,
                        );
                      }

                      return RefreshIndicator(
                        color: primaryTeal,
                        onRefresh: _refresh,
                        child: Consumer<AppProvider>(
                          builder: (context, provider, _) => ListView.builder(
                            physics: const BouncingScrollPhysics(
                              parent: AlwaysScrollableScrollPhysics(),
                            ),
                            padding: const EdgeInsets.symmetric(
                              horizontal: 20,
                              vertical: 8,
                            ),
                            itemCount: exercises.length,
                            itemBuilder: (context, index) {
                              return AnimatedExerciseCard(
                                index: index,
                                config: exercises[index],
                                progress: _weeklyProgress(provider, exercises[index]),
                              );
                            },
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMessage({
    required IconData icon,
    required String text,
    required VoidCallback onRetry,
  }) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: lightTealBg, shape: BoxShape.circle),
              child: Icon(icon, size: 36, color: primaryTeal),
            ),
            const SizedBox(height: 16),
            Text(
              text,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 14, color: Colors.black54, height: 1.4),
            ),
            const SizedBox(height: 16),
            TextButton(
              onPressed: onRetry,
              child: const Text('Refresh', style: TextStyle(color: primaryTeal, fontWeight: FontWeight.w600)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTopCircleButton(IconData icon, bool hasBadge) {
    return Stack(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: primaryTeal.withValues(alpha: 0.85),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: Colors.white, size: 20),
        ),
        if (hasBadge)
          Positioned(
            right: 4,
            top: 4,
            child: Container(
              width: 8,
              height: 8,
              decoration: const BoxDecoration(
                color: Colors.redAccent,
                shape: BoxShape.circle,
              ),
            ),
          ),
      ],
    );
  }
}

class AnimatedExerciseCard extends StatefulWidget {
  final int index;
  final ExerciseConfig config;

  /// 0.0–1.0 progress toward this exercise's default weekly goal.
  final double progress;

  const AnimatedExerciseCard({
    super.key,
    required this.index,
    required this.config,
    required this.progress,
  });

  @override
  State<AnimatedExerciseCard> createState() => _AnimatedExerciseCardState();
}

class _AnimatedExerciseCardState extends State<AnimatedExerciseCard>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _fadeAnimation;
  late Animation<Offset> _slideAnimation;

  double _scale = 1.0;

  @override
  void initState() {
    super.initState();

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 500),
    );

    _fadeAnimation = Tween<double>(
      begin: 0.0,
      end: 1.0,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOut));

    _slideAnimation = Tween<Offset>(
      begin: const Offset(0, 0.2),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));

    Future.delayed(Duration(milliseconds: widget.index * 120), () {
      if (mounted) {
        _controller.forward();
      }
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  // Icon + accent color per exercise category. Hold exercises always get
  // the timer icon regardless of category, since "how long" matters more
  // than "which body part" at a glance.
  ({Color accent, IconData icon}) get _visuals {
    if (widget.config.isHold) {
      return (accent: const Color(0xFFE91E63), icon: Icons.timer_rounded);
    }
    switch (widget.config.category) {
      case 'MCL Recovery':
        return (accent: const Color(0xFF00ACC1), icon: Icons.swap_vert_rounded);
      case 'Lower Body':
        return (accent: const Color(0xFFFF7043), icon: Icons.directions_walk_rounded);
      case 'Core & Hips':
        return (accent: const Color(0xFF7E57C2), icon: Icons.self_improvement_rounded);
      case 'Arm Rehabilitation':
        return (accent: const Color(0xFF42A5F5), icon: Icons.fitness_center_rounded);
      default:
        return (accent: const Color(0xFF23A8AA), icon: Icons.accessibility_new_rounded);
    }
  }

  ({Color bg, Color fg}) get _difficultyColors {
    switch (widget.config.difficulty) {
      case ExerciseDifficulty.easy:
        return (bg: const Color(0xFFE2F3D3), fg: const Color(0xFF558B2F));
      case ExerciseDifficulty.moderate:
        return (bg: const Color(0xFFFFF3D6), fg: const Color(0xFFF9A825));
      case ExerciseDifficulty.hard:
        return (bg: const Color(0xFFFFE0E0), fg: const Color(0xFFD32F2F));
    }
  }

  void _startExercise() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => PoseDetectorScreen(selectedExercise: widget.config),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final config = widget.config;
    final visuals = _visuals;
    final diff = _difficultyColors;

    return FadeTransition(
      opacity: _fadeAnimation,
      child: SlideTransition(
        position: _slideAnimation,
        child: GestureDetector(
          onTapDown: (_) => setState(() => _scale = 0.97),
          onTapUp: (_) => setState(() => _scale = 1.0),
          onTapCancel: () => setState(() => _scale = 1.0),
          onTap: _startExercise,
          child: AnimatedScale(
            scale: _scale,
            duration: const Duration(milliseconds: 150),
            curve: Curves.easeOut,
            child: Container(
              margin: const EdgeInsets.only(bottom: 16),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(22),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 52,
                        height: 52,
                        decoration: BoxDecoration(
                          color: visuals.accent.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Icon(visuals.icon, color: visuals.accent, size: 26),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              config.title,
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color: Colors.black87,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Row(
                              children: [
                                Icon(Icons.schedule_rounded, size: 13, color: Colors.grey.shade500),
                                const SizedBox(width: 4),
                                Text(
                                  '${config.estimatedMinutes} min',
                                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                                ),
                                const SizedBox(width: 12),
                                Icon(Icons.repeat_rounded, size: 13, color: Colors.grey.shade500),
                                const SizedBox(width: 4),
                                Expanded(
                                  child: Text(
                                    config.repsText,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: diff.bg,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              config.difficulty.label,
                              style: TextStyle(color: diff.fg, fontSize: 11, fontWeight: FontWeight.w700),
                            ),
                          ),
                          const SizedBox(height: 10),
                          GestureDetector(
                            onTap: _startExercise,
                            child: Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(color: visuals.accent, shape: BoxShape.circle),
                              child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 20),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: LinearProgressIndicator(
                      value: widget.progress,
                      minHeight: 6,
                      backgroundColor: visuals.accent.withValues(alpha: 0.12),
                      valueColor: AlwaysStoppedAnimation(visuals.accent),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
