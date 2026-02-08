<?php
// php/inventory_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";

$u = require_login_api("Owner"); // only Owner can manage supply chain

header("Content-Type: application/json; charset=utf-8");

function j(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

$action = (string)($_GET["action"] ?? "");

if ($action === "list_inventory") {
  $stmt = $pdo->query("
    SELECT
      i.item_id,
      i.item_name,
      i.category,
      i.quantity,
      i.unit_price,
      i.alert_threshold,
      i.supplier_id,
      i.last_updated,
      s.company_name AS supplier_name
    FROM inventory i
    LEFT JOIN suppliers s ON s.supplier_id = i.supplier_id
    ORDER BY i.last_updated DESC
  ");
  j(["ok" => true, "items" => $stmt->fetchAll()]);
}

if ($action === "list_suppliers") {
  $stmt = $pdo->query("
    SELECT
      supplier_id,
      company_name,
      contact_person,
      email,
      phone,
      service_type,
      contract_status,
      next_delivery_date
    FROM suppliers
    ORDER BY company_name ASC
  ");
  j(["ok" => true, "suppliers" => $stmt->fetchAll()]);
}

/** Read JSON body or fallback to POST form */
function body(): array {
  $raw = file_get_contents("php://input");
  $json = json_decode($raw ?: "", true);
  if (is_array($json)) return $json;
  return $_POST ?: [];
}

if ($action === "add_item") {
  $b = body();

  $name = trim((string)($b["item_name"] ?? ""));
  $cat  = trim((string)($b["category"] ?? ""));
  $qty  = (int)($b["quantity"] ?? 0);
  $price = (float)($b["unit_price"] ?? 0);
  $threshold = (int)($b["alert_threshold"] ?? 5);
  $supplierId = (int)($b["supplier_id"] ?? 0);
  if ($supplierId <= 0) $supplierId = null;

  if ($name === "" || $cat === "") j(["ok"=>false,"error"=>"Item name and category required"], 422);

  $stmt = $pdo->prepare("
    INSERT INTO inventory (item_name, category, quantity, unit_price, alert_threshold, supplier_id)
    VALUES (:n, :c, :q, :p, :t, :sid)
  ");
  $stmt->execute([
    ":n" => $name,
    ":c" => $cat,
    ":q" => $qty,
    ":p" => $price,
    ":t" => $threshold,
    ":sid" => $supplierId
  ]);

  j(["ok" => true, "item_id" => (int)$pdo->lastInsertId()]);
}

if ($action === "add_supplier") {
  $b = body();

  $company = trim((string)($b["company_name"] ?? ""));
  $person  = trim((string)($b["contact_person"] ?? ""));
  $email   = trim((string)($b["email"] ?? ""));
  $phone   = trim((string)($b["phone"] ?? ""));
  $stype   = (string)($b["service_type"] ?? "Hardware");
  $status  = (string)($b["contract_status"] ?? "Active");
  $next    = (string)($b["next_delivery_date"] ?? "");
  if ($next === "") $next = null;

  if ($company === "") j(["ok"=>false,"error"=>"Company name required"], 422);

  $stmt = $pdo->prepare("
    INSERT INTO suppliers (company_name, contact_person, email, phone, service_type, contract_status, next_delivery_date)
    VALUES (:cn, :cp, :em, :ph, :st, :cs, :nd)
  ");
  $stmt->execute([
    ":cn" => $company,
    ":cp" => ($person === "" ? null : $person),
    ":em" => ($email === "" ? null : $email),
    ":ph" => ($phone === "" ? null : $phone),
    ":st" => $stype,
    ":cs" => $status,
    ":nd" => $next
  ]);

  j(["ok" => true, "supplier_id" => (int)$pdo->lastInsertId()]);
}

if ($action === "add_stock") {
  $b = body();

  $itemId = (int)($b["item_id"] ?? 0);

  // ✅ accept multiple possible keys to avoid mismatch
  $addQty = (int)($b["add_qty"] ?? $b["qty"] ?? $b["quantity"] ?? 0);

  if ($itemId <= 0 || $addQty <= 0) {
    j(["ok"=>false, "error"=>"Invalid item_id or quantity"], 422);
  }

  $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + :add WHERE item_id = :id");
  $stmt->execute([":add" => $addQty, ":id" => $itemId]);

  // ✅ if item_id wrong, rowCount will be 0
  if ($stmt->rowCount() === 0) {
    j(["ok"=>false, "error"=>"Item not found or no change applied"], 404);
  }

  // ✅ return updated quantity (so UI can confirm)
  $q = $pdo->prepare("SELECT quantity FROM inventory WHERE item_id = :id");
  $q->execute([":id" => $itemId]);
  $row = $q->fetch();

  j(["ok" => true, "item_id" => $itemId, "new_quantity" => (int)$row["quantity"]]);
}

if ($action === "request_restock") {
  $b = body();
  $itemId = (int)($b["item_id"] ?? 0);
  $qty    = (int)($b["qty"] ?? 0);

  if ($itemId <= 0 || $qty <= 0) j(["ok"=>false,"error"=>"Invalid item or qty"], 422);

  // get item details
  $stmt = $pdo->prepare("
    SELECT item_name, unit_price
    FROM inventory
    WHERE item_id = :id
  ");
  $stmt->execute([":id" => $itemId]);
  $item = $stmt->fetch();
  if (!$item) j(["ok"=>false,"error"=>"Item not found"], 404);

  $amount = ((float)$item["unit_price"]) * $qty;
  $details = "Restock request: {$item["item_name"]} | Qty: {$qty}";

  // create approval request (Purchase Order)
  $stmt = $pdo->prepare("
    INSERT INTO approvals (requester_id, type, details, amount, status, reviewed_by)
    VALUES (:rid, 'Purchase Order', :d, :a, 'Pending', NULL)
  ");
  $stmt->execute([
    ":rid" => (int)$u["user_id"],
    ":d" => $details,
    ":a" => $amount
  ]);

  // also notify Admin (user_id = 1) (optional but useful)
  $stmt = $pdo->prepare("
    INSERT INTO notifications (user_id, type, title, message, is_read)
    VALUES (1, 'Alert', 'Restock Approval Needed', :msg, 0)
  ");
  $stmt->execute([":msg" => $details . " | Amount: " . number_format($amount, 2)]);

  j(["ok" => true]);
}

j(["ok" => false, "error" => "Unknown action"], 400);