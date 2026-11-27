<?php
/**
 * SpreadsheetML (Excel 2003 XML) export helpers.
 *
 * Produces a real, styled Excel workbook as plain XML — no ZipArchive needed.
 * Excel opens it natively; it supports fonts, fills, borders, alignment and
 * number formats, so the downloaded statement can carry the Redline Royale
 * colour design that CSV cannot.
 */

function xlsx_header(string $filename): void
{
    if (headers_sent()) return;
    ob_clean();
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

/** Creates a fresh in-memory workbook accumulator. */
function xlsx_open(): array
{
    return [
        'styles'  => [],
        'columns' => [],
        'rows'    => [],
    ];
}

/** Registers a named style (CSS-like properties). */
function xlsx_style(array &$doc, string $id, array $props): void
{
    $doc['styles'][$id] = $props;
}

/** Sets column widths (in points). */
function xlsx_columns(array &$doc, array $widths): void
{
    $doc['columns'] = $widths;
}

/**
 * Appends a row. Each cell may be a scalar (auto-typed) or an array with keys:
 *   value, type (String|Number|DateTime), style (style id), formula.
 */
function xlsx_row(array &$doc, array $cells, string $rowStyle = '', ?float $height = null): void
{
    $normalised = [];
    foreach ($cells as $cell) {
        if (is_array($cell)) {
            $normalised[] = [
                'value'  => $cell['value'] ?? '',
                'type'   => $cell['type'] ?? null,
                'style'  => $cell['style'] ?? $rowStyle,
                'formula'=> $cell['formula'] ?? null,
                'merge'  => $cell['merge'] ?? 0,
                'index'  => $cell['index'] ?? null,
            ];
        } else {
            $normalised[] = [
                'value'  => $cell,
                'type'   => null,
                'style'  => $rowStyle,
                'formula'=> null,
                'merge'  => 0,
                'index'  => null,
            ];
        }
    }
    $doc['rows'][] = ['cells' => $normalised, 'height' => $height];
}

/** Renders the accumulated workbook to php://output. */
function xlsx_render(array $doc, string $filename): void
{
    xlsx_header($filename);
    $out = fopen('php://output', 'w');

    fwrite($out, '<?xml version="1.0" encoding="UTF-8"?>'."\n");
    fwrite($out, '<?mso-application progid="Excel.Sheet"?>'."\n");
    fwrite($out, '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"');
    fwrite($out, ' xmlns:o="urn:schemas-microsoft-com:office:office"');
    fwrite($out, ' xmlns:x="urn:schemas-microsoft-com:office:excel"');
    fwrite($out, ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"');
    fwrite($out, ' xmlns:html="http://www.w3.org/TR/REC-html40">'."\n");

    // Styles
    fwrite($out, "  <Styles>\n");
    // Default style
    fwrite($out, '    <Style ss:ID="Default"><Font ss:FontName="Arial" ss:Size="10" ss:Color="#292528"/></Style>'."\n");
    foreach ($doc['styles'] as $id => $props) {
        fwrite($out, '    <Style ss:ID="'.xlsx_escape_attr($id).'">');
        if (!empty($props['font'])) {
            $f = $props['font'];
            fwrite($out, '<Font ss:FontName="'.($f['name'] ?? 'Arial').'" ss:Size="'.($f['size'] ?? 10).'"');
            if (!empty($f['bold'])) fwrite($out, ' ss:Bold="1"');
            if (!empty($f['italic'])) fwrite($out, ' ss:Italic="1"');
            if (!empty($f['color'])) fwrite($out, ' ss:Color="'.xlsx_escape_attr($f['color']).'"');
            fwrite($out, '/>');
        }
        if (!empty($props['fill'])) {
            fwrite($out, '<Interior ss:Color="'.xlsx_escape_attr($props['fill']).'" ss:Pattern="Solid"/>');
        }
        if (!empty($props['align'])) {
            $a = $props['align'];
            fwrite($out, '<Alignment');
            if (!empty($a['horizontal'])) fwrite($out, ' ss:Horizontal="'.xlsx_escape_attr($a['horizontal']).'"');
            if (!empty($a['vertical'])) fwrite($out, ' ss:Vertical="'.xlsx_escape_attr($a['vertical']).'"');
            if (!empty($a['wrap'])) fwrite($out, ' ss:WrapText="1"');
            fwrite($out, '/>');
        }
        if (!empty($props['border'])) {
            fwrite($out, '<Borders>');
            foreach (['Left','Right','Top','Bottom'] as $side) {
                fwrite($out, '<Border ss:Position="'.$side.'" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="'.xlsx_escape_attr($props['border']).'"/>');
            }
            fwrite($out, '</Borders>');
        }
        if (!empty($props['numberFormat'])) {
            fwrite($out, '<NumberFormat ss:Format="'.xlsx_escape_attr($props['numberFormat']).'"/>');
        }
        fwrite($out, "</Style>\n");
    }
    fwrite($out, "  </Styles>\n");

    // Worksheet
    fwrite($out, '  <Worksheet ss:Name="Statement">'."\n");
    $cols = $doc['columns'] ?: [];
    $expandedCols = count($cols);
    foreach ($doc['rows'] as $row) {
        $span = 0;
        foreach ($row['cells'] as $cell) {
            $span += 1 + (int)($cell['merge'] ?? 0);
        }
        $expandedCols = max($expandedCols, $span);
    }
    if ($expandedCols < 1) $expandedCols = 1;
    while (count($cols) < $expandedCols) $cols[] = 100;
    $expandedRows = count($doc['rows']) + 1;
    fwrite($out, '    <Table ss:ExpandedColumnCount="'.$expandedCols.'" ss:ExpandedRowCount="'.$expandedRows.'">'."\n");
    foreach ($cols as $w) {
        fwrite($out, '      <Column ss:Width="'.(float)$w.'"/>'."\n");
    }
    foreach ($doc['rows'] as $row) {
        $heightAttr = $row['height'] ? ' ss:Height="'.$row['height'].'"' : '';
        fwrite($out, '      <Row'.$heightAttr.'>'."\n");
        foreach ($row['cells'] as $cell) {
            $style = $cell['style'] ?: 'Default';
            $value = $cell['value'];
            $type = $cell['type'];
            if ($type === null) {
                if (is_bool($value)) { $type = 'Boolean'; }
                elseif (is_int($value) || is_float($value)) { $type = 'Number'; }
                elseif (preg_match('/^\-?\d+(\.\d+)?$/', (string)$value)) { $type = 'Number'; }
                else { $type = 'String'; }
            }
            $attr = ' ss:StyleID="'.xlsx_escape_attr($style).'"';
            if (!empty($cell['merge'])) $attr .= ' ss:MergeAcross="'.(int)$cell['merge'].'"';
            $indexAttr = $cell['index'] !== null ? ' ss:Index="'.(int)$cell['index'].'"' : '';
            if ($cell['formula']) {
                fwrite($out, '        <Cell'.$indexAttr.$attr.' ss:Formula="'.xlsx_escape_attr($cell['formula']).'"><Data ss:Type="'.$type.'">'.xlsx_escape_data($value).'</Data></Cell>'."\n");
            } else {
                fwrite($out, '        <Cell'.$indexAttr.$attr.'><Data ss:Type="'.$type.'">'.xlsx_escape_data($value).'</Data></Cell>'."\n");
            }
        }
        fwrite($out, "      </Row>\n");
    }
    fwrite($out, "    </Table>\n");
    fwrite($out, "  </Worksheet>\n");
    fwrite($out, "</Workbook>\n");
    fclose($out);
    exit;
}

function xlsx_escape_attr($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsx_escape_data($v): string
{
    return htmlspecialchars((string)$v, ENT_XML1, 'UTF-8');
}
