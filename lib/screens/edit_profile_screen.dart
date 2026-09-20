import 'package:flutter/material.dart';

class ProfileData {
  final String name;
  final String email;
  final String phone;
  final String dob;
  final String condition;

  const ProfileData({
    required this.name,
    required this.email,
    required this.phone,
    required this.dob,
    required this.condition,
  });
}

class EditProfileScreen extends StatefulWidget {
  final ProfileData initial;
  const EditProfileScreen({super.key, required this.initial});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen>
    with SingleTickerProviderStateMixin {
  static const Color primaryTeal = Color(0xFF23A8AA);
  static const Color darkTeal = Color(0xFF0D6E71);

  late final AnimationController _ctrl;
  late final Animation<double> _fade;
  late final Animation<Offset> _slide;

  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameCtrl;
  late final TextEditingController _emailCtrl;
  late final TextEditingController _phoneCtrl;
  late final TextEditingController _dobCtrl;
  late final TextEditingController _conditionCtrl;
  late final TextEditingController _therapistCtrl;

  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: widget.initial.name);
    _emailCtrl = TextEditingController(text: widget.initial.email);
    _phoneCtrl = TextEditingController(text: widget.initial.phone);
    _dobCtrl = TextEditingController(text: widget.initial.dob);
    _conditionCtrl = TextEditingController(text: widget.initial.condition);
    _therapistCtrl = TextEditingController(text: 'Mr. Heintz');

    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
    _fade = CurvedAnimation(parent: _ctrl, curve: Curves.easeOut);
    _slide = Tween<Offset>(begin: const Offset(0, 0.1), end: Offset.zero)
        .animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeOutCubic));
    _ctrl.forward();
  }

  @override
  void dispose() {
    _ctrl.dispose();
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _dobCtrl.dispose();
    _conditionCtrl.dispose();
    _therapistCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isSaving = true);
    await Future.delayed(const Duration(milliseconds: 800));
    if (!mounted) return;
    Navigator.pop(
      context,
      ProfileData(
        name: _nameCtrl.text.trim(),
        email: _emailCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
        dob: _dobCtrl.text.trim(),
        condition: _conditionCtrl.text.trim(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F4),
      body: Column(
        children: [
          _buildHeader(context),
          Expanded(
            child: FadeTransition(
              opacity: _fade,
              child: SlideTransition(
                position: _slide,
                child: Form(
                  key: _formKey,
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildAvatarSection(),
                        const SizedBox(height: 24),
                        _sectionLabel('Personal Info', Icons.person_outline_rounded),
                        const SizedBox(height: 12),
                        _buildCard([
                          _field(_nameCtrl, 'Full Name', Icons.person_outline, validator: _required),
                          _divider(),
                          _field(_emailCtrl, 'Email', Icons.email_outlined,
                              keyboardType: TextInputType.emailAddress,
                              validator: (v) => v != null && v.contains('@') ? null : 'Enter a valid email'),
                          _divider(),
                          _field(_phoneCtrl, 'Phone', Icons.phone_outlined,
                              keyboardType: TextInputType.phone, validator: _required),
                          _divider(),
                          _field(_dobCtrl, 'Date of Birth', Icons.cake_outlined, validator: _required),
                        ]),
                        const SizedBox(height: 20),
                        _sectionLabel('Rehab Info', Icons.medical_services_outlined),
                        const SizedBox(height: 12),
                        _buildCard([
                          _field(_conditionCtrl, 'Condition', Icons.medical_services_outlined, validator: _required),
                          _divider(),
                          _field(_therapistCtrl, 'Therapist', Icons.person_pin_outlined, readOnly: true),
                        ]),
                        const SizedBox(height: 28),
                        _buildSaveButton(),
                      ],
                    ),
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
                  width: 40, height: 40,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 18),
                ),
              ),
              const SizedBox(width: 14),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Edit Profile', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white)),
                    Text('Update your personal information', style: TextStyle(fontSize: 12, color: Colors.white70)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildAvatarSection() {
    return Center(
      child: Stack(
        children: [
          Container(
            width: 90, height: 90,
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [Color(0xFF23A8AA), Color(0xFF0D6E71)]),
              shape: BoxShape.circle,
              boxShadow: [BoxShadow(color: primaryTeal.withValues(alpha: 0.3), blurRadius: 16, offset: const Offset(0, 6))],
            ),
            child: const Icon(Icons.person, size: 46, color: Colors.white),
          ),
          Positioned(
            bottom: 0, right: 0,
            child: Container(
              width: 30, height: 30,
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 6)],
              ),
              child: const Icon(Icons.camera_alt_rounded, size: 16, color: primaryTeal),
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionLabel(String label, IconData icon) {
    return Row(
      children: [
        Icon(icon, size: 16, color: primaryTeal),
        const SizedBox(width: 8),
        Text(label, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.black54)),
      ],
    );
  }

  Widget _buildCard(List<Widget> children) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 12, offset: const Offset(0, 3))],
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        child: Column(children: children),
      ),
    );
  }

  Widget _field(
    TextEditingController ctrl,
    String label,
    IconData icon, {
    TextInputType? keyboardType,
    String? Function(String?)? validator,
    bool readOnly = false,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: TextFormField(
        controller: ctrl,
        keyboardType: keyboardType,
        readOnly: readOnly,
        validator: validator,
        style: const TextStyle(fontSize: 14, color: Colors.black87, fontWeight: FontWeight.w500),
        decoration: InputDecoration(
          prefixIcon: Icon(icon, size: 18, color: readOnly ? Colors.black26 : primaryTeal),
          labelText: label,
          labelStyle: TextStyle(fontSize: 13, color: readOnly ? Colors.black26 : Colors.black45),
          border: InputBorder.none,
          suffixIcon: readOnly ? const Icon(Icons.lock_outline, size: 14, color: Colors.black26) : null,
        ),
      ),
    );
  }

  Widget _divider() => const Divider(height: 1, color: Color(0xFFF0F0F0));

  String? _required(String? v) => (v == null || v.trim().isEmpty) ? 'Required' : null;

  Widget _buildSaveButton() {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: _isSaving ? null : _save,
        style: ElevatedButton.styleFrom(
          backgroundColor: primaryTeal,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
        child: _isSaving
            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
            : const Text('Save Changes', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
      ),
    );
  }
}
