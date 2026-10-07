import 'package:flutter/material.dart';
import 'package:phosphor_flutter/phosphor_flutter.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:intl/intl.dart';
import '../services/app_provider.dart';
import '../models/session.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen>
    with TickerProviderStateMixin {
  static const Color primaryTeal = Color(0xFF23A8AA);
  static const Color darkTeal = Color(0xFF0D6E71);

  int _selectedCategory = 0;
  final List<String> _categories = ['All', 'Knee', 'Shoulder', 'Back', 'Hip'];

  late final AnimationController _headerCtrl;
  late final AnimationController _listCtrl;
  late final Animation<double> _headerFade;
  late final Animation<Offset> _headerSlide;
  late final Animation<double> _listFade;
  late final Animation<Offset> _listSlide;

  // Sample exercises shown when no real sessions exist
  final List<_ExerciseItem> _exercises = [
    _ExerciseItem(
      name: 'Knee Extension',
      category: 'Knee',
      sets: 3,
      reps: 12,
      duration: '15 min',
      difficulty: 'Moderate',
      icon: PhosphorIconsRegular.arrowsVertical,
      color: Color(0xFF23A8AA),
      progress: 0.75,
    ),
    _ExerciseItem(
      name: 'Shoulder Rotation',
      category: 'Shoulder',
      sets: 3,
      reps: 10,
      duration: '10 min',
      difficulty: 'Easy',
      icon: PhosphorIconsRegular.arrowsClockwise,
      color: Color(0xFF4A90D9),
      progress: 0.5,
    ),
    _ExerciseItem(
      name: 'Hip Abduction',
      category: 'Hip',
      sets: 4,
      reps: 15,
      duration: '20 min',
      difficulty: 'Hard',
      icon: PhosphorIconsRegular.arrowsOutLineHorizontal,
      color: Color(0xFFFF6B35),
      progress: 0.3,
    ),
    _ExerciseItem(
      name: 'Lower Back Stretch',
      category: 'Back',
      sets: 2,
      reps: 8,
      duration: '12 min',
      difficulty: 'Easy',
      icon: PhosphorIconsRegular.arrowsDownUp,
      color: Color(0xFF7C4DFF),
      progress: 0.9,
    ),
    _ExerciseItem(
      name: 'Quad Strengthening',
      category: 'Knee',
      sets: 3,
      reps: 10,
      duration: '18 min',
      difficulty: 'Moderate',
      icon: PhosphorIconsRegular.lightning,
      color: Color(0xFFE53935),
      progress: 0.6,
    ),
  ];

  @override
  void initState() {
    super.initState();
    _headerCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _listCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 800));

    _headerFade = CurvedAnimation(parent: _headerCtrl, curve: Curves.easeOut);
    _headerSlide = Tween<Offset>(begin: const Offset(0, -0.2), end: Offset.zero)
        .animate(CurvedAnimation(parent: _headerCtrl, curve: Curves.easeOutCubic));
    _listFade = CurvedAnimation(parent: _listCtrl, curve: Curves.easeOut);
    _listSlide = Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero)
        .animate(CurvedAnimation(parent: _listCtrl, curve: Curves.easeOutCubic));

    _headerCtrl.forward();
    Future.delayed(const Duration(milliseconds: 200), () => _listCtrl.forward());
  }

  @override
  void dispose() {
    _headerCtrl.dispose();
    _listCtrl.dispose();
    super.dispose();
  }

  List<_ExerciseItem> get _filtered => _selectedCategory == 0
      ? _exercises
      : _exercises.where((e) => e.category == _categories[_selectedCategory]).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F4),
      body: Column(
        children: [
          SlideTransition(
            position: _headerSlide,
            child: FadeTransition(opacity: _headerFade, child: _buildHeader()),
          ),
          Expanded(
            child: SlideTransition(
              position: _listSlide,
              child: FadeTransition(
                opacity: _listFade,
                child: Consumer<AppProvider>(
                  builder: (context, provider, _) {
                    final sessions = provider.sessionHistory;
                    return SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const SizedBox(height: 18),
                          _buildSummaryRow(sessions),
                          const SizedBox(height: 18),
                          _buildCategoryFilter(),
                          const SizedBox(height: 16),
                          _buildTodayExercises(),
                          const SizedBox(height: 18),
                          if (sessions.isNotEmpty) ...[
                            _buildSectionTitle('Session History', PhosphorIcons.clockCounterClockwise()),
                            const SizedBox(height: 12),
                            ...sessions.map((s) => _SessionCard(session: s)),
                          ] else ...[
                            _buildSectionTitle('Recommended', PhosphorIcons.star()),
                            const SizedBox(height: 12),
                            _buildRecommendedList(),
                          ],
                          const SizedBox(height: 16),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── HEADER ──────────────────────────────────────────────────────────────────
  Widget _buildHeader() {
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
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Exercise',
                        style: TextStyle(
                          fontSize: 26,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                      Text(
                        'Track your recovery progress',
                        style: TextStyle(fontSize: 13, color: Colors.white60),
                      ),
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: GestureDetector(
                      onTap: () => ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: const Text('Add custom exercise coming soon!'),
                          backgroundColor: const Color(0xFF23A8AA),
                          behavior: SnackBarBehavior.floating,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          margin: const EdgeInsets.all(16),
                        ),
                      ),
                      child: PhosphorIcon(PhosphorIcons.plus(), color: Colors.white, size: 22),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              // Search bar
              Container(
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
                ),
                child: Row(
                  children: [
                    const SizedBox(width: 12),
                    PhosphorIcon(PhosphorIcons.magnifyingGlass(), color: Colors.white60, size: 18),
                    const SizedBox(width: 10),
                    const Expanded(
                      child: TextField(
                        style: TextStyle(color: Colors.white, fontSize: 14),
                        decoration: InputDecoration(
                          hintText: 'Search exercises...',
                          hintStyle: TextStyle(color: Colors.white54, fontSize: 14),
                          border: InputBorder.none,
                          isDense: true,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ── SUMMARY ROW ─────────────────────────────────────────────────────────────
  Widget _buildSummaryRow(List<ExerciseSession> sessions) {
    final totalSessions = sessions.length;
    final totalSets = sessions.fold(0, (s, e) => s + e.completedSets);
    final avgScore = sessions.isEmpty
        ? 0
        : (sessions.map((s) => s.overallCompliance).reduce((a, b) => a + b) /
                sessions.length *
                100)
            .toInt();

    return Row(
      children: [
        _summaryCard('Sessions', '$totalSessions', PhosphorIcons.calendar(), const Color(0xFF23A8AA)),
        const SizedBox(width: 10),
        _summaryCard('Total Sets', '$totalSets', PhosphorIcons.repeat(), const Color(0xFF4A90D9)),
        const SizedBox(width: 10),
        _summaryCard('Avg Score', '$avgScore%', PhosphorIcons.chartBar(), const Color(0xFF7C4DFF)),
      ],
    );
  }

  Widget _summaryCard(String label, String value, PhosphorIconData icon, Color color) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(color: color.withValues(alpha: 0.12), blurRadius: 12, offset: const Offset(0, 4)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(7),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(9),
              ),
              child: PhosphorIcon(icon, size: 16, color: color),
            ),
            const SizedBox(height: 10),
            Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.black87)),
            Text(label, style: const TextStyle(fontSize: 11, color: Colors.black45)),
          ],
        ),
      ),
    );
  }

  // ── CATEGORY FILTER ──────────────────────────────────────────────────────────
  Widget _buildCategoryFilter() {
    return SizedBox(
      height: 36,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _categories.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (_, i) {
          final selected = _selectedCategory == i;
          return GestureDetector(
            onTap: () => setState(() => _selectedCategory = i),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 250),
              curve: Curves.easeInOut,
              padding: const EdgeInsets.symmetric(horizontal: 18),
              decoration: BoxDecoration(
                gradient: selected
                    ? const LinearGradient(colors: [primaryTeal, darkTeal])
                    : null,
                color: selected ? null : Colors.white,
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: selected ? primaryTeal.withValues(alpha: 0.3) : Colors.black.withValues(alpha: 0.05),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: Center(
                child: Text(
                  _categories[i],
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: selected ? Colors.white : Colors.black54,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  // ── TODAY'S EXERCISES ────────────────────────────────────────────────────────
  Widget _buildTodayExercises() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildSectionTitle("Today's Plan", PhosphorIcons.clipboardText()),
        const SizedBox(height: 12),
        SizedBox(
          height: 170,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: _filtered.length,
            separatorBuilder: (_, _) => const SizedBox(width: 12),
            itemBuilder: (_, i) => _ExerciseCard(item: _filtered[i], index: i),
          ),
        ),
      ],
    );
  }

  // ── RECOMMENDED LIST ─────────────────────────────────────────────────────────
  Widget _buildRecommendedList() {
    return Column(
      children: _filtered
          .map((item) => _ExerciseListTile(item: item))
          .toList(),
    );
  }

  Widget _buildSectionTitle(String title, PhosphorIconData icon) {
    return Row(
      children: [
        PhosphorIcon(icon, size: 18, color: primaryTeal),
        const SizedBox(width: 8),
        Text(
          title,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black87),
        ),
      ],
    );
  }
}

// ── EXERCISE CARD (horizontal scroll) ────────────────────────────────────────
class _ExerciseCard extends StatefulWidget {
  final _ExerciseItem item;
  final int index;
  const _ExerciseCard({required this.item, required this.index});

  @override
  State<_ExerciseCard> createState() => _ExerciseCardState();
}

class _ExerciseCardState extends State<_ExerciseCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
    _scale = Tween<double>(begin: 0.8, end: 1.0)
        .animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeOutBack));
    Future.delayed(Duration(milliseconds: 100 * widget.index), () {
      if (mounted) _ctrl.forward();
    });
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;
    return ScaleTransition(
      scale: _scale,
      child: Container(
        width: 150,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [item.color, item.color.withValues(alpha: 0.7)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(color: item.color.withValues(alpha: 0.35), blurRadius: 14, offset: const Offset(0, 6)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(10),
              ),
              child: PhosphorIcon(item.icon, size: 20, color: Colors.white),
            ),
            const Spacer(),
            Text(
              item.name,
              style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
              maxLines: 2,
            ),
            const SizedBox(height: 4),
            Text(
              '${item.sets} sets · ${item.reps} reps',
              style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 11),
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: item.progress,
                backgroundColor: Colors.white.withValues(alpha: 0.3),
                valueColor: const AlwaysStoppedAnimation(Colors.white),
                minHeight: 4,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ── EXERCISE LIST TILE ────────────────────────────────────────────────────────
class _ExerciseListTile extends StatelessWidget {
  final _ExerciseItem item;
  const _ExerciseListTile({required this.item});

  Color get _difficultyColor => switch (item.difficulty) {
        'Easy' => const Color(0xFF4CAF50),
        'Hard' => const Color(0xFFE53935),
        _ => const Color(0xFFFF9800),
      };

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(color: item.color.withValues(alpha: 0.08), blurRadius: 12, offset: const Offset(0, 4)),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: item.color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(14),
            ),
            child: PhosphorIcon(item.icon, size: 24, color: item.color),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.name, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.black87)),
                const SizedBox(height: 4),
                Row(
                  children: [
                    PhosphorIcon(PhosphorIcons.clock(), size: 12, color: Colors.grey.shade400),
                    const SizedBox(width: 4),
                    Text(item.duration, style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
                    const SizedBox(width: 10),
                    PhosphorIcon(PhosphorIcons.repeat(), size: 12, color: Colors.grey.shade400),
                    const SizedBox(width: 4),
                    Text('${item.sets}×${item.reps}', style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
                  ],
                ),
                const SizedBox(height: 8),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: item.progress,
                    backgroundColor: Colors.grey.shade100,
                    valueColor: AlwaysStoppedAnimation(item.color),
                    minHeight: 5,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: _difficultyColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  item.difficulty,
                  style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: _difficultyColor),
                ),
              ),
              const SizedBox(height: 8),
              GestureDetector(
                onTap: () => showDialog(
                  context: context,
                  builder: (_) => AlertDialog(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                    title: Text(item.name),
                    content: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('${item.sets} sets · ${item.reps} reps · ${item.duration}',
                            style: const TextStyle(fontSize: 13, color: Colors.black54)),
                        const SizedBox(height: 8),
                        Text('Difficulty: ${item.difficulty}',
                            style: TextStyle(fontSize: 13, color: _difficultyColor, fontWeight: FontWeight.w600)),
                      ],
                    ),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: item.color,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        onPressed: () => Navigator.pop(context),
                        child: const Text('Start'),
                      ),
                    ],
                  ),
                ),
                child: Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    color: item.color,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const PhosphorIcon(PhosphorIconsRegular.play, size: 14, color: Colors.white),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ── SESSION HISTORY CARD ──────────────────────────────────────────────────────
class _SessionCard extends StatelessWidget {
  final ExerciseSession session;
  const _SessionCard({required this.session});

  @override
  Widget build(BuildContext context) {
    final compliance = session.overallCompliance;
    final color = compliance >= 0.8
        ? const Color(0xFF23A8AA)
        : compliance >= 0.5
            ? Colors.orange
            : Colors.red;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(color: color.withValues(alpha: 0.08), blurRadius: 12, offset: const Offset(0, 4)),
        ],
      ),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          leading: Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Center(
              child: Text(
                '${(compliance * 100).toInt()}%',
                style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.bold),
              ),
            ),
          ),
          title: Text(session.exerciseName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Row(
              children: [
                PhosphorIcon(PhosphorIcons.calendar(), size: 11, color: Colors.grey.shade400),
                const SizedBox(width: 4),
                Text(
                  DateFormat('MMM d, y').format(session.startTime),
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                ),
                const SizedBox(width: 10),
                PhosphorIcon(PhosphorIcons.repeat(), size: 11, color: Colors.grey.shade400),
                const SizedBox(width: 4),
                Text(
                  '${session.completedSets}/${session.targetSets} sets',
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                ),
              ],
            ),
          ),
          children: [
            if (session.repComplianceScores.isNotEmpty) ...[
              const Text('Rep Performance', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.black54)),
              const SizedBox(height: 10),
              SizedBox(
                height: 120,
                child: BarChart(
                  BarChartData(
                    barGroups: session.repComplianceScores.asMap().entries.map((e) =>
                      BarChartGroupData(x: e.key, barRods: [
                        BarChartRodData(
                          toY: e.value * 100,
                          color: e.value >= 0.8 ? const Color(0xFF23A8AA) : e.value >= 0.5 ? Colors.orange : Colors.red,
                          width: 14,
                          borderRadius: BorderRadius.circular(6),
                        ),
                      ]),
                    ).toList(),
                    maxY: 100,
                    titlesData: FlTitlesData(
                      leftTitles: AxisTitles(sideTitles: SideTitles(
                        showTitles: true,
                        reservedSize: 32,
                        getTitlesWidget: (v, _) => Text('${v.toInt()}%', style: const TextStyle(fontSize: 9, color: Colors.black45)),
                      )),
                      bottomTitles: AxisTitles(sideTitles: SideTitles(
                        showTitles: true,
                        getTitlesWidget: (v, _) => Text('R${v.toInt() + 1}', style: const TextStyle(fontSize: 9, color: Colors.black45)),
                      )),
                      topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                      rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    ),
                    gridData: FlGridData(
                      show: true,
                      drawVerticalLine: false,
                      getDrawingHorizontalLine: (_) => FlLine(color: Colors.grey.shade100, strokeWidth: 1),
                    ),
                    borderData: FlBorderData(show: false),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

// ── DATA MODEL ────────────────────────────────────────────────────────────────
class _ExerciseItem {
  final String name, category, duration, difficulty;
  final int sets, reps;
  final PhosphorIconData icon;
  final Color color;
  final double progress;

  const _ExerciseItem({
    required this.name,
    required this.category,
    required this.sets,
    required this.reps,
    required this.duration,
    required this.difficulty,
    required this.icon,
    required this.color,
    required this.progress,
  });
}
