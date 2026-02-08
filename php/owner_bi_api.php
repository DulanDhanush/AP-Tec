<?php
// php/owner_bi_api.php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

/* ---------- stop PHP from printing HTML errors ---------- */
ini_set("display_errors", "0");
ini_set("html_errors", "0");
error_reporting(E_ALL);

/* Convert warnings/notices into exceptions so we can return JSON */
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/* Catch fatal errors too */
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        header("Content-Type: application/json; charset=utf-8");
        http_response_code(500);
        echo json_encode([
            "ok" => false,
            "error" => "Fatal error: " . $err["message"],
            "where" => basename($err["file"]) . ":" . $err["line"]
        ]);
        exit;
    }
});

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

function j($arr, int $code = 200): void {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

try {
    $action = (string)($_GET["action"] ?? "");
    $range  = (string)($_GET["range"] ?? "this_month");
    $limit  = max(1, min(25, (int)($_GET["limit"] ?? 10)));

    $now = new DateTime("now");
    $today = $now->format("Y-m-d");

    function range_dates(string $range, DateTime $now): array {
        $end = clone $now;
        $endStr = $end->format("Y-m-d");

        if ($range === "ytd") {
            $start = new DateTime($now->format("Y-01-01"));
            return [$start->format("Y-m-d"), $endStr];
        }

        if ($range === "last_quarter") {
            $firstThisMonth = new DateTime($now->format("Y-m-01"));
            $start = (clone $firstThisMonth)->modify("-3 months");
            $endQ  = (clone $firstThisMonth)->modify("-1 day");
            return [$start->format("Y-m-d"), $endQ->format("Y-m-d")];
        }

        $start = new DateTime($now->format("Y-m-01"));
        return [$start->format("Y-m-d"), $endStr];
    }

    [$startDate, $endDate] = range_dates($range, $now);

    /* -------------------- SUMMARY (KPIs) -------------------- */
    if ($action === "summary") {

        // ✅ Revenue = sum of PAID invoices.total_amount within range
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount),0) AS revenue
            FROM invoices
            WHERE status='Paid'
              AND issue_date BETWEEN :s AND :e
        ");
        $stmt->execute([":s" => $startDate, ":e" => $endDate]);
        $revenue = (float)$stmt->fetch()["revenue"];

        // ✅ Expenses = sum of APPROVED Purchase Orders approvals.amount within range
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount),0) AS costs
            FROM approvals
            WHERE type='Purchase Order'
              AND status='Approved'
              AND DATE(created_at) BETWEEN :s AND :e
        ");
        $stmt->execute([":s" => $startDate, ":e" => $endDate]);
        $costs = (float)$stmt->fetch()["costs"];

        $profit = $revenue - $costs;

        // ✅ Outstanding = sum of invoices with status Unpaid or Overdue within range
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount),0) AS outstanding
            FROM invoices
            WHERE status IN ('Unpaid','Overdue')
              AND issue_date BETWEEN :s AND :e
        ");
        $stmt->execute([":s" => $startDate, ":e" => $endDate]);
        $outstanding = (float)$stmt->fetch()["outstanding"];

        // ✅ Overdue count = (status Overdue) OR (Unpaid and due_date < today)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS overdue_count
            FROM invoices
            WHERE (status='Overdue' OR (status='Unpaid' AND due_date < :today))
        ");
        $stmt->execute([":today" => $today]);
        $overdueCount = (int)$stmt->fetch()["overdue_count"];

        // ✅ Active supplier contracts = suppliers.contract_status='Active'
        $stmt = $pdo->query("
            SELECT COUNT(*) AS active_contracts
            FROM suppliers
            WHERE contract_status='Active'
        ");
        $activeContracts = (int)$stmt->fetch()["active_contracts"];

        j([
            "ok" => true,
            "start" => $startDate,
            "end" => $endDate,
            "revenue" => $revenue,
            "profit" => $profit,
            "outstanding" => $outstanding,
            "overdueCount" => $overdueCount,
            "activeContracts" => $activeContracts
        ]);
    }

    /* -------------------- CHART 1: Revenue vs Expenses -------------------- */
    if ($action === "chart_revenue_expenses") {
        $groupDaily = ($range === "this_month");

        if ($groupDaily) {
            $revSql = "
              SELECT DATE(issue_date) AS k, COALESCE(SUM(total_amount),0) AS v
              FROM invoices
              WHERE status='Paid' AND issue_date BETWEEN :s AND :e
              GROUP BY DATE(issue_date)
              ORDER BY DATE(issue_date)
            ";
            $expSql = "
              SELECT DATE(created_at) AS k, COALESCE(SUM(amount),0) AS v
              FROM approvals
              WHERE type='Purchase Order' AND status='Approved'
                AND DATE(created_at) BETWEEN :s AND :e
              GROUP BY DATE(created_at)
              ORDER BY DATE(created_at)
            ";
        } else {
            $revSql = "
              SELECT DATE_FORMAT(issue_date, '%Y-%m') AS k, COALESCE(SUM(total_amount),0) AS v
              FROM invoices
              WHERE status='Paid' AND issue_date BETWEEN :s AND :e
              GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
              ORDER BY k
            ";
            $expSql = "
              SELECT DATE_FORMAT(created_at, '%Y-%m') AS k, COALESCE(SUM(amount),0) AS v
              FROM approvals
              WHERE type='Purchase Order' AND status='Approved'
                AND DATE(created_at) BETWEEN :s AND :e
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY k
            ";
        }

        $stmt = $pdo->prepare($revSql);
        $stmt->execute([":s" => $startDate, ":e" => $endDate]);
        $rev = [];
        while ($r = $stmt->fetch()) $rev[$r["k"]] = (float)$r["v"];

        $stmt = $pdo->prepare($expSql);
        $stmt->execute([":s" => $startDate, ":e" => $endDate]);
        $exp = [];
        while ($r = $stmt->fetch()) $exp[$r["k"]] = (float)$r["v"];

        $labels = [];
        $revenueData = [];
        $expenseData = [];

        $startDT = new DateTime($startDate);
        $endDT   = new DateTime($endDate);

        if ($groupDaily) {
            $cursor = clone $startDT;
            while ($cursor <= $endDT) {
                $k = $cursor->format("Y-m-d");
                $labels[] = $cursor->format("d");
                $revenueData[] = $rev[$k] ?? 0;
                $expenseData[] = $exp[$k] ?? 0;
                $cursor->modify("+1 day");
            }
        } else {
            $cursor = new DateTime($startDT->format("Y-m-01"));
            $endMonth = new DateTime($endDT->format("Y-m-01"));
            while ($cursor <= $endMonth) {
                $k = $cursor->format("Y-m");
                $labels[] = $cursor->format("M");
                $revenueData[] = $rev[$k] ?? 0;
                $expenseData[] = $exp[$k] ?? 0;
                $cursor->modify("+1 month");
            }
        }

        j(["ok" => true, "labels" => $labels, "revenue" => $revenueData, "expenses" => $expenseData]);
    }

    /* -------------------- CHART 2: Service Mix -------------------- */
    if ($action === "chart_service_mix") {
        // ✅ Using: orders, order_items, inventory (matches your dump)
        $sql = "
          SELECT
            CASE
              WHEN i.category = 'Toner' THEN 'Toner Refill'
              WHEN i.category = 'Printer Part' THEN 'Printer Repair'
              WHEN i.category IN ('PC Part','Network','Accessory','Stationery') THEN 'Hardware Sales'
              ELSE 'Maintenance'
            END AS label,
            COALESCE(SUM(oi.quantity * oi.price_at_purchase),0) AS value
          FROM orders o
          JOIN order_items oi ON oi.order_id = o.order_id
          LEFT JOIN inventory i ON i.item_id = oi.item_id
          WHERE o.status <> 'Cancelled'
            AND DATE(o.order_date) BETWEEN :s AND :e
          GROUP BY label
          ORDER BY value DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":s" => $startDate, ":e" => $endDate]);

        $labels = [];
        $data = [];
        while ($r = $stmt->fetch()) {
            $labels[] = $r["label"];
            $data[] = (float)$r["value"];
        }

        if (!$labels) {
            $labels = ["Toner Refill","Printer Repair","Hardware Sales","Maintenance"];
            $data   = [0,0,0,0];
        }

        j(["ok" => true, "labels" => $labels, "data" => $data]);
    }

    /* -------------------- TRANSACTIONS TABLE (HY093 FIXED) -------------------- */
    if ($action === "transactions") {

        $sql = "
          (
            SELECT
              CONCAT('INV-', invoice_number) AS trx_id,
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
            SELECT
              CONCAT('PO-', approval_id) AS trx_id,
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
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":s1" => $startDate,
            ":e1" => $endDate,
            ":s2" => $startDate,
            ":e2" => $endDate,
        ]);

        $rows = $stmt->fetchAll();
        j(["ok" => true, "rows" => $rows]);
    }

    j(["ok" => false, "error" => "Invalid action"], 400);

} catch (Throwable $e) {
    j([
        "ok" => false,
        "error" => $e->getMessage(),
        "where" => basename($e->getFile()) . ":" . $e->getLine()
    ], 500);
}