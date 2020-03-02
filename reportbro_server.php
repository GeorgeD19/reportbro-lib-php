<?php
require_once(__DIR__ . '/reportbro/reportbro.php');

use Ramsey\Uuid\Uuid;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$key = "";
if(isset($_GET['key'])) {
    $key = $_GET['key'];
}
if ($key != "") {
    header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
    header("Connection: keep-alive");
    header("Content-Disposition: inline; filename=\"report.pdf\"");
    header("Content-Type: application/pdf");
    header('Date: '.gmdate('D, d M Y H:i:s \G\M\T', time())); // 1 hour
    header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (60 * 60)));
    header("Pragma: no-cache");
    $file = file_get_contents(__DIR__ . '/report.pdf');
    echo $file;
} else {
    // Takes raw data from the request
    $json = file_get_contents('php://input');

    // Converts it into a PHP object
    $data = json_decode($json);
    
    try {
        $report = new Report($data->report, $data->data, $data->is_test_data);
    } catch (Exception $err) {
        echo 'failed to initialize report: ' . $err->__toString(); return;
    }

    if ($report->errors) {
        var_dump($report->errors); return;
    }

    try {
        $report_file = $report->generate_pdf();
    } catch (Exception $err) {
        echo implode($err, $report->errors); return;
    }

    $key = Uuid::uuid4();
    
    $f = fopen("report.pdf", "a");
    fwrite($f, $report_file);
    fclose($f);

    header("Content-Type: text/html");
    echo 'key:' . $key->toString();
}