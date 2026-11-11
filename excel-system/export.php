<?php
declare(strict_types=1);

ob_start();


/*
|--------------------------------------------------------------------------
| Request Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "POST request required."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check ZipArchive
|--------------------------------------------------------------------------
*/

if (!class_exists("ZipArchive")) {

    http_response_code(500);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" =>
            "PHP ZipArchive is not enabled. " .
            "Enable extension=zip in php.ini and restart Apache."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Read JSON
|--------------------------------------------------------------------------
*/

$raw = file_get_contents("php://input");

if ($raw === false || trim($raw) === "") {

    http_response_code(400);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "No spreadsheet data received."
    ]);

    exit;
}


$payload = json_decode(
    $raw,
    true
);


if (
    $payload === null &&
    json_last_error() !== JSON_ERROR_NONE
) {

    http_response_code(400);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" =>
            "Invalid JSON: " .
            json_last_error_msg()
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Spreadsheet Data
|--------------------------------------------------------------------------
*/

$fileName =
    trim(
        (string)(
            $payload["file_name"]
            ?? "My Spreadsheet"
        )
    );


$headers =
    $payload["headers"]
    ?? [];


$rows =
    $payload["data"]
    ?? [];


if ($fileName === "") {
    $fileName = "My Spreadsheet";
}


if (!is_array($headers)) {

    http_response_code(400);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "Invalid column data."
    ]);

    exit;
}


if (!is_array($rows)) {

    http_response_code(400);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "Invalid row data."
    ]);

    exit;
}


if (count($headers) === 0) {

    http_response_code(400);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "At least one column is required."
    ]);

    exit;
}


require_once __DIR__ . '/column_types.php';
try {
    if (count($headers) > 50 || count($rows) > 500) throw new InvalidArgumentException('Maximum of 50 columns and 500 rows allowed.');
    require_once __DIR__ . '/cell_formats.php';
    $columnWidths = columnWidths($payload, count($headers));
    $formats = cellFormats($payload, count($rows), count($headers));
    $types = columnTypes($payload, count($headers));
    $options = columnOptions($payload, $types);
    validateTypedRows($rows, $types, $options);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Clean File Name
|--------------------------------------------------------------------------
*/

$fileName = preg_replace(
    '/[\\\\\/:*?"<>|]+/',
    "-",
    $fileName
);

$fileName =
    trim(
        (string)$fileName,
        ". "
    );

if ($fileName === "") {
    $fileName = "My Spreadsheet";
}


/*
|--------------------------------------------------------------------------
| Excel XML Helpers
|--------------------------------------------------------------------------
*/

function xmlEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_XML1 | ENT_QUOTES,
        "UTF-8"
    );
}


function excelColumn(int $number): string
{
    $letters = "";

    while ($number > 0) {

        $number--;

        $letters =
            chr(
                65 +
                ($number % 26)
            ) .
            $letters;

        $number =
            intdiv(
                $number,
                26
            );
    }

    return $letters;
}


/*
|--------------------------------------------------------------------------
| Create Temporary XLSX File
|--------------------------------------------------------------------------
*/

$tempFile =
    tempnam(
        sys_get_temp_dir(),
        "excel_"
    );


if ($tempFile === false) {

    http_response_code(500);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "Unable to create temporary file."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| XML Content
|--------------------------------------------------------------------------
*/

$formatStyles = [];
$extraFonts = '';
$extraXfs = '';
function formatStyle(array $format, bool $date): int
{
    global $formatStyles, $extraFonts, $extraXfs;
    if (!$format) return $date ? 2 : 0;
    $key = json_encode([$format, $date]);
    if (isset($formatStyles[$key])) return $formatStyles[$key];
    $index = count($formatStyles) + 3;
    $font = count($formatStyles) + 2;
    $extraFonts .= '<font><sz val="' . ($format['fontSize'] ?? 11) . '"/><name val="Calibri"/>' . (!empty($format['bold']) ? '<b/>' : '') . (!empty($format['italic']) ? '<i/>' : '') . (!empty($format['underline']) ? '<u/>' : '') . (!empty($format['strike']) ? '<strike/>' : '') . '</font>';
    $extraXfs .= '<xf numFmtId="' . ($date ? 14 : 0) . '" fontId="' . $font . '" fillId="0" borderId="0" applyFont="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="' . ($format['align'] ?? 'left') . '" vertical="' . (($format['valign'] ?? 'top') === 'middle' ? 'center' : ($format['valign'] ?? 'top')) . '" wrapText="1"/></xf>';
    return $formatStyles[$key] = $index;
}

$sheetRows = "";

$columnCount =
    count($headers);


/*
|--------------------------------------------------------------------------
| Header Row
|--------------------------------------------------------------------------
*/

$sheetRows .= '<row r="1">';

for (
    $column = 0;
    $column < $columnCount;
    $column++
) {

    $cellReference =
        excelColumn($column + 1) .
        "1";

    $value =
        is_scalar($headers[$column] ?? null)
            ? (string)$headers[$column]
            : "";


    $sheetRows .=
        '<c r="' .
        $cellReference .
        '" t="inlineStr" s="1">' .
        '<is><t>' .
        xmlEscape($value) .
        '</t></is></c>';
}

$sheetRows .= "</row>";


/*
|--------------------------------------------------------------------------
| Data Rows
|--------------------------------------------------------------------------
*/

foreach ($rows as $rowIndex => $row) {

    if (!is_array($row)) {
        $row = [];
    }

    $excelRow =
        $rowIndex + 2;

    $sheetRows .=
        '<row r="' .
        $excelRow .
        '">';


    for (
        $column = 0;
        $column < $columnCount;
        $column++
    ) {

        $value =
            $row[$column]
            ?? "";


        if (
            is_array($value) ||
            is_object($value)
        ) {
            $value = "";
        }


        $value =
            (string)$value;


        $cellReference =
            excelColumn($column + 1) .
            $excelRow;


        $cellStyle = formatStyle($formats[$rowIndex][$column] ?? [], $types[$column] === 'date');
        if ($value !== '' && $types[$column] === 'date') {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $base = new DateTimeImmutable('1899-12-31', new DateTimeZone('UTC'));
            $serial = (int)$base->diff($date)->format('%a');
            if ($value >= '1900-03-01') $serial++;
            $sheetRows .= '<c r="' . $cellReference . '" s="' . $cellStyle . '"><v>' . $serial . '</v></c>';
            continue;
        }
        if ($value !== '' && $types[$column] === 'number') {
            $sheetRows .= '<c r="' . $cellReference . '" s="' . $cellStyle . '"><v>' . sprintf('%.15g', (float)$value) . '</v></c>';
            continue;
        }

        $sheetRows .=
            '<c r="' .
            $cellReference .
            '" s="' . $cellStyle . '" t="inlineStr">' .
            '<is><t>' .
            xmlEscape($value) .
            '</t></is></c>';
    }


    $sheetRows .= "</row>";
}


$lastColumn =
    excelColumn(
        $columnCount
    );


$lastRow =
    count($rows) + 1;


$dimension =
    "A1:" .
    $lastColumn .
    $lastRow;


/*
|--------------------------------------------------------------------------
| worksheet.xml
|--------------------------------------------------------------------------
*/

$validations = '';
foreach ($types as $c => $type) {
    if ($type === 'text') continue;
    $range = excelColumn($c + 1) . '2:' . excelColumn($c + 1) . max(2, $lastRow);
    $rule = in_array($type, ['gender', 'dropdown'], true) ? 'list' : ($type === 'date' ? 'date' : 'decimal');
    $formula = $type === 'gender' ? '<formula1>&quot;Male,Female&quot;</formula1>' : ($type === 'date' ? '<formula1>1</formula1><formula2>2958465</formula2>' : '<formula1>-1.7976931348623157E+308</formula1><formula2>1.7976931348623157E+308</formula2>');
    if ($type === 'dropdown') $formula = '<formula1>Choices_' . ($c + 1) . '</formula1>';
    $validations .= '<dataValidation type="' . $rule . '" operator="between" allowBlank="1" showDropDown="0" showErrorMessage="1" errorStyle="stop" errorTitle="Invalid value" error="Enter a valid ' . $type . ' value." sqref="' . $range . '">' . $formula . '</dataValidation>';
}
if ($validations !== '') $validations = '<dataValidations count="' . count(array_filter($types, fn($t) => $t !== 'text')) . '">' . $validations . '</dataValidations>';

$columnXml = '';
foreach ($columnWidths as $c => $pixels) {
    $columnXml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . round(($pixels - 5) / 7, 2) . '" customWidth="1"/>';
}
$worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet
    xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">

    <dimension ref="' . $dimension . '"/>

    <sheetViews>
        <sheetView workbookViewId="0">
            <pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>
            <selection pane="bottomLeft" activeCell="A2" sqref="A2"/>
        </sheetView>
    </sheetViews>

    <sheetFormatPr defaultRowHeight="20"/>

    <cols>
        ' . $columnXml . '
    </cols>

    <sheetData>
        ' . $sheetRows . '
    </sheetData>

    <autoFilter ref="' . $dimension . '"/>
    ' . $validations . '

</worksheet>';


/*
|--------------------------------------------------------------------------
| workbook.xml
|--------------------------------------------------------------------------
*/

$definedNames = '';
$choiceRows = '';
$maxChoices = 0;
foreach ($types as $c => $type) {
    if ($type !== 'dropdown') continue;
    $letter = excelColumn($c + 1);
    $count = count($options[$c]);
    $maxChoices = max($maxChoices, $count);
    $definedNames .= '<definedName name="Choices_' . ($c + 1) . '">' . "'_Choices'!$" . $letter . '$1:$' . $letter . '$' . $count . '</definedName>';
}
for ($r = 0; $r < $maxChoices; $r++) {
    $choiceRows .= '<row r="' . ($r + 1) . '">';
    foreach ($types as $c => $type) {
        if ($type !== 'dropdown' || !isset($options[$c][$r])) continue;
        $choiceRows .= '<c r="' . excelColumn($c + 1) . ($r + 1) . '" t="inlineStr"><is><t xml:space="preserve">' . xmlEscape($options[$c][$r]) . '</t></is></c>';
    }
    $choiceRows .= '</row>';
}
$choicesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $choiceRows . '</sheetData></worksheet>';
if ($definedNames !== '') $definedNames = '<definedNames>' . $definedNames . '</definedNames>';

$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook
    xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">

    <bookViews>
        <workbookView/>
    </bookViews>

    <sheets>
        <sheet
            name="Data"
            sheetId="1"
            r:id="rId1"/>
        <sheet name="_Choices" sheetId="2" state="hidden" r:id="rId3"/>
    </sheets>
    ' . $definedNames . '
</workbook>';


/*
|--------------------------------------------------------------------------
| styles.xml
|--------------------------------------------------------------------------
*/

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet
    xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">

    <fonts count="2">

        <font>
            <sz val="11"/>
            <name val="Calibri"/>
            <family val="2"/>
        </font>

        <font>
            <b/>
            <color rgb="FFFFFFFF"/>
            <sz val="11"/>
            <name val="Calibri"/>
            <family val="2"/>
        </font>

    </fonts>

    <fills count="3">

        <fill>
            <patternFill patternType="none"/>
        </fill>

        <fill>
            <patternFill patternType="gray125"/>
        </fill>

        <fill>
            <patternFill patternType="solid">
                <fgColor rgb="FF7740F8"/>
                <bgColor indexed="64"/>
            </patternFill>
        </fill>

    </fills>

    <borders count="1">

        <border>
            <left/>
            <right/>
            <top/>
            <bottom/>
            <diagonal/>
        </border>

    </borders>

    <cellStyleXfs count="1">

        <xf numFmtId="0"
            fontId="0"
            fillId="0"
            borderId="0"/>

    </cellStyleXfs>

    <cellXfs count="3">

        <xf numFmtId="0"
            fontId="0"
            fillId="0"
            borderId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>

        <xf numFmtId="0"
            fontId="1"
            fillId="2"
            borderId="0"
            applyFont="1"
            applyFill="1"
            applyAlignment="1">

            <alignment
                horizontal="center"
                vertical="center" wrapText="1"/>

        </xf>

        <xf numFmtId="14" fontId="0" fillId="0" borderId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
    </cellXfs>

</styleSheet>';


/*
|--------------------------------------------------------------------------
| Relationships
|--------------------------------------------------------------------------
*/

$stylesXml = str_replace('<fonts count="2">', '<fonts count="' . (2 + count($formatStyles)) . '">', $stylesXml);
$stylesXml = str_replace('</fonts>', $extraFonts . '</fonts>', $stylesXml);
$stylesXml = str_replace('<cellXfs count="3">', '<cellXfs count="' . (3 + count($formatStyles)) . '">', $stylesXml);
$stylesXml = str_replace('</cellXfs>', $extraXfs . '</cellXfs>', $stylesXml);

$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships
    xmlns="http://schemas.openxmlformats.org/package/2006/relationships">

    <Relationship
        Id="rId1"
        Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
        Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>

</Relationships>';


$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships
    xmlns="http://schemas.openxmlformats.org/package/2006/relationships">

    <Relationship
        Id="rId1"
        Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
        Target="xl/workbook.xml"/>

</Relationships>';


/*
|--------------------------------------------------------------------------
| Content Types
|--------------------------------------------------------------------------
*/

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types
    xmlns="http://schemas.openxmlformats.org/package/2006/content-types">

    <Default
        Extension="rels"
        ContentType="application/vnd.openxmlformats-package.relationships+xml"/>

    <Default
        Extension="xml"
        ContentType="application/xml"/>

    <Override
        PartName="/xl/workbook.xml"
        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>

    <Override
        PartName="/xl/worksheets/sheet1.xml"
        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>

    <Override
        PartName="/xl/styles.xml"
        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>

    <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>';


/*
|--------------------------------------------------------------------------
| Create ZIP / XLSX
|--------------------------------------------------------------------------
*/

$zip =
    new ZipArchive();

$result =
    $zip->open(
        $tempFile,
        ZipArchive::CREATE |
        ZipArchive::OVERWRITE
    );


if ($result !== true) {

    @unlink($tempFile);

    http_response_code(500);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "Unable to create Excel workbook."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Add XLSX Files
|--------------------------------------------------------------------------
*/

$zip->addFromString(
    "[Content_Types].xml",
    $contentTypes
);

$zip->addFromString(
    "_rels/.rels",
    $rootRels
);

$zip->addFromString(
    "xl/workbook.xml",
    $workbookXml
);

$zip->addFromString(
    "xl/_rels/workbook.xml.rels",
    $workbookRels
);

$zip->addFromString(
    "xl/styles.xml",
    $stylesXml
);

$zip->addFromString(
    "xl/worksheets/sheet1.xml",
    $worksheetXml
);


$zip->addFromString('xl/worksheets/sheet2.xml', $choicesXml);
$zip->close();


/*
|--------------------------------------------------------------------------
| Download
|--------------------------------------------------------------------------
*/

if (!file_exists($tempFile)) {

    http_response_code(500);

    header("Content-Type: application/json");

    echo json_encode([
        "success" => false,
        "message" => "Excel file was not created."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Clean Any Previous Output
|--------------------------------------------------------------------------
*/

if (ob_get_length()) {
    ob_clean();
}


header(
    "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
);

header(
    'Content-Disposition: attachment; filename="' .
    $fileName .
    '.xlsx"'
);

header(
    "Content-Length: " .
    filesize($tempFile)
);

header(
    "Cache-Control: max-age=0"
);

header(
    "Pragma: public"
);


/*
|--------------------------------------------------------------------------
| Send File
|--------------------------------------------------------------------------
*/

readfile($tempFile);


/*
|--------------------------------------------------------------------------
| Remove Temporary File
|--------------------------------------------------------------------------
*/

@unlink($tempFile);

exit;
?>