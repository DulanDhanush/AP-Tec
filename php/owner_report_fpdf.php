<?php
// php/owner_report_fpdf.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/fpdf/fpdf.php"; // ✅ correct path for your project

$range = (string)($_GET["range"] ?? "this_month");
$now = new DateTime("now");
$today = $now->format("Y-m-d");

function range_dates(string $range, DateTime $now): array {
    $end = clone $now;
    $endStr = $end->format("Y-m-d");

    if ($range === "ytd") {
        $start = new DateTime($now->format("Y-01-01"));
        return [$start->format("Y-m-d"), $endStr, "Year to Date"];
    }
    if ($range === "last_quarter") {
        $firstThisMonth = new DateTime($now->format("Y-m-01"));
        $start = (clone $firstThisMonth)->modify("-3 months");
        $endQ  = (clone $firstThisMonth)->modify("-1 day");
        return [$start->format("Y-m-d"), $endQ->format("Y-m-d"), "Last Quarter"];
    }

    $start = new DateTime($now->format("Y-m-01"));
    return [$start->format("Y-m-d"), $endStr, "This Month"];
}

[$startDate, $endDate, $rangeLabel] = range_dates($range, $now);

function money(float $n): string {
    return number_format($n, 2, ".", ",");
}

/* ---------------- KPIs ---------------- */

// Revenue
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount),0) AS revenue
    FROM invoices
    WHERE status='Paid'
      AND issue_date BETWEEN :s AND :e
");
$stmt->execute([":s"=>$startDate, ":e"=>$endDate]);
$revenue = (float)($stmt->fetch()["revenue"] ?? 0);

// Costs
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0) AS costs
    FROM approvals
    WHERE type='Purchase Order'
      AND status='Approved'
      AND DATE(created_at) BETWEEN :s AND :e
");
$stmt->execute([":s"=>$startDate, ":e"=>$endDate]);
$costs = (float)($stmt->fetch()["costs"] ?? 0);

$profit = $revenue - $costs;

// Outstanding
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount),0) AS outstanding
    FROM invoices
    WHERE status IN ('Unpaid','Overdue')
      AND issue_date BETWEEN :s AND :e
");
$stmt->execute([":s"=>$startDate, ":e"=>$endDate]);
$outstanding = (float)($stmt->fetch()["outstanding"] ?? 0);

// Overdue count
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS overdue_count
    FROM invoices
    WHERE (status='Overdue' OR (status='Unpaid' AND due_date < :today))
");
$stmt->execute([":today"=>$today]);
$overdueCount = (int)($stmt->fetch()["overdue_count"] ?? 0);

// Active supplier contracts
$stmt = $pdo->query("
    SELECT COUNT(*) AS active_contracts
    FROM suppliers
    WHERE contract_status='Active'
");
$activeContracts = (int)($stmt->fetch()["active_contracts"] ?? 0);

/* ---------------- Transactions (HY093-safe) ---------------- */

$limit = 15;

$trx = $pdo->prepare("
  (
    SELECT CONCAT('INV-', invoice_number) AS trx_id,
           issue_date AS trx_date,
           (SELECT full_name FROM users u WHERE u.user_id = invoices.customer_id LIMIT 1) AS party,
           'Service Payment' AS trx_type,
           total_amount AS amount,
           status AS status
    FROM invoices
    WHERE issue_date BETWEEN :s1 AND :e1
  )
  UNION ALL
  (
    SELECT CONCAT('PO-', approval_id) AS trx_id,
           DATE(created_at) AS trx_date,
           (SELECT full_name FROM users u WHERE u.user_id = approvals.requester_id LIMIT 1) AS party,
           'Inventory Purchase' AS trx_type,
           (amount * -1) AS amount,
           status AS status
    FROM approvals
    WHERE type='Purchase Order'
      AND DATE(created_at) BETWEEN :s2 AND :e2
  )
  ORDER BY trx_date DESC
  LIMIT $limit
");

$trx->execute([
    ":s1" => $startDate,
    ":e1" => $endDate,
    ":s2" => $startDate,
    ":e2" => $endDate,
]);

$rows = $trx->fetchAll();

/* ---------------- PDF Output ---------------- */

class PDF extends FPDF {
    function Header() {
        $this->SetFont("Arial","B",14);
        $this->Cell(0,8,"AP TEC - Business Intelligence Report",0,1,"L");
        $this->SetFont("Arial","",10);
        $this->Cell(0,6,"Generated: ".date("Y-m-d H:i"),0,1,"L");
        $this->Ln(3);
        $this->SetDrawColor(200,200,200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(6);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont("Arial","I",9);
        $this->Cell(0,10,"Page ".$this->PageNo()."/{nb}",0,0,"C");
    }
}

$pdf = new PDF("P","mm","A4");
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

// Range
$pdf->SetFont("Arial","B",11);
$pdf->Cell(0,7,"Range: $rangeLabel ($startDate to $endDate)",0,1,"L");
$pdf->Ln(2);

// KPI Table
$pdf->SetFont("Arial","B",10);
$pdf->Cell(48,8,"Revenue",1,0,"C");
$pdf->Cell(48,8,"Costs",1,0,"C");
$pdf->Cell(48,8,"Net Profit",1,0,"C");
$pdf->Cell(48,8,"Outstanding",1,1,"C");

$pdf->SetFont("Arial","",10);
$pdf->Cell(48,8,"$".money($revenue),1,0,"C");
$pdf->Cell(48,8,"$".money($costs),1,0,"C");
$pdf->Cell(48,8,"$".money($profit),1,0,"C");
$pdf->Cell(48,8,"$".money($outstanding),1,1,"C");

$pdf->Ln(2);
$pdf->SetFont("Arial","",10);
$pdf->Cell(0,6,"Overdue Invoices: $overdueCount",0,1,"L");
$pdf->Cell(0,6,"Active Supplier Contracts: $activeContracts",0,1,"L");

$pdf->Ln(6);

// Transactions
$pdf->SetFont("Arial","B",11);
$pdf->Cell(0,7,"Recent Transactions",0,1,"L");
$pdf->Ln(1);

$pdf->SetFont("Arial","B",9);
$pdf->Cell(28,7,"ID",1,0,"C");
$pdf->Cell(22,7,"Date",1,0,"C");
$pdf->Cell(55,7,"Client / Supplier",1,0,"C");
$pdf->Cell(35,7,"Type",1,0,"C");
$pdf->Cell(25,7,"Amount",1,0,"C");
$pdf->Cell(25,7,"Status",1,1,"C");

$pdf->SetFont("Arial","",9);

foreach ($rows as $r) {
    $id = (string)($r["trx_id"] ?? "");
    $date = (string)($r["trx_date"] ?? "");
    $party = (string)($r["party"] ?? "-");
    $type = (string)($r["trx_type"] ?? "");
    $amt = (float)($r["amount"] ?? 0);
    $status = (string)($r["status"] ?? "");

    // shorten text so it doesn't overflow
    if (mb_strlen($party) > 28) $party = mb_substr($party, 0, 28) . "...";
    if (mb_strlen($type) > 18)  $type  = mb_substr($type, 0, 18) . "...";

    $pdf->Cell(28,7,$id,1,0,"L");
    $pdf->Cell(22,7,$date,1,0,"C");
    $pdf->Cell(55,7,$party,1,0,"L");
    $pdf->Cell(35,7,$type,1,0,"L");

    // amount color
    if ($amt < 0) $pdf->SetTextColor(200,0,0);
    else          $pdf->SetTextColor(0,140,0);

    $pdf->Cell(25,7,($amt>=0?"+$":"-$").money(abs($amt)),1,0,"R");
    $pdf->SetTextColor(0,0,0);

    $pdf->Cell(25,7,$status,1,1,"C");
}

$pdf->Output("I", "AP_TEC_BI_Report.pdf");
exit;