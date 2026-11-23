  import 'package:flutter/material.dart';
  import 'package:firebase_core/firebase_core.dart';
  import 'firebase_options.dart';

  void main() async {
    WidgetsFlutterBinding.ensureInitialized();
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
    runApp(const MyApp());
  }

  class MyApp extends StatelessWidget {
    const MyApp({super.key});

    @override
    Widget build(BuildContext context) {
      // JANGAN return context.widget; di sini!
      return MaterialApp(
        title: 'Meet 8 Firebase',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
          useMaterial3: true,
        ),
        home: const HomePage(), // Ganti dengan nama halaman/widget utama kamu
      );
    }
  }

  // Contoh halaman utama jika belum dibuat
  class HomePage extends StatelessWidget {
    const HomePage({super.key});

    @override
    Widget build(BuildContext context) {
      return Scaffold(
        appBar: AppBar(title: const Text('Firebase Connected')),
        body: const Center(
          child: Text('Aplikasi Berhasil Berjalan!'),
        ),
      );
    }
  }