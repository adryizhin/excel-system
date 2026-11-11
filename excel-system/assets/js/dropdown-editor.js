let dropdownEditorColumn = null;
const dropdownDialog = $("#dropdownDialog");

function addDropdownChoice(value = "") {
    const list = $("#dropdownChoiceList");
    if (list.children.length >= 100) {
        $("#dropdownError").textContent = "You can add up to 100 choices.";
        return;
    }
    const row = document.createElement("div");
    row.className = "dropdown-choice-row";
    const input = document.createElement("input");
    input.type = "text";
    input.maxLength = 100;
    input.value = value;
    input.placeholder = "Enter a choice";
    input.setAttribute("aria-label", "Choice value");
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "icon-btn danger";
    remove.textContent = "×";
    remove.setAttribute("aria-label", "Remove choice");
    remove.addEventListener("click", () => {
        const next = row.nextElementSibling || row.previousElementSibling;
        row.remove();
        (next?.querySelector("input") || $("#addDropdownChoice")).focus();
    });
    row.append(input, remove);
    list.appendChild(row);
    return input;
}

function openDropdownEditor(column) {
    dropdownEditorColumn = column;
    selectPosition(state.selectedRow, column);
    $("#dropdownColumnName").textContent = `${columnLetter(column)} - ${state.headers[column]}`;
    $("#dropdownError").textContent = "";
    $("#dropdownChoiceList").replaceChildren();
    const choices = state.column_options[column] || [];
    (choices.length ? choices : [""]).forEach(value => addDropdownChoice(value));
    dropdownDialog.showModal();
    $("#dropdownChoiceList input").focus();
}

$("#addDropdownChoice").addEventListener("click", () => addDropdownChoice()?.focus());
$("#cancelDropdownChoices").addEventListener("click", () => dropdownDialog.close());
$("#dropdownForm").addEventListener("submit", event => {
    event.preventDefault();
    const choices = [...new Set([...document.querySelectorAll("#dropdownChoiceList input")]
        .map(input => input.value.trim()).filter(Boolean))];
    if (!choices.length) {
        $("#dropdownError").textContent = "Add at least one choice before applying.";
        return;
    }
    const c = dropdownEditorColumn;
    window.recordSheetHistory?.();
    state.column_types[c] = "dropdown";
    state.column_options[c] = choices;
    dropdownDialog.close();
    renderTable();
    spreadsheet.querySelector(`[data-row="${state.selectedRow}"][data-col="${c}"]`)?.focus();
});
