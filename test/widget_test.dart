// This is a basic Flutter widget test.
//
// To perform an interaction with a widget in your test, use the WidgetTester
// utility in the flutter_test package. For example, you can send tap and scroll
// gestures. You can also use WidgetTester to find child widgets in the widget
// tree, read text, and verify that the values of widget properties are correct.

import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:rehabplus/main.dart';
import 'package:rehabplus/services/app_provider.dart';

void main() {
  testWidgets('App renders home screen smoke test', (WidgetTester tester) async {
    final provider = AppProvider();
    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: provider,
        child: const RehabPlusApp(),
      ),
    );
    expect(find.text('RehabPlus'), findsOneWidget);
  });
}
