import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class FcmTokenCard extends StatefulWidget {
  const FcmTokenCard({super.key});

  @override
  State<FcmTokenCard> createState() => _FcmTokenCardState();
}

class _FcmTokenCardState extends State<FcmTokenCard> {
  late Future<String?> _tokenFuture;

  @override
  void initState() {
    super.initState();
    _tokenFuture = _fetchToken();
  }

  Future<String?> _fetchToken() async {
    await FirebaseMessaging.instance.requestPermission();
    return await FirebaseMessaging.instance.getToken();
  }

  void _refreshToken() {
    setState(() {
      _tokenFuture = _fetchToken();
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<String?>(
      future: _tokenFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }

        if (snapshot.hasError) {
          return Card(
            color: Colors.red.shade50,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Gagal mengambil FCM Token:',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: Colors.red,
                    ),
                  ),
                  const SizedBox(height: 8),
                  SelectableText(
                    '${snapshot.error}',
                    style: const TextStyle(fontSize: 12, color: Colors.red),
                  ),
                  const SizedBox(height: 8),
                  ElevatedButton.icon(
                    icon: const Icon(Icons.refresh),
                    label: const Text('Coba Lagi'),
                    onPressed: _refreshToken,
                  ),
                ],
              ),
            ),
          );
        }

        final token = snapshot.data ?? 'Token tidak tersedia';
        return Card(
          elevation: 4,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'FCM Token:',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                SelectableText(token, style: const TextStyle(fontSize: 12)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    ElevatedButton.icon(
                      icon: const Icon(Icons.copy),
                      label: const Text('Salin Token'),
                      onPressed: snapshot.data == null
                          ? null
                          : () {
                              Clipboard.setData(ClipboardData(text: token));
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Token disalin!')),
                              );
                            },
                    ),
                    const SizedBox(width: 8),
                    IconButton(
                      icon: const Icon(Icons.refresh),
                      tooltip: 'Refresh Token',
                      onPressed: _refreshToken,
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}