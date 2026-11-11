window.setupColumnResize = () => {
    spreadsheet.querySelector("colgroup")?.remove();
    const group = document.createElement("colgroup");
    [48, ...state.column_widths].forEach(width => {
        const col = document.createElement("col");
        col.style.width = `${width}px`;
        group.appendChild(col);
    });
    spreadsheet.prepend(group);
    const fit = () => {
        const available = Math.max(1, spreadsheet.closest(".spreadsheet-wrap").clientWidth - 48);
        const total = state.column_widths.reduce((sum, value) => sum + value, 0);
        group.children[0].style.width = "48px";
        state.column_widths.forEach((width, c) => { group.children[c + 1].style.width = `${available * width / total}px`; });
        spreadsheet.style.width = "100%";
    };
    const updateWidth = (c, width) => {
        state.column_widths[c] = Math.max(80, Math.min(1200, Math.round(width)));
        fit();
        resizeSheetText();
    };
    fit();
    window.fitSheetColumns = fit;
    spreadsheet.querySelectorAll(".column-header").forEach((header, c) => {
        const handle = document.createElement("span");
        handle.className = "column-resize-handle";
        handle.tabIndex = 0;
        handle.setAttribute("role", "separator");
        handle.setAttribute("aria-orientation", "vertical");
        handle.setAttribute("aria-label", `Resize column ${columnLetter(c)}`);
        handle.title = "Drag to resize; double-click to fit contents";
        handle.addEventListener("pointerdown", event => {
            if (event.button !== 0) return;
            event.preventDefault();
            event.stopPropagation();
            const startX = event.clientX, startWidth = state.column_widths[c];
            const displayedWidth = header.getBoundingClientRect().width;
            const available = spreadsheet.closest(".spreadsheet-wrap").clientWidth - 48;
            const others = state.column_widths.reduce((sum, width, index) => sum + (index === c ? 0 : width), 0);
            let recorded = false;
            handle.setPointerCapture(event.pointerId);
            document.body.classList.add("resizing-column");
            const move = next => {
                if (!recorded && Math.abs(next.clientX - startX) >= 2) { recordSheetHistory(); recorded = true; }
                if (recorded) {
                    const desired = Math.max(20, Math.min(available - 20, displayedWidth + next.clientX - startX));
                    updateWidth(c, others ? desired * others / (available - desired) : startWidth);
                }
            };
            const finish = () => {
                handle.removeEventListener("pointermove", move);
                handle.removeEventListener("pointerup", finish);
                handle.removeEventListener("pointercancel", finish);
                document.body.classList.remove("resizing-column");
            };
            handle.addEventListener("pointermove", move);
            handle.addEventListener("pointerup", finish);
            handle.addEventListener("pointercancel", finish);
        });
        const autoFit = () => {
            const canvas = document.createElement("canvas");
            const context = canvas.getContext("2d");
            let width = 100;
            const measure = (value, style) => {
                context.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
                String(value).split(/\r?\n/).forEach(line => width = Math.max(width, context.measureText(line).width + 36));
            };
            measure(state.headers[c], getComputedStyle(header.querySelector("textarea")));
            state.data.forEach((row, r) => measure(row[c], getComputedStyle(spreadsheet.querySelector(`[data-row="${r}"][data-col="${c}"]`))));
            recordSheetHistory();
            updateWidth(c, width);
        };
        handle.addEventListener("dblclick", event => { event.preventDefault(); event.stopPropagation(); autoFit(); });
        handle.addEventListener("keydown", event => {
            if (!["ArrowLeft", "ArrowRight", "Enter"].includes(event.key)) return;
            event.preventDefault(); event.stopPropagation();
            if (event.key === "Enter") autoFit();
            else { recordSheetHistory(); updateWidth(c, state.column_widths[c] + (event.key === "ArrowRight" ? 10 : -10)); }
        });
        header.appendChild(handle);
    });
};
