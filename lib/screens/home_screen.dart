import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/exercise.dart';
import '../services/app_provider.dart';
import '../widgets/exercise_card.dart';
import 'history_screen.dart';
import 'dashboard_screen.dart';
import 'messages_screen.dart';
import 'profile_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          _CyanHeader(
            onDashboard: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const DashboardScreen()),
            ),
          ),
          Expanded(
            child: IndexedStack(
              index: _tab,
              children: const [
                _ExerciseList(),
                HistoryScreen(),
                MessagesScreen(),
                DashboardScreen(),
                ProfileScreen(),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.fitness_center), label: 'Exercises'),
          NavigationDestination(icon: Icon(Icons.history), label: 'History'),
          NavigationDestination(icon: Icon(Icons.message), label: 'Messages'),
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), label: 'Dashboard'),
          NavigationDestination(icon: Icon(Icons.person_outlined), label: 'Profile'),
        ],
      ),
    );
  }
}

class _CyanHeader extends StatelessWidget {
  final VoidCallback onDashboard;
  const _CyanHeader({required this.onDashboard});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF00BCD4), Color(0xFF006064)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 8, 20),
          child: Row(
            children: [
              const Icon(Icons.self_improvement, color: Colors.white, size: 32),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('RehabPlus',
                        style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold)),
                    Text('Your recovery companion',
                        style: TextStyle(color: Colors.white70, fontSize: 12)),
                  ],
                ),
              ),
              IconButton(
                icon: const Icon(Icons.dashboard_outlined, color: Colors.white),
                tooltip: 'Therapist Dashboard',
                onPressed: onDashboard,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ExerciseList extends StatelessWidget {
  // ignore: use_super_parameters
  const _ExerciseList({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
          child: Row(
            children: [
              Container(
                width: 4,
                height: 20,
                decoration: BoxDecoration(
                  color: const Color(0xFF00BCD4),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                'Select an Exercise',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
            ],
          ),
        ),
        Expanded(
          child: ListView.builder(
            itemCount: defaultExercises.length,
            itemBuilder: (context, i) {
              final exercise = defaultExercises[i];
              return ExerciseCard(
                exercise: exercise,
                onStart: () => _startSession(context, exercise),
              );
            },
          ),
        ),
      ],
    );
  }

  void _startSession(BuildContext context, Exercise exercise) async {
    final provider = context.read<AppProvider>();
    await provider.startSession(exercise);
    if (context.mounted) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => const HistoryScreen()));
    }
  }
}
