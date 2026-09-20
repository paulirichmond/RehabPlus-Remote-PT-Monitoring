import 'package:flutter/material.dart';

class ComplianceMeter extends StatelessWidget {
  final double score; // 0.0 to 1.0
  final int completedReps;
  final int targetReps;
  final int completedSets;
  final int targetSets;

  const ComplianceMeter({
    super.key,
    required this.score,
    required this.completedReps,
    required this.targetReps,
    required this.completedSets,
    required this.targetSets,
  });

  Color get _color {
    if (score >= 0.8) return Colors.greenAccent;
    if (score >= 0.5) return Colors.orangeAccent;
    return Colors.redAccent;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: 56,
            height: 56,
            child: Stack(
              alignment: Alignment.center,
              children: [
                CircularProgressIndicator(
                  value: score,
                  strokeWidth: 5,
                  backgroundColor: Colors.white24,
                  valueColor: AlwaysStoppedAnimation(_color),
                ),
                Text(
                  '${(score * 100).toInt()}%',
                  style: TextStyle(color: _color, fontSize: 12, fontWeight: FontWeight.bold),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Reps: $completedReps / $targetReps',
                  style: const TextStyle(color: Colors.white, fontSize: 13)),
              Text('Sets: $completedSets / $targetSets',
                  style: const TextStyle(color: Colors.white70, fontSize: 12)),
            ],
          ),
        ],
      ),
    );
  }
}
