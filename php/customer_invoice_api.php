<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

$u = require_login_api(["customer"]);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function j($data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data);
  exit;
}

$action = (string)($_GET["action"] ?? "");
if ($action !== "ensure_invoice") j(["ok" => false, "error" => "Invalid action"], 400);

$customerId = (int)$u["user_id"];
$orderId = (int)($_POST["order_id"] ?? 0);
if ($orderId <= 0) j(["ok" => false, "error" => "Invalid order_id"], 400);

// verify order belongs to this customer
$o = $pdo->prepare("SELECT order_id FROM orders WHERE order_id = :oid AND customer_id = :cid LIMIT 1");
$o->execute([":oid" => $orderId, ":cid" => $customerId]);
if (!$o->fetch()) j(["ok" => false, "error" => "Order not found"], 404);

// if invoice already exists
$inv = $pdo->prepare("SELECT invoice_id FROM invoices WHERE order_id = :oid AND customer_id = :cid LIMIT 1");
$inv->execute([":oid" => $orderId, ":cid" => $customerId]);
$existing = $inv->fetchColumn();
if ($existing) j(["ok" => true, "invoice_id" => (int)$existing]);

// create invoice (adjust columns if needed)
$invoiceNo = "INV-" . date("ymd") . "-" . random_int(1000, 9999);
$issuedAt = date("Y-m-d");
$dueDate  = date("Y-m-d", strtotime("+14 days"));
$taxRate  = 0.00;

$ins = $pdo->prepare("
  INSERT INTO invoices (invoice_no, order_id, customer_id, status, issued_at, due_date, tax_rate)
  VALUES (:no, :oid, :cid, 'Unpaid', :issued, :due, :tax)
");
$ins->execute([
  ":no" => $invoiceNo,
  ":oid" => $orderId,
  ":cid" => $customerId,
  ":issued" => $issuedAt,
  ":due" => $dueDate,
  ":tax" => $taxRate
]);

$invoiceId = (int)$pdo->lastInsertId();
j(["ok" => true, "invoice_id" => $invoiceId]);