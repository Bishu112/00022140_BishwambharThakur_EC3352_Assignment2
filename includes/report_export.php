<?php
/**
 * Shared CSV export helpers.
 *
 * The generated files are deliberately "Excel first": UTF-8 with BOM,
 * CRLF line endings, a branded title block, summary KPIs, a blank spacer,
 * a clean detail table and a totals row. Numbers are written as plain
 * decimals (no thousands separators) so Excel keeps them numeric and can
 * sum them, while cells are escaped against CSV/formula injection.
 */

function csv_header(string $filename): void
{
    if (headers_sent()) return;
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

/** Opens the output stream and writes the UTF-8 BOM Excel expects. */
function csv_open()
{
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    return $out;
}

/** Escapes a cell and neutralises spreadsheet formula injection. */
function csv_cell($value): string
{
    $value = (string)$value;
    if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
        $value = "'".$value;
    }
    return $value;
}

/** Writes one RFC-4180 row terminated with CRLF, as Excel prefers. */
function csv_row($out, array $cells = []): void
{
    fputcsv($out, array_map('csv_cell', $cells), ',', '"', '', "\r\n");
}

/** Plain 2-decimal number so Excel treats it as a value, not text. */
function csv_money($amount): string
{
    return number_format((float)$amount, 2, '.', '');
}

function csv_label(string $range): string
{
    return ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'custom' => 'Custom date range'][$range] ?? 'Monthly';
}

/** Branded title block followed by its metadata and a blank spacer row. */
function csv_document_header($out, string $business, string $title, array $meta): void
{
    csv_row($out, [$business]);
    csv_row($out, [$title]);
    foreach ($meta as $label => $value) {
        csv_row($out, [$label, $value]);
    }
    csv_row($out);
}

/** Section heading + table header row. */
function csv_section($out, string $heading, array $headers): void
{
    csv_row($out, [$heading]);
    csv_row($out, $headers);
}

/** Closing totals row aligned to the final numeric column. */
function csv_total_row($out, string $label, $value, int $columns): void
{
    $row = array_fill(0, max(1, $columns), '');
    $row[0] = $label;
    $row[$columns - 1] = csv_money($value);
    csv_row($out, $row);
}
