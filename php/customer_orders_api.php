<?php
declare(strict_types=1);

ini_set("display_errors", "0");

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

try {
  // Verify order belongs to this customer
  $o = $pdo->prepare("SELECT order_id FROM orders WHERE order_id = :oid AND customer_id = :cid LIMIT 1");
  $o->execute([":oid" => $orderId, ":cid" => $customerId]);
  if (!$o->fetch()) j(["ok" => false, "error" => "Order not found"], 404);

  // If invoice already exists, return it
  $inv = $pdo->prepare("SELECT invoice_id FROM invoices WHERE order_id = :oid AND customer_id = :cid LIMIT 1");
  $inv->execute([":oid" => $orderId, ":cid" => $customerId]);
  $existing = $inv->fetchColumn();
  if ($existing) j(["ok" => true, "invoice_id" => (int)$existing]);

  // Calculate subtotal from order items
  $itemsStmt = $pdo->prepare("
    SELECT oi.quantity, COALESCE(oi.price_at_purchase, i.unit_price, 0) AS unit_price
    FROM order_items oi
    LEFT JOIN inventory i ON i.item_id = oi.item_id
    WHERE oi.order_id = :oid
  ");
  $itemsStmt->execute([":oid" => $orderId]);
  $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

  $subtotal = 0.0;
  foreach ($items as $it) {
    $subtotal += ((float)$it["unit_price"]) * ((int)$it["quantity"]);
  }

  $taxAmount = 0.0;              // change if you want tax
  $totalAmount = $subtotal + $taxAmount;

  // Create invoice
  $invoiceNumber = "INV-" . date("ymd") . "-" . random_int(1000, 9999);
  $issueDate = date("Y-m-d");
  $dueDate   = date("Y-m-d", strtotime("+14 days"));

  $ins = $pdo->prepare("
    INSERT INTO invoices
      (invoice_number, order_id, customer_id, issue_date, due_date, subtotal, tax_amount, total_amount, status, created_by)
    VALUES
      (:num, :oid, :cid, :issue, :due, :sub, :tax, :total, 'Unpaid', NULL)
  ");
  $ins->execute([
    ":num" => $invoiceNumber,
    ":oid" => $orderId,
    ":cid" => $customerId,
    ":issue" => $issueDate,
    ":due" => $dueDate,
    ":sub" => $subtotal,
    ":tax" => $taxAmount,
    ":total" => $totalAmount,
  ]);

  $invoiceId = (int)$pdo->lastInsertId();
  j(["ok" => true, "invoice_id" => $invoiceId]);

} catch (Throwable $e) {
  j(["ok" => false, "error" => "SERVER_ERROR: " . $e->getMessage()], 500);
}