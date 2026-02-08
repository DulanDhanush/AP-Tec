<?php
// php/owner_report.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

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

// Revenue
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS revenue FROM invoices WHERE status='Paid' AND issue_date BETWEEN :s AND :e");
$stmt->execute([":s"=>$startDate, ":e"=>$endDate]);
$revenue = (float)$stmt->fetch()["revenue"];

// Costs
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS costs FROM approvals WHERE type='Purchase Order' AND status='Approved' AND DATE(created_at) BETWEEN :s AND :e");
$stmt->execute([":s"=>$startDate, ":e"=>$endDate]);
$costs = (float)$stmt->fetch()["costs"];

$profit = $revenue - $costs;

// Outstanding
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS outstanding FROM invoices WHERE status IN ('Unpaid','Overdue') AND issue_date BETWEEN :s AND :e");
$stmt->execute([":s"=>$startDate, ":e"=>$endDate]);
$outstanding = (float)$stmt->fetch()["outstanding"];

// Overdue count
$stmt = $pdo->prepare("SELECT COUNT(*) AS overdue_count FROM invoices WHERE (status='Overdue' OR (status='Unpaid' AND due_date < :today))");
$stmt->execute([":today"=>$today]);
$overdueCount = (int)$stmt->fetch()["overdue_count"];

// Transactions (✅ HY093 FIX: unique placeholders per UNION part)
$trx = $pdo->prepare("
  (
    SELECT CONCAT('INV-', invoice_number) AS trx_id, issue_date AS trx_date,
           (SELECT full_name FROM users u WHERE u.user_id = invoices.customer_id LIMIT 1) AS party,
           'Service Payment' AS trx_type, total_amount AS amount, status AS status
    FROM invoices
    WHERE issue_date BETWEEN :s1 AND :e1
  )
  UNION ALL
  (
    SELECT CONCAT('PO-', approval_id) AS trx_id, DATE(created_at) AS trx_date,
           (SELECT full_name FROM users u WHERE u.user_id = approvals.requester_id LIMIT 1) AS party,
           'Inventory Purchase' AS trx_type, (amount * -1) AS amount, status AS status
    FROM approvals
    WHERE type='Purchase Order' AND DATE(created_at) BETWEEN :s2 AND :e2
  )
  ORDER BY trx_date DESC
  LIMIT 15
");

$trx->execute([
  ":s1" => $startDate,
  ":e1" => $endDate,
  ":s2" => $startDate,
  ":e2" => $endDate,
]);

$rows = $trx->fetchAll();

function money($n): string { return number_format((float)$n, 2, ".", ","); }
?><!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AP Tec - BI Report</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 30px; color:#111; }
    .top { display:flex; justify-content:space-between; align-items:flex-start; }
    h1 { margin:0; }
    .muted { color:#555; }
    .cards { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin:18px 0; }
    .card { border:1px solid #ddd; padding:12px; border-radius:10px; }
    table { width:100%; border-collapse:collapse; margin-top:12px; }
    th, td { border:1px solid #ddd; padding:8px; font-size: 13px; }
    th { background:#f3f3f3; text-align:left; }
    .pos { color: #0a7; font-weight: 700; }
    .neg { color: #c22; font-weight: 700; }
    @media print { .no-print { display:none; } body { margin: 10mm; } }
  </style>
</head>
<body>
  <div class="top">
    <div>
      <h1>Business Intelligence Report</h1>
      <div class="muted">Range: <b><?=htmlspecialchars($rangeLabel)?></b> (<?=htmlspecialchars($startDate)?> → <?=htmlspecialchars($endDate)?>)</div>
      <div class="muted">Generated: <?=htmlspecialchars((new DateTime())->format("Y-m-d H:i"))?></div>
    </div>
    <div class="no-print">
      <button onclick="window.print()">Print / Save as PDF</button>
    </div>
  </div>

  <div class="cards">
    <div class="card"><div class="muted">Revenue</div><div style="font-size:20px"><b>$<?=money($revenue)?></b></div></div>
    <div class="card"><div class="muted">Costs (Approved POs)</div><div style="font-size:20px"><b>$<?=money($costs)?></b></div></div>
    <div class="card"><div class="muted">Net Profit</div><div style="font-size:20px"><b>$<?=money($profit)?></b></div></div>
    <div class="card"><div class="muted">Outstanding</div><div style="font-size:20px"><b>$<?=money($outstanding)?></b><div class="muted"><?=$overdueCount?> overdue</div></div></div>
  </div>

  <h2 style="margin-bottom:6px;">Recent Transactions</h2>
  <table>
    <thead>
      <tr><th>ID</th><th>Date</th><th>Client/Supplier</th><th>Type</th><th>Amount</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $amt = (float)$r["amount"];
      ?>
      <tr>
        <td><?=htmlspecialchars((string)$r["trx_id"])?></td>
        <td><?=htmlspecialchars((string)$r["trx_date"])?></td>
        <td><?=htmlspecialchars((string)($r["party"] ?? "-"))?></td>
        <td><?=htmlspecialchars((string)$r["trx_type"])?></td>
        <td class="<?=($amt>=0?'pos':'neg')?>"><?=($amt>=0?'+$':'-$')?><?=money(abs($amt))?></td>
        <td><?=htmlspecialchars((string)$r["status"])?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>