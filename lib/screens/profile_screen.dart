import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_provider.dart';
import 'login_page.dart';
import 'edit_profile_screen.dart';
import 'notification_page.dart';
import 'privacy_policy_screen.dart';
import 'messages_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  static const Color primaryTeal = Color(0xFF23A8AA);
  static const Color darkTeal = Color(0xFF0D6E71);

  ProfileData _profile = const ProfileData(
    name: 'Rob Thomas',
    email: 'rob@rehabplus.com',
    phone: '+1 (555) 000-0000',
    dob: 'Jan 1, 2002',
    condition: 'Knee Rehabilitation',
  );

  Future<void> _openEdit() async {
    final result = await Navigator.push<ProfileData>(
      context,
      MaterialPageRoute(builder: (_) => EditProfileScreen(initial: _profile)),
    );
    if (result != null) {
      setState(() => _profile = result);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Row(children: [
            Icon(Icons.check_circle_outline, color: Colors.white, size: 18),
            SizedBox(width: 8),
            Text('Profile updated successfully!'),
          ]),
          backgroundColor: primaryTeal,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AppProvider>(
      builder: (context, provider, _) {
        final sessions = provider.sessionHistory;
        final avgCompliance = sessions.isEmpty
            ? 0.0
            : sessions.map((s) => s.overallCompliance).reduce((a, b) => a + b) / sessions.length;
        final totalSets = sessions.fold(0, (sum, s) => sum + s.completedSets);
        final totalReps = sessions.fold(0, (sum, s) => sum + s.completedReps);

        return Scaffold(
          backgroundColor: const Color(0xFFF0F4F4),
          body: SingleChildScrollView(
            child: Column(
              children: [
                _buildHeader(),
                const SizedBox(height: 16),
                _buildStatsRow(sessions.length, totalSets, totalReps, avgCompliance),
                const SizedBox(height: 14),
                _buildInfoCard(
                  title: 'Personal Info',
                  icon: Icons.person_outline_rounded,
                  items: [
                    _InfoRow(icon: Icons.person_outline, label: 'Full Name', value: _profile.name),
                    _InfoRow(icon: Icons.email_outlined, label: 'Email', value: _profile.email),
                    _InfoRow(icon: Icons.phone_outlined, label: 'Phone', value: _profile.phone),
                    _InfoRow(icon: Icons.cake_outlined, label: 'Date of Birth', value: _profile.dob),
                  ],
                ),
                const SizedBox(height: 14),
                _buildInfoCard(
                  title: 'Rehab Info',
                  icon: Icons.medical_services_outlined,
                  items: [
                    _InfoRow(icon: Icons.medical_services_outlined, label: 'Condition', value: _profile.condition),
                    const _InfoRow(icon: Icons.person_pin_outlined, label: 'Therapist', value: 'Mr. Heintz'),
                    const _InfoRow(icon: Icons.calendar_today_outlined, label: 'Program Start', value: 'Jan 15, 2025'),
                  ],
                ),
                const SizedBox(height: 14),
                _buildSettingsCard(),
                const SizedBox(height: 14),
                _buildSignOutButton(),
                const SizedBox(height: 28),
              ],
            ),
          ),
        );
      },
    );
  }

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
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Profile', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white)),
                  GestureDetector(
                    onTap: _openEdit,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.18),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: Colors.white.withValues(alpha: 0.3)),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.edit_outlined, color: Colors.white, size: 14),
                          SizedBox(width: 5),
                          Text('Edit', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Container(
                    width: 64, height: 64,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white.withValues(alpha: 0.4), width: 2),
                    ),
                    child: const Icon(Icons.person, size: 34, color: Colors.white),
                  ),
                  const SizedBox(width: 16),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(_profile.name, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white)),
                      const SizedBox(height: 3),
                      Text(_profile.email, style: const TextStyle(fontSize: 13, color: Colors.white70)),
                      const SizedBox(height: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.circle, size: 7, color: Color(0xFF69F0AE)),
                            SizedBox(width: 5),
                            Text('Active Patient', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w500)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatsRow(int sessions, int sets, int reps, double avg) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [BoxShadow(color: primaryTeal.withValues(alpha: 0.08), blurRadius: 16, offset: const Offset(0, 4))],
        ),
        child: Row(
          children: [
            _statItem('Sessions', '$sessions', Icons.fitness_center_rounded),
            _vDivider(),
            _statItem('Total Sets', '$sets', Icons.repeat_rounded),
            _vDivider(),
            _statItem('Total Reps', '$reps', Icons.loop_rounded),
            _vDivider(),
            _statItem('Avg Score', '${(avg * 100).toInt()}%', Icons.bar_chart_rounded),
          ],
        ),
      ),
    );
  }

  Widget _statItem(String label, String value, IconData icon) {
    return Expanded(
      child: Column(
        children: [
          Icon(icon, color: primaryTeal, size: 20),
          const SizedBox(height: 6),
          Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.black87)),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(color: Colors.black38, fontSize: 10), textAlign: TextAlign.center),
        ],
      ),
    );
  }

  Widget _vDivider() => Container(width: 1, height: 36, color: Colors.grey.shade100);

  Widget _buildInfoCard({required String title, required IconData icon, required List<_InfoRow> items}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 12, offset: const Offset(0, 3))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(7),
                    decoration: BoxDecoration(color: primaryTeal.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(9)),
                    child: Icon(icon, size: 16, color: primaryTeal),
                  ),
                  const SizedBox(width: 10),
                  Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Colors.black87)),
                ],
              ),
            ),
            const Divider(height: 1, indent: 16, endIndent: 16),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              child: Column(children: items),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSettingsCard() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 12, offset: const Offset(0, 3))],
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(7),
                    decoration: BoxDecoration(color: primaryTeal.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(9)),
                    child: const Icon(Icons.settings_outlined, size: 16, color: primaryTeal),
                  ),
                  const SizedBox(width: 10),
                  const Text('Settings', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Colors.black87)),
                ],
              ),
            ),
            const Divider(height: 1, indent: 16, endIndent: 16),
            _settingsTile(icon: Icons.notifications_outlined, label: 'Notifications',
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationPage()))),
            const Divider(height: 1, indent: 56, endIndent: 16),
            _settingsTile(icon: Icons.lock_outline_rounded, label: 'Privacy & Security',
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const PrivacyPolicyScreen()))),
            const Divider(height: 1, indent: 56, endIndent: 16),
            _settingsTile(icon: Icons.help_outline_rounded, label: 'Help & Support',
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ChatDetailScreen(contact: rehabPlusSupportContact)))),
          ],
        ),
      ),
    );
  }

  Widget _settingsTile({required IconData icon, required String label, required VoidCallback onTap}) {
    return ListTile(
      onTap: onTap,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
      leading: Container(
        width: 36, height: 36,
        decoration: BoxDecoration(color: primaryTeal.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, size: 18, color: primaryTeal),
      ),
      title: Text(label, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: Colors.black87)),
      trailing: const Icon(Icons.chevron_right_rounded, color: Colors.black26, size: 20),
    );
  }

  Widget _buildSignOutButton() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: GestureDetector(
        onTap: () => Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const LoginPage()),
          (_) => false,
        ),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 15),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: Colors.red.shade200),
            boxShadow: [BoxShadow(color: Colors.red.withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, 3))],
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.logout_rounded, color: Colors.red.shade400, size: 18),
              const SizedBox(width: 8),
              Text('Sign Out', style: TextStyle(color: Colors.red.shade400, fontSize: 14, fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label, value;
  const _InfoRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Icon(icon, size: 17, color: Colors.black38),
          const SizedBox(width: 12),
          Text(label, style: const TextStyle(color: Colors.black45, fontSize: 13)),
          const Spacer(),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: Colors.black87)),
        ],
      ),
    );
  }
}
