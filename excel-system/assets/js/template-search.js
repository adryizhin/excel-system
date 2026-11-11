function filterTemplates() {
    const query = document.getElementById("templateSearch").value.trim().toLocaleLowerCase();
    const list = document.getElementById("templateList");
    const rows = [...list.querySelectorAll("tr[data-template-id]")];
    let matches = 0;
    rows.forEach(row => {
        row.hidden = !row.querySelector("strong").textContent.toLocaleLowerCase().includes(query);
        if (!row.hidden) matches++;
    });
    list.querySelector(".search-empty-row")?.remove();
    if (rows.length && !matches) {
        const row = document.createElement("tr");
        row.className = "search-empty-row";
        const cell = row.insertCell();
        cell.colSpan = 5;
        cell.className = "empty";
        cell.textContent = "No matching templates.";
        list.appendChild(row);
    }
}
document.getElementById("templateSearch").addEventListener("input", filterTemplates);
