document.addEventListener("DOMContentLoaded", function ()
{
    initThemeToggle();
    initBookForm();
});

function initThemeToggle()
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
            toggle.setAttribute("aria-label", "Switch to light mode");
        }
        else
        {
            document.body.classList.remove("dark");
            toggle.textContent = "🌙";
            toggle.setAttribute("aria-label", "Switch to dark mode");
        }

        localStorage.setItem("bookdb-theme", theme);
    }

    const savedTheme = localStorage.getItem("bookdb-theme");
    setTheme(savedTheme === "dark" || savedTheme === "light" ? savedTheme : "light");

    toggle.addEventListener("click", function ()
    {
        const isDark = document.body.classList.contains("dark");
        setTheme(isDark ? "light" : "dark");
    });
}

function initBookForm()
{
    const config = window.BookDBBookFormConfig;
    const genreRowsContainer = document.getElementById("genre-rows");
    const regalSelection = document.getElementById("regal_selection");
    const newRegalField = document.getElementById("new_regal_field");
    const regalfachSelectField = document.getElementById("regalfach_select_field");
    const regalfachSelection = document.getElementById("regalfach_selection");
    const newRegalfachField = document.getElementById("new_regalfach_field");
    const schuberCheckbox = document.getElementById("ist_im_schuber");
    const schuberSelectField = document.getElementById("schuber_select_field");
    const schuberSelection = document.getElementById("schuber_selection");
    const newSchuberField = document.getElementById("new_schuber_field");

    if (
        !config
        || !genreRowsContainer
        || !regalSelection
        || !newRegalField
        || !regalfachSelectField
        || !regalfachSelection
        || !newRegalfachField
        || !schuberCheckbox
        || !schuberSelectField
        || !schuberSelection
        || !newSchuberField
    )
    {
        return;
    }

    const availableGenres = Array.isArray(config.availableGenres) ? config.availableGenres : [];
    const faecherMap = config.availableFaecherMap || {};
    const initialRegalfachSelection = config.initialRegalfachSelection || "";

    function createLabel(text, htmlFor)
    {
        const label = document.createElement("label");
        label.setAttribute("for", htmlFor);
        label.textContent = text;
        return label;
    }

    function createGenreItem(index)
    {
        const item = document.createElement("div");
        item.className = "genre-item";
        item.dataset.index = String(index);

        const selectField = document.createElement("div");
        selectField.className = "form-field";

        const selectId = "genre_selection_" + index;
        const inputId = "new_genre_value_" + index;

        selectField.appendChild(createLabel("Genre " + (index + 1), selectId));

        const select = document.createElement("select");
        select.id = selectId;
        select.name = "genre_selections[]";
        select.className = "genre-selection";

        const emptyOption = document.createElement("option");
        emptyOption.value = "";
        emptyOption.textContent = "Please select";
        select.appendChild(emptyOption);

        availableGenres.forEach(function (availableGenre)
        {
            const option = document.createElement("option");
            option.value = String(availableGenre.id);
            option.textContent = availableGenre.name;
            select.appendChild(option);
        });

        const newOption = document.createElement("option");
        newOption.value = "__new__";
        newOption.textContent = "Add new genre...";
        select.appendChild(newOption);

        selectField.appendChild(select);

        const newField = document.createElement("div");
        newField.className = "form-field genre-new-field";
        newField.style.display = "none";

        newField.appendChild(createLabel("New genre " + (index + 1), inputId));

        const input = document.createElement("input");
        input.type = "text";
        input.id = inputId;
        input.name = "new_genre_values[]";
        input.className = "new-genre-value";

        newField.appendChild(input);

        item.appendChild(selectField);
        item.appendChild(newField);

        return item;
    }

    function getItems()
    {
        return Array.from(genreRowsContainer.querySelectorAll(".genre-item"));
    }

    function updateLabels()
    {
        getItems().forEach(function (item, index)
        {
            const select = item.querySelector(".genre-selection");
            const input = item.querySelector(".new-genre-value");
            const labels = item.querySelectorAll("label");
            const selectId = "genre_selection_" + index;
            const inputId = "new_genre_value_" + index;

            item.dataset.index = String(index);
            labels[0].setAttribute("for", selectId);
            labels[0].textContent = "Genre " + (index + 1);
            labels[1].setAttribute("for", inputId);
            labels[1].textContent = "New genre " + (index + 1);
            select.id = selectId;
            input.id = inputId;
        });
    }

    function updateNewGenreVisibility()
    {
        getItems().forEach(function (item)
        {
            const select = item.querySelector(".genre-selection");
            const newField = item.querySelector(".genre-new-field");
            newField.style.display = select.value === "__new__" ? "" : "none";
        });
    }

    function ensureTrailingEmptyItem()
    {
        const items = getItems();

        if (items.length === 0)
        {
            genreRowsContainer.appendChild(createGenreItem(0));
            return;
        }

        const lastItem = items[items.length - 1];
        const lastSelect = lastItem.querySelector(".genre-selection");
        const lastInput = lastItem.querySelector(".new-genre-value");

        if (lastSelect.value !== "" || lastInput.value.trim() !== "")
        {
            genreRowsContainer.appendChild(createGenreItem(items.length));
        }
    }

    function cleanupTrailingEmptyItems()
    {
        let items = getItems();

        while (items.length > 1)
        {
            const lastItem = items[items.length - 1];
            const previousItem = items[items.length - 2];
            const lastSelect = lastItem.querySelector(".genre-selection");
            const lastInput = lastItem.querySelector(".new-genre-value");
            const previousSelect = previousItem.querySelector(".genre-selection");
            const previousInput = previousItem.querySelector(".new-genre-value");

            const lastIsEmpty = lastSelect.value === "" && lastInput.value.trim() === "";
            const previousIsEmpty = previousSelect.value === "" && previousInput.value.trim() === "";

            if (!(lastIsEmpty && previousIsEmpty))
            {
                break;
            }

            lastItem.remove();
            items = getItems();
        }
    }

    function syncGenreItems()
    {
        updateNewGenreVisibility();
        cleanupTrailingEmptyItems();
        ensureTrailingEmptyItem();
        updateLabels();
    }

    function getResolvedRegal()
    {
        return regalSelection.value === "__new__" ? "" : regalSelection.value;
    }

    function rebuildRegalfachOptions()
    {
        const resolvedRegal = getResolvedRegal();
        const faecher = resolvedRegal && faecherMap[resolvedRegal] ? faecherMap[resolvedRegal] : [];
        const previousValue = regalfachSelection.dataset.currentValue || regalfachSelection.value || "";

        regalfachSelection.innerHTML = "";

        const emptyOption = document.createElement("option");
        emptyOption.value = "";
        emptyOption.textContent = "No compartment";
        regalfachSelection.appendChild(emptyOption);

        faecher.forEach(function (fach)
        {
            const option = document.createElement("option");
            option.value = fach;
            option.textContent = fach;
            regalfachSelection.appendChild(option);
        });

        const newOption = document.createElement("option");
        newOption.value = "__new__";
        newOption.textContent = "Add new compartment...";
        regalfachSelection.appendChild(newOption);

        const validValues = [""].concat(faecher).concat(["__new__"]);
        regalfachSelection.value = validValues.indexOf(previousValue) !== -1 ? previousValue : "";
        regalfachSelection.dataset.currentValue = regalfachSelection.value;
    }

    function syncRegalfachFields()
    {
        newRegalfachField.style.display = regalfachSelection.value === "__new__" ? "" : "none";
        regalfachSelection.dataset.currentValue = regalfachSelection.value;
    }

    function syncRegalFields()
    {
        const hasRegal = regalSelection.value !== "";

        newRegalField.style.display = regalSelection.value === "__new__" ? "" : "none";
        regalfachSelectField.style.display = hasRegal ? "" : "none";

        if (!hasRegal)
        {
            regalfachSelection.innerHTML = '<option value="">No compartment</option>';
            regalfachSelection.value = "";
            regalfachSelection.dataset.currentValue = "";
            newRegalfachField.style.display = "none";
            return;
        }

        rebuildRegalfachOptions();
        syncRegalfachFields();
    }

    function syncSchuberFields()
    {
        const isChecked = schuberCheckbox.checked;

        schuberSelectField.style.display = isChecked ? "" : "none";
        newSchuberField.style.display = isChecked && schuberSelection.value === "__new__" ? "" : "none";
    }

    genreRowsContainer.addEventListener("change", function (event)
    {
        if (event.target.classList.contains("genre-selection"))
        {
            syncGenreItems();
        }
    });

    genreRowsContainer.addEventListener("input", function (event)
    {
        if (event.target.classList.contains("new-genre-value"))
        {
            syncGenreItems();
        }
    });

    regalSelection.addEventListener("change", syncRegalFields);
    regalfachSelection.addEventListener("change", syncRegalfachFields);
    schuberCheckbox.addEventListener("change", syncSchuberFields);
    schuberSelection.addEventListener("change", syncSchuberFields);

    regalfachSelection.dataset.currentValue = initialRegalfachSelection;
    syncGenreItems();
    syncRegalFields();
    syncSchuberFields();
}
