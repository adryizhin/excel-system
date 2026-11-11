<?php

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once __DIR__ . "/config/database.php";

$templates = [];

try {

    $result = $conn->query("
        SELECT
            id,
            name,
            column_count,
            row_count,
            created_at
        FROM templates
        ORDER BY id DESC
    ");

    if ($result) {
        $templates = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }

} catch (Throwable $e) {

    $templates = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

    <title>Excel Builder System</title>
    <script src="assets/js/theme.js?v=<?= filemtime(__DIR__ . '/assets/js/theme.js') ?>"></script>

    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >
</head>

<body>

<div class="app-shell">

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-icon">
                <i class="fa-solid fa-file-excel"></i>
            </div>

            <div>
                <h2>Excel Builder</h2>
                <span>Data Conversion System</span>
            </div>

        </div>

        <nav>

            <button
                class="nav-item active"
                data-view="dashboard"
            >
                <i class="fa-solid fa-gauge-high"></i>
                Dashboard
            </button>

            <button
                class="nav-item"
                data-view="builder"
            >
                <i class="fa-solid fa-table"></i>
                Create Excel
            </button>

        </nav>

        <div class="sidebar-footer">

            <i class="fa-solid fa-code"></i>

            <span>
                Almanza Uno Health Center
            </span>

        </div>

    </aside>


    <main class="main">

        <header class="topbar">

            <div>

                <button
                    class="mobile-menu"
                    id="mobileMenu"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <span class="eyebrow">
                    Almanza Uno Health Center Excel System
                </span>

                <h1 id="pageTitle">
                    Dashboard
                </h1>

            </div>

            <div class="topbar-actions">
                <button type="button" class="secondary-btn" id="themeToggle" aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
                    <i class="fa-solid fa-moon" aria-hidden="true"></i>
                </button>
                <button class="primary-btn" id="newExcelBtn">
                    <i class="fa-solid fa-plus"></i> New Excel
                </button>
            </div>

        </header>


        <section
            class="view active"
            id="dashboardView"
        >

            <div class="hero-card">

                <div>

                    <span class="badge">
                        <i class="fa-solid fa-bolt"></i>
                        Flexible Excel Builder
                    </span>

                    <h2>
                        Create your spreadsheet your way.
                    </h2>

                    <p>
                        Choose the number of columns and rows,
                        define your own headings, encode your data,
                        preview it, and export it as an Excel workbook.
                    </p>

                    <button
                        class="primary-btn large"
                        id="heroCreateBtn"
                    >
                        <i class="fa-solid fa-file-circle-plus"></i>
                        Create New Spreadsheet
                    </button>

                </div>

                <div class="hero-icon">
                    <i class="fa-solid fa-file-excel"></i>
                </div>

            </div>


            <div class="stats-grid">

                <div class="stat-card">

                    <div class="stat-icon purple">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>

                    <div>
                        <span>Saved Templates</span>
                        <strong id="templateCount">
                            <?= count($templates) ?>
                        </strong>
                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon green">
                        <i class="fa-solid fa-table-columns"></i>
                    </div>

                    <div>
                        <span>Flexible Columns</span>
                        <strong>Unlimited</strong>
                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon orange">
                        <i class="fa-solid fa-file-export"></i>
                    </div>

                    <div>
                        <span>Export Format</span>
                        <strong>XLSX</strong>
                    </div>

                </div>

            </div>


            <div class="panel">

                <div class="panel-header">

                    <div>

                        <h3>
                            Saved Templates
                        </h3>

                        <p>
                            Previously saved spreadsheet structures.
                        </p>

                    </div>

                    <button
                        class="secondary-btn"
                        id="refreshTemplates"
                    >
                        <i class="fa-solid fa-rotate"></i>
                        Refresh
                    </button>

                </div>


                <div class="template-search">
                    <input type="search" id="templateSearch" aria-label="Search saved templates" placeholder="Search saved templates...">
                </div>
                <div class="table-wrap">

                    <table class="data-table">

                        <thead>

                            <tr>

                                <th>Name</th>
                                <th>Columns</th>
                                <th>Rows</th>
                                <th>Created</th>
                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody id="templateList">

                        <?php if (!$templates): ?>

                            <tr>

                                <td
                                    colspan="5"
                                    class="empty"
                                >
                                    No saved templates yet.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($templates as $template): ?>

                                <tr data-template-id="<?= (int)$template['id'] ?>">

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $template["name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= (int)$template["column_count"] ?>
                                    </td>

                                    <td>
                                        <?= (int)$template["row_count"] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (new DateTimeImmutable($template["created_at"]))->format('M d, Y h:i A'),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

                                    <td>

                                        <button
                                            class="icon-btn"
                                            title="Load template"
                                            onclick="loadTemplate(<?= (int)$template['id'] ?>)"
                                        >
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="icon-btn danger delete-template-btn"
                                            title="Delete template"
                                            aria-label="Delete template"
                                            data-id="<?= (int)$template['id'] ?>"
                                        >
                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </section>


        <section
            class="view"
            id="builderView"
        >

            <div class="builder-grid">

                <div class="panel setup-panel">

                    <div class="panel-header">

                        <div>

                            <h3>
                                Spreadsheet Setup
                            </h3>

                            <p>
                                Start by choosing your spreadsheet dimensions.
                            </p>

                        </div>

                    </div>


                    <form id="setupForm">

                        <div class="field">

                            <label for="fileName">
                                Excel File Name
                            </label>

                            <div class="input-icon">

                                <i class="fa-solid fa-file"></i>

                                <input
                                    type="text"
                                    id="fileName"
                                    value="My Spreadsheet"
                                    maxlength="120"
                                    required
                                >

                            </div>

                        </div>


                        <div class="dimension-grid">

                            <div class="field">

                                <label for="columnCount">
                                    Columns
                                </label>

                                <div class="number-control">

                                    <button
                                        type="button"
                                        data-target="columnCount"
                                        data-step="-1"
                                    >
                                        −
                                    </button>

                                    <input
                                        type="number"
                                        id="columnCount"
                                        min="1"
                                        max="50"
                                        value="5"
                                    >

                                    <button
                                        type="button"
                                        data-target="columnCount"
                                        data-step="1"
                                    >
                                        +
                                    </button>

                                </div>

                            </div>


                            <div class="field">

                                <label for="rowCount">
                                    Rows
                                </label>

                                <div class="number-control">

                                    <button
                                        type="button"
                                        data-target="rowCount"
                                        data-step="-1"
                                    >
                                        −
                                    </button>

                                    <input
                                        type="number"
                                        id="rowCount"
                                        min="1"
                                        max="500"
                                        value="10"
                                    >

                                    <button
                                        type="button"
                                        data-target="rowCount"
                                        data-step="1"
                                    >
                                        +
                                    </button>

                                </div>

                            </div>

                        </div>


                        <button
                            type="button"
                            class="primary-btn full"
                            id="generateBtn"
                        >
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                            Generate Table
                        </button>

                    </form>


                    <div class="tip">

                        <i class="fa-solid fa-circle-info"></i>

                        <div>

                            <strong>Tip</strong>

                            <span>
                                You can also add or remove rows
                                and columns after generating the table.
                            </span>

                        </div>

                    </div>

                </div>


                <div class="panel instructions">

                    <div class="panel-header">

                        <div>

                            <h3>
                                How it works
                            </h3>

                            <p>
                                Four simple steps.
                            </p>

                        </div>

                    </div>


                    <ol class="steps">

                        <li>

                            <span>1</span>

                            <div>

                                <strong>
                                    Choose dimensions
                                </strong>

                                <small>
                                    Set your initial rows and columns.
                                </small>

                            </div>

                        </li>


                        <li>

                            <span>2</span>

                            <div>

                                <strong>
                                    Name your columns
                                </strong>

                                <small>
                                    Enter the headings you need.
                                </small>

                            </div>

                        </li>


                        <li>

                            <span>3</span>

                            <div>

                                <strong>
                                    Encode information
                                </strong>

                                <small>
                                    Fill in your spreadsheet data.
                                </small>

                            </div>

                        </li>


                        <li>

                            <span>4</span>

                            <div>

                                <strong>
                                    Export to Excel
                                </strong>

                                <small>
                                    Download a real .xlsx file.
                                </small>

                            </div>

                        </li>

                    </ol>

                </div>

            </div>


            <div
                class="panel spreadsheet-panel hidden"
                id="spreadsheetPanel"
            >

                <div class="panel-header">

                    <div>

                        <h3 id="sheetTitle">My Spreadsheet</h3>

                    </div>

                </div>

                <div class="format-toolbar" role="toolbar" aria-label="Spreadsheet formatting">
                    <button type="button" id="undoBtn" title="Undo (Ctrl+Z)" aria-label="Undo"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
                    <button type="button" id="redoBtn" title="Redo (Ctrl+Y)" aria-label="Redo"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
                    <span class="format-divider"></span>
                    <button type="button" data-format="bold" title="Bold (Ctrl+B)" aria-label="Bold" aria-pressed="false"><i class="fa-solid fa-bold" aria-hidden="true"></i></button>
                    <button type="button" data-format="italic" title="Italic (Ctrl+I)" aria-label="Italic" aria-pressed="false"><i class="fa-solid fa-italic" aria-hidden="true"></i></button>
                    <button type="button" data-format="underline" title="Underline (Ctrl+U)" aria-label="Underline" aria-pressed="false"><i class="fa-solid fa-underline" aria-hidden="true"></i></button>
                    <button type="button" data-format="strike" title="Strikethrough" aria-label="Strikethrough" aria-pressed="false"><i class="fa-solid fa-strikethrough" aria-hidden="true"></i></button>
                    <select id="cellFontSize" aria-label="Font size" title="Font size">
                        <option>9</option><option>10</option><option selected>11</option><option>12</option><option>14</option><option>16</option><option>18</option><option>20</option><option>24</option>
                    </select>
                    <span class="format-divider"></span>
                    <button type="button" data-align="left" title="Align left (Ctrl+L)" aria-label="Align left" aria-pressed="false"><i class="fa-solid fa-align-left" aria-hidden="true"></i></button>
                    <button type="button" data-align="center" title="Center (Ctrl+E)" aria-label="Center text" aria-pressed="false"><i class="fa-solid fa-align-center" aria-hidden="true"></i></button>
                    <button type="button" data-align="right" title="Align right (Ctrl+R)" aria-label="Align right" aria-pressed="false"><i class="fa-solid fa-align-right" aria-hidden="true"></i></button>
                    <span class="format-divider"></span>
                    <button type="button" data-valign="top" title="Align top" aria-label="Align top" aria-pressed="true"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                    <button type="button" data-valign="middle" title="Align middle" aria-label="Align middle" aria-pressed="false"><i class="fa-solid fa-arrows-up-down" aria-hidden="true"></i></button>
                    <button type="button" data-valign="bottom" title="Align bottom" aria-label="Align bottom" aria-pressed="false"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
                    <button type="button" id="clearFormatBtn" title="Clear formatting" aria-label="Clear formatting"><i class="fa-solid fa-eraser" aria-hidden="true"></i></button>
                </div>

                <div class="sheet-toolbar">

                    <div>

                        <button
                            class="secondary-btn small"
                            id="saveTemplateBtn"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Template
                        </button>

                        <button
                            class="primary-btn small"
                            id="exportBtn"
                        >
                            <i class="fa-solid fa-file-excel"></i>
                            Export to Excel
                        </button>

                    </div>

                </div>


                <div class="pdf-settings" aria-label="PDF export settings">
                    <label for="pdfPaperSize">PDF paper
                        <select id="pdfPaperSize">
                            <option value="a4">A4 (210 × 297 mm)</option>
                            <option value="legal">Legal (8.5 × 14 in)</option>
                            <option value="letter">Letter (8.5 × 11 in)</option>
                            <option value="a3">A3 (297 × 420 mm)</option>
                            <option value="a5">A5 (148 × 210 mm)</option>
                            <option value="folio">Long / Folio (8.5 × 13 in)</option>
                            <option value="custom">Custom size</option>
                        </select>
                    </label>
                    <label for="pdfOrientation">Orientation
                        <select id="pdfOrientation">
                            <option value="portrait">Portrait</option>
                            <option value="landscape">Landscape</option>
                        </select>
                    </label>
                    <label for="pdfWidth" class="pdf-custom hidden">Width (mm)
                        <input id="pdfWidth" type="number" min="50" max="2000" step="0.1" value="210">
                    </label>
                    <label for="pdfHeight" class="pdf-custom hidden">Height (mm)
                        <input id="pdfHeight" type="number" min="50" max="2000" step="0.1" value="297">
                    </label>
                    <button type="button" class="secondary-btn small" id="exportPdfBtn">
                        <i class="fa-solid fa-file-pdf"></i> Export to PDF
                    </button>
                </div>

                <div class="spreadsheet-wrap">

                    <table id="spreadsheet">

                        <thead></thead>

                        <tbody></tbody>

                    </table>

                </div>

            </div>

        </section>

    </main>

</div>


<dialog id="dropdownDialog" aria-labelledby="dropdownDialogTitle">
    <form id="dropdownForm">
        <h2 id="dropdownDialogTitle">Dropdown choices</h2>
        <p id="dropdownColumnName"></p>
        <label class="dropdown-list-label">Allowed values</label>
        <div id="dropdownChoiceList"></div>
        <button type="button" class="secondary-btn small" id="addDropdownChoice">+ Add choice</button>
        <p id="dropdownError" role="alert"></p>
        <div class="dropdown-dialog-actions">
            <button type="button" class="secondary-btn" id="cancelDropdownChoices">Cancel</button>
            <button type="submit" class="primary-btn">Apply</button>
        </div>
    </form>
</dialog>

<div
    class="toast"
    id="toast"
></div>


<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
<script src="assets/js/template-search.js?v=<?= filemtime(__DIR__ . '/assets/js/template-search.js') ?>"></script>
<script src="assets/js/sheet-formatting.js?v=<?= filemtime(__DIR__ . '/assets/js/sheet-formatting.js') ?>"></script>
<script src="assets/js/column-resize.js?v=<?= filemtime(__DIR__ . '/assets/js/column-resize.js') ?>"></script>
<script src="assets/js/sheet-context-menu.js?v=<?= filemtime(__DIR__ . '/assets/js/sheet-context-menu.js') ?>"></script>
<script src="assets/js/dropdown-editor.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdown-editor.js') ?>"></script>
<script src="assets/js/vendor/jspdf.umd.min.js"></script>
<script src="assets/js/vendor/jspdf.plugin.autotable.min.js"></script>
<script src="assets/js/pdf-export.js?v=<?= filemtime(__DIR__ . '/assets/js/pdf-export.js') ?>"></script>

</body>
</html>
