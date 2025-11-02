<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/partner_earnings.php';
require_once '../includes/wallet.php';
require_once '../fpdf/fpdf.php';

requireAuth();

$txId = isset($_GET['tx']) ? (int)$_GET['tx'] : 0;
if ($txId <= 0) { http_response_code(400); echo 'Transacción inválida'; exit; }

// Fetch transaction and partner data
$stmt = $pdo->prepare("SELECT t.*, b.percentage FROM partner_wallet_transactions t JOIN partner_benefits b ON b.partner_name=t.partner_name WHERE t.id=? AND t.type='withdraw'");
$stmt->execute([$txId]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tx) { http_response_code(404); echo 'Retiro no encontrado'; exit; }

// Only allow owner/admin or super_admin. Allow super_admin for CAJA explicitly.
$__role = $_SESSION['role'] ?? 'user';
if (strcasecmp($tx['partner_name'], 'CAJA') === 0) {
  if ($__role !== 'super_admin') { http_response_code(403); echo 'No autorizado'; exit; }
} else {
  try {
    if (function_exists('requirePartnerAccess')) { requirePartnerAccess($tx['partner_name']); }
  } catch (Throwable $e) { http_response_code(403); echo 'No autorizado'; exit; }
}

$partnerName = $tx['partner_name'];
$amount = (float)$tx['amount'];
$prev = (float)$tx['previous_balance'];
$new  = (float)$tx['new_balance'];
$notes = $tx['notes'] ?? '';
$createdAt = $tx['created_at'];
$method = $tx['method'] ?? 'partner_withdraw';

// --- Ticket PDF (compact receipt) ---

class TicketPDF extends FPDF {
  public $logoPath = '';
  public $company = [];

  function Header(){
    $left = 6; $top = 6; $right = 6; $lineH = 4.5;
    $this->SetXY($left, $top);
    $logoW = 16; $logoH = 16; $gap = 4;
    $xStart = $left;
    if ($this->logoPath && file_exists($this->logoPath)) {
      $this->Image($this->logoPath, $left, $top, $logoW);
      $xStart = $left + $logoW + $gap;
    }
    // Company name
    $this->SetFont('Arial','B',12);
    $this->SetTextColor(13,71,161); // Deep blue
    $this->SetXY($xStart, $top);
    $name = isset($this->company['name']) ? $this->company['name'] : 'Abeme Modjobuy';
    $this->Cell(0, 6, utf8_decode($name), 0, 1, 'L');
    // Tagline
    $this->SetFont('Arial','',8.5);
    $this->SetTextColor(33,150,243); // Lighter blue
    $this->SetX($xStart);
    $tag = isset($this->company['tagline']) ? $this->company['tagline'] : utf8_decode('Envíos entre Ghana y Guinea Ecuatorial');
    $this->Cell(0, $lineH, utf8_decode($tag), 0, 1, 'L');
    // Contact
    $this->SetX($xStart);
    $this->SetTextColor(80,80,80);
    $contact = [];
    if (!empty($this->company['phone'])) $contact[] = 'WhatsApp: '.$this->company['phone'];
    if (!empty($this->company['website'])) $contact[] = $this->company['website'];
    if (!empty($contact)) {
      $this->Cell(0, $lineH, utf8_decode(implode(' • ', $contact)), 0, 1, 'L');
    }
    // Divider
    $this->Ln(1);
    $this->SetDrawColor(33,150,243);
    $this->SetLineWidth(0.7);
    $this->Line($left, $this->GetY(), $this->GetPageWidth() - $right, $this->GetY());
    $this->Ln(2);
    // Title
    $this->SetFont('Arial','B',11);
    $this->SetTextColor(22,197,94); // Green accent
    $this->Cell(0, 7, utf8_decode('TICKET DE RETIRO DE MONEDERO'), 0, 1, 'C');
    $this->SetTextColor(0,0,0);
    $this->Ln(1);
  }

  function Footer(){
    $this->SetY(-18);
    $this->SetFont('Arial','I',8);
    $this->SetTextColor(33,150,243);
    $msg = isset($this->company['motto']) ? $this->company['motto'] : 'Gracias por su confianza. ¡Seguimos creciendo juntos!';
    $this->MultiCell(0, 4.5, utf8_decode($msg), 0, 'C');
    $this->SetTextColor(120,120,120);
    $this->SetFont('Arial','',7);
    $this->Cell(0, 4, utf8_decode('Abeme Modjobuy • Página ').$this->PageNo().'/{nb}', 0, 0, 'C');
  }
}

// Company data (pulled from app context)
$company = [
  'name'    => 'Abeme Modjobuy',
  'tagline' => 'Envíos entre Ghana y Guinea Ecuatorial',
  'phone'   => '+240 222 374 204', // From app usage (WhatsApp)
  'website' => 'abememodjobuy.com',
  'motto'   => 'Gracias por su confianza. ¡Seguimos creciendo juntos!'
];
$logoPath = realpath(__DIR__ . '/../img/logo.png');
if ($logoPath === false) { $logoPath = ''; }

// Use wider compact receipt size (approx 100mm x 140mm) to avoid cutting text
$pdf = new TicketPDF('P','mm', [100, 140]);
$pdf->logoPath = $logoPath;
$pdf->company = $company;
$pdf->AliasNbPages();
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 18); // ensure content flows and footer space remains
$pdf->AddPage();


// --- Body with color and layout ---
$pdf->SetFont('Arial','',9);
$rowH = 5.5;

// Section: Datos del retiro
$pdf->SetFillColor(33,150,243); // blue
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',9.5);
$pdf->Cell(0,6,utf8_decode('Datos del Retiro'),0,1,'L',true);
$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0,0,0);
$pdf->SetFillColor(240,248,255);
$pdf->Cell(32,$rowH,utf8_decode('ID de retiro:'),0,0,'L',false); $pdf->Cell(0,$rowH,'#'.$txId,0,1,'L',false);
$pdf->Cell(32,$rowH,utf8_decode('Socio:'),0,0,'L',false);        $pdf->MultiCell(0,$rowH,utf8_decode($partnerName),0,'L',false);
$pdf->Cell(32,$rowH,utf8_decode('Fecha:'),0,0,'L',false);        $pdf->Cell(0,$rowH,utf8_decode(date('d/m/Y H:i', strtotime($createdAt))),0,1,'L',false);
$pdf->Cell(32,$rowH,utf8_decode('Método:'),0,0,'L',false);       $pdf->MultiCell(0,$rowH,utf8_decode($method),0,'L',false);

$pdf->Ln(1);
$pdf->SetDrawColor(22,197,94); // green accent
$pdf->SetLineWidth(0.5);
$y = $pdf->GetY();
$x1 = 6; $x2 = $pdf->GetPageWidth() - 6;
$pdf->Line($x1, $y, $x2, $y);
$pdf->Ln(2);

// Section: Montos
$pdf->SetFont('Arial','B',9.5);
$pdf->SetFillColor(22,197,94); // green
$pdf->SetTextColor(255,255,255);
$pdf->Cell(0,6,utf8_decode('Resumen de Montos'),0,1,'L',true);
$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0,0,0);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(40,$rowH,utf8_decode('Monto retirado:'),0,0,'L',false); $pdf->SetFont('Arial','B',10); $pdf->SetTextColor(22,197,94); $pdf->Cell(0,$rowH,'XAF '.number_format($amount,2,',','.'),0,1,'L',false);
$pdf->SetFont('Arial','',9); $pdf->SetTextColor(0,0,0);
$pdf->Cell(40,$rowH,utf8_decode('Saldo anterior:'),0,0,'L',false); $pdf->Cell(0,$rowH,'XAF '.number_format($prev,2,',','.'),0,1,'L',false);
$pdf->Cell(40,$rowH,utf8_decode('Saldo actual:'),0,0,'L',false);   $pdf->Cell(0,$rowH,'XAF '.number_format($new,2,',','.'),0,1,'L',false);

if ($notes !== '') {
  $pdf->Ln(2);
  $pdf->SetFont('Arial','B',9.5);
  $pdf->SetFillColor(255,193,7); // yellow
  $pdf->SetTextColor(60,60,60);
  $pdf->Cell(0,6,utf8_decode('Notas'),0,1,'L',true);
  $pdf->SetFont('Arial','',8.5);
  $pdf->SetTextColor(0,0,0);
  $pdf->MultiCell(0,4.5,utf8_decode($notes));
}

$pdf->Ln(2);
$pdf->SetFont('Arial','',7.5);
$pdf->SetTextColor(80,80,80);
$pdf->MultiCell(0,4.2,utf8_decode('Este ticket confirma un retiro efectuado desde el monedero digital del socio. Guarde este documento para su control.'));

$filename = 'Retiro_Abeme_'.$txId.'.pdf';
$pdf->Output('I', $filename);
