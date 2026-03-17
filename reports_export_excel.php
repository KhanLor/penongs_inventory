<?php
require_once 'config.php';
requireManager();

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_branch = $_SESSION['branch_id'];
$low_stock_only = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';

// Get branch name
$branch_query = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
$branch_query->bind_param("i", $selected_branch);
$branch_query->execute();
$branch_name = $branch_query->get_result()->fetch_assoc()['branch_name'];

// Get inventory data for selected date, optionally filtered to low stock only
$inventory_sql = "
    SELECT di.*, i.item_name, i.unit, c.category_name,
           u1.full_name as prepared_by_name,
           u2.full_name as reviewed_by_name
    FROM daily_inventory di
    JOIN items i ON di.item_id = i.id
    JOIN categories c ON i.category_id = c.id
    LEFT JOIN users u1 ON di.prepared_by = u1.id
    LEFT JOIN users u2 ON di.reviewed_by = u2.id
    WHERE di.inventory_date = ? AND di.branch_id = ?
";

if ($low_stock_only) {
    $inventory_sql .= " AND di.ending_inventory < 10";
}

$inventory_sql .= " ORDER BY c.category_name, i.item_name";

$inventory_query = $conn->prepare($inventory_sql);
$inventory_query->bind_param("si", $selected_date, $selected_branch);
$inventory_query->execute();
$inventory_items = $inventory_query->get_result();

function exportInventoryCsvAndExit($inventory_items, $selected_date, $branch_name)
{
    $filename = 'inventory_report_' . $selected_date . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        http_response_code(500);
        echo 'Unable to generate CSV export.';
        exit;
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Penongs - Daily Food Inventory Report']);
    fputcsv($output, ['Branch', $branch_name]);
    fputcsv($output, ['Date', date('F d, Y', strtotime($selected_date))]);
    fputcsv($output, ['Generated', date('F d, Y h:i A')]);
    fputcsv($output, []);
    fputcsv($output, ['Category', 'Item Name', 'Unit', 'Beginning', 'Added', 'Total', 'Sales', 'Ending', 'Remarks']);

    $total_beginning = 0;
    $total_added = 0;
    $total_stock = 0;
    $total_sales = 0;
    $total_ending = 0;

    while ($item = $inventory_items->fetch_assoc()) {
        fputcsv($output, [
            $item['category_name'],
            $item['item_name'],
            $item['unit'],
            (float) $item['beginning_inventory'],
            (float) $item['added_stock'],
            (float) $item['total_stock'],
            (float) $item['daily_sales'],
            (float) $item['ending_inventory'],
            $item['remarks'] ?? ''
        ]);

        $total_beginning += (float) $item['beginning_inventory'];
        $total_added += (float) $item['added_stock'];
        $total_stock += (float) $item['total_stock'];
        $total_sales += (float) $item['daily_sales'];
        $total_ending += (float) $item['ending_inventory'];
    }

    fputcsv($output, []);
    fputcsv($output, ['GRAND TOTAL', '', '', $total_beginning, $total_added, $total_stock, $total_sales, $total_ending, '']);

    fclose($output);
    exit;
}

$composerAutoload = __DIR__ . '/vendor/autoload.php';
$composerAutoloadReal = __DIR__ . '/vendor/composer/autoload_real.php';

if (!is_readable($composerAutoload) || !is_readable($composerAutoloadReal)) {
    exportInventoryCsvAndExit($inventory_items, $selected_date, $branch_name);
}

require_once $composerAutoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Inventory Report');

$row = 1;
$preparedByName = '';
$checkedByName = '';

// Base styling
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
$spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// Theme colors (matching reports.php print design)
$COLOR_RED = 'FFE74C3C';
$COLOR_YELLOW = 'FFF4D03F';
$COLOR_HEADER_FILL = 'FFF39C12';
$COLOR_HEADER_BORDER = 'FFE8B208';
$COLOR_INFO_FILL = 'FFF8F9FA';
$COLOR_GRID_BORDER = 'FFE8E8E8';
$COLOR_CATEGORY_FILL = 'FFFEF5E7';
$COLOR_CATEGORY_FONT = 'FFF39C12';
$COLOR_TOTAL_FILL = 'FFFADBD8';
$COLOR_TOTAL_FONT = 'FFC0392B';

// Header
$sheet->setCellValue('A' . $row, 'Penongs');
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setItalic(true)->setSize(28)->getColor()->setARGB($COLOR_RED);
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getRowDimension($row)->setRowHeight(34);
$row++;

$sheet->setCellValue('A' . $row, 'Daily Food Inventory Report');
$sheet->mergeCells('A' . $row . ':H' . $row);
$sheet->getStyle('A' . $row)->getFont()->setBold(false)->setSize(16)->getColor()->setARGB('FF333333');
$sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getRowDimension($row)->setRowHeight(24);

// Bottom border like the print view header divider
$sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getBottom()
    ->setBorderStyle(Border::BORDER_THICK)
    ->getColor()->setARGB($COLOR_YELLOW);
$row++;

// Report info (styled panel)
$infoStartRow = $row;
$sheet->setCellValue('A' . $row, 'Branch: ' . $branch_name);
$sheet->mergeCells('A' . $row . ':H' . $row);
$row++;
$sheet->setCellValue('A' . $row, 'Date: ' . date('F d, Y', strtotime($selected_date)));
$sheet->mergeCells('A' . $row . ':H' . $row);
$row++;
$sheet->setCellValue('A' . $row, 'Report Generated: ' . date('F d, Y h:i A'));
$sheet->mergeCells('A' . $row . ':H' . $row);
$infoEndRow = $row;
$sheet->getStyle('A' . $infoStartRow . ':H' . $infoEndRow)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB($COLOR_INFO_FILL);
$sheet->getStyle('A' . $infoStartRow . ':H' . $infoEndRow)->getBorders()->getOutline()
    ->setBorderStyle(Border::BORDER_THIN)
    ->getColor()->setARGB($COLOR_GRID_BORDER);
$sheet->getStyle('A' . $infoStartRow . ':H' . $infoEndRow)->getFont()->setSize(11);
$row++;
$row++; // empty row

if ($inventory_items->num_rows > 0) {
    // Table header
    $tableHeaderRow = $row;
    $headers = ['Item Name', 'Unit', 'Beginning', 'Added', 'Total', 'Sales', 'Ending', 'Remarks'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $row, $h);
        $sheet->getStyle($col . $row)->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);
        $sheet->getStyle($col . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($COLOR_HEADER_FILL);
        $sheet->getStyle($col . $row)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB($COLOR_HEADER_BORDER);
        $col++;
    }
    $sheet->getStyle('A' . $row . ':H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getRowDimension($row)->setRowHeight(20);
    $row++;
    $firstDataRow = $row;

    $current_category = '';
    $total_beginning = 0;
    $total_added = 0;
    $total_stock = 0;
    $total_sales = 0;
    $total_ending = 0;
    $categoryRows = [];

    while ($item = $inventory_items->fetch_assoc()) {
        if ($preparedByName === '' && !empty($item['prepared_by_name'])) {
            $preparedByName = $item['prepared_by_name'];
        }
        if ($checkedByName === '' && !empty($item['reviewed_by_name'])) {
            $checkedByName = $item['reviewed_by_name'];
        }

        if ($current_category != $item['category_name']) {
            $current_category = $item['category_name'];
            $sheet->setCellValue('A' . $row, '🗂️ ' . $current_category);
            $sheet->mergeCells('A' . $row . ':H' . $row);
            $categoryRows[] = $row;
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->getColor()->setARGB($COLOR_CATEGORY_FONT);
            $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($COLOR_CATEGORY_FILL);
            $row++;
        }

        $sheet->setCellValue('A' . $row, $item['item_name']);
        $sheet->setCellValue('B' . $row, $item['unit']);
        $sheet->setCellValue('C' . $row, (float) $item['beginning_inventory']);
        $sheet->setCellValue('D' . $row, (float) $item['added_stock']);
        $sheet->setCellValue('E' . $row, (float) $item['total_stock']);
        $sheet->setCellValue('F' . $row, (float) $item['daily_sales']);
        $sheet->setCellValue('G' . $row, (float) $item['ending_inventory']);
        $sheet->setCellValue('H' . $row, $item['remarks'] ?? '');
        $row++;

        $total_beginning += $item['beginning_inventory'];
        $total_added += $item['added_stock'];
        $total_stock += $item['total_stock'];
        $total_sales += $item['daily_sales'];
        $total_ending += $item['ending_inventory'];
    }

    // Grand total row
    $totalRow = $row;
    $sheet->setCellValue('A' . $row, 'GRAND TOTAL');
    $sheet->setCellValue('B' . $row, '');
    $sheet->setCellValue('C' . $row, $total_beginning);
    $sheet->setCellValue('D' . $row, $total_added);
    $sheet->setCellValue('E' . $row, $total_stock);
    $sheet->setCellValue('F' . $row, $total_sales);
    $sheet->setCellValue('G' . $row, $total_ending);
    $sheet->setCellValue('H' . $row, '');
    $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true)->getColor()->setARGB($COLOR_TOTAL_FONT);
    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($COLOR_TOTAL_FILL);

    $lastTableRow = $row;

    // Table styling (borders, alignment, number formats, wrapping)
    $tableRange = 'A' . $tableHeaderRow . ':H' . $lastTableRow;
    $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)
        ->getColor()->setARGB($COLOR_GRID_BORDER);

    // Alignments
    $sheet->getStyle('B' . $firstDataRow . ':B' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $firstDataRow . ':G' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('A' . $firstDataRow . ':A' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('H' . $firstDataRow . ':H' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);

    // Number formatting for numeric columns
    $sheet->getStyle('C' . $firstDataRow . ':G' . $lastTableRow)->getNumberFormat()->setFormatCode('#,##0.00');

    // Repeat table header when printing and freeze pane under headers
    $sheet->freezePane('A' . ($tableHeaderRow + 1));
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($tableHeaderRow, $tableHeaderRow);
    $sheet->setAutoFilter('A' . $tableHeaderRow . ':H' . $tableHeaderRow);
}

// Signature block (matching report print format)
$row += 2;
$signatureNameRow = $row;
$sheet->setCellValue('A' . $signatureNameRow, $preparedByName);
$sheet->setCellValue('E' . $signatureNameRow, $checkedByName);
$sheet->mergeCells('A' . $signatureNameRow . ':D' . $signatureNameRow);
$sheet->mergeCells('E' . $signatureNameRow . ':H' . $signatureNameRow);
$sheet->getStyle('A' . $signatureNameRow . ':H' . $signatureNameRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A' . $signatureNameRow . ':H' . $signatureNameRow)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

$signatureLabelRow = $signatureNameRow + 1;
$sheet->setCellValue('A' . $signatureLabelRow, 'Prepared by');
$sheet->setCellValue('E' . $signatureLabelRow, 'Checked by');
$sheet->mergeCells('A' . $signatureLabelRow . ':D' . $signatureLabelRow);
$sheet->mergeCells('E' . $signatureLabelRow . ':H' . $signatureLabelRow);
$sheet->getStyle('A' . $signatureLabelRow . ':H' . $signatureLabelRow)->getFont()->setItalic(true)->getColor()->setARGB('FF6C757D');
$sheet->getStyle('A' . $signatureLabelRow . ':H' . $signatureLabelRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$signatureRoleRow = $signatureLabelRow + 1;
$sheet->setCellValue('A' . $signatureRoleRow, 'Manager');
$sheet->setCellValue('E' . $signatureRoleRow, $checkedByName !== '' ? 'Manager' : '');
$sheet->mergeCells('A' . $signatureRoleRow . ':D' . $signatureRoleRow);
$sheet->mergeCells('E' . $signatureRoleRow . ':H' . $signatureRoleRow);
$sheet->getStyle('A' . $signatureRoleRow . ':H' . $signatureRoleRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row = $signatureRoleRow;

// Column widths
$sheet->getColumnDimension('A')->setWidth(34);
$sheet->getColumnDimension('B')->setWidth(8);
$sheet->getColumnDimension('C')->setWidth(12);
$sheet->getColumnDimension('D')->setWidth(10);
$sheet->getColumnDimension('E')->setWidth(10);
$sheet->getColumnDimension('F')->setWidth(10);
$sheet->getColumnDimension('G')->setWidth(10);
$sheet->getColumnDimension('H')->setWidth(30);

// Print setup (like the print view)
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setLeft(0.3)->setBottom(0.4);
$sheet->getHeaderFooter()->setOddFooter('&L&"Calibri"&8Generated: ' . date('Y-m-d H:i') . '&RPage &P of &N');

$filename = 'inventory_report_' . $selected_date . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
