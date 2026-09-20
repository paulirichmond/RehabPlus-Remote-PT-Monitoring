import 'package:flutter/material.dart';
import 'package:phosphor_flutter/phosphor_flutter.dart';
import 'package:video_player/video_player.dart';
import 'package:chewie/chewie.dart';

class DemoScreen extends StatefulWidget {
  const DemoScreen({super.key});

  @override
  State<DemoScreen> createState() => _DemoScreenState();
}

class _DemoScreenState extends State<DemoScreen> with TickerProviderStateMixin {
  static const Color primaryTeal = Color(0xFF23A8AA);
  static const Color darkTeal = Color(0xFF0D6E71);

  final PageController _pageCtrl = PageController();
  int _currentPage = 0;

  late final AnimationController _headerCtrl;
  late final AnimationController _contentCtrl;
  late final Animation<double> _headerFade;
  late final Animation<Offset> _headerSlide;
  late final Animation<double> _contentFade;
  late final Animation<Offset> _contentSlide;

  // Active exercise demo state
  int _activeStep = 0;
  bool _isPlaying = false;
  late final AnimationController _pulseCtrl;
  late final Animation<double> _pulse;

  final List<_DemoFeature> _features = [
    _DemoFeature(
      icon: PhosphorIconsRegular.chartLineUp,
      color: Color(0xFF23A8AA),
      title: 'Track Progress',
      subtitle: 'Monitor your weekly recovery with visual charts and day-by-day check-ins.',
      preview: _PreviewType.progress,
    ),
    _DemoFeature(
      icon: PhosphorIconsRegular.barbell,
      color: Color(0xFF4A90D9),
      title: 'Exercise Plans',
      subtitle: 'Follow guided rehab exercises assigned by your therapist with step-by-step instructions.',
      preview: _PreviewType.exercise,
    ),
    _DemoFeature(
      icon: PhosphorIconsRegular.chatCircle,
      color: Color(0xFF7C4DFF),
      title: 'Message Therapist',
      subtitle: 'Stay connected with your therapist through real-time messaging and quick updates.',
      preview: _PreviewType.messages,
    ),
    _DemoFeature(
      icon: PhosphorIconsRegular.bell,
      color: Color(0xFFFF6B35),
      title: 'Smart Reminders',
      subtitle: 'Never miss a session with personalized notifications and progress alerts.',
      preview: _PreviewType.notifications,
    ),
  ];

  final List<_ExerciseStep> _steps = [
    _ExerciseStep('Starting Position', 'Stand straight, feet shoulder-width apart. Keep your back neutral.', 5),
    _ExerciseStep('Bend Knees', 'Slowly lower your body by bending both knees to 90°. Hold for 2 seconds.', 8),
    _ExerciseStep('Extend Leg', 'Straighten your right leg fully. Feel the quad engage. Hold 3 seconds.', 6),
    _ExerciseStep('Return', 'Slowly return to starting position. Breathe out as you come up.', 5),
  ];

  @override
  void initState() {
    super.initState();
    _headerCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _contentCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 800));
    _pulseCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1000))
      ..repeat(reverse: true);

    _headerFade = CurvedAnimation(parent: _headerCtrl, curve: Curves.easeOut);
    _headerSlide = Tween<Offset>(begin: const Offset(0, -0.2), end: Offset.zero)
        .animate(CurvedAnimation(parent: _headerCtrl, curve: Curves.easeOutCubic));
    _contentFade = CurvedAnimation(parent: _contentCtrl, curve: Curves.easeOut);
    _contentSlide = Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero)
        .animate(CurvedAnimation(parent: _contentCtrl, curve: Curves.easeOutCubic));
    _pulse = Tween<double>(begin: 1.0, end: 1.08)
        .animate(CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut));

    _headerCtrl.forward();
    Future.delayed(const Duration(milliseconds: 200), () => _contentCtrl.forward());
  }

  @override
  void dispose() {
    _headerCtrl.dispose();
    _contentCtrl.dispose();
    _pulseCtrl.dispose();
    _pageCtrl.dispose();
    super.dispose();
  }

  void _nextStep() {
    if (_activeStep < _steps.length - 1) {
      setState(() => _activeStep++);
    } else {
      setState(() { _activeStep = 0; _isPlaying = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F4),
      body: Column(
        children: [
          SlideTransition(
            position: _headerSlide,
            child: FadeTransition(opacity: _headerFade, child: _buildHeader(context)),
          ),
          Expanded(
            child: SlideTransition(
              position: _contentSlide,
              child: FadeTransition(
                opacity: _contentFade,
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildFeatureCarousel(),
                      const SizedBox(height: 24),
                      _buildVideoSection(),
                      const SizedBox(height: 24),
                      _buildExerciseDemo(),
                      const SizedBox(height: 24),
                      _buildStatsPreview(),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [darkTeal, Color(0xFF1A8587), primaryTeal],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(32)),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
          child: Row(
            children: [
              GestureDetector(
                onTap: () => Navigator.pop(context),
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 18),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'App Demo',
                      style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white),
                    ),
                    Text(
                      'Explore what Rehab+ can do',
                      style: TextStyle(fontSize: 13, color: Colors.white.withValues(alpha: 0.75)),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.25)),
                ),
                child: Row(
                  children: [
                    ScaleTransition(
                      scale: _pulse,
                      child: Container(
                        width: 7, height: 7,
                        decoration: const BoxDecoration(color: Color(0xFF4CAF50), shape: BoxShape.circle),
                      ),
                    ),
                    const SizedBox(width: 6),
                    const Text('Live', style: TextStyle(fontSize: 11, color: Colors.white, fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ── FEATURE CAROUSEL ─────────────────────────────────────────────────────────
  Widget _buildFeatureCarousel() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const PhosphorIcon(PhosphorIconsRegular.sparkle, size: 18, color: primaryTeal),
            const SizedBox(width: 8),
            const Text('Key Features', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black87)),
          ],
        ),
        const SizedBox(height: 14),
        SizedBox(
          height: 220,
          child: PageView.builder(
            controller: _pageCtrl,
            itemCount: _features.length,
            onPageChanged: (i) => setState(() => _currentPage = i),
            itemBuilder: (_, i) => _FeatureCard(feature: _features[i]),
          ),
        ),
        const SizedBox(height: 12),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(_features.length, (i) => AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOutCubic,
            margin: const EdgeInsets.symmetric(horizontal: 4),
            width: _currentPage == i ? 20 : 7,
            height: 7,
            decoration: BoxDecoration(
              color: _currentPage == i ? primaryTeal : Colors.grey.shade300,
              borderRadius: BorderRadius.circular(4),
            ),
          )),
        ),
      ],
    );
  }

  // ── VIDEO SECTION ──────────────────────────────────────────────────────────
  Widget _buildVideoSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const PhosphorIcon(PhosphorIconsRegular.videoCamera, size: 18, color: primaryTeal),
            const SizedBox(width: 8),
            const Text('Exercise Videos', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black87)),
          ],
        ),
        const SizedBox(height: 14),
        _VideoCard(
          title: 'Knee Extension',
          duration: '3:24',
          level: 'Beginner',
          color: Color(0xFF23A8AA),
          videoUrl: 'https://www.w3schools.com/html/mov_bbb.mp4',
        ),
        const SizedBox(height: 12),
        _VideoCard(
          title: 'Shoulder Rotation',
          duration: '2:15',
          level: 'Intermediate',
          color: Color(0xFF4A90D9),
          videoUrl: 'https://www.w3schools.com/html/movie.mp4',
        ),
      ],
    );
  }

  // ── EXERCISE DEMO ─────────────────────────────────────────────────────────────
  Widget _buildExerciseDemo() {
    final step = _steps[_activeStep];
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(color: primaryTeal.withValues(alpha: 0.08), blurRadius: 20, offset: const Offset(0, 6)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const PhosphorIcon(PhosphorIconsRegular.barbell, size: 18, color: primaryTeal),
              const SizedBox(width: 8),
              const Expanded(
                child: Text('Exercise Demo', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black87)),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFF4A90D9).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Text('Knee Extension', style: TextStyle(fontSize: 11, color: Color(0xFF4A90D9), fontWeight: FontWeight.w600)),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // Step indicator
          Row(
            children: List.generate(_steps.length, (i) => Expanded(
              child: GestureDetector(
                onTap: () => setState(() => _activeStep = i),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  curve: Curves.easeOutCubic,
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  height: 5,
                  decoration: BoxDecoration(
                    color: i <= _activeStep ? primaryTeal : Colors.grey.shade200,
                    borderRadius: BorderRadius.circular(3),
                  ),
                ),
              ),
            )),
          ),
          const SizedBox(height: 6),
          Text(
            'Step ${_activeStep + 1} of ${_steps.length}',
            style: const TextStyle(fontSize: 11, color: Colors.black38),
          ),
          const SizedBox(height: 16),

          // Step content
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 350),
            switchInCurve: Curves.easeOutCubic,
            switchOutCurve: Curves.easeInCubic,
            transitionBuilder: (child, anim) => FadeTransition(
              opacity: anim,
              child: SlideTransition(
                position: Tween<Offset>(begin: const Offset(0.1, 0), end: Offset.zero).animate(anim),
                child: child,
              ),
            ),
            child: Container(
              key: ValueKey(_activeStep),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFE0F5F5),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Row(
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(colors: [primaryTeal, darkTeal]),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Center(
                      child: Text(
                        '${_activeStep + 1}',
                        style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white),
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(step.title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.black87)),
                        const SizedBox(height: 4),
                        Text(step.description, style: const TextStyle(fontSize: 12, color: Colors.black54, height: 1.4)),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            const PhosphorIcon(PhosphorIconsRegular.timer, size: 13, color: primaryTeal),
                            const SizedBox(width: 4),
                            Text('${step.seconds}s hold', style: const TextStyle(fontSize: 11, color: primaryTeal, fontWeight: FontWeight.w600)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _activeStep > 0 ? () => setState(() => _activeStep--) : null,
                  icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 14),
                  label: const Text('Prev'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: primaryTeal,
                    side: const BorderSide(color: primaryTeal),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: ElevatedButton.icon(
                  onPressed: _nextStep,
                  icon: Icon(_activeStep < _steps.length - 1 ? Icons.arrow_forward_ios_rounded : Icons.refresh_rounded, size: 14),
                  label: Text(_activeStep < _steps.length - 1 ? 'Next Step' : 'Restart'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: primaryTeal,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ── STATS PREVIEW ─────────────────────────────────────────────────────────────
  Widget _buildStatsPreview() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [darkTeal, Color(0xFF1A8587), primaryTeal],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(color: primaryTeal.withValues(alpha: 0.3), blurRadius: 20, offset: const Offset(0, 8)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              PhosphorIcon(PhosphorIconsRegular.chartBar, size: 18, color: Colors.white),
              SizedBox(width: 8),
              Text('Sample Progress', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
            ],
          ),
          const SizedBox(height: 6),
          Text('This is how your recovery data will look', style: TextStyle(fontSize: 12, color: Colors.white.withValues(alpha: 0.7))),
          const SizedBox(height: 20),
          Row(
            children: [
              _statPill('Sessions', '12', PhosphorIconsRegular.heartbeat),
              const SizedBox(width: 10),
              _statPill('Streak', '5 days', PhosphorIconsRegular.flame),
              const SizedBox(width: 10),
              _statPill('Score', '87%', PhosphorIconsRegular.trophy),
            ],
          ),
          const SizedBox(height: 20),
          // Fake bar chart
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              _bar('M', 0.6),
              _bar('T', 0.85),
              _bar('W', 0.4),
              _bar('T', 0.9),
              _bar('F', 0.7),
              _bar('S', 0.5),
              _bar('S', 0.75),
            ],
          ),
          const SizedBox(height: 8),
          Center(
            child: Text('Weekly Activity', style: TextStyle(fontSize: 11, color: Colors.white.withValues(alpha: 0.6))),
          ),
        ],
      ),
    );
  }

  Widget _statPill(String label, String value, PhosphorIconData icon) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 10),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
        ),
        child: Column(
          children: [
            PhosphorIcon(icon, size: 18, color: Colors.white),
            const SizedBox(height: 6),
            Text(value, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.white)),
            Text(label, style: TextStyle(fontSize: 10, color: Colors.white.withValues(alpha: 0.7))),
          ],
        ),
      ),
    );
  }

  Widget _bar(String day, double height) {
    return Column(
      children: [
        Container(
          width: 28,
          height: 80 * height,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.25 + height * 0.4),
            borderRadius: BorderRadius.circular(6),
          ),
        ),
        const SizedBox(height: 6),
        Text(day, style: TextStyle(fontSize: 10, color: Colors.white.withValues(alpha: 0.7))),
      ],
    );
  }
}

// ── FEATURE CARD ──────────────────────────────────────────────────────────────
class _FeatureCard extends StatelessWidget {
  final _DemoFeature feature;
  const _FeatureCard({required this.feature});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 4),
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(color: feature.color.withValues(alpha: 0.12), blurRadius: 16, offset: const Offset(0, 6)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: feature.color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(16),
            ),
            child: PhosphorIcon(feature.icon, size: 28, color: feature.color),
          ),
          const SizedBox(height: 16),
          Text(feature.title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.black87)),
          const SizedBox(height: 8),
          Text(feature.subtitle, style: const TextStyle(fontSize: 13, color: Colors.black54, height: 1.5)),
        ],
      ),
    );
  }
}

// ── DATA MODELS ───────────────────────────────────────────────────────────────
enum _PreviewType { progress, exercise, messages, notifications }

class _DemoFeature {
  final PhosphorIconData icon;
  final Color color;
  final String title, subtitle;
  final _PreviewType preview;
  const _DemoFeature({required this.icon, required this.color, required this.title, required this.subtitle, required this.preview});
}

class _ExerciseStep {
  final String title, description;
  final int seconds;
  const _ExerciseStep(this.title, this.description, this.seconds);
}

// ── VIDEO CARD ────────────────────────────────────────────────────────────────
class _VideoCard extends StatefulWidget {
  final String title, duration, level, videoUrl;
  final Color color;
  const _VideoCard({
    required this.title,
    required this.duration,
    required this.level,
    required this.color,
    required this.videoUrl,
  });

  @override
  State<_VideoCard> createState() => _VideoCardState();
}

class _VideoCardState extends State<_VideoCard> {
  static const Color primaryTeal = Color(0xFF23A8AA);
  VideoPlayerController? _vpCtrl;
  ChewieController? _chewieCtrl;
  bool _loaded = false;
  bool _expanded = false;

  Color get _levelColor => switch (widget.level) {
        'Beginner' => const Color(0xFF4CAF50),
        'Intermediate' => const Color(0xFFFF9800),
        _ => const Color(0xFFE53935),
      };

  Future<void> _initVideo() async {
    if (_loaded) return;
    _vpCtrl = VideoPlayerController.networkUrl(Uri.parse(widget.videoUrl));
    await _vpCtrl!.initialize();
    _chewieCtrl = ChewieController(
      videoPlayerController: _vpCtrl!,
      autoPlay: true,
      looping: false,
      aspectRatio: 16 / 9,
      placeholder: Container(color: Colors.black),
      materialProgressColors: ChewieProgressColors(
        playedColor: primaryTeal,
        handleColor: primaryTeal,
        bufferedColor: primaryTeal.withValues(alpha: 0.3),
        backgroundColor: Colors.grey.shade800,
      ),
    );
    if (mounted) setState(() => _loaded = true);
  }

  @override
  void dispose() {
    _chewieCtrl?.dispose();
    _vpCtrl?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: widget.color.withValues(alpha: 0.1), blurRadius: 14, offset: const Offset(0, 5))],
      ),
      child: Column(
        children: [
          // Thumbnail / player row
          GestureDetector(
            onTap: () async {
              if (!_expanded) {
                await _initVideo();
                setState(() => _expanded = true);
              } else {
                _chewieCtrl?.dispose();
                _vpCtrl?.dispose();
                _chewieCtrl = null;
                _vpCtrl = null;
                setState(() { _expanded = false; _loaded = false; });
              }
            },
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 350),
              curve: Curves.easeOutCubic,
              height: _expanded ? 200 : 72,
              decoration: BoxDecoration(
                color: _expanded ? Colors.black : widget.color.withValues(alpha: 0.08),
                borderRadius: _expanded
                    ? const BorderRadius.vertical(top: Radius.circular(20))
                    : BorderRadius.circular(20),
              ),
              child: _expanded
                  ? (_loaded && _chewieCtrl != null
                      ? ClipRRect(
                          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                          child: Chewie(controller: _chewieCtrl!),
                        )
                      : const Center(child: CircularProgressIndicator(color: primaryTeal, strokeWidth: 2)))
                  : Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Row(
                        children: [
                          Container(
                            width: 44, height: 44,
                            decoration: BoxDecoration(
                              color: widget.color,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 26),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(widget.title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.black87)),
                                const SizedBox(height: 3),
                                Row(
                                  children: [
                                    Icon(Icons.access_time_rounded, size: 12, color: Colors.grey.shade400),
                                    const SizedBox(width: 4),
                                    Text(widget.duration, style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
                                    const SizedBox(width: 10),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: _levelColor.withValues(alpha: 0.1),
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(widget.level, style: TextStyle(fontSize: 10, color: _levelColor, fontWeight: FontWeight.w700)),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          Icon(
                            _expanded ? Icons.keyboard_arrow_up_rounded : Icons.play_circle_outline_rounded,
                            color: widget.color, size: 28,
                          ),
                        ],
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
