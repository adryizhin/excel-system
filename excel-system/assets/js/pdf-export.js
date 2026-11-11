const pdfPaperSizes = {
    a4: [210, 297], legal: [215.9, 355.6], letter: [215.9, 279.4],
    a3: [297, 420], a5: [148, 210], folio: [215.9, 330.2]
};

$("#pdfPaperSize").addEventListener("change", event => {
    document.querySelectorAll(".pdf-custom").forEach(label => {
        label.classList.toggle("hidden", event.target.value !== "custom");
    });
});

function exportPdf() {
    if (!state.headers.length || !state.data.length) {
        showToast("Generate a spreadsheet first.", "error");
        return;
    }
    if (!validateSheet()) return;

    let size = pdfPaperSizes[$("#pdfPaperSize").value];
    if (!size) {
        const width = $("#pdfWidth");
        const height = $("#pdfHeight");
        if (!width.reportValidity() || !height.reportValidity() || !width.value || !height.value) {
            showToast("Enter a paper width and height between 50 and 2000 mm.", "error");
            return;
        }
        size = [Number(width.value), Number(height.value)];
    }
    let [width, height] = [...size].sort((a, b) => a - b);
    if ($("#pdfOrientation").value === "landscape") [width, height] = [height, width];

    if (!window.jspdf?.jsPDF || typeof window.jspdf.jsPDF.API.autoTable !== "function") {
        showToast("PDF export could not load. Refresh the page and try again.", "error");
        return;
    }
    const preview = window.open("", "_blank");
    try {
        const fileName = $("#fileName").value.trim() || "My Spreadsheet";
        const pdf = new window.jspdf.jsPDF({
            orientation: width > height ? "landscape" : "portrait",
            unit: "mm", format: [width, height], compress: true
        });
        pdf.setProperties({title: fileName});
        pdf.setFont("helvetica", "bold");
        pdf.setFontSize(16);
        const titleLines = pdf.splitTextToSize(fileName, width - 20);
        pdf.text(titleLines, width / 2, 16, {align: "center"});
        const columnWidth = (width - 20) / state.cols;
        const totalColumnWidth = state.column_widths.reduce((sum, value) => sum + value, 0);
        pdf.autoTable({
            head: [state.headers.map((header, c) => header || `Column ${c + 1}`)],
            body: state.data.map(row => state.headers.map((_, c) => String(row[c] ?? ""))),
            didParseCell: data => {
                if (data.section !== "body") return;
                const style = state.formats[data.row.index]?.[data.column.index] || {};
                data.cell.styles.fontStyle = style.bold && style.italic ? "bolditalic" : style.bold ? "bold" : style.italic ? "italic" : "normal";
                data.cell.styles.halign = style.align || "left";
                data.cell.styles.valign = style.valign || "top";
                if (style.fontSize) data.cell.styles.fontSize = style.fontSize;
            },
            didDrawCell: data => {
                if (data.section !== "body") return;
                const style = state.formats[data.row.index]?.[data.column.index] || {};
                if (!style.underline && !style.strike) return;
                const size = data.cell.styles.fontSize;
                const fontStyle = data.cell.styles.fontStyle;
                pdf.setFont("helvetica", fontStyle);
                pdf.setFontSize(size);
                pdf.setDrawColor(32, 32, 42);
                pdf.setLineWidth(0.2);
                const align = data.cell.styles.halign;
                const lineHeight = size / pdf.internal.scaleFactor * pdf.getLineHeightFactor();
                data.cell.text.forEach((line, i) => {
                    const textWidth = pdf.getTextWidth(line);
                    const x = align === "center" ? data.cell.x + (data.cell.width - textWidth) / 2 : align === "right" ? data.cell.x + data.cell.width - data.cell.padding("right") - textWidth : data.cell.x + data.cell.padding("left");
                    const baseline = data.cell.getTextPos().y + i * lineHeight;
                    if (style.underline) pdf.line(x, baseline + 0.6, x + textWidth, baseline + 0.6);
                    if (style.strike) pdf.line(x, baseline - size / pdf.internal.scaleFactor * 0.3, x + textWidth, baseline - size / pdf.internal.scaleFactor * 0.3);
                });
            },
            startY: 20 + (titleLines.length - 1) * 7,
            margin: {top: 10, right: 10, bottom: 10, left: 10},
            tableWidth: width - 20,
            theme: "grid",
            showHead: "everyPage",
            rowPageBreak: "avoid",
            styles: {
                font: "helvetica", fontSize: Math.min(10, columnWidth * 2.83465 / 8),
                cellPadding: Math.min(1.4, columnWidth / 10),
                overflow: "linebreak", valign: "top", textColor: [32, 32, 42],
                lineColor: [170, 170, 170], lineWidth: 0.15
            },
            headStyles: {fillColor: [238, 233, 247], textColor: [32, 32, 42], fontStyle: "bold"},
            columnStyles: Object.fromEntries(state.headers.map((_, c) => [c, {cellWidth: (width - 20) * state.column_widths[c] / totalColumnWidth}]))
        });
        // Print an actual PDF, not an HTML page: browser date/URL headers do not apply.
        if (preview) {
            preview.opener = null;
            const url = URL.createObjectURL(pdf.output("blob"));
            preview.location.replace(url);
            const cleanup = setInterval(() => {
                if (preview.closed) { URL.revokeObjectURL(url); clearInterval(cleanup); }
            }, 30000);
        } else {
            pdf.save(fileName.replace(/[\\/:*?"<>|]/g, "-") + ".pdf");
        }
    } catch (error) {
        if (preview) preview.close();
        console.error("PDF export error:", error);
        showToast("Unable to generate the PDF. Please try again.", "error");
    }
}

$("#exportPdfBtn").addEventListener("click", exportPdf);
