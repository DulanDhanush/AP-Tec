<?php
require('fpdf/fpdf.php');
require_once 'db.php';

// Optional: Security Check
// require_once 'auth.php'; 
// require_login(['Admin', 'Owner']);

class ModernPDF extends FPDF
{
    // Brand Colors
    private $col_primary = [13, 44, 77];    // Dark Blue
    private $col_accent  = [100, 255, 218]; // Neon Cyan
    private $col_text    = [50, 50, 50];    // Dark Grey
    private $col_gray    = [245, 245, 245]; // Light Gray background

    function Header()
    {
        // 1. Sidebar Accent (Decorative Strip on the left)
        $this->SetFillColor($this->col_primary[0], $this->col_primary[1], $this->col_primary[2]);
        $this->Rect(0, 0, 10, 297, 'F'); // A4 height is 297mm

        // 2. Company Info (Top Left)
        $this->SetX(20); // Move past the blue strip
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor($this->col_primary[0], $this->col_primary[1], $this->col_primary[2]);
        $this->Cell(0, 10, 'AP TEC.', 0, 1, 'L');

        $this->SetX(20);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Security & System Management', 0, 1, 'L');

        // 3. Report Details (Top Right)
        // We use absolute positioning to place the Date/Report ID nicely
        $this->SetXY(120, 10);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->col_primary[0], $this->col_primary[1], $this->col_primary[2]);
        $this->Cell(80, 5, 'SYSTEM AUDIT LOG', 0, 1, 'R');

        $this->SetXY(120, 16);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(80, 5, 'Generated: ' . date('Y-M-d H:i'), 0, 1, 'R');
        
        $this->SetXY(120, 21);
        $this->Cell(80, 5, 'Ref: LOG-' . date('Ymd'), 0, 1, 'R');

        // 4. Divider Line
        $this->Ln(15);
        $this->SetDrawColor($this->col_accent[0], $this->col_accent[1], $this->col_accent[2]);
        $this->SetLineWidth(0.5);
        $this->Line(20, 35, 200, 35);
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetX(20); // Align with content
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        
        // Footer Content
        $this->Cell(90, 10, 'Confidential Document - For Internal Use Only', 0, 0, 'L');
        $this->Cell(90, 10, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'R');
    }

    function SectionTitle($label)
    {
        $this->SetX(20);
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0);
        $this->Cell(180, 8, "  " . strtoupper($label), 0, 1, 'L', true);
        $this->Ln(4);
    }

    function RenderTable($header, $data)
    {
        $this->SetX(20);
        
        // --- Table Header ---
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor($this->col_primary[0], $this->col_primary[1], $this->col_primary[2]);
        $this->SetTextColor(255); // White text
        $this->SetLineWidth(0.2);

        // Define Column Widths
        $w = array(25, 40, 25, 30, 60); 

        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 8, $header[$i], 0, 0, 'C', true);
        }
        $this->Ln();

        // --- Table Body ---
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(50);
        $fill = false;

        foreach($data as $row)
        {
            $this->SetX(20);
            
            // Alternating Row Color
            if ($fill) {
                $this->SetFillColor(245, 248, 250); // Very light blue-grey
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            // Cell 1: ID
            $this->Cell($w[0], 8, '#'.$row['log_id'], 0, 0, 'C', $fill);
            
            // Cell 2: Date (Clean Format)
            $dateClean = date('M d, H:i', strtotime($row['created_at']));
            $this->Cell($w[1], 8, $dateClean, 0, 0, 'L', $fill);

            // Cell 3: Level (Badges)
            $level = strtoupper($row['level']);
            
            // Dynamic text color for Level
            if($level == 'ERROR') {
                $this->SetTextColor(231, 76, 60); // Red
                $this->SetFont('Arial', 'B', 8);
            } elseif($level == 'WARNING') {
                $this->SetTextColor(241, 196, 15); // Orange
                $this->SetFont('Arial', 'B', 8);
            } else {
                $this->SetTextColor(46, 204, 113); // Green/Normal
                $this->SetFont('Arial', 'B', 8);
            }
            $this->Cell($w[2], 8, $level, 0, 0, 'C', $fill);

            // Reset Text Color for remaining columns
            $this->SetTextColor(50); 
            $this->SetFont('Arial', '', 9);

            // Cell 4: Module
            $this->Cell($w[3], 8, $row['module'], 0, 0, 'L', $fill);

            // Cell 5: Message (Truncated)
            $msg = iconv('UTF-8', 'windows-1252', $row['message']); 
            if(strlen($msg) > 35) $msg = substr($msg, 0, 32) . '...';
            $this->Cell($w[4], 8, $msg, 0, 0, 'L', $fill);

            $this->Ln();
            
            // Subtle Separator Line
            $this->SetDrawColor(230);
            $this->Line(20, $this->GetY(), 200, $this->GetY());
            
            $fill = !$fill;
        }
        
        // Closing bottom line
        $this->SetX(20);
        $this->SetDrawColor($this->col_primary[0], $this->col_primary[1], $this->col_primary[2]);
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

// --- DATA FETCHING ---

// 1. Get raw data
$sql = "SELECT sl.log_id, sl.created_at, sl.level, sl.module, sl.message FROM system_logs sl ORDER BY sl.created_at DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Calculate Stats for the Dashboard
$total = count($data);
$errors = 0;
$warnings = 0;
foreach($data as $d) {
    if($d['level'] == 'ERROR') $errors++;
    if($d['level'] == 'WARNING') $warnings++;
}

// --- PDF GENERATION ---

$pdf = new ModernPDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// 1. SUMMARY DASHBOARD (The "Modern" Touch)
$pdf->SetX(20);
$pdf->SetFillColor(245, 245, 245);
$pdf->Rect(20, 45, 180, 20, 'F'); // Grey container

// Draw Stats inside the box
$pdf->SetY(52); 
$pdf->SetX(25);

$pdf->SetFont('Arial', '', 10); $pdf->Cell(30, 6, 'Total Records:', 0, 0);
$pdf->SetFont('Arial', 'B', 12); $pdf->Cell(20, 6, $total, 0, 0);

$pdf->SetX(90);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(20, 6, 'Errors:', 0, 0);
$pdf->SetTextColor(231, 76, 60); // Red
$pdf->SetFont('Arial', 'B', 12); $pdf->Cell(20, 6, $errors, 0, 0);

$pdf->SetX(150);
$pdf->SetTextColor(50);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(25, 6, 'Warnings:', 0, 0);
$pdf->SetTextColor(241, 196, 15); // Orange
$pdf->SetFont('Arial', 'B', 12); $pdf->Cell(20, 6, $warnings, 0, 1);

$pdf->Ln(15);

// 2. MAIN DATA TABLE
$pdf->SectionTitle('Recent Log Activity');
$header = array('ID', 'Timestamp', 'Level', 'Module', 'Activity / Message');
$pdf->RenderTable($header, $data);

// 3. Output
$pdf->Output('I', 'APTec_Audit_AdminReport.pdf'); 
?>