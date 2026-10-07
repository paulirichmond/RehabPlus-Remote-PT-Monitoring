import 'package:flutter/material.dart';

class NotificationModel {
  final String title;
  final String description;
  final String time;
  final IconData icon;
  final Color iconBgColor;
  final Color iconColor;
  final bool isUnread;

  NotificationModel({
    required this.title,
    required this.description,
    required this.time,
    required this.icon,
    required this.iconBgColor,
    required this.iconColor,
    this.isUnread = false,
  });
}

class NotificationPage extends StatefulWidget {
  const NotificationPage({super.key});

  @override
  State<NotificationPage> createState() => _NotificationPageState();
}

class _NotificationPageState extends State<NotificationPage> {
  int _selectedFilterIndex = 0;

  static const Color primaryTeal = Color(0xFF23A8AA);
  static const Color darkTeal = Color(0xFF0D6E71);

  final List<NotificationModel> _notifications = [
    NotificationModel(
      title: 'Exercise Completed',
      description: 'Great job! You have completed "Knee Extension".',
      time: '12:03 PM',
      icon: Icons.accessibility_new_rounded,
      iconBgColor: Color(0xFFB3EBF2),
      iconColor: Color(0xFF0097A7),
      isUnread: true,
    ),
    NotificationModel(
      title: 'New Exercise Assigned',
      description: 'Your therapist has assigned a new exercise: Knee Extension.',
      time: '10:30 AM',
      icon: Icons.assignment_outlined,
      iconBgColor: Color(0xFFC8E6C9),
      iconColor: Color(0xFF388E3C),
      isUnread: true,
    ),
    NotificationModel(
      title: 'Upcoming Session',
      description: 'You have a session with your therapist tomorrow at 1:00 PM.',
      time: '8:00 AM',
      icon: Icons.calendar_month_outlined,
      iconBgColor: Color(0xFFBBDEFB),
      iconColor: Color(0xFF1976D2),
      isUnread: true,
    ),
    NotificationModel(
      title: 'Weekly Progress Report',
      description: 'Your weekly report is now available. Tap to view your progress.',
      time: 'Yesterday',
      icon: Icons.bar_chart_rounded,
      iconBgColor: Color(0xFFE1BEE7),
      iconColor: Color(0xFF7B1FA2),
      isUnread: false,
    ),
    NotificationModel(
      title: 'Milestone Achieved',
      description: "Congratulations! You've completed 75% of your weekly goal.",
      time: 'Yesterday',
      icon: Icons.emoji_events_outlined,
      iconBgColor: Color(0xFFFFF9C4),
      iconColor: Color(0xFFFBC02D),
      isUnread: false,
    ),
    NotificationModel(
      title: 'Message from Therapist',
      description: "Don't forget to stretch after your exercises. Keep it up!",
      time: 'Monday',
      icon: Icons.chat_bubble_outline_rounded,
      iconBgColor: Color(0xFFB2EBF2),
      iconColor: Color(0xFF00838F),
      isUnread: false,
    ),
    NotificationModel(
      title: 'Form Correction',
      description: 'Please adjust your posture during Squat. Keep your back straight.',
      time: 'Monday',
      icon: Icons.warning_amber_rounded,
      iconBgColor: Color(0xFFFFE0B2),
      iconColor: Color(0xFFF57C00),
      isUnread: false,
    ),
  ];

  List<NotificationModel> get _filtered {
    if (_selectedFilterIndex == 1) return _notifications.where((n) => !n.isUnread).toList();
    if (_selectedFilterIndex == 2) return _notifications.where((n) => n.isUnread).toList();
    return _notifications;
  }

  int get _unreadCount => _notifications.where((n) => n.isUnread).length;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F4),
      body: Column(
        children: [
          _buildHeader(context),
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: _buildFilterRow(),
          ),
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  _selectedFilterIndex == 0
                      ? 'All Notifications'
                      : _selectedFilterIndex == 1
                          ? 'Read'
                          : 'Unread',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: Colors.black87,
                  ),
                ),
                Text(
                  '${_filtered.length} items',
                  style: const TextStyle(fontSize: 12, color: Colors.black45),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Expanded(
            child: _filtered.isEmpty
                ? _buildEmpty()
                : ListView.builder(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    itemCount: _filtered.length,
                    itemBuilder: (_, i) => _notificationCard(_filtered[i]),
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
              const Expanded(
                child: Text(
                  'Notifications',
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
              ),
              if (_unreadCount > 0)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.3)),
                  ),
                  child: Text(
                    '$_unreadCount new',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFilterRow() {
    const labels = ['All', 'Read', 'Unread'];
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: List.generate(3, (i) {
          final selected = _selectedFilterIndex == i;
          return Expanded(
            child: GestureDetector(
              onTap: () => setState(() => _selectedFilterIndex = i),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 220),
                curve: Curves.easeInOut,
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  gradient: selected
                      ? const LinearGradient(colors: [primaryTeal, darkTeal])
                      : null,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: selected
                      ? [BoxShadow(color: primaryTeal.withValues(alpha: 0.3), blurRadius: 8, offset: const Offset(0, 3))]
                      : null,
                ),
                child: Text(
                  labels[i],
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                    color: selected ? Colors.white : Colors.black45,
                  ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget _notificationCard(NotificationModel item) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: item.isUnread
            ? Border.all(color: primaryTeal.withValues(alpha: 0.25), width: 1.2)
            : Border.all(color: Colors.transparent),
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
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: item.iconBgColor, shape: BoxShape.circle),
            child: Icon(item.icon, color: item.iconColor, size: 24),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.title,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          color: Colors.black87,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      item.time,
                      style: const TextStyle(fontSize: 11, color: Colors.black38),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: Text(
                        item.description,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.black54,
                          height: 1.4,
                        ),
                      ),
                    ),
                    if (item.isUnread) ...[
                      const SizedBox(width: 8),
                      Container(
                        width: 9,
                        height: 9,
                        decoration: const BoxDecoration(
                          color: primaryTeal,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: primaryTeal.withValues(alpha: 0.08),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.notifications_off_outlined, size: 40, color: primaryTeal),
          ),
          const SizedBox(height: 16),
          const Text('No notifications here',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: Colors.black54)),
        ],
      ),
    );
  }
}
