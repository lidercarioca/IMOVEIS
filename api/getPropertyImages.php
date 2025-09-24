<?php
// api/getPropertyImages.php
header('Content-Type: application/json');
$id = isset($_GET['id']) ? preg_replace('/[^0-9]/', '', $_GET['id']) : '';
if (!$id) {
  echo json_encode([]);
  exit;
}
$dir = __DIR__ . '/../assets/imagens/' . $id . '/';
$images = [];
if (is_dir($dir)) {
  foreach (scandir($dir) as $file) {
    if ($file === '.' || $file === '..') continue;
    if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
      $images[] = '/assets/imagens/' . $id . '/' . $file;
    }
  }
}
echo json_encode($images);
