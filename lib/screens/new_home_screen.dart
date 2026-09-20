import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_provider.dart';
import '../models/exercise.dart';
import '../widgets/exercise_card.dart';
import 'history_screen.dart';

class NewHomeScreen extends StatelessWidget {
  const NewHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<AppProvider>(
      builder: (context, provider, _) {
        final sessions = provider.sessionHistory;
        final avgCompliance = sessions.isEmpty
            ? 0.0
            : sessions.map((s) => s.overallCompliance).reduce((a, b) => a + b) / sessions.length;
        final streak = sessions.length;

        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Greeting card
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF00BCD4), Color(0xFF006064)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Good day! 👋',
                        style: TextStyle(color: Colors.white70, fontSize: 14)),
                    const SizedBox(height: 4),
                    const Text('Ready to recover?',
                        style: TextStyle(
                            color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        _MiniStat(
                            label: 'Sessions', value: '$streak', icon: Icons.fitness_center),
                        const SizedBox(width: 16),
                        _MiniStat(
                            label: 'Avg Score',
                            value: '${(avgCompliance * 100).toInt()}%',
                            icon: Icons.bar_chart),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Progress ring section
              if (sessions.isNotEmpty) ...[
                _SectionTitle(title: 'Overall Progress'),
                const SizedBox(height: 12),
                _ProgressCard(compliance: avgCompliance, totalSessions: sessions.length),
                const SizedBox(height: 24),
              ],

              // Quick start
              _SectionTitle(title: 'Quick Start'),
              const SizedBox(height: 12),
              ...defaultExercises.take(3).map((exercise) => ExerciseCard(
                    exercise: exercise,
                    onStart: () => _startSession(context, exercise, provider),
                  )),
            ],
          ),
        );
      },
    );
  }

  void _startSession(BuildContext context, Exercise exercise, AppProvider provider) async {
    await provider.startSession(exercise);
    if (context.mounted) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => const HistoryScreen()));
    }
  }
}

class _MiniStat extends StatelessWidget {
  final String label, value;
  final IconData icon;
  const _MiniStat({required this.label, required this.value, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: Colors.white70, size: 16),
        const SizedBox(width: 6),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(value,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
            Text(label, style: const TextStyle(color: Colors.white60, fontSize: 11)),
          ],
        ),
      ],
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String title;
  const _SectionTitle({required this.title});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 4,
          height: 18,
          decoration: BoxDecoration(
            color: const Color(0xFF00BCD4),
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        const SizedBox(width: 8),
        Text(title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
      ],
    );
  }
}

class _ProgressCard extends StatelessWidget {
  final double compliance;
  final int totalSessions;
  const _ProgressCard({required this.compliance, required this.totalSessions});

  @override
  Widget build(BuildContext context) {
    final color = compliance >= 0.8
        ? const Color(0xFF00BCD4)
        : compliance >= 0.5
            ? Colors.orange
            : Colors.red;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Row(
          children: [
            SizedBox(
              width: 72,
              height: 72,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  CircularProgressIndicator(
                    value: compliance,
                    strokeWidth: 7,
                    backgroundColor: color.withValues(alpha: 0.15),
                    valueColor: AlwaysStoppedAnimation(color),
                  ),
                  Text('${(compliance * 100).toInt()}%',
                      style: TextStyle(
                          color: color, fontWeight: FontWeight.bold, fontSize: 14)),
                ],
              ),
            ),
            const SizedBox(width: 20),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Compliance Rate',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 4),
                Text('Based on $totalSessions session${totalSessions == 1 ? '' : 's'}',
                    style: const TextStyle(color: Colors.grey, fontSize: 12)),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    compliance >= 0.8
                        ? 'Excellent'
                        : compliance >= 0.5
                            ? 'Keep going'
                            : 'Needs work',
                    style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
