import 'package:flutter/material.dart';
import 'package:phosphor_flutter/phosphor_flutter.dart';

enum _ForgotStage { enterEmail, verifyCode, newPassword, success }

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen>
    with TickerProviderStateMixin {
  static const Color primaryTeal = Color(0xFF23A8AA);
  static const Color darkTeal = Color(0xFF0D6E71);

  _ForgotStage _stage = _ForgotStage.enterEmail;

  static const String _demoCode = '123456';
  final List<String> _otpDigits = List.filled(6, '');

  // Controllers
  final _emailCtrl = TextEditingController();
  final _codeCtrl = TextEditingController();
  final _newPassCtrl = TextEditingController();
  final _confirmPassCtrl = TextEditingController();

  bool _obscureNew = true;
  bool _obscureConfirm = true;
  bool _isLoading = false;

  // Stage transition animation
  late final AnimationController _stageCtrl;
  late final Animation<double> _stageFade;
  late final Animation<Offset> _stageSlide;

  // Logo entrance animation
  late final AnimationController _logoCtrl;
  late final Animation<double> _logoScale;
  late final Animation<double> _logoFade;

  // Success pulse animation
  late final AnimationController _successCtrl;
  late final Animation<double> _successScale;

  @override
  void initState() {
    super.initState();

    _stageCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 420));
    _stageFade = CurvedAnimation(parent: _stageCtrl, curve: Curves.easeOut);
    _stageSlide = Tween<Offset>(begin: const Offset(0.06, 0), end: Offset.zero)
        .animate(CurvedAnimation(parent: _stageCtrl, curve: Curves.easeOutCubic));

    _logoCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
    _logoScale = Tween<double>(begin: 0.5, end: 1.0)
        .animate(CurvedAnimation(parent: _logoCtrl, curve: Curves.elasticOut));
    _logoFade = CurvedAnimation(parent: _logoCtrl, curve: Curves.easeOut);

    _successCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _successScale = Tween<double>(begin: 0.0, end: 1.0)
        .animate(CurvedAnimation(parent: _successCtrl, curve: Curves.elasticOut));

    Future.delayed(const Duration(milliseconds: 80), () {
      _logoCtrl.forward();
      _stageCtrl.forward();
    });

    _newPassCtrl.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _codeCtrl.dispose();
    _newPassCtrl.dispose();
    _confirmPassCtrl.dispose();
    _stageCtrl.dispose();
    _logoCtrl.dispose();
    _successCtrl.dispose();
    super.dispose();
  }

  Future<void> _transitionTo(_ForgotStage next) async {
    setState(() => _isLoading = true);
    await Future.delayed(const Duration(milliseconds: 900));
    if (!mounted) return;
    await _stageCtrl.reverse();
    setState(() {
      _stage = next;
      _isLoading = false;
    });
    _stageCtrl.forward();
    if (next == _ForgotStage.success) _successCtrl.forward();
  }

  void _onContinue() {
    switch (_stage) {
      case _ForgotStage.enterEmail:
        if (_emailCtrl.text.trim().isEmpty || !_emailCtrl.text.contains('@')) {
          _showSnack('Enter a valid email address.', isError: true);
          return;
        }
        _transitionTo(_ForgotStage.verifyCode);
      case _ForgotStage.verifyCode:
        final entered = _otpDigits.join();
        if (entered.length < 6 || _otpDigits.any((d) => d.isEmpty)) {
          _showSnack('Enter the complete 6-digit code.', isError: true);
          return;
        }
        if (entered != _demoCode) {
          _showSnack('Incorrect code. Hint: 123456', isError: true);
          return;
        }
        _transitionTo(_ForgotStage.newPassword);
      case _ForgotStage.newPassword:
        if (_newPassCtrl.text.length < 6) {
          _showSnack('Password must be at least 6 characters.', isError: true);
          return;
        }
        if (_newPassCtrl.text != _confirmPassCtrl.text) {
          _showSnack('Passwords do not match.', isError: true);
          return;
        }
        _transitionTo(_ForgotStage.success);
      case _ForgotStage.success:
        Navigator.pop(context);
    }
  }

  void _showSnack(String msg, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: isError ? const Color(0xFFE53935) : primaryTeal,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      margin: const EdgeInsets.all(16),
    ));
  }

  // ── Stage metadata ────────────────────────────────────────────────────────
  String get _stageTitle => switch (_stage) {
        _ForgotStage.enterEmail => 'Forgot Password?',
        _ForgotStage.verifyCode => 'Check Your Email',
        _ForgotStage.newPassword => 'New Password',
        _ForgotStage.success => 'All Done!',
      };

  String get _stageSubtitle => switch (_stage) {
        _ForgotStage.enterEmail => 'Enter your email and we\'ll send a verification code.',
        _ForgotStage.verifyCode => 'We sent a 6-digit code to ${_emailCtrl.text.trim()}.',
        _ForgotStage.newPassword => 'Create a strong new password for your account.',
        _ForgotStage.success => 'Your password has been reset successfully.',
      };

  String get _buttonLabel => switch (_stage) {
        _ForgotStage.enterEmail => 'Send Code',
        _ForgotStage.verifyCode => 'Verify Code',
        _ForgotStage.newPassword => 'Reset Password',
        _ForgotStage.success => 'Back to Login',
      };

  int get _stepIndex => _stage.index;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF0D6E71), Color(0xFF1E8E92), Color(0xFF2FB8BD)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Stack(
          children: [
            // Decorative circles
            Positioned(top: -80, right: -80, child: _circle(260, 0.07)),
            Positioned(top: 60, right: -30, child: _circle(120, 0.05)),
            Positioned(bottom: -100, left: -60, child: _circle(300, 0.06)),

            SafeArea(
              child: Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(horizontal: 28),
                  child: Column(
                    children: [
                      const SizedBox(height: 20),
                      _buildBrandingHeader(),
                      const SizedBox(height: 28),
                      _buildCard(),
                      const SizedBox(height: 24),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _circle(double size, double opacity) => Container(
        width: size, height: size,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: opacity),
          shape: BoxShape.circle,
        ),
      );

  // ── Branding header (shared across all stages) ────────────────────────────
  Widget _buildBrandingHeader() {
    return FadeTransition(
      opacity: _logoFade,
      child: Column(
        children: [
          ScaleTransition(
            scale: _logoScale,
            child: Container(
              width: 76,
              height: 76,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.15),
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white.withValues(alpha: 0.3), width: 2),
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  PhosphorIcon(PhosphorIcons.shieldPlus(), color: Colors.white, size: 42),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          const Text(
            'Rehab +',
            style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold, letterSpacing: 1),
          ),
          const Text(
            'Physiotherapy',
            style: TextStyle(color: Colors.white70, fontSize: 13, letterSpacing: 2),
          ),
          const SizedBox(height: 20),
          // Step indicator
          if (_stage != _ForgotStage.success) _buildStepIndicator(),
        ],
      ),
    );
  }

  Widget _buildStepIndicator() {
    const labels = ['Email', 'Verify', 'Password'];
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(3, (i) {
        final done = i < _stepIndex;
        final active = i == _stepIndex;
        return Row(
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 350),
              curve: Curves.easeOutCubic,
              width: active ? 32 : 28,
              height: active ? 32 : 28,
              decoration: BoxDecoration(
                color: done || active ? Colors.white : Colors.white.withValues(alpha: 0.25),
                shape: BoxShape.circle,
                boxShadow: active
                    ? [BoxShadow(color: Colors.white.withValues(alpha: 0.4), blurRadius: 10)]
                    : null,
              ),
              child: Center(
                child: done
                    ? const Icon(Icons.check_rounded, size: 14, color: Color(0xFF0D6E71))
                    : Text(
                        '${i + 1}',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: active ? const Color(0xFF0D6E71) : Colors.white70,
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 4),
            if (i < 2)
              AnimatedContainer(
                duration: const Duration(milliseconds: 350),
                width: 40,
                height: 2,
                margin: const EdgeInsets.symmetric(horizontal: 4),
                decoration: BoxDecoration(
                  color: done ? Colors.white : Colors.white.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(1),
                ),
              ),
          ],
        );
      }),
    );
  }

  // ── Card wrapper ──────────────────────────────────────────────────────────
  Widget _buildCard() {
    return SlideTransition(
      position: _stageSlide,
      child: FadeTransition(
        opacity: _stageFade,
        child: Container(
          padding: const EdgeInsets.all(28),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.18),
                blurRadius: 40,
                offset: const Offset(0, 16),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Back button (not on success)
              if (_stage != _ForgotStage.success)
                GestureDetector(
                  onTap: () {
                    if (_stage == _ForgotStage.enterEmail) {
                      Navigator.pop(context);
                    } else {
                      _stageCtrl.reverse().then((_) {
                        setState(() => _stage = _ForgotStage.values[_stage.index - 1]);
                        _stageCtrl.forward();
                      });
                    }
                  },
                  child: Container(
                    width: 36, height: 36,
                    margin: const EdgeInsets.only(bottom: 16),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF5F5F5),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.arrow_back_ios_new_rounded, size: 16, color: Colors.black54),
                  ),
                ),

              Text(
                _stageTitle,
                style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Color(0xFF1A1A1A)),
              ),
              const SizedBox(height: 6),
              Text(
                _stageSubtitle,
                style: TextStyle(fontSize: 13, color: Colors.grey.shade500, height: 1.4),
              ),
              const SizedBox(height: 28),

              // Stage content
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 300),
                switchInCurve: Curves.easeOutCubic,
                switchOutCurve: Curves.easeInCubic,
                transitionBuilder: (child, anim) => FadeTransition(
                  opacity: anim,
                  child: SlideTransition(
                    position: Tween<Offset>(begin: const Offset(0.05, 0), end: Offset.zero).animate(anim),
                    child: child,
                  ),
                ),
                child: KeyedSubtree(
                  key: ValueKey(_stage),
                  child: _buildStageContent(),
                ),
              ),

              const SizedBox(height: 24),

              // CTA button
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _isLoading ? null : _onContinue,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF1E8E92),
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 250),
                    child: _isLoading
                        ? const SizedBox(
                            width: 22, height: 22,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                          )
                        : Text(
                            _buttonLabel,
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, letterSpacing: 0.5),
                          ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ── Stage content ─────────────────────────────────────────────────────────
  Widget _buildStageContent() {
    return switch (_stage) {
      _ForgotStage.enterEmail => _buildEnterEmail(),
      _ForgotStage.verifyCode => _buildVerifyCode(),
      _ForgotStage.newPassword => _buildNewPassword(),
      _ForgotStage.success => _buildSuccess(),
    };
  }

  Widget _buildEnterEmail() {
    return _inputField(
      controller: _emailCtrl,
      hint: 'Email address',
      icon: PhosphorIcons.envelopeSimple(),
      keyboardType: TextInputType.emailAddress,
    );
  }

  Widget _buildVerifyCode() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // 6-box OTP input
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: List.generate(6, (i) => _OtpBox(
            index: i,
            onChanged: (v) => setState(() => _otpDigits[i] = v),
          )),
        ),
        const SizedBox(height: 16),
        Center(
          child: TextButton(
            onPressed: () {
              setState(() => _otpDigits.fillRange(0, 6, ''));
              _showSnack('Code resent! Use 123456');
            },
            style: TextButton.styleFrom(
              padding: EdgeInsets.zero,
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: RichText(
              text: const TextSpan(
                text: "Didn't receive it? ",
                style: TextStyle(fontSize: 12, color: Colors.black45),
                children: [
                  TextSpan(
                    text: 'Resend Code',
                    style: TextStyle(color: Color(0xFF2EA9AE), fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildNewPassword() {
    return Column(
      children: [
        _inputField(
          controller: _newPassCtrl,
          hint: 'New password',
          icon: PhosphorIcons.lockSimple(),
          obscureText: _obscureNew,
          suffix: IconButton(
            onPressed: () => setState(() => _obscureNew = !_obscureNew),
            icon: PhosphorIcon(
              _obscureNew ? PhosphorIcons.eye() : PhosphorIcons.eyeSlash(),
              size: 20, color: Colors.grey.shade400,
            ),
          ),
        ),
        const SizedBox(height: 14),
        _inputField(
          controller: _confirmPassCtrl,
          hint: 'Confirm password',
          icon: PhosphorIcons.lockKey(),
          obscureText: _obscureConfirm,
          suffix: IconButton(
            onPressed: () => setState(() => _obscureConfirm = !_obscureConfirm),
            icon: PhosphorIcon(
              _obscureConfirm ? PhosphorIcons.eye() : PhosphorIcons.eyeSlash(),
              size: 20, color: Colors.grey.shade400,
            ),
          ),
        ),
        const SizedBox(height: 12),
        _PasswordStrengthBar(password: _newPassCtrl.text),
      ],
    );
  }

  Widget _buildSuccess() {
    return Center(
      child: Column(
        children: [
          ScaleTransition(
            scale: _successScale,
            child: Container(
              width: 80, height: 80,
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFF23A8AA), Color(0xFF0D6E71)]),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(color: const Color(0xFF23A8AA).withValues(alpha: 0.4), blurRadius: 20, offset: const Offset(0, 8)),
                ],
              ),
              child: const Icon(Icons.check_rounded, color: Colors.white, size: 40),
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Password Reset!',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.black87),
          ),
          const SizedBox(height: 8),
          Text(
            'You can now sign in with your new password.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: Colors.grey.shade500, height: 1.4),
          ),
        ],
      ),
    );
  }

  Widget _inputField({
    required TextEditingController controller,
    required String hint,
    required PhosphorIconData icon,
    bool obscureText = false,
    Widget? suffix,
    TextInputType? keyboardType,
  }) {
    return TextField(
      controller: controller,
      obscureText: obscureText,
      keyboardType: keyboardType,
      style: const TextStyle(fontSize: 14, color: Color(0xFF1A1A1A)),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(fontSize: 14, color: Colors.grey.shade400),
        prefixIcon: PhosphorIcon(icon, size: 20, color: Colors.grey.shade400),
        suffixIcon: suffix,
        filled: true,
        fillColor: const Color(0xFFF5F5F5),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: Colors.grey.shade200),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFF2EA9AE), width: 1.8),
        ),
      ),
    );
  }
}

// ── OTP Box ───────────────────────────────────────────────────────────────────
class _OtpBox extends StatefulWidget {
  final int index;
  final ValueChanged<String> onChanged;
  const _OtpBox({required this.index, required this.onChanged});

  @override
  State<_OtpBox> createState() => _OtpBoxState();
}

class _OtpBoxState extends State<_OtpBox> {
  static const Color primaryTeal = Color(0xFF23A8AA);
  bool _filled = false;

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      curve: Curves.easeOutCubic,
      width: 44, height: 52,
      decoration: BoxDecoration(
        color: _filled ? const Color(0xFFE0F5F5) : const Color(0xFFF5F5F5),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: _filled ? primaryTeal : Colors.grey.shade200,
          width: _filled ? 1.8 : 1,
        ),
      ),
      child: TextField(
        textAlign: TextAlign.center,
        maxLength: 1,
        keyboardType: TextInputType.number,
        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1A1A1A)),
        decoration: const InputDecoration(
          counterText: '',
          border: InputBorder.none,
          contentPadding: EdgeInsets.zero,
        ),
        onChanged: (v) {
          setState(() => _filled = v.isNotEmpty);
          widget.onChanged(v);
          if (v.isNotEmpty) FocusScope.of(context).nextFocus();
        },
      ),
    );
  }
}

// ── Password Strength Bar ─────────────────────────────────────────────────────
class _PasswordStrengthBar extends StatelessWidget {
  final String password;
  const _PasswordStrengthBar({required this.password});

  int get _strength {
    if (password.isEmpty) return 0;
    int s = 0;
    if (password.length >= 6) s++;
    if (password.length >= 10) s++;
    if (password.contains(RegExp(r'[A-Z]'))) s++;
    if (password.contains(RegExp(r'[0-9]'))) s++;
    if (password.contains(RegExp(r'[!@#\$%^&*]'))) s++;
    return s;
  }

  Color get _color => switch (_strength) {
        0 || 1 => const Color(0xFFE53935),
        2 => const Color(0xFFFF9800),
        3 => const Color(0xFFFFC107),
        _ => const Color(0xFF4CAF50),
      };

  String get _label => switch (_strength) {
        0 || 1 => 'Weak',
        2 => 'Fair',
        3 => 'Good',
        _ => 'Strong',
      };

  @override
  Widget build(BuildContext context) {
    if (password.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: List.generate(5, (i) => Expanded(
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 300),
              curve: Curves.easeOutCubic,
              height: 4,
              margin: const EdgeInsets.only(right: 4),
              decoration: BoxDecoration(
                color: i < _strength ? _color : Colors.grey.shade200,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          )),
        ),
        const SizedBox(height: 6),
        Text(
          'Password strength: $_label',
          style: TextStyle(fontSize: 11, color: _color, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}
