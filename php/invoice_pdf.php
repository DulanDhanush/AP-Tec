<?php
// php/invoice_pdf.php
declare(strict_types=1);

ini_set("display_errors", "0");

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/fpdf/fpdf.php";

/**
 * Supports TWO modes:
 *  A) GET  invoice_id=...   (customer downloads invoice PDF from DB)
 *  B) POST JSON             (employee/admin/owner generate invoice from JSON like before)
 */

function bad(string $msg, int $code = 400): void {
  http_response_code($code);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(["ok" => false, "error" => $msg]);
  exit;
}

function safe(string $s): string {
  // FPDF default fonts are not UTF-8; keep basic chars
  $s = preg_replace('/[^\x20-\x7E]/', '', $s);
  return $s ?? "";
}

function money(float $v): string {
  // you can change currency here if you want
  return "LKR " . number_format($v, 2);
}

class PDF extends FPDF {
  function Header() {
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

/* =========================================================
   MODE A: CUSTOMER DOWNLOAD (GET invoice_id)
   ========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["invoice_id"])) {

  // customer only (can also allow admin to download by adding roles)
  $u = require_login_api(["customer"]);

  $customerId = (int)$u["user_id"];
  $invoiceId  = (int)($_GET["invoice_id"] ?? 0);

  if ($invoiceId <= 0) {
    http_response_code(404);
    echo "Invalid invoice_id";
    exit;
  }

  // 1) Invoice must belong to this customer
  $invStmt = $pdo->prepare("
 SELECT invoice_id, invoice_number, order_id, customer_id, issue_date, due_date,
       subtotal, tax_amount, total_amount, status
FROM invoices
    WHERE invoice_id = :iid AND customer_id = :cid
    LIMIT 1
  ");
  $invStmt->execute([":iid" => $invoiceId, ":cid" => $customerId]);
  $inv = $invStmt->fetch(PDO::FETCH_ASSOC);

  if (!$inv) {
    http_response_code(404);
    echo "Invoice not found";
    exit;
  }

  $orderId = (int)$inv["order_id"];

  // 2) Customer info
  $cStmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE user_id = :cid LIMIT 1");
  $cStmt->execute([":cid" => $customerId]);
  $cust = $cStmt->fetch(PDO::FETCH_ASSOC) ?: ["full_name" => "Customer", "email" => "", "phone" => ""];

  // 3) Order info (optional but nice)
  $oStmt = $pdo->prepare("
    SELECT order_id, order_reference, order_date, status
    FROM orders
    WHERE order_id = :oid AND customer_id = :cid
    LIMIT 1
  ");
  $oStmt->execute([":oid" => $orderId, ":cid" => $customerId]);
  $order = $oStmt->fetch(PDO::FETCH_ASSOC) ?: ["order_reference" => "ORD-" . $orderId, "order_date" => date("Y-m-d"), "status" => ""];

  // 4) Items
  $itemsStmt = $pdo->prepare("
    SELECT
      COALESCE(i.item_name, 'Item') AS description,
      oi.quantity AS qty,
      COALESCE(oi.price_at_purchase, i.unit_price, 0) AS price
    FROM order_items oi
    LEFT JOIN inventory i ON i.item_id = oi.item_id
    WHERE oi.order_id = :oid
    ORDER BY oi.order_item_id ASC
  ");
  $itemsStmt->execute([":oid" => $orderId]);
  $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

  // Build clean items
  $cleanItems = [];
  foreach ($items as $it) {
    $desc = trim((string)($it["description"] ?? ""));
    $qty  = (float)($it["qty"] ?? 0);
    $price= (float)($it["price"] ?? 0);
    if ($desc === "" || $qty <= 0) continue;
    $cleanItems[] = ["description" => $desc, "qty" => $qty, "price" => $price];
  }

  // totals
  $taxRate = isset($inv["tax_rate"]) ? (float)$inv["tax_rate"] : 0.0;
  $subtotal = 0.0;
  foreach ($cleanItems as $it) $subtotal += $it["qty"] * $it["price"];
  $tax = $subtotal * $taxRate;
  $total = $subtotal + $tax;

  $invoiceNo = (string)($inv["invoice_no"] ?? ("INV-" . $invoiceId));
  $issueDate = (string)($inv["issued_at"] ?? date("Y-m-d"));
  $dueDate   = (string)($inv["due_date"] ?? "");

  $client  = (string)($cust["full_name"] ?? "Client");
  $address = ""; // if you have address column, load it here

  // ---------- PDF ----------
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
  $pdf->Cell(0,6,safe(substr($issueDate,0,10)),0,1,'L');

  $pdf->Cell(120,6,'',0,0);
  $pdf->SetFont('Arial','B',11);
  $pdf->Cell(35,6,'Due Date:',0,0,'R');
  $pdf->SetFont('Arial','',11);
  $pdf->Cell(0,6,safe($dueDate !== "" ? substr($dueDate,0,10) : "-"),0,1,'L');

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

  // Order reference (nice for your system)
  $pdf->Ln(2);
  $pdf->SetFont('Arial','B',10);
  $pdf->Cell(0,6,'Order Ref: #' . safe((string)$order["order_reference"]),0,1,'L');
  $pdf->SetFont('Arial','',10);
  $pdf->Cell(0,6,'Order Date: ' . safe(substr((string)$order["order_date"],0,10)),0,1,'L');

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

  if (count($cleanItems) === 0) {
    $pdf->Cell($wDesc + $wQty + $wPrice + $wTot,8,'No items found for this invoice.',1,1,'L');
  } else {
    foreach ($cleanItems as $it) {
      $lineTotal = $it["qty"] * $it["price"];

      $x = $pdf->GetX();
      $y = $pdf->GetY();

      $desc = safe($it["description"]);
      $pdf->MultiCell($wDesc,6,$desc,1,'L');
      $h = $pdf->GetY() - $y;

      $pdf->SetXY($x + $wDesc, $y);
      $pdf->Cell($wQty,$h,number_format($it["qty"],2),1,0,'R');
      $pdf->Cell($wPrice,$h,money((float)$it["price"]),1,0,'R');
      $pdf->Cell($wTot,$h,money((float)$lineTotal),1,1,'R');
    }
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
  header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');
  $pdf->Output("D", $filename . ".pdf");
  exit;
}

/* =========================================================
   MODE B: STAFF JSON GENERATION (POST) - YOUR OLD VERSION
   ========================================================= */

// protect: employee/admin/owner (same as your old file)
$u = require_login_api(["employee", "admin", "owner"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  bad("METHOD_NOT_ALLOWED", 405);
}

$raw = file_get_contents("php://input");
$data = json_decode($raw ?: "{}", true);
if (!is_array($data)) bad("INVALID_JSON");

$invoiceNo = trim((string)($data["invoice_no"] ?? "INV-0000"));
$issueDate = trim((string)($data["issue_date"] ?? ""));
$dueDate   = trim((string)($data["due_date"] ?? ""));
$client    = trim((string)($data["client"] ?? "Client"));
$address   = trim((string)($data["address"] ?? ""));
$taxRate   = (float)($data["tax_rate"] ?? 0.10);

$items = $data["items"] ?? [];
if (!is_array($items) || count($items) === 0) bad("NO_ITEMS");

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

// ---------- PDF ----------
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

  $x = $pdf->GetX();
  $y = $pdf->GetY();

  $desc = safe($it["description"]);
  $pdf->MultiCell($wDesc,6,$desc,1,'L');
  $h = $pdf->GetY() - $y;

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
header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');
$pdf->Output("D", $filename . ".pdf");
exit;