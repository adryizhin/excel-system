const state = {
    templateId: null,
    selectedRow: 0,
    selectedColumn: 0,
    rows: 10,
    cols: 5,
    headers: [],
    column_types: [],
    column_options: [],
    formats: [],
    column_widths: [],
    selectionKind: "cell",
    data: []
};

const $ = (selector) => document.querySelector(selector);

const dashboardView = $("#dashboardView");
const builderView = $("#builderView");
const pageTitle = $("#pageTitle");
const spreadsheetPanel = $("#spreadsheetPanel");
const spreadsheet = $("#spreadsheet");
const toast = $("#toast");

function showToast(message, type = "success") {
    toast.textContent = message;
    toast.className = `toast show ${type}`;
    clearTimeout(showToast.timer);
    showToast.timer = setTimeout(() => toast.classList.remove("show"), 3000);
}

function switchView(view) {
    const dashboard = view === "dashboard";
    dashboardView.classList.toggle("active", dashboard);
    builderView.classList.toggle("active", !dashboard);
    pageTitle.textContent = dashboard ? "Dashboard" : "Create Excel";

    document.querySelectorAll(".nav-item").forEach(btn => {
        btn.classList.toggle("active", btn.dataset.view === view);
    });
}

function createEmptyState(rows, cols) {
    state.selectedRow = 0;
    state.selectedColumn = 0;
    state.selectionKind = "cell";
    state.rows = Math.max(1, Math.min(500, Number(rows) || 1));
    state.cols = Math.max(1, Math.min(50, Number(cols) || 1));

    state.column_types = Array.from({length: state.cols}, (_, c) => state.column_types[c] || "text");
    state.column_options = Array.from({length: state.cols}, (_, c) => state.column_options[c] || []);
    state.column_widths = Array.from({length: state.cols}, () => 180);
    state.formats = Array.from({length: state.rows}, () => Array.from({length: state.cols}, () => ({})));
    state.headers = Array.from({length: state.cols}, (_, i) => `Column ${i + 1}`);
    state.data = Array.from({length: state.rows}, () =>
        Array.from({length: state.cols}, () => "")
    );
}

function preserveAndResize(newRows, newCols) {
    const oldData = state.data;
    const oldHeaders = state.headers;

    state.rows = Math.max(1, Math.min(500, Number(newRows) || 1));
    state.cols = Math.max(1, Math.min(50, Number(newCols) || 1));

    state.column_types = Array.from({length: state.cols}, (_, c) => state.column_types[c] || "text");
    state.column_options = Array.from({length: state.cols}, (_, c) => state.column_options[c] || []);
    state.headers = Array.from({length: state.cols}, (_, c) =>
        oldHeaders[c] || `Column ${c + 1}`
    );

    state.data = Array.from({length: state.rows}, (_, r) =>
        Array.from({length: state.cols}, (_, c) =>
            oldData[r]?.[c] ?? ""
        )
    );
}

function renderTable() {
    const scrollArea = spreadsheet.closest(".spreadsheet-wrap");
    const scrollTop = scrollArea.scrollTop;
    const scrollLeft = scrollArea.scrollLeft;
    spreadsheetPanel.classList.remove("hidden");

    const thead = spreadsheet.querySelector("thead");
    const tbody = spreadsheet.querySelector("tbody");

    thead.innerHTML = "";
    tbody.innerHTML = "";

    const headerRow = document.createElement("tr");
    const indexHead = document.createElement("th");
    indexHead.className = "row-number header-number";
    indexHead.textContent = "#";
    headerRow.appendChild(indexHead);

    state.headers.forEach((header, c) => {
        const th = document.createElement("th");
        th.className = "column-header";
        const columnButton = document.createElement("button");
        columnButton.type = "button";
        columnButton.className = "column-selector";
        columnButton.textContent = columnLetter(c);
        columnButton.dataset.column = c;
        columnButton.title = "Right-click for column options";
        columnButton.setAttribute("aria-haspopup", "menu");
        columnButton.setAttribute("aria-label", `Select column ${columnLetter(c)}`);
        columnButton.addEventListener("click", () => selectPosition(state.selectedRow, c, "column"));
        th.appendChild(columnButton);

        const input = document.createElement("textarea");
        input.rows = 1;
        input.value = header;
        input.placeholder = `Column ${c + 1}`;
        input.dataset.col = c;
        input.setAttribute("aria-label", `Column ${c + 1} name`);

        input.addEventListener("input", e => {
            window.recordSheetHistory?.(`header-${c}`);
            state.headers[c] = e.target.value;
            resizeCellText(input);
        });
        input.addEventListener("focus", () => selectPosition(state.selectedRow, c));

        th.appendChild(input);
        const select = document.createElement("select");
        select.className = "column-type";
        select.setAttribute("aria-label", `Column ${c + 1} type`);
        Object.entries({text: "Text", dropdown: "Dropdown List", date: "Date", number: "Number"}).forEach(([value, label]) => select.add(new Option(label, value)));
        select.value = state.column_types[c] || "text";
        select.addEventListener("focus", () => selectPosition(state.selectedRow, c));
        select.addEventListener("change", () => {
            if (select.value === "dropdown") {
                select.value = state.column_types[c] || "text";
                openDropdownEditor(c);
            } else {
                window.recordSheetHistory?.();
                state.column_types[c] = select.value;
                renderTable();
            }
        });
        th.appendChild(select);
        if (state.column_types[c] === "dropdown") {
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "edit-dropdown-btn";
            edit.textContent = "Edit choices";
            edit.setAttribute("aria-label", `Edit dropdown choices for column ${columnLetter(c)}`);
            edit.addEventListener("click", () => openDropdownEditor(c));
            th.appendChild(edit);
        }
        headerRow.appendChild(th);
    });

    thead.appendChild(headerRow);

    state.data.forEach((row, r) => {
        const tr = document.createElement("tr");

        const rowNumber = document.createElement("td");
        rowNumber.className = "row-number";
        const rowButton = document.createElement("button");
        rowButton.type = "button";
        rowButton.className = "row-selector";
        rowButton.textContent = r + 1;
        rowButton.dataset.row = r;
        rowButton.title = "Right-click for row options";
        rowButton.setAttribute("aria-haspopup", "menu");
        rowButton.setAttribute("aria-label", `Select row ${r + 1}`);
        rowButton.addEventListener("click", () => selectPosition(r, state.selectedColumn, "row"));
        rowNumber.appendChild(rowButton);
        tr.appendChild(rowNumber);

        state.data[r].forEach((value, c) => {
            const td = document.createElement("td");
            const input = document.createElement(["gender", "dropdown"].includes(state.column_types[c]) ? "select" : state.column_types[c] === "date" ? "input" : "textarea");
            if (input.tagName === "SELECT") {
                input.className = "cell-dropdown";
                fillSelection(input, c, value);
            }
            const type = state.column_types[c] || "text";
            if (input.tagName === "INPUT") input.type = type === "date" || type === "number" ? type : "text";
            if (input.tagName === "TEXTAREA") { input.rows = 1; if (type === "number") input.inputMode = "decimal"; }
            if (type === "date") { input.min = "1900-01-01"; input.max = "9999-12-31"; }
            // Keep incompatible existing values visible until corrected.
            if (!validCell(value, type, c) && input.tagName === "INPUT") input.type = "text";
            input.classList.toggle("invalid-cell", !validCell(value, type, c));
            input.setAttribute("aria-label", `${state.headers[c]}, row ${r + 1}`);
            input.value = value;
            window.applyCellFormat?.(input, state.formats[r]?.[c] || {});
            input.dataset.row = r;
            input.dataset.col = c;
            input.addEventListener("focus", () => selectPosition(r, c));
            input.addEventListener("input", e => {
                window.recordSheetHistory?.(`cell-${r}-${c}`);
                state.data[r][c] = e.target.value;
                resizeCellText(input);
                if (input.nextElementSibling?.classList.contains("dropdown-value")) input.nextElementSibling.textContent = input.value;
                input.classList.toggle("invalid-cell", !validCell(input.value, type, c));
            });
            td.style.verticalAlign = state.formats[r]?.[c]?.valign || "top";
            td.appendChild(input);
            if (input.tagName === "SELECT") {
                td.classList.add("dropdown-cell");
                const display = document.createElement("div");
                display.className = "dropdown-value";
                display.setAttribute("aria-hidden", "true");
                display.textContent = value || "\u00a0";
                td.appendChild(display);
                window.applyCellFormat?.(input, state.formats[r]?.[c] || {});
            }
            tr.appendChild(td);
        });

        tbody.appendChild(tr);
    });

    $("#sheetTitle").textContent = $("#fileName").value.trim() || "My Spreadsheet";
    $("#rowCount").value = state.rows;
    $("#columnCount").value = state.cols;
    selectPosition(state.selectedRow, state.selectedColumn, state.selectionKind);
    window.setupColumnResize?.();
    resizeSheetText();
    scrollArea.scrollTop = scrollTop;
    scrollArea.scrollLeft = scrollLeft;
}

function columnLetter(index) {
    let letters = "";
    for (index++; index > 0; index = Math.floor((index - 1) / 26)) letters = String.fromCharCode(65 + (index - 1) % 26) + letters;
    return letters;
}

function selectPosition(row, column, kind = "cell") {
    state.selectionKind = kind;
    state.selectedRow = Math.max(0, Math.min(state.rows - 1, Number(row) || 0));
    state.selectedColumn = Math.max(0, Math.min(state.cols - 1, Number(column) || 0));
    spreadsheet.querySelectorAll(".column-selector").forEach((button, c) => {
        button.classList.toggle("selected", c === state.selectedColumn);
        button.setAttribute("aria-pressed", String(c === state.selectedColumn));
    });
    spreadsheet.querySelectorAll(".row-selector").forEach((button, r) => {
        button.classList.toggle("selected", r === state.selectedRow);
        button.setAttribute("aria-pressed", String(r === state.selectedRow));
    });
    window.updateFormatToolbar?.();
}

function insertRow(index) {
    if (state.rows >= 500) return showToast("Maximum of 500 rows reached.", "error");
    window.recordSheetHistory?.();
    state.formats.splice(index, 0, Array.from({length: state.cols}, () => ({})));
    state.data.splice(index, 0, Array.from({length: state.cols}, () => ""));
    state.rows++;
    state.selectedRow = index;
    renderTable();
}

function insertColumn(index) {
    if (state.cols >= 50) return showToast("Maximum of 50 columns reached.", "error");
    window.recordSheetHistory?.();
    state.formats.forEach(row => row.splice(index, 0, {}));
    state.column_widths.splice(index, 0, 180);
    state.headers.splice(index, 0, "New Column");
    state.column_types.splice(index, 0, "text");
    state.column_options.splice(index, 0, []);
    state.data.forEach(row => row.splice(index, 0, ""));
    state.cols++;
    state.selectedColumn = index;
    renderTable();
}

function clearRowContents(index) {
    if (!Number.isInteger(index) || index < 0 || index >= state.rows) return;
    if (!state.data[index].some(value => value !== "")) return;
    window.recordSheetHistory?.();
    state.data[index] = Array.from({length: state.cols}, () => "");
    renderTable();
}

function clearColumnContents(index) {
    if (!Number.isInteger(index) || index < 0 || index >= state.cols) return;
    if (!state.data.some(row => row[index] !== "")) return;
    window.recordSheetHistory?.();
    state.data.forEach(row => { row[index] = ""; });
    renderTable();
}

function deleteRow(index = state.selectedRow) {
    if (state.rows <= 1) return showToast("The spreadsheet must have at least one row.", "error");
    window.recordSheetHistory?.();
    state.formats.splice(index, 1);
    state.data.splice(index, 1);
    state.rows--;
    if (index < state.selectedRow) state.selectedRow--;
    renderTable();
}

function deleteColumn(index = state.selectedColumn) {
    if (state.cols <= 1) return showToast("The spreadsheet must have at least one column.", "error");
    window.recordSheetHistory?.();
    state.formats.forEach(row => row.splice(index, 1));
    state.column_widths.splice(index, 1);
    state.headers.splice(index, 1);
    state.column_types.splice(index, 1);
    state.column_options.splice(index, 1);
    state.data.forEach(row => row.splice(index, 1));
    state.cols--;
    if (index < state.selectedColumn) state.selectedColumn--;
    renderTable();
}

function generateTable() {
    const rows = Number($("#rowCount").value);
    const cols = Number($("#columnCount").value);

    state.templateId = null;
    state.column_types = [];
    state.column_options = [];
    createEmptyState(rows, cols);
    renderTable();
    window.resetSheetHistory?.();
    showToast("Spreadsheet created.");
}

function addRow() {
    insertRow(state.rows);
}

function addColumn() {
    insertColumn(state.cols);
}

function removeLastColumn() {
    deleteColumn(state.cols - 1);
}

function validCell(value, type, c) {
    if (type === "dropdown") return value === "" || (state.column_options[c] || []).includes(value);
    if (value === "" || type === "text") return true;
    if (type === "gender") return ["Male", "Female"].includes(value);
    if (type === "number") return /^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/.test(value) && Number.isFinite(Number(value));
    if (type === "date") return /^\d{4}-\d{2}-\d{2}$/.test(value) && value >= "1900-01-01" && value <= "9999-12-31" && !Number.isNaN(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
    return false;
}
function fillSelection(cell, c, value) {
    const choices = state.column_types[c] === "gender" ? ["Male", "Female"] : state.column_options[c] || [];
    cell.replaceChildren(new Option("", ""), ...choices.map(v => new Option(v, v)));
    if (value && !choices.includes(value)) cell.add(new Option(value, value));
    cell.value = value;
}

function validateSheet() {
    for (let c = 0; c < state.cols; c++) {
        if (state.column_types[c] === "dropdown") {
            const choices = state.column_options[c] || [];
            if (!choices.length || choices.length > 100 || choices.some(v => [...v].length > 100)) {
                showToast(`${state.headers[c]}: enter 1 to 100 choices, up to 100 characters each.`, "error");
                return false;
            }
        }
    }
    for (let r = 0; r < state.rows; r++) for (let c = 0; c < state.cols; c++) {
        if (!validCell(state.data[r][c], state.column_types[c], c)) {
            showToast(`Row ${r + 1}, ${state.headers[c]}: enter a valid ${state.column_types[c]} value.`, "error");
            spreadsheet.querySelector(`[data-row="${r}"][data-col="${c}"]`)?.focus();
            return false;
        }
    }
    return true;
}
function collectPayload() {
    return {
        template_id: state.templateId,
        file_name: String(
            document.querySelector("#fileName")?.value || ""
        ).trim(),

        formats: state.formats,
        column_widths: state.column_widths,
        column_types: [...state.column_types],
        column_options: state.column_options.map(options => [...options]),
        headers: Array.isArray(state.headers)
            ? state.headers.map(value => String(value ?? ""))
            : [],

        data: Array.isArray(state.data)
            ? state.data.map(row => {
                if (!Array.isArray(row)) {
                    return [];
                }

                return row.map(value =>
                    String(value ?? "")
                );
            })
            : []
    };
}

async function exportExcel() {
    if (!validateSheet()) return;

    if (
        !state.headers.length ||
        !state.data.length
    ) {

        showToast(
            "Generate a spreadsheet first.",
            "error"
        );

        return;
    }


    const button =
        $("#exportBtn");


    const originalHTML =
        button.innerHTML;


    button.disabled = true;


    button.innerHTML =
        '<i class="fa-solid fa-spinner fa-spin"></i> Exporting...';


    try {

        const response =
            await fetch(
                "export.php",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/json",

                        "Accept":
                            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json"
                    },

                    body:
                        JSON.stringify(
                            collectPayload()
                        )
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Check for PHP error
        |--------------------------------------------------------------------------
        */

        if (!response.ok) {

            const errorText =
                await response.text();

            let message =
                "Excel export failed.";


            try {

                const errorJSON =
                    JSON.parse(
                        errorText
                    );

                message =
                    errorJSON.message ||
                    message;

            } catch {

                if (errorText.trim()) {
                    console.error(
                        errorText
                    );
                }
            }


            throw new Error(message);
        }


        /*
        |--------------------------------------------------------------------------
        | Convert Response To Blob
        |--------------------------------------------------------------------------
        */

        const blob =
            await response.blob();


        if (!blob.size) {

            throw new Error(
                "The generated Excel file is empty."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Get File Name
        |--------------------------------------------------------------------------
        */

        const disposition =
            response.headers.get(
                "Content-Disposition"
            );


        let fileName =
            "My Spreadsheet.xlsx";


        if (disposition) {

            const match =
                disposition.match(
                    /filename="?([^"]+)"?/i
                );


            if (match && match[1]) {

                fileName =
                    match[1];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Download
        |--------------------------------------------------------------------------
        */

        const url =
            URL.createObjectURL(
                blob
            );


        const link =
            document.createElement(
                "a"
            );


        link.href = url;

        link.download =
            fileName;


        document.body.appendChild(
            link
        );


        link.click();


        link.remove();


        URL.revokeObjectURL(
            url
        );


        showToast(
            "Excel file exported successfully!"
        );


    } catch (error) {

        console.error(
            "Export error:",
            error
        );


        showToast(
            error.message ||
            "Unable to export Excel file.",
            "error"
        );


    } finally {

        button.disabled = false;

        button.innerHTML =
            originalHTML;
    }
}

async function saveTemplate() {
    const button = $("#saveTemplateBtn");
    if (button.disabled) return;
    if (!validateSheet()) return;
    const payload = collectPayload();

    if (!payload.file_name) {
        showToast("Please enter a file name.", "error");
        return;
    }

    if (!payload.headers.length) {
        showToast("Please create at least one column.", "error");
        return;
    }

    button.disabled = true;
    try {
        const response = await fetch("save_template.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        const responseText = await response.text();

        console.log("Save response:", responseText);

        let result;

        try {
            result = JSON.parse(responseText);
        } catch (error) {
            console.error("Server returned:", responseText);

            throw new Error(
                "Server did not return valid JSON. Check PHP errors."
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || "Unable to save template."
            );
        }

        state.templateId = Number(result.id);
        showToast(
            result.message || "Template saved successfully!",
            "success"
        );

        if (typeof refreshTemplates === "function") {
            refreshTemplates();
        }

    } catch (error) {

        console.error("Save template error:", error);

        showToast(
            error.message || "Unable to save template.",
            "error"
        );
    } finally {
        button.disabled = false;
    }
}

async function loadTemplate(id) {
    try {
        const response = await fetch(`load_template.php?id=${encodeURIComponent(id)}`);
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Could not load template.");
        }

        $("#fileName").value = result.template.name;
        $("#columnCount").value = result.template.column_count;
        $("#rowCount").value = result.template.row_count;

        state.templateId = Number(result.template.id);
        state.selectedRow = 0;
        state.selectedColumn = 0;
        state.rows = Number(result.template.row_count);
        state.cols = Number(result.template.column_count);
        state.headers = result.template.headers;
        state.data = result.template.data;
        state.column_widths = state.headers.map((_, c) => result.template.column_widths?.[c] || 180);
        state.formats = state.data.map((row, r) => row.map((_, c) => result.template.formats?.[r]?.[c] || {}));
        state.column_types = state.headers.map((_, c) => result.template.column_types?.[c] === "gender" ? "dropdown" : result.template.column_types?.[c] || "text");
        state.column_options = state.headers.map((_, c) => result.template.column_types?.[c] === "gender" ? ["Male", "Female"] : result.template.column_options?.[c] || []);

        switchView("builder");
        renderTable();
        window.resetSheetHistory?.();
        showToast("Template loaded.");
    } catch (error) {
        showToast(error.message, "error");
    }
}

function clearSpreadsheet() {
    if (!state.data.length) return;

    if (!confirm("Clear all spreadsheet data and reset the table?")) return;

    window.recordSheetHistory?.();
    createEmptyState($("#rowCount").value, $("#columnCount").value);
    renderTable();
    showToast("Spreadsheet cleared.");
}

document.querySelectorAll(".delete-template-btn").forEach(button => {
    button.addEventListener("click", async () => {
        const row = button.closest("tr");
        const name = row.querySelector("strong").textContent.trim();
        if (!confirm(`Delete "${name}"? This permanently removes the saved template.`)) return;
        button.disabled = true;
        try {
            const response = await fetch("delete_template.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({id: Number(button.dataset.id)})
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || "Unable to delete template.");
            row.remove();
            const remaining = document.querySelectorAll("#templateList tr[data-template-id]").length;
            $("#templateCount").textContent = remaining;
            if (!remaining) {
                const emptyRow = document.createElement("tr");
                const cell = emptyRow.insertCell();
                cell.colSpan = 5;
                cell.className = "empty";
                cell.textContent = "No saved templates yet.";
                $("#templateList").appendChild(emptyRow);
            }
            window.filterTemplates?.();
            showToast(result.message);
        } catch (error) {
            showToast(error.message || "Unable to delete template.", "error");
        } finally {
            button.disabled = false;
        }
    });
});

document.querySelectorAll(".nav-item").forEach(btn => {
    btn.addEventListener("click", () => switchView(btn.dataset.view));
});

$("#newExcelBtn").addEventListener("click", () => {
    switchView("builder");
});

$("#heroCreateBtn").addEventListener("click", () => {
    switchView("builder");
});

$("#generateBtn").addEventListener("click", generateTable);
$("#exportBtn").addEventListener("click", exportExcel);
$("#saveTemplateBtn").addEventListener("click", saveTemplate);

document.querySelectorAll(".number-control button").forEach(btn => {
    btn.addEventListener("click", () => {
        const input = document.getElementById(btn.dataset.target);
        const min = Number(input.min);
        const max = Number(input.max);
        const next = Number(input.value) + Number(btn.dataset.step);
        input.value = Math.max(min, Math.min(max, next));
    });
});

$("#mobileMenu").addEventListener("click", () => {
    document.querySelector(".sidebar").classList.toggle("open");
});

$("#refreshTemplates").addEventListener("click", () => {
    window.location.reload();
});

createEmptyState($("#rowCount").value, $("#columnCount").value);

$("#fileName").addEventListener("input", () => {
    $("#sheetTitle").textContent = $("#fileName").value.trim() || "My Spreadsheet";
});

function resizeCellText(input) {
    if (input.tagName !== "TEXTAREA") return;
    input.style.height = "0px";
    input.style.height = `${input.scrollHeight + 2}px`;
}
function resizeSheetText() {
    spreadsheet.querySelectorAll("textarea").forEach(resizeCellText);
}
let cellResizeFrame;
window.addEventListener("resize", () => {
    cancelAnimationFrame(cellResizeFrame);
    cellResizeFrame = requestAnimationFrame(() => { window.fitSheetColumns?.(); resizeSheetText(); });
});
