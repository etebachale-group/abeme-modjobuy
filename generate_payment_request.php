<?php
require('fpdf/fpdf.php'); // Path to FPDF library

if (isset($_GET['partner_name']) && isset($_GET['amount'])) {
    $partnerName = htmlspecialchars($_GET['partner_name']);
    $amount = floatval($_GET['amount']);

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, utf8_decode('Solicitud de Pago de Beneficios'), 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, utf8_decode('Estimado/a ' . $partnerName . ','), 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Por medio de este documento, se solicita el pago de sus beneficios acumulados.'), 0, 1);
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, utf8_decode('Monto a Pagar: XAF ' . number_format($amount, 2, ',', '.')), 0, 1);
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 10, utf8_decode('Fecha de Solicitud: ' . date('d/m/Y')), 0, 1);
    $pdf->Cell(0, 10, utf8_decode('Por favor, contacte a la administración para coordinar el pago.'), 0, 1);

    $filename = 'Solicitud_Pago_' . str_replace(' ', '_', $partnerName) . '_' . date('Ymd') . '.pdf';
    $pdf->Output('D', $filename); // 'D' for download
} else {
    echo "Parámetros insuficientes para generar la solicitud de pago.";
}
?>