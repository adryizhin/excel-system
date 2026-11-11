(() => {
    const past = [], future = [];
    const fields = ["rows", "cols", "headers", "column_types", "column_options", "data", "formats", "column_widths", "selectedRow", "selectedColumn", "selectionKind"];
    let group = null, lastTime = 0;
    const snapshot = () => JSON.stringify(Object.fromEntries(fields.map(key => [key, state[key]])));
    window.recordSheetHistory = (key = null) => {
        const now = Date.now();
        if (!key || group !== key || now - lastTime > 700) {
            past.push(snapshot());
            if (past.length > 40) past.shift();
        }
        group = key;
        lastTime = now;
        future.length = 0;
        updateFormatToolbar();
    };
    window.resetSheetHistory = () => {
        past.length = 0; future.length = 0; group = null;
        updateFormatToolbar();
    };
    function travel(source, destination) {
        if (!source.length) return;
        destination.push(snapshot());
        Object.assign(state, JSON.parse(source.pop()));
        group = null;
        renderTable();
        spreadsheet.querySelector(`[data-row="${state.selectedRow}"][data-col="${state.selectedColumn}"]`)?.focus({preventScroll: true});
    }
    window.applyCellFormat = (input, format) => {
        input.style.fontWeight = format.bold ? "700" : "400";
        input.style.fontStyle = format.italic ? "italic" : "normal";
        input.style.textDecoration = [format.underline ? "underline" : "", format.strike ? "line-through" : ""].filter(Boolean).join(" ") || "none";
        input.style.textAlign = format.align || "left";
        if (input.parentElement?.tagName === "TD") input.parentElement.style.verticalAlign = format.valign || "top";
        input.style.fontSize = `${format.fontSize || 11}pt`;
        if (input.nextElementSibling?.classList.contains("dropdown-value")) input.nextElementSibling.style.cssText = input.style.cssText;
        resizeCellText(input);
    };
    function selectedCells() {
        const cells = [];
        for (let r = 0; r < state.rows; r++) for (let c = 0; c < state.cols; c++) {
            if (state.selectionKind === "row" ? r === state.selectedRow : state.selectionKind === "column" ? c === state.selectedColumn : r === state.selectedRow && c === state.selectedColumn) cells.push([r, c]);
        }
        return cells;
    }
    function format(key, value) {
        const cells = selectedCells();
        const active = state.formats[state.selectedRow]?.[state.selectedColumn] || {};
        const next = value === undefined ? !active[key] : value;
        recordSheetHistory();
        cells.forEach(([r, c]) => {
            state.formats[r] ||= [];
            state.formats[r][c] = key === "clear" ? {} : {...state.formats[r][c], [key]: next};
            const input = spreadsheet.querySelector(`[data-row="${r}"][data-col="${c}"]`);
            if (input) applyCellFormat(input, state.formats[r][c]);
        });
        updateFormatToolbar();
    }
    window.updateFormatToolbar = () => {
        const active = state.formats[state.selectedRow]?.[state.selectedColumn] || {};
        document.querySelectorAll("[data-format]").forEach(button => button.setAttribute("aria-pressed", String(!!active[button.dataset.format])));
        document.querySelectorAll("[data-align]").forEach(button => button.setAttribute("aria-pressed", String((active.align || "left") === button.dataset.align)));
        document.querySelectorAll("[data-valign]").forEach(button => button.setAttribute("aria-pressed", String((active.valign || "top") === button.dataset.valign)));
        document.getElementById("cellFontSize").value = active.fontSize || 11;
        document.getElementById("undoBtn").disabled = !past.length;
        document.getElementById("redoBtn").disabled = !future.length;
        spreadsheet.querySelectorAll("[data-row][data-col]").forEach(input => {
            const r = Number(input.dataset.row), c = Number(input.dataset.col);
            const selected = state.selectionKind === "row" ? r === state.selectedRow : state.selectionKind === "column" ? c === state.selectedColumn : r === state.selectedRow && c === state.selectedColumn;
            input.parentElement.classList.toggle("format-selected", selected);
        });
    };
    $("#undoBtn").addEventListener("click", () => travel(past, future));
    $("#redoBtn").addEventListener("click", () => travel(future, past));
    document.querySelectorAll("[data-format]").forEach(button => button.addEventListener("click", () => format(button.dataset.format)));
    document.querySelectorAll("[data-align]").forEach(button => button.addEventListener("click", () => format("align", button.dataset.align)));
    document.querySelectorAll("[data-valign]").forEach(button => button.addEventListener("click", () => format("valign", button.dataset.valign)));
    $("#cellFontSize").addEventListener("change", event => format("fontSize", Number(event.target.value)));
    $("#clearFormatBtn").addEventListener("click", () => format("clear"));
    document.addEventListener("keydown", event => {
        if (!(event.ctrlKey || event.metaKey) || event.altKey || !builderView.classList.contains("active") || spreadsheetPanel.classList.contains("hidden") || document.querySelector("dialog[open]")) return;
        const target = event.target;
        if (target.matches("input, select, textarea") && !spreadsheet.contains(target) && !target.closest(".format-toolbar")) return;
        const key = event.key.toLowerCase();
        const action = {
            z: () => event.shiftKey ? travel(future, past) : travel(past, future),
            y: () => travel(future, past), b: () => format("bold"), i: () => format("italic"),
            u: () => format("underline"), e: () => format("align", "center"),
            l: () => format("align", "left"), r: () => format("align", "right"),
            s: () => saveTemplate(), p: () => exportPdf()
        }[key];
        if (action) { event.preventDefault(); action(); }
    });
    // Keep separate editing visits as separate undo steps.
    spreadsheet.addEventListener("focusout", () => { group = null; });
    updateFormatToolbar();
})();
