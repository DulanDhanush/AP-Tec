<?php
// php/invoice_print.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

$u = require_login("customer");
$customerId = (int)$u["user_id"];

$invoiceId = (int)($_GET["invoice_id"] ?? 0);
if ($invoiceId <= 0) { http_response_code(400); echo "Invalid invoice."; exit; }

$st = $pdo->prepare("
  SELECT i.*, u.full_name, u.email, u.phone, u.address
  FROM invoices i
  JOIN users u ON u.user_id = i.customer_id
  WHERE i.invoice_id=:iid AND i.customer_id=:cid
  LIMIT 1
");
$st->execute([":iid" => $invoiceId, ":cid" => $customerId]);
$inv = $st->fetch(PDO::FETCH_ASSOC);
if (!$inv) { http_response_code(404); echo "Invoice not found."; exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?=h($inv["invoice_number"])?> - Invoice</title>
  <style>
    body{font-family: Arial, sans-serif; padding:30px; color:#111;}
    .top{display:flex; justify-content:space-between; align-items:flex-start; gap:20px;}
    .box{border:1px solid #ddd; padding:14px; border-radius:10px;}
    .muted{color:#555;}
    table{width:100%; border-collapse:collapse; margin-top:18px;}
    th,td{border-bottom:1px solid #eee; padding:10px; text-align:left;}
    .right{text-align:right;}
    .total{font-size:18px; font-weight:700;}
    .print{margin-top:18px;}
    @media print{ .print{display:none;} }
  </style>
</head>
<body>
  <div class="top">
    <div>
      <h2>AP TEC.</h2>
      <div class="muted">Billing Invoice</div>
    </div>
    <div class="box">
      <div><b>Invoice:</b> <?=h($inv["invoice_number"])?></div>
      <div><b>Issue Date:</b> <?=h($inv["issue_date"])?></div>
      <div><b>Due Date:</b> <?=h($inv["due_date"])?></div>
      <div><b>Status:</b> <?=h($inv["status"])?></div>
    </div>
  </div>

  <div style="margin-top:18px" class="box">
    <div><b>Billed To:</b> <?=h($inv["full_name"])?></div>
    <div class="muted"><?=h($inv["email"])?><?= $inv["phone"] ? " • ".h($inv["phone"]) : "" ?></div>
    <div class="muted"><?= $inv["address"] ? h($inv["address"]) : "" ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Services / Items for Order #<?=h($inv["order_id"] ?? "N/A")?></td>
        <td class="right"><?=number_format((float)($inv["subtotal"] ?? 0),2)?></td>
      </tr>
      <tr>
        <td>Tax</td>
        <td class="right"><?=number_format((float)($inv["tax_amount"] ?? 0),2)?></td>
      </tr>
      <tr>
        <td class="total">Total</td>
        <td class="right total"><?=number_format((float)($inv["total_amount"] ?? 0),2)?></td>
      </tr>
    </tbody>
  </table>

  <div class="print">
    <button onclick="window.print()">Print / Save as PDF</button>
  </div>
</body>
</html>