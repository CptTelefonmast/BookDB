document.addEventListener("DOMContentLoaded", function ()
{
    const toggle = document.getElementById("themeToggle");

    if (!toggle)
    {
        return;
    }

    function setTheme(theme)
    {
        if (theme === "dark")
        {
            document.body.classList.add("dark");
            toggle.textContent = "☀️";
            toggle.setAttribute("aria-label", "Zum Daymode wechseln");
        }
        else
        {
            document.body.classList.remove("dark");
            toggle.textContent = "🌙";
            toggle.setAttribute("aria-label", "Zum Nightmode wechseln");
        }

        localStorage.setItem("bookdb-theme", theme);
    }

    const savedTheme = localStorage.getItem("bookdb-theme");

    if (savedTheme === "dark" || savedTheme === "light")
    {
        setTheme(savedTheme);
    }
    else
    {
        setTheme("light");
    }

    toggle.addEventListener("click", function ()
    {
        const isDark = document.body.classList.contains("dark");
        setTheme(isDark ? "light" : "dark");
    });
});