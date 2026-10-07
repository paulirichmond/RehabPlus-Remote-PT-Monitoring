import 'package:flutter/material.dart';

class PrivacyPolicyScreen extends StatelessWidget {
  const PrivacyPolicyScreen({super.key});

  static const Color primaryTeal = Color(0xFF23A8AA);
  static const Color darkTeal = Color(0xFF0D6E71);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F4),
      body: Column(
        children: [
          _buildHeader(context),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 24, 16, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Privacy Policy',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      color: Colors.black87,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Last updated: January 2025',
                    style: TextStyle(fontSize: 12, color: Colors.black38),
                  ),
                  const SizedBox(height: 20),
                  _policyCard(
                    icon: Icons.search_rounded,
                    iconBgColor: const Color(0xFFD3EFF8),
                    iconColor: const Color(0xFF00838F),
                    title: 'No Surprises',
                    description:
                        "We'll only ever collect, use and share your information in ways that are described in this policy.",
                  ),
                  _policyCard(
                    icon: Icons.lock_outline_rounded,
                    iconBgColor: const Color(0xFFE2F3D3),
                    iconColor: const Color(0xFF558B2F),
                    title: 'Keeping Your Information Safe',
                    description:
                        "We've committed to the confidentiality and security of the personal data you give us.",
                  ),
                  _policyCard(
                    icon: Icons.tune_rounded,
                    iconBgColor: const Color(0xFFFFE0E0),
                    iconColor: const Color(0xFFD32F2F),
                    title: "You're Always in Control",
                    description:
                        'Update your profile and communication preferences at any time from your account settings.',
                  ),
                  _policyCard(
                    icon: Icons.share_outlined,
                    iconBgColor: const Color(0xFFFFF9C4),
                    iconColor: const Color(0xFFF9A825),
                    title: 'Data Sharing',
                    description:
                        'We never sell your personal data. Information is only shared with your care team to support your recovery.',
                  ),
                  _policyCard(
                    icon: Icons.delete_outline_rounded,
                    iconBgColor: const Color(0xFFE8EAF6),
                    iconColor: const Color(0xFF3949AB),
                    title: 'Right to Deletion',
                    description:
                        'You may request deletion of your account and all associated data at any time by contacting support.',
                  ),
                  const SizedBox(height: 8),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [primaryTeal, darkTeal],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: const Text(
                      'By using Rehab+, you agree to the terms described in this policy. For questions, contact us via Help & Support.',
                      style: TextStyle(fontSize: 12, color: Colors.white, height: 1.5),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ],
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
          padding: const EdgeInsets.fromLTRB(8, 8, 20, 24),
          child: Row(
            children: [
              IconButton(
                icon: const Icon(Icons.arrow_back_ios, color: Colors.white, size: 20),
                onPressed: () => Navigator.pop(context),
              ),
              const SizedBox(width: 4),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.shield_outlined, color: Colors.white, size: 20),
              ),
              const SizedBox(width: 12),
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Privacy & Security',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  Text(
                    'Your data, your control',
                    style: TextStyle(fontSize: 12, color: Colors.white60),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _policyCard({
    required IconData icon,
    required Color iconBgColor,
    required Color iconColor,
    required String title,
    required String description,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(color: iconBgColor, shape: BoxShape.circle),
            child: Icon(icon, color: iconColor, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: Colors.black87,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  description,
                  style: const TextStyle(fontSize: 12, color: Colors.black54, height: 1.45),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
