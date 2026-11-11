(() => {
    const key = "excel-builder-theme";
    const system = window.matchMedia("(prefers-color-scheme: dark)");
    let preference = null;
    try { preference = localStorage.getItem(key); } catch {}
    if (!["light", "dark"].includes(preference)) preference = null;

    function apply(theme) {
        document.documentElement.dataset.theme = theme;
        const toggle = document.getElementById("themeToggle");
        if (toggle) {
            const dark = theme === "dark";
            toggle.innerHTML = dark
                ? '<i class="fa-solid fa-sun" aria-hidden="true"></i>'
                : '<i class="fa-solid fa-moon" aria-hidden="true"></i>';
            const label = dark ? "Switch to light mode" : "Switch to dark mode";
            toggle.setAttribute("aria-label", label);
            toggle.title = label;
            toggle.setAttribute("aria-pressed", String(dark));
        }
    }
    apply(preference || (system.matches ? "dark" : "light"));
    document.addEventListener("DOMContentLoaded", () => {
        apply(document.documentElement.dataset.theme);
        document.getElementById("themeToggle").addEventListener("click", () => {
            preference = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
            try { localStorage.setItem(key, preference); } catch {}
            apply(preference);
        });
    });
    system.addEventListener("change", event => {
        if (!preference) apply(event.matches ? "dark" : "light");
    });
})();
