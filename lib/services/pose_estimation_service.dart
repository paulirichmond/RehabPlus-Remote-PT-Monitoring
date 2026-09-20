import 'dart:typed_data';
import 'dart:ui' as ui;
import 'package:camera/camera.dart';
import 'package:google_mlkit_pose_detection/google_mlkit_pose_detection.dart';
import '../models/pose_data.dart';

class PoseEstimationService {
  final PoseDetector _detector = PoseDetector(
    options: PoseDetectorOptions(mode: PoseDetectionMode.stream),
  );

  bool _isProcessing = false;

  Future<PoseData?> processFrame(CameraImage image, CameraDescription camera) async {
    if (_isProcessing) return null;
    _isProcessing = true;
    try {
      final inputImage = _toInputImage(image, camera);
      if (inputImage == null) return null;
      final poses = await _detector.processImage(inputImage);
      if (poses.isEmpty) return null;
      final pose = poses.first;
      return PoseData(
        pose: pose,
        jointAngles: JointAngleCalculator.computeAngles(pose),
        imageSize: ui.Size(image.width.toDouble(), image.height.toDouble()),
      );
    } finally {
      _isProcessing = false;
    }
  }

  InputImage? _toInputImage(CameraImage image, CameraDescription camera) {
    final rotation = _rotationFromCamera(camera.sensorOrientation);
    final size = ui.Size(image.width.toDouble(), image.height.toDouble());

    if (image.format.group == ImageFormatGroup.yuv420) {
      return InputImage.fromBytes(
        bytes: _concatenatePlanes(image.planes),
        metadata: InputImageMetadata(
          size: size,
          rotation: rotation,
          format: InputImageFormat.yuv_420_888,
          bytesPerRow: image.planes[0].bytesPerRow,
        ),
      );
    } else if (image.format.group == ImageFormatGroup.bgra8888) {
      return InputImage.fromBytes(
        bytes: image.planes[0].bytes,
        metadata: InputImageMetadata(
          size: size,
          rotation: rotation,
          format: InputImageFormat.bgra8888,
          bytesPerRow: image.planes[0].bytesPerRow,
        ),
      );
    }
    return null;
  }

  Uint8List _concatenatePlanes(List<Plane> planes) {
    final allBytes = <int>[];
    for (final plane in planes) {
      allBytes.addAll(plane.bytes);
    }
    return Uint8List.fromList(allBytes);
  }

  InputImageRotation _rotationFromCamera(int sensorOrientation) {
    switch (sensorOrientation) {
      case 90: return InputImageRotation.rotation90deg;
      case 180: return InputImageRotation.rotation180deg;
      case 270: return InputImageRotation.rotation270deg;
      default: return InputImageRotation.rotation0deg;
    }
  }

  Future<void> dispose() => _detector.close();
}
