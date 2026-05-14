<?php

function prescriptionPdfNormalizeText($value) {
    $text = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', trim($text));

    if ($text === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false && $converted !== '') {
            $text = $converted;
        }
    }

    return preg_replace('/[^\x20-\x7E]/', '', $text);
}

function prescriptionPdfEscape($value) {
    return str_replace(
        ['\\', '(', ')'],
        ['\\\\', '\(', '\)'],
        prescriptionPdfNormalizeText($value)
    );
}

function prescriptionPdfWrapLines($text, $width) {
    $normalized = prescriptionPdfNormalizeText($text);
    if ($normalized === '') {
        return [''];
    }

    return explode("\n", wordwrap($normalized, $width, "\n", true));
}

function createPrescriptionPdf($doctorName, $patientName, $medicineName, $dosage, $medicineTimes, $durationDays) {
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'prescriptions';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        return false;
    }

    $timeLabels = [];
    foreach ((array) $medicineTimes as $time) {
        $timeLabels[] = ucfirst(prescriptionPdfNormalizeText($time));
    }

    $safeBase = strtolower($patientName . '-' . $medicineName);
    $safeBase = preg_replace('/[^a-z0-9]+/', '-', $safeBase);
    $safeBase = trim((string) $safeBase, '-');
    if ($safeBase === '') {
        $safeBase = 'prescription';
    }

    $filename = date('Ymd_His') . '_' . $safeBase . '.pdf';
    $diskPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    $prescriptionId = strtoupper(substr(sha1($filename . '|' . $patientName . '|' . $medicineName), 0, 10));
    $issuedOn = date('d M Y h:i A');
    $reminderText = !empty($timeLabels) ? implode(', ', $timeLabels) : 'Not specified';
    $durationText = max(1, (int) $durationDays) . ' day(s)';

    $streamLines = [
        '0.07 0.72 0.63 rg',
        '40 770 515 42 re',
        'f',
        '0.07 0.72 0.63 RG',
        '40 756 m',
        '555 756 l',
        'S',
    ];

    $headerBlocks = [
        ['font' => 'F2', 'size' => 20, 'x' => 55, 'y' => 787, 'width' => 38, 'text' => 'TELEMEDICINE Prescription'],
        ['font' => 'F1', 'size' => 10, 'x' => 55, 'y' => 771, 'width' => 70, 'text' => 'Digital consultation prescription copy'],
        ['font' => 'F2', 'size' => 10, 'x' => 385, 'y' => 787, 'width' => 28, 'text' => 'Prescription ID'],
        ['font' => 'F1', 'size' => 11, 'x' => 385, 'y' => 771, 'width' => 30, 'text' => $prescriptionId],
    ];

    foreach ($headerBlocks as $block) {
        foreach (prescriptionPdfWrapLines($block['text'], $block['width']) as $line) {
            $streamLines[] = 'BT';
            $streamLines[] = '/' . $block['font'] . ' ' . (int) $block['size'] . ' Tf';
            $streamLines[] = sprintf('1 0 0 1 %.2f %.2f Tm', $block['x'], $block['y']);
            $streamLines[] = '(' . prescriptionPdfEscape($line) . ') Tj';
            $streamLines[] = 'ET';
            $block['y'] -= 12;
        }
    }

    $sections = [
        [
            'title' => 'Doctor Details',
            'rows' => [
                ['label' => 'Doctor', 'value' => 'Dr. ' . $doctorName],
                ['label' => 'Issued On', 'value' => $issuedOn],
                ['label' => 'Platform', 'value' => 'TELEMEDICINE'],
            ],
        ],
        [
            'title' => 'Patient Details',
            'rows' => [
                ['label' => 'Patient', 'value' => $patientName],
                ['label' => 'Prescription Type', 'value' => 'E-Prescription'],
            ],
        ],
        [
            'title' => 'Medicine Details',
            'rows' => [
                ['label' => 'Medicine', 'value' => $medicineName],
                ['label' => 'Dosage', 'value' => $dosage],
                ['label' => 'Daily Reminders', 'value' => $reminderText],
                ['label' => 'Duration', 'value' => $durationText],
            ],
        ],
        [
            'title' => 'Instructions',
            'rows' => [
                ['label' => 'Important', 'value' => 'Please follow the doctor instructions exactly and continue the course for the full prescribed duration.'],
                ['label' => 'Note', 'value' => 'This digital prescription was generated from your TELEMEDICINE consultation and can be used as your prescription copy.'],
            ],
        ],
    ];

    $currentY = 728;
    foreach ($sections as $section) {
        $streamLines[] = '0.10 0.14 0.24 rg';
        $streamLines[] = 'BT';
        $streamLines[] = '/F2 13 Tf';
        $streamLines[] = sprintf('1 0 0 1 50 %.2f Tm', $currentY);
        $streamLines[] = '(' . prescriptionPdfEscape($section['title']) . ') Tj';
        $streamLines[] = 'ET';
        $currentY -= 18;

        $streamLines[] = '0.85 0.90 0.93 RG';
        $streamLines[] = sprintf('50 %.2f m', $currentY + 6);
        $streamLines[] = sprintf('545 %.2f l', $currentY + 6);
        $streamLines[] = 'S';

        foreach ($section['rows'] as $row) {
            $streamLines[] = '0.34 0.40 0.48 rg';
            $streamLines[] = 'BT';
            $streamLines[] = '/F2 10 Tf';
            $streamLines[] = sprintf('1 0 0 1 55 %.2f Tm', $currentY - 10);
            $streamLines[] = '(' . prescriptionPdfEscape($row['label']) . ') Tj';
            $streamLines[] = 'ET';

            $valueY = $currentY - 25;
            foreach (prescriptionPdfWrapLines($row['value'], 72) as $line) {
                $streamLines[] = '0.08 0.10 0.16 rg';
                $streamLines[] = 'BT';
                $streamLines[] = '/F1 11 Tf';
                $streamLines[] = sprintf('1 0 0 1 55 %.2f Tm', $valueY);
                $streamLines[] = '(' . prescriptionPdfEscape($line) . ') Tj';
                $streamLines[] = 'ET';
                $valueY -= 15;
            }

            $currentY = $valueY - 8;
        }

        $currentY -= 6;
    }

    $footerText = 'Generated electronically by TELEMEDICINE. Re-download anytime from your patient portal.';
    foreach (prescriptionPdfWrapLines($footerText, 88) as $index => $line) {
        $streamLines[] = '0.42 0.47 0.53 rg';
        $streamLines[] = 'BT';
        $streamLines[] = '/F1 9 Tf';
        $streamLines[] = sprintf('1 0 0 1 50 %.2f Tm', 70 - ($index * 12));
        $streamLines[] = '(' . prescriptionPdfEscape($line) . ') Tj';
        $streamLines[] = 'ET';
    }

    $contentStream = implode("\n", $streamLines) . "\n";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
        4 => "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream",
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= $objectNumber . " 0 obj\n" . $objectBody . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    if (file_put_contents($diskPath, $pdf) === false) {
        return false;
    }

    return [
        'filename' => $filename,
        'db_path' => '../uploads/prescriptions/' . $filename,
        'public_path' => 'uploads/prescriptions/' . $filename,
    ];
}
