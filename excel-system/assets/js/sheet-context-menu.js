(() => {
    const menu = document.createElement("div");
    menu.className = "sheet-context-menu";
    menu.setAttribute("role", "menu");
    menu.hidden = true;
    document.body.appendChild(menu);
    let target = null;
    let openedAt = 0;

    function close(restoreFocus = false) {
        menu.hidden = true;
        if (restoreFocus) target?.focus({preventScroll: true});
    }

    function open(button, x, y) {
        openedAt = performance.now();
        target = button;
        const isRow = button.classList.contains("row-selector");
        const index = Number(isRow ? button.dataset.row : button.dataset.column);
        selectPosition(isRow ? index : state.selectedRow, isRow ? state.selectedColumn : index);
        const items = isRow ? [
            ["Insert row above", "fa-arrow-up", () => insertRow(index), state.rows >= 500],
            ["Insert row below", "fa-arrow-down", () => insertRow(index + 1), state.rows >= 500],
            ["Clear contents", "fa-eraser", () => clearRowContents(index), false],
            ["Delete row", "fa-trash", () => deleteRow(index), state.rows <= 1]
        ] : [
            ["Insert column left", "fa-arrow-left", () => insertColumn(index), state.cols >= 50],
            ["Insert column right", "fa-arrow-right", () => insertColumn(index + 1), state.cols >= 50],
            ["Clear contents", "fa-eraser", () => clearColumnContents(index), false],
            ["Delete column", "fa-trash", () => deleteColumn(index), state.cols <= 1]
        ];
        menu.replaceChildren();
        menu.setAttribute("aria-label", isRow ? `Row ${index + 1} options` : `Column ${columnLetter(index)} options`);
        items.forEach(([label, icon, action, disabled]) => {
            const item = document.createElement("button");
            item.type = "button";
            item.setAttribute("role", "menuitem");
            item.disabled = disabled;
            if (icon === "fa-trash") item.className = "delete-menu-item";
            const symbol = document.createElement("i");
            symbol.className = `fa-solid ${icon}`;
            symbol.setAttribute("aria-hidden", "true");
            item.append(symbol, document.createTextNode(label));
            item.addEventListener("click", () => {
                close();
                action();
                const selector = isRow
                    ? `.row-selector[data-row="${state.selectedRow}"]`
                    : `.column-selector[data-column="${state.selectedColumn}"]`;
                spreadsheet.querySelector(selector)?.focus({preventScroll: true});
            });
            menu.appendChild(item);
        });
        menu.hidden = false;
        const rect = menu.getBoundingClientRect();
        menu.style.left = `${Math.max(8, Math.min(x, window.innerWidth - rect.width - 8))}px`;
        menu.style.top = `${Math.max(8, Math.min(y, window.innerHeight - rect.height - 8))}px`;
        menu.querySelector("button:not(:disabled)")?.focus({preventScroll: true});
    }

    spreadsheet.addEventListener("contextmenu", event => {
        const button = event.target.closest(".row-selector, .column-selector");
        if (!button) return;
        event.preventDefault();
        const rect = button.getBoundingClientRect();
        open(button, event.clientX || rect.left, event.clientY || rect.bottom);
    });
    spreadsheet.addEventListener("keydown", event => {
        const button = event.target.closest(".row-selector, .column-selector");
        if (button && (event.key === "ContextMenu" || (event.shiftKey && event.key === "F10"))) {
            event.preventDefault();
            const rect = button.getBoundingClientRect();
            open(button, rect.left, rect.bottom);
        }
    });
    // Touch users can tap the header instead of right-clicking.
    spreadsheet.addEventListener("pointerup", event => {
        if (event.pointerType !== "touch") return;
        const button = event.target.closest(".row-selector, .column-selector");
        if (button) open(button, event.clientX, event.clientY);
    });
    menu.addEventListener("keydown", event => {
        if (event.key === "Escape" || event.key === "Tab") {
            close(event.key === "Escape");
            if (event.key === "Escape") event.preventDefault();
            return;
        }
        if (!["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) return;
        event.preventDefault();
        const items = [...menu.querySelectorAll("button:not(:disabled)")];
        const current = items.indexOf(document.activeElement);
        const next = event.key === "Home" ? 0 : event.key === "End" ? items.length - 1
            : (current + (event.key === "ArrowDown" ? 1 : -1) + items.length) % items.length;
        items[next]?.focus({preventScroll: true});
    });
    document.addEventListener("pointerdown", event => { if (!menu.contains(event.target)) close(); });
    document.addEventListener("scroll", event => {
        // A header can scroll into view just before its context menu opens.
        if (performance.now() - openedAt > 150 && !menu.contains(event.target)) close();
    }, true);
    window.addEventListener("resize", () => close());
    window.addEventListener("blur", () => close());
})();
