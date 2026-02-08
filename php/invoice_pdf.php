<?php
// php/invoice_pdf.php
declare(strict_types=1);

ini_set("display_errors", "0");

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

// protect: employee/admin/owner (change if you want)
$u = require_login_api(["employee", "admin", "owner"]);

require_once __DIR__ . "/fpdf/fpdf.php";

function bad(string $msg, int $code = 400): void {
  http_response_code($code);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(["ok" => false, "error" => $msg]);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  bad("METHOD_NOT_ALLOWED", 405);
}

// Read JSON
$raw = file_get_contents("php://input");
$data = json_decode($raw ?: "{}", true);
if (!is_array($data)) bad("INVALID_JSON");

$invoiceNo = trim((string)($data["invoice_no"] ?? "INV-0000"));
$issueDate = trim((string)($data["issue_date"] ?? ""));
$dueDate   = trim((string)($data["due_date"] ?? ""));
$client    = trim((string)($data["client"] ?? "Client"));
$address   = trim((string)($data["address"] ?? "")); // optional
$taxRate   = (float)($data["tax_rate"] ?? 0.10);

$items = $data["items"] ?? [];
if (!is_array($items) || count($items) === 0) {
  bad("NO_ITEMS");
}

// Sanitize items
$cleanItems = [];
foreach ($items as $it) {
  if (!is_array($it)) continue;
  $desc = trim((string)($it["description"] ?? ""));
  $qty  = (float)($it["qty"] ?? 0);
  $price = (float)($it["price"] ?? 0);
  if ($desc === "" || $qty <= 0) continue;
  $cleanItems[] = ["description" => $desc, "qty" => $qty, "price" => $price];
}
if (count($cleanItems) === 0) bad("NO_VALID_ITEMS");

// totals
$subtotal = 0.0;
foreach ($cleanItems as $it) $subtotal += $it["qty"] * $it["price"];
$tax = $subtotal * $taxRate;
$total = $subtotal + $tax;

function money(float $v): string {
  return "LKR " . number_format($v, 2);
}

function safe(string $s): string {
  // FPDF default fonts are not UTF-8; keep basic chars
  $s = preg_replace('/[^\x20-\x7E]/', '', $s);
  return $s ?? "";
}

class PDF extends FPDF {
  function Header() {
    // Title
    $this->SetFont('Arial','B',18);
    $this->Cell(0,10,'INVOICE',0,1,'L');
    $this->SetFont('Arial','',10);
    $this->SetTextColor(80,80,80);
    $this->Cell(0,6,'AP Tec Management Systems',0,1,'L');
    $this->Ln(4);
    $this->SetTextColor(0,0,0);
  }
  function Footer() {
    $this->SetY(-18);
    $this->SetFont('Arial','',9);
    $this->SetTextColor(120,120,120);
    $this->Cell(0,6,'Thank you for your business!',0,1,'C');
    $this->Cell(0,6,'AP Tec Services | support@aptec.com | +94 11 234 5678',0,0,'C');
  }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 28);

// Meta (invoice no + date)
$pdf->SetFont('Arial','',11);
$pdf->Cell(120,6,'',0,0);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(35,6,'Invoice #:',0,0,'R');
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,6,safe($invoiceNo),0,1,'L');

$pdf->Cell(120,6,'',0,0);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(35,6,'Issue Date:',0,0,'R');
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,6,safe($issueDate),0,1,'L');

$pdf->Cell(120,6,'',0,0);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(35,6,'Due Date:',0,0,'R');
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,6,safe($dueDate),0,1,'L');

$pdf->Ln(6);

// Bill To
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,7,'Bill To:',0,1,'L');
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,7,safe($client),0,1,'L');
$pdf->SetFont('Arial','',10);
if ($address !== "") {
  $pdf->MultiCell(0,5,safe($address),0,'L');
} else {
  $pdf->SetTextColor(120,120,120);
  $pdf->MultiCell(0,5,'(Address not provided)',0,'L');
  $pdf->SetTextColor(0,0,0);
}

$pdf->Ln(6);

// Table header
$wDesc = 95;
$wQty  = 20;
$wPrice= 35;
$wTot  = 40;

$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(240,240,240);
$pdf->Cell($wDesc,8,'Description',1,0,'L',true);
$pdf->Cell($wQty,8,'Qty',1,0,'R',true);
$pdf->Cell($wPrice,8,'Price',1,0,'R',true);
$pdf->Cell($wTot,8,'Total',1,1,'R',true);

$pdf->SetFont('Arial','',10);

foreach ($cleanItems as $it) {
  $lineTotal = $it["qty"] * $it["price"];

  // Description cell with MultiCell needs manual row height calculation
  $x = $pdf->GetX();
  $y = $pdf->GetY();

  $desc = safe($it["description"]);
  $pdf->MultiCell($wDesc,6,$desc,1,'L');
  $h = $pdf->GetY() - $y;

  // Right side cells aligned to same row height
  $pdf->SetXY($x + $wDesc, $y);
  $pdf->Cell($wQty,$h,number_format($it["qty"],2),1,0,'R');
  $pdf->Cell($wPrice,$h,money((float)$it["price"]),1,0,'R');
  $pdf->Cell($wTot,$h,money((float)$lineTotal),1,1,'R');
}

// Totals
$pdf->Ln(6);
$pdf->SetFont('Arial','',11);

$pdf->Cell(150,7,'Subtotal:',0,0,'R');
$pdf->Cell(0,7,money($subtotal),0,1,'R');

$pdf->Cell(150,7,'Tax ('.(int)round($taxRate*100).'%) :',0,0,'R');
$pdf->Cell(0,7,money($tax),0,1,'R');

$pdf->SetFont('Arial','B',12);
$pdf->Cell(150,8,'Total:',0,0,'R');
$pdf->Cell(0,8,money($total),0,1,'R');

// Output PDF (download)
$filename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoiceNo) ?: "invoice";
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename={$filename}.pdf");
$pdf->Output("I", "{$filename}.pdf");
exit;