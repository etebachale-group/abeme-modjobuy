<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
// Asumimos que FPDF está en una carpeta 'fpdf'
require_once 'fpdf/fpdf.php';

// Verificar si se proporcionó un ID de envío
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de envío no válido.');
}

$shipment_id = intval($_GET['id']);

// Obtener los datos del envío desde la base de datos
$shipment = getShipmentById($pdo, $shipment_id);

if (!$shipment) {
    die('Envío no encontrado.');
}

class PDF extends FPDF
{
    protected $primaryColor;
    protected $secondaryColor;
    protected $accentColor;
    protected $textColor;

    function __construct()
    {
        parent::__construct('P', 'mm', array(210, 297)); // A4 optimizado
        // Definir colores de la paleta
        $this->primaryColor = array(26, 77, 46); // #1a4d2e
        $this->secondaryColor = array(45, 90, 39); // #2d5a27
        $this->accentColor = array(251, 176, 52); // #fbb034
        $this->textColor = array(44, 62, 80); // #2c3e50
    }

    // Cabecera de página
    function Header()
    {
        // Fondo del encabezado con degradado
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Rect(0, 0, 210, 40, 'F');
        
        // Logo con fondo circular blanco
        if (file_exists('img/logo.png')) {
            // Círculo blanco para el logo
            $this->SetFillColor(255, 255, 255);
            $this->Circle(25, 20, 15, 'F');
            // Logo
            $this->Image('img/logo.png', 10, 5, 30);
        }
        
        // Nombre de la empresa
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(15);
        $this->Cell(210, 10, 'ABEME MODJOBUY', 0, 0, 'C');
        
        // Línea decorativa con el color de acento
        $this->SetDrawColor($this->accentColor[0], $this->accentColor[1], $this->accentColor[2]);
        $this->SetLineWidth(0.5);
        $this->Line(40, 38, 170, 38);
    }

    // Método para dibujar un círculo
    function Circle($x, $y, $r, $style='D')
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    // Método para dibujar una elipse
    function Ellipse($x, $y, $rx, $ry, $style='D')
    {
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';

        $this->_out(sprintf('%.2F %.2F m',($x+$rx)*$this->k,($this->h-$y)*$this->k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', 
            ($x+$rx)*$this->k,($this->h-($y-$ry))*$this->k,
            ($x+$rx)*$this->k,($this->h-($y+$ry))*$this->k,
            $x*$this->k,($this->h-($y+$ry))*$this->k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', 
            ($x-$rx)*$this->k,($this->h-($y+$ry))*$this->k,
            ($x-$rx)*$this->k,($this->h-($y-$ry))*$this->k,
            $x*$this->k,($this->h-($y-$ry))*$this->k));
        $this->_out($op);
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-25);
        // Línea decorativa
        $this->SetDrawColor($this->accentColor[0], $this->accentColor[1], $this->accentColor[2]);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        
        $this->SetY(-20);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor($this->textColor[0], $this->textColor[1], $this->textColor[2]);
        $this->Cell(0, 10, utf8_decode('© ' . date('Y') . ' Abeme Modjobuy - Página ').$this->PageNo().'/{nb}', 0, 0, 'C');
    }

    // Celda de detalle con estilo
    function DetailCell($label, $value)
    {
        // Color de fondo suave para las etiquetas
        $this->SetFillColor(237, 240, 243);
        $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        
        // Etiqueta
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell(50, 8, utf8_decode($label), 1, 0, 'L', true);
        
        // Valor
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor($this->textColor[0], $this->textColor[1], $this->textColor[2]);
        $this->Cell(0, 8, ' ' . utf8_decode($value), 1, 1, 'L', false);
        
        // Pequeño espacio entre filas
        $this->Ln(1);
    }
    
    // Método para crear una sección
    function Section($title)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell(0, 10, utf8_decode($title), 0, 1, 'L');
        $this->SetDrawColor($this->accentColor[0], $this->accentColor[1], $this->accentColor[2]);
        $this->SetLineWidth(0.3);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 50, $this->GetY());
        $this->Ln(5);
    }
}

// Creación del objeto PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 30);

// Espacio después del encabezado
$pdf->Ln(35);

// Título del documento y código de envío
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(44, 62, 80);
$pdf->Cell(0, 10, utf8_decode('Comprobante de Envío'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('Código: ' . $shipment['code']), 0, 1, 'C');
$pdf->Ln(10);

// Sección de datos del remitente
$pdf->Section('Datos del Remitente');
$pdf->DetailCell('Nombre:', $shipment['sender_name']);
$pdf->DetailCell('Teléfono:', $shipment['sender_phone']);
$pdf->Ln(5);

// Sección de datos del destinatario
$pdf->Section('Datos del Destinatario');
$pdf->DetailCell('Nombre:', $shipment['receiver_name']);
$pdf->DetailCell('Teléfono:', $shipment['receiver_phone']);
$pdf->Ln(5);

// Sección de información del paquete
$pdf->Section('Detalles del Envío');

$pdf->DetailCell('Producto:', $shipment['product']);
$pdf->DetailCell('Peso:', $shipment['weight'] . ' kg');
$pdf->DetailCell('Precio Total:', number_format($shipment['sale_price'], 0, ',', '.') . ' XAF');
$pdf->DetailCell('Pago Adelantado:', number_format($shipment['advance_payment'], 2, ',', '.') . ' XAF');
$pdf->DetailCell('Saldo Pendiente:', number_format($shipment['sale_price'] - $shipment['advance_payment'], 2, ',', '.') . ' XAF');
$pdf->DetailCell('Fecha de Envío:', date("d/m/Y", strtotime($shipment['ship_date'])));
$pdf->DetailCell('Fecha Estimada:', date("d/m/Y", strtotime($shipment['est_date'])));
$pdf->DetailCell('Estado:', ucfirst($shipment['status']));

// Agregar código QR o barras si se desea implementar en el futuro
$pdf->Ln(10);

// Nota legal
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(108, 117, 125);
$pdf->MultiCell(0, 4, utf8_decode('Este documento es un comprobante oficial de envío de Abeme Modjobuy. La información contenida en este documento es confidencial y está protegida por las leyes aplicables.'), 0, 'C');

// Salida del PDF
// 'D' para forzar la descarga, 'I' para mostrar en el navegador
$pdf->Output('I', 'envio_'.$shipment['code'].'.pdf');
?>