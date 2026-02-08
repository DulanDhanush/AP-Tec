<?php
// php/customer_invoices_api.php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$u = require_login_api("customer"); // your auth normalizes role
$customerId = (int)$u["user_id"];

$action = (string)($_GET["action"] ?? "");

function j(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

/**
 * This project dump doesn't include payments table.
 * We create it once (safe).
 */
function ensure_payments_table_exists(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS invoice_payments (
      payment_id INT AUTO_INCREMENT PRIMARY KEY,
      invoice_id INT NOT NULL,
      customer_id INT NOT NULL,
      amount DECIMAL(10,2) NOT NULL,
      method VARCHAR(30) DEFAULT 'Card',
      card_last4 VARCHAR(4) DEFAULT NULL,
      status ENUM('Success','Failed') DEFAULT 'Success',
      paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (invoice_id),
      INDEX (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}

/** If Unpaid but past due date => show Overdue (even if DB status still Unpaid) */
function display_status(array $inv): string {
  $status = (string)$inv["status"];
  $due = (string)$inv["due_date"];
  if ($status === "Unpaid" && strtotime($due) < strtotime(date("Y-m-d"))) {
    return "Overdue";
  }
  return $status;
}

if ($action === "list") {
  $filter = strtolower((string)($_GET["filter"] ?? "all")); // all | unpaid | paid

  $where = "customer_id = :cid";
  if ($filter === "paid") {
    $where .= " AND status='Paid'";
  } elseif ($filter === "unpaid") {
    $where .= " AND (status IN ('Unpaid','Overdue') OR (status='Unpaid' AND due_date < CURDATE()))";
  }

  $st = $pdo->prepare("
    SELECT invoice_id, invoice_number, issue_date, due_date, total_amount, status
    FROM invoices
    WHERE {$where}
    ORDER BY issue_date DESC, invoice_id DESC
  ");
  $st->execute([":cid" => $customerId]);
  $rows = $st->fetchAll();

  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      "invoice_id"      => (int)$r["invoice_id"],
      "invoice_number"  => (string)$r["invoice_number"],
      "issue_date"      => (string)$r["issue_date"],
      "due_date"        => (string)$r["due_date"],
      "total_amount"    => (float)$r["total_amount"],
      "status"          => display_status($r),
    ];
  }

  j(["ok" => true, "invoices" => $out]);
}

if ($action === "stats") {
  ensure_payments_table_exists($pdo);

  // outstanding = unpaid + overdue + unpaid past due
  $st1 = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount),0) AS outstanding
    FROM invoices
    WHERE customer_id=:cid
      AND (
        status IN ('Unpaid','Overdue')
        OR (status='Unpaid' AND due_date < CURDATE())
      )
  ");
  $st1->execute([":cid" => $customerId]);
  $outstanding = (float)$st1->fetch()["outstanding"];

  // paid ytd from payments
  $st2 = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0) AS paid_ytd
    FROM invoice_payments
    WHERE customer_id=:cid AND status='Success' AND YEAR(paid_at)=YEAR(CURDATE())
  ");
  $st2->execute([":cid" => $customerId]);
  $paidYtd = (float)$st2->fetch()["paid_ytd"];

  // last payment date
  $st3 = $pdo->prepare("
    SELECT MAX(paid_at) AS last_paid
    FROM invoice_payments
    WHERE customer_id=:cid AND status='Success'
  ");
  $st3->execute([":cid" => $customerId]);
  $lastPaid = $st3->fetch()["last_paid"] ?? null;

  // overdue count
  $st4 = $pdo->prepare("
    SELECT COUNT(*) AS overdue_count
    FROM invoices
    WHERE customer_id=:cid
      AND (
        status='Overdue'
        OR (status='Unpaid' AND due_date < CURDATE())
      )
  ");
  $st4->execute([":cid" => $customerId]);
  $overdueCount = (int)$st4->fetch()["overdue_count"];

  j([
    "ok" => true,
    "stats" => [
      "outstanding" => $outstanding,
      "paid_ytd" => $paidYtd,
      "last_payment_date" => $lastPaid,
      "overdue_count" => $overdueCount
    ]
  ]);
}

if ($action === "pay") {
  ensure_payments_table_exists($pdo);

  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    j(["ok" => false, "error" => "Invalid method"], 405);
  }

  $invoiceId  = (int)($_POST["invoice_id"] ?? 0);
  $cardholder = trim((string)($_POST["cardholder"] ?? ""));
  $cardNumber = preg_replace("/\D+/", "", (string)($_POST["card_number"] ?? ""));
  $cvc        = preg_replace("/\D+/", "", (string)($_POST["cvc"] ?? ""));

  if ($invoiceId <= 0) j(["ok" => false, "error" => "Missing invoice"], 400);
  if ($cardholder === "") j(["ok" => false, "error" => "Cardholder required"], 400);
  if (strlen($cardNumber) < 12) j(["ok" => false, "error" => "Invalid card number"], 400);
  if (strlen($cvc) < 3) j(["ok" => false, "error" => "Invalid CVC"], 400);

  // invoice must belong to this customer
  $st = $pdo->prepare("
    SELECT invoice_id, invoice_number, total_amount, status, due_date
    FROM invoices
    WHERE invoice_id=:iid AND customer_id=:cid
    LIMIT 1
  ");
  $st->execute([":iid" => $invoiceId, ":cid" => $customerId]);
  $inv = $st->fetch(PDO::FETCH_ASSOC);
  if (!$inv) j(["ok" => false, "error" => "Invoice not found"], 404);

  if ((string)$inv["status"] === "Paid") j(["ok" => false, "error" => "Invoice already paid"], 400);
  if ((string)$inv["status"] === "Cancelled") j(["ok" => false, "error" => "Invoice cancelled"], 400);

  $amount = (float)$inv["total_amount"];
  $last4  = substr($cardNumber, -4);
  $ip     = (string)($_SERVER["REMOTE_ADDR"] ?? "");

  // Demo payment: record payment success + set invoice paid
  try {
    $pdo->beginTransaction();

    $insPay = $pdo->prepare("
      INSERT INTO invoice_payments (invoice_id, customer_id, amount, method, card_last4, status)
      VALUES (:iid, :cid, :amt, 'Card', :l4, 'Success')
    ");
    $insPay->execute([
      ":iid" => $invoiceId,
      ":cid" => $customerId,
      ":amt" => $amount,
      ":l4"  => $last4
    ]);

    $upInv = $pdo->prepare("
      UPDATE invoices
      SET status='Paid'
      WHERE invoice_id=:iid AND customer_id=:cid
    ");
    $upInv->execute([":iid" => $invoiceId, ":cid" => $customerId]);

    // notification (matches your notifications table)
    $insNotif = $pdo->prepare("
      INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
      VALUES (:uid, 'Success', 'Payment Received', :msg, 0, NOW())
    ");
    $msg = "Invoice ".$inv["invoice_number"]." paid successfully (Card •••• ".$last4.").";
    $insNotif->execute([":uid" => $customerId, ":msg" => $msg]);

    // system log (matches your system_logs table)
    $insLog = $pdo->prepare("
      INSERT INTO system_logs (level, module, message, user_id, ip_address, created_at)
      VALUES ('INFO', 'Billing', :msg, :uid, :ip, NOW())
    ");
    $logMsg = "Customer paid invoice ".$inv["invoice_number"]." amount ".$amount;
    $insLog->execute([":msg" => $logMsg, ":uid" => $customerId, ":ip" => $ip]);

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    j(["ok" => false, "error" => "Payment failed"], 500);
  }

  j(["ok" => true, "message" => "Payment successful"]);
}

j(["ok" => false, "error" => "Unknown action"], 400);