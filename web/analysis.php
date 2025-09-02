<?php

$dir = dirname(__FILE__);
require_once "../conf/general";
require_once "../commonlib/phplib/utility.php";

$token = get_http_var('token');
$file = basename(get_http_var('file'));

function not_found() {
  http_response_code(404);
  include('404.html');
  die();
}

if (!$token || !defined('OPTION_DATA_TOKEN') || !defined('OPTION_EXPORT_DATA_DIR') || !$file || $token != OPTION_DATA_TOKEN) {
  not_found();
}

$data_dir = dirname(__FILE__) . '/../' . OPTION_EXPORT_DATA_DIR;
$file_path = $data_dir . $file;

if (file_exists($file_path)) {
  $mime_type = mime_content_type($file_path);
  if (str_ends_with($file, 'parquet')) {
    $mime_type = 'application/vnd.apache.parquet';
  }
  header("Content-Disposition: attachment; filename=$file;");
  header("Content-Type: $mime_type");
  header('Content-Length: ' . filesize($file_path));

  $fp = fopen($file_path, 'rb');
  fpassthru($fp);
  exit();
} else {
  not_found();
}
?>
