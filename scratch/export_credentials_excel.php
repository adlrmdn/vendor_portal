<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$logFile = __DIR__.'/task-3347.log';
if (! file_exists($logFile)) {
    exit("Log file not found at $logFile\n");
}

$lines = file($logFile);
$data = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (str_starts_with($line, '|')) {
        $parts = explode('|', $line);
        // Clean parts
        $parts = array_map('trim', $parts);
        $parts = array_slice($parts, 1, -1);

        if (count($parts) === 4) {
            if ($parts[0] === 'Vendor Code') {
                continue; // Skip header
            }
            $data[] = $parts;
        }
    }
}

$spreadsheet = new Spreadsheet;
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Subcon Vendors');

// Set headers
$sheet->setCellValue('A1', 'Vendor Code');
$sheet->setCellValue('B1', 'Vendor Name');
$sheet->setCellValue('C1', 'Login Email');
$sheet->setCellValue('D1', 'Login Password');
$sheet->getStyle('A1:D1')->getFont()->setBold(true);

$rowNum = 2;
foreach ($data as $row) {
    $sheet->setCellValue('A'.$rowNum, $row[0]);
    $sheet->setCellValue('B'.$rowNum, $row[1]);
    $sheet->setCellValue('C'.$rowNum, $row[2]);
    $sheet->setCellValue('D'.$rowNum, $row[3]);
    $rowNum++;
}

// Auto size columns
foreach (range('A', 'D') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$outputFile = __DIR__.'/../subcon_vendors_credentials.xlsx';
$writer->save($outputFile);

echo 'Successfully wrote '.count($data)." vendors to $outputFile\n";
