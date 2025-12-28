<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Ods;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header
$headers = ['Nom', 'Code', 'CodeCirconscription', 'Adresse', 'Latitude', 'Longitude'];
foreach ($headers as $k => $v) {
    $sheet->setCellValue([$k + 1, 1], $v);
}

// Data
// Format: Nom, Code, CodeCirco, Adresse, Lat, Lon
$data = [
    ['Ecole Primaire Publique Gbégamey', 'EPP-GBE', 'LIT', 'Rue des cheminots', '6.3645', '2.4256'], // Valide (si LIT existe)
    ['CEG Sainte Rita', 'CEG-RITA', 'LIT', 'Cotonou', '6.3700', '2.4300'], // Valide
    ['Centre Inconnu', 'ERR-01', 'ZZZ99', 'Nulle part', '', ''], // ERREUR: Circo inconnue
    ['Centre Doublon', 'EPP-GBE', 'LIT', 'Doublon du code', '', ''], // ERREUR: Doublon code (si EPP-GBE passe en premier)
    ['', '', 'LIT', '', '', ''], // ERREUR: Données manquantes
];

$row = 2;
foreach ($data as $item) {
    $col = 1;
    foreach ($item as $val) {
        $sheet->setCellValue([$col, $row], $val);
        $col++;
    }
    $row++;
}

$writer = new Ods($spreadsheet);
$file = __DIR__ . '/centres_test.ods';
$writer->save($file);

echo "Fichier généré : $file\n";
