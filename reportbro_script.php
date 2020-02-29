<?php 
require('./reportbro/reportbro.php');

$reports = array();
$dir = 'demo';
$files = scandir($dir);
foreach($files as $file) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if ($ext == 'json') {
        array_push($reports, $dir . '/' . str_replace('.' . $ext, '', $file));
    }
}

foreach ($reports as $file) {
    if (!file_exists($file . ".json")) {
        echo $file . ".json missing"; return;
    }
    $json_file = file_get_contents($file . ".json");

    $json_data = json_decode($json_file);
    if (!$json_data) {
        echo "json data invalid"; return;
    }

    $report_definition = property_exists($json_data, "report") ? $json_data->{"report"} : false;
    $output_format = property_exists($json_data, "outputFormat") ? $json_data->{"outputFormat"} : false;
    if (!in_array($output_format, array("pdf", "xlsx"))) {
        echo "outputFormat parameter missing or invalid"; return;
    }

    $data = property_exists($json_data, "data") ? $json_data->{"data"} : json_decode("{}");
    $is_test_data = boolval($data);

    try {
        $report = new Report($report_definition, $data, $is_test_data);
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

    $f = fopen($file . ".pdf", "a");
    fwrite($f, $report_file);
    fclose($f);    
}