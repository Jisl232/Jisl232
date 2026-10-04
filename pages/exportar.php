<?php
require_once __DIR__ . '/../includes/user_access.php';
app_require_admin('mis_casos.php');
require_once __DIR__ . '/../includes/data_store.php';

$type = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : 'resumen';
$format = is_string($_GET['formato'] ?? null) ? $_GET['formato'] : 'pdf';
$labels = [
    'resumen' => 'Resumen del bufete',
    'clientes' => 'Clientes',
    'casos' => 'Casos',
    'citas' => 'Agenda de citas',
    'contratos' => 'Contratos',
    'facturas' => 'Facturas',
    'pagos' => 'Pagos recibidos',
    'facturacion' => 'Reporte completo de facturación e ingresos',
    'respaldo' => 'Respaldo de datos del sistema',
];
$allowedFormats = $type === 'respaldo' ? ['json'] : ['pdf', 'csv'];
if (!isset($labels[$type]) || !in_array($format, $allowedFormats, true)) {
    http_response_code(400);
    exit('Solicitud de exportación no válida.');
}

if ($type === 'respaldo') {
    $backupData = [];
    foreach (app_data_types() as $dataType) {
        $backupData[$dataType] = app_data_read($dataType);
    }
    $backup = [
        'nombre' => 'Respaldo de datos del sistema',
        'generado' => date(DATE_ATOM),
        'base_de_datos' => app_data_connection()->query('SELECT DATABASE()')->fetchColumn(),
        'nota' => 'No incluye cuentas, contraseñas de la tabla abogados ni archivos adjuntos guardados fuera de MySQL.',
        'datos' => $backupData,
    ];
    $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        exit('No se pudo generar el respaldo.');
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="respaldo-bufete-' . date('Y-m-d') . '.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit();
}

$normalizeDate = static function ($value): string {
    if (!is_string($value)) {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
};
$startDate = $normalizeDate($_GET['desde'] ?? '');
$endDate = $normalizeDate($_GET['hasta'] ?? '');
if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
$recordInRange = static function (array $record) use ($startDate, $endDate): bool {
    $recordDate = '';
    foreach (['Fecha', 'fecha', 'fecha_inicio', 'created_at'] as $key) {
        if (empty($record[$key])) continue;
        $value = substr((string)$record[$key], 0, 19);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($value, 0, 10))) {
            $recordDate = substr($value, 0, 10);
            break;
        }
        $legacyDate = DateTimeImmutable::createFromFormat('!d/m/Y', $value);
        if ($legacyDate) {
            $recordDate = $legacyDate->format('Y-m-d');
            break;
        }
    }
    if (($startDate !== '' || $endDate !== '') && $recordDate === '') return false;
    return ($startDate === '' || $recordDate >= $startDate) && ($endDate === '' || $recordDate <= $endDate);
};

$rows = [];
if ($type === 'resumen') {
    foreach (['clientes', 'casos', 'citas', 'contratos', 'facturas', 'pagos'] as $dataType) {
        $records = array_values(array_filter(app_data_read($dataType), $recordInRange));
        $rows[] = ['Módulo' => ucfirst($dataType), 'Registros en el período' => count($records)];
    }
} elseif ($type === 'facturacion') {
    $invoices = app_data_read('facturas');
    $payments = app_data_read('pagos');
    $paidByInvoice = [];
    foreach ($payments as $payment) {
        $number = $payment['numero_factura'] ?? '';
        $paidByInvoice[$number] = ($paidByInvoice[$number] ?? 0) + (float)($payment['importe'] ?? 0);
    }
    foreach ($invoices as $index => $invoice) {
        $number = $invoice['numero'] ?? 'LEG-' . str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT);
        $total = (float)($invoice['total'] ?? $invoice['importe'] ?? 0);
        $paid = (float)($paidByInvoice[$number] ?? 0);
        $balance = max(0, round($total - $paid, 2));
        $status = $invoice['estado'] ?? 'Pendiente';
        if ($status !== 'Borrador' && $status !== 'Anulada' && $total > 0 && $balance <= 0.001) {
            $status = 'Pagada';
        } elseif ($status !== 'Borrador' && $status !== 'Anulada' && !empty($invoice['fecha_vencimiento']) && $invoice['fecha_vencimiento'] < date('Y-m-d')) {
            $status = 'Vencida';
        }
        $rows[] = [
            'Tipo' => 'Factura',
            'ID' => $number,
            'Cliente' => $invoice['cliente'] ?? '',
            'Caso o contrato' => $invoice['caso_contrato'] ?? '',
            'Fecha' => $invoice['fecha'] ?? '',
            'Total' => number_format($total, 2, '.', ''),
            'Pagado' => number_format($paid, 2, '.', ''),
            'Saldo' => number_format($balance, 2, '.', ''),
            'Estado' => $status,
        ];
    }
    foreach ($payments as $payment) {
        $rows[] = [
            'Tipo' => 'Pago',
            'ID' => $payment['numero_factura'] ?? '',
            'Cliente' => $payment['cliente'] ?? '',
            'Caso o contrato' => $payment['caso_contrato'] ?? '',
            'Fecha' => $payment['fecha'] ?? '',
            'Total' => '',
            'Pagado' => number_format((float)($payment['importe'] ?? 0), 2, '.', ''),
            'Saldo' => '',
            'Estado' => $payment['metodo'] ?? '',
        ];
    }
} elseif ($type !== 'resumen') {
    $rows = app_data_read($type);
}
if ($type !== 'resumen') {
    $rows = array_values(array_filter($rows, $recordInRange));
}
$filename = $type . '-' . date('Y-m-d');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    if ($rows) {
        fputcsv($output, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }
    } else {
        fputcsv($output, ['Reporte', 'Registros']);
        fputcsv($output, [$labels[$type], count($rows)]);
    }
    fclose($output);
    exit();
}

$lines = [$labels[$type], 'Fecha de exportacion: ' . date('d/m/Y H:i'), ''];
if ($type === 'resumen') {
    foreach ($rows as $row) {
        $lines[] = $row['Módulo'] . ': ' . $row['Registros en el período'];
    }
} elseif (!$rows) {
    $lines[] = 'Todavia no hay registros guardados en este modulo.';
} else {
    foreach ($rows as $index => $row) {
        $parts = [];
        foreach ($row as $key => $value) {
            if ($key === 'created_by') {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'Si' : 'No';
            }
            $parts[] = ucfirst(str_replace('_', ' ', (string)$key)) . ': ' . (string)$value;
        }
        $lines[] = ($index + 1) . '. ' . implode(' | ', $parts);
    }
}

$pdfLines = [];
foreach ($lines as $line) {
    foreach (explode("\n", wordwrap($line, 88, "\n", true)) as $wrappedLine) {
        $pdfLines[] = $wrappedLine;
    }
}
$pageLines = array_chunk($pdfLines ?: ['No hay informacion para exportar.'], 48);
$pageReferences = [];
foreach ($pageLines as $index => $_lines) {
    $pageReferences[] = (4 + 2 * $index) . ' 0 R';
}
$objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [' . implode(' ', $pageReferences) . '] /Count ' . count($pageLines) . ' >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
];
foreach ($pageLines as $index => $currentPageLines) {
    $stream = "BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";
    foreach ($currentPageLines as $line) {
        $encoded = function_exists('iconv') ? iconv('UTF-8', 'Windows-1252//TRANSLIT', $line) : $line;
        $encoded = $encoded === false ? preg_replace('/[^\x20-\x7E]/', '?', $line) : $encoded;
        $escaped = str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $encoded);
        $stream .= '(' . $escaped . ") Tj\nT*\n";
    }
    $stream .= 'ET';
    $pageObject = 4 + 2 * $index;
    $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . ($pageObject + 1) . ' 0 R >>';
    $objects[] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream";
}
$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
$offsets = [0];
foreach ($objects as $index => $object) {
    $offsets[] = strlen($pdf);
    $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
}
$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
foreach (array_slice($offsets, 1) as $offset) {
    $pdf .= sprintf("%010d 00000 n \n", $offset);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
