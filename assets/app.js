/* =========================================================================
   Ultimate Xtreme — Console interactions
   Sidebar, command palette, dialogs, filters, copy, toasts.
   ========================================================================= */

(function () {
    "use strict";

    var body = document.body;
    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    var ux = window.UX || {};
    var base = typeof ux.base === "string" ? ux.base.replace(/\/+$/, "") : "";

    // ---------------------------------------------------------------------
    // Toasts
    // ---------------------------------------------------------------------
    var toastStack = $("#toasts");

    function iconUse(name) {
        return '<svg class="icon toast-icon" aria-hidden="true"><use href="#i-' + name + '"></use></svg>';
    }

    function toast(message, type) {
        if (!toastStack) { return; }
        type = type || "info";

        var el = document.createElement("div");
        el.className = "toast " + type;
        el.setAttribute("role", "status");
        el.innerHTML = iconUse(type === "error" ? "alert" : (type === "info" ? "info" : "check"))
            + "<span></span>";
        el.lastChild.textContent = message;
        toastStack.appendChild(el);

        while (toastStack.children.length > 4) {
            toastStack.removeChild(toastStack.firstChild);
        }

        var dismiss = function () {
            if (!el.parentNode) { return; }
            el.classList.add("is-leaving");
            setTimeout(function () { if (el.parentNode) { el.remove(); } }, 220);
        };

        el.addEventListener("click", dismiss);
        setTimeout(dismiss, type === "error" ? 7000 : 4600);
    }

    window.uxToast = toast;

    $$("#toasts .toast").forEach(function (el) {
        el.addEventListener("click", function () {
            el.classList.add("is-leaving");
            setTimeout(function () { el.remove(); }, 220);
        });
        setTimeout(function () {
            if (!el.parentNode) { return; }
            el.classList.add("is-leaving");
            setTimeout(function () { if (el.parentNode) { el.remove(); } }, 220);
        }, 5200);
    });

    // ---------------------------------------------------------------------
    // Sidebar
    // ---------------------------------------------------------------------
    function closeSidebar() {
        body.classList.remove("sidebar-open");
    }

    document.addEventListener("click", function (event) {
        if (event.target.closest("[data-sidebar-toggle]")) {
            body.classList.toggle("sidebar-open");
            return;
        }

        if (event.target.closest("[data-sidebar-close]")) {
            closeSidebar();
            return;
        }

        if (event.target.closest(".sidebar .nav-link")) {
            closeSidebar();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") { closeSidebar(); }
    });

    // ---------------------------------------------------------------------
    // Copy to clipboard
    // ---------------------------------------------------------------------
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { return true; }).catch(function () { return legacyCopy(text); });
        }
        return Promise.resolve(legacyCopy(text));
    }

    function legacyCopy(text) {
        var area = document.createElement("textarea");
        area.value = text;
        area.setAttribute("readonly", "");
        area.style.cssText = "position:fixed;top:-1000px;left:-1000px;opacity:0";
        document.body.appendChild(area);
        area.select();

        var ok = false;
        try { ok = document.execCommand("copy"); } catch (e) { ok = false; }

        document.body.removeChild(area);
        return ok;
    }

    document.addEventListener("click", function (event) {
        var el = event.target.closest("[data-copy]");
        if (!el) { return; }

        event.preventDefault();
        var value = el.getAttribute("data-copy") || "";

        copyText(value).then(function (ok) {
            toast(ok ? "Copied to clipboard." : "Could not copy. Select the URL manually.", ok ? "success" : "error");
        });
    });

    // ---------------------------------------------------------------------
    // Password reveal
    // ---------------------------------------------------------------------
    document.addEventListener("click", function (event) {
        var btn = event.target.closest("[data-reveal]");
        if (!btn) { return; }

        var input = document.getElementById(btn.getAttribute("data-reveal"));
        if (!input) { return; }

        var reveal = input.type === "password";
        input.type = reveal ? "text" : "password";
        btn.setAttribute("aria-label", reveal ? "Hide password" : "Show password");
        btn.innerHTML = '<svg class="icon" aria-hidden="true"><use href="#i-' + (reveal ? "eye-off" : "eye") + '"></use></svg>';
    });

    // ---------------------------------------------------------------------
    // Auto-submit selects
    // ---------------------------------------------------------------------
    document.addEventListener("change", function (event) {
        var el = event.target;
        if (el.matches && el.matches("[data-auto-submit]") && el.form) {
            el.form.submit();
        }
    });

    // ---------------------------------------------------------------------
    // Server list filtering
    // ---------------------------------------------------------------------
    var serverSearch = $("#serverSearch");
    var statusFilter = $("#statusFilter");
    var modeFilter = $("#modeFilter");
    var filterEmpty = $("#filterEmpty");

    function filterServerRows() {
        var term = serverSearch ? serverSearch.value.trim().toLowerCase() : "";
        var status = statusFilter ? statusFilter.value : "";
        var mode = modeFilter ? modeFilter.value : "";
        var visible = 0;

        $$("[data-row]").forEach(function (row) {
            var haystack = row.getAttribute("data-search") || "";
            var match = true;

            if (term !== "" && haystack.indexOf(term) === -1) { match = false; }
            if (status !== "" && row.getAttribute("data-status") !== status) { match = false; }
            if (mode !== "" && row.getAttribute("data-mode") !== mode) { match = false; }

            row.hidden = !match;
            if (match) { visible += 1; }
        });

        if (filterEmpty) {
            var hasRows = $$("[data-row]").length > 0;
            filterEmpty.hidden = !hasRows || visible > 0;
        }
    }

    if (serverSearch || statusFilter || modeFilter) {
        [serverSearch, statusFilter, modeFilter].forEach(function (el) {
            if (!el) { return; }
            el.addEventListener(el.tagName === "INPUT" ? "input" : "change", filterServerRows);
        });
    }

    // ---------------------------------------------------------------------
    // Server add / edit dialog
    // ---------------------------------------------------------------------
    var serverDialog = $("#serverDialog");
    var serverForm = $("#serverForm");

    function field(id) { return document.getElementById(id); }

    function slugify(value) {
        return value.toLowerCase()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .substring(0, 32);
    }

    var nameDirty = false;

    function openServerDialog(data) {
        if (!serverDialog || !serverForm) { return; }

        serverForm.reset();
        nameDirty = false;

        var isEdit = !!(data && data.id);
        var mode = (data && data.mode) || serverDialog.getAttribute("data-default-mode") || "proxy";

        field("f-id").value = isEdit ? String(data.id) : "";
        field("f-title").value = isEdit ? (data.title || "") : "";
        field("f-name").value = isEdit ? (data.name || "") : "";
        field("f-host").value = isEdit ? (data.host || "") : "";
        field("f-username").value = isEdit ? (data.username || "") : "";
        field("f-password").value = isEdit ? (data.password || "") : "";
        field("f-mode").value = mode === "rewrite" ? "rewrite" : "proxy";
        field("f-status").checked = isEdit ? Number(data.status) === 1 : true;
        field("f-playlist").checked = isEdit ? Number(data.playlist) === 1 : true;
        field("f-ua").value = isEdit ? (data.user_agent || "") : "";
        field("f-note").value = isEdit ? (data.note || "") : "";

        $("#dialogEyebrow").textContent = isEdit ? "Editing provider" : "New provider";
        $("#dialogTitle").textContent = isEdit ? "Edit server" : "Add server";

        if (isEdit) { nameDirty = true; }

        if (typeof serverDialog.showModal === "function") {
            if (!serverDialog.open) { serverDialog.showModal(); }
        } else {
            serverDialog.setAttribute("open", "");
        }

        setTimeout(function () {
            var focusField = isEdit ? field("f-title") : field("f-title");
            if (focusField) { focusField.focus(); }
        }, 60);
    }

    function closeServerDialog() {
        if (!serverDialog) { return; }
        if (typeof serverDialog.close === "function" && serverDialog.open) {
            serverDialog.close();
        } else {
            serverDialog.removeAttribute("open");
        }
    }

    document.addEventListener("click", function (event) {
        if (event.target.closest("[data-add-server]")) {
            event.preventDefault();
            openServerDialog(null);
            return;
        }

        var edit = event.target.closest(".js-edit");
        if (edit) {
            event.preventDefault();
            var data = null;
            try { data = JSON.parse(edit.getAttribute("data-server") || "null"); } catch (e) { data = null; }
            if (data) { openServerDialog(data); }
            return;
        }

        if (event.target.closest("[data-close]")) {
            closeServerDialog();
            closeUserDialog();
        }
    });

    if (serverDialog) {
        serverDialog.addEventListener("click", function (event) {
            if (event.target === serverDialog) {
                var rect = serverDialog.getBoundingClientRect();
                var inside = event.clientX >= rect.left && event.clientX <= rect.right
                    && event.clientY >= rect.top && event.clientY <= rect.bottom;
                if (!inside) { closeServerDialog(); }
            }
        });
    }

    if (field("f-title")) {
        field("f-title").addEventListener("input", function () {
            if (!serverForm || field("f-id").value !== "" || nameDirty) { return; }
            field("f-name").value = slugify(field("f-title").value);
        });
    }

    if (field("f-name")) {
        field("f-name").addEventListener("input", function () { nameDirty = true; });
    }

    if (window.UX_OPEN_NEW) {
        openServerDialog(null);
    }

    // ---------------------------------------------------------------------
    // Team add / edit dialog
    // ---------------------------------------------------------------------
    var userDialog = $("#userDialog");
    var userForm = $("#userForm");

    function openUserDialog(data) {
        if (!userDialog || !userForm) { return; }

        userForm.reset();

        var isEdit = !!(data && data.id);
        var isSelf = !!(data && data.self);

        field("u-id").value = isEdit ? String(data.id) : "";
        field("u-username").value = isEdit ? (data.username || "") : "";
        field("u-role").value = (data && data.role) === "admin" ? "admin" : "manager";
        field("u-status").checked = isEdit ? Number(data.status) === 1 : true;
        field("u-password").value = "";
        field("u-confirm").value = "";
        field("u-password").required = !isEdit;
        field("u-confirm").required = !isEdit;

        field("u-role").disabled = isSelf;
        field("u-status").disabled = isSelf;

        $("#userEyebrow").textContent = isEdit ? "Editing access" : "New team member";
        $("#userTitle").textContent = isEdit ? "Edit account" : "Add account";
        $("#u-pass-req").textContent = isEdit ? "optional" : "required";
        $("#u-pass-hint").textContent = isEdit
            ? "Leave blank to keep the current password. Otherwise use 8+ characters."
            : "At least 8 characters.";
        $("#selfUserNote").hidden = !isSelf;

        var meter = $('[data-meter-for="u-password"]');
        if (meter) { delete meter.dataset.score; }

        if (typeof userDialog.showModal === "function") {
            if (!userDialog.open) { userDialog.showModal(); }
        } else {
            userDialog.setAttribute("open", "");
        }

        setTimeout(function () {
            if (field("u-username")) { field("u-username").focus(); }
        }, 60);
    }

    function closeUserDialog() {
        if (!userDialog) { return; }

        if (field("u-role")) { field("u-role").disabled = false; }
        if (field("u-status")) { field("u-status").disabled = false; }

        if (typeof userDialog.close === "function" && userDialog.open) {
            userDialog.close();
        } else {
            userDialog.removeAttribute("open");
        }
    }

    document.addEventListener("click", function (event) {
        if (event.target.closest("[data-add-user]")) {
            event.preventDefault();
            openUserDialog(null);
            return;
        }

        var edit = event.target.closest(".js-edit-user");
        if (edit) {
            event.preventDefault();
            var data = null;
            try { data = JSON.parse(edit.getAttribute("data-user") || "null"); } catch (e) { data = null; }
            if (data) { openUserDialog(data); }
        }
    });

    if (userDialog) {
        userDialog.addEventListener("click", function (event) {
            if (event.target === userDialog) {
                var rect = userDialog.getBoundingClientRect();
                var inside = event.clientX >= rect.left && event.clientX <= rect.right
                    && event.clientY >= rect.top && event.clientY <= rect.bottom;
                if (!inside) { closeUserDialog(); }
            }
        });
    }

    // ---------------------------------------------------------------------
    // Password strength meter
    // ---------------------------------------------------------------------
    function passwordScore(value) {
        if (!value) { return 0; }

        var score = 0;
        if (value.length >= 8) { score += 1; }
        if (value.length >= 12) { score += 1; }
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) { score += 1; }
        if (/\d/.test(value)) { score += 1; }
        if (/[^A-Za-z0-9]/.test(value)) { score += 1; }

        return Math.min(4, score);
    }

    $$("input[data-strength]").forEach(function (input) {
        var meter = $('[data-meter-for="' + input.getAttribute("data-strength") + '"]');
        if (!meter) { return; }

        var update = function () {
            var score = passwordScore(input.value);
            if (score > 0) {
                meter.dataset.score = String(score);
            } else {
                delete meter.dataset.score;
            }
        };

        input.addEventListener("input", update);
        update();
    });

    // ---------------------------------------------------------------------
    // Test connection
    // ---------------------------------------------------------------------
    document.addEventListener("click", function (event) {
        var btn = event.target.closest(".js-test");
        if (!btn || btn.disabled) { return; }

        event.preventDefault();
        btn.disabled = true;
        toast("Testing provider credentials…", "info");

        var url = base + "/admin/servers.php?ajax=test&id=" + encodeURIComponent(btn.getAttribute("data-id") || "");

        fetch(url, {
            credentials: "same-origin",
            headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var ok = !!(data && data.ok);
                toast((data && data.message) ? data.message : (ok ? "Connected." : "Connection failed."), ok ? "success" : "error");
            })
            .catch(function () {
                toast("Could not reach the test endpoint. Reload and try again.", "error");
            })
            .then(function () {
                btn.disabled = false;
            });
    });

    // ---------------------------------------------------------------------
    // Confirm dialog
    // ---------------------------------------------------------------------
    var confirmDialog = $("#confirmDialog");
    var confirmPending = null;

    function askConfirm(message, label, onConfirm) {
        if (!confirmDialog || typeof confirmDialog.showModal !== "function") {
            if (window.confirm(message)) { onConfirm(); }
            return;
        }

        var titleEl = $("#confirmTitle");
        var msgEl = $("#confirmMessage");
        var okEl = $("#confirmOk");

        if (titleEl) { titleEl.textContent = "Please confirm"; }
        if (msgEl) { msgEl.textContent = message; }
        if (okEl) { okEl.textContent = label || "Confirm"; }

        confirmPending = onConfirm;

        if (!confirmDialog.open) {
            confirmDialog.showModal();
        }
    }

    if (confirmDialog) {
        $("#confirmOk").addEventListener("click", function () {
            var run = confirmPending;
            confirmPending = null;
            confirmDialog.close();
            if (run) { run(); }
        });

        $$("[data-confirm-cancel]", confirmDialog).forEach(function (el) {
            el.addEventListener("click", function () {
                confirmPending = null;
                confirmDialog.close();
            });
        });

        confirmDialog.addEventListener("close", function () { confirmPending = null; });
    }

    document.addEventListener("submit", function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) { return; }

        if (form.dataset.confirmDone === "1") {
            delete form.dataset.confirmDone;
            return;
        }

        var message = form.getAttribute("data-confirm");
        if (!message) { return; }

        event.preventDefault();
        askConfirm(message, form.getAttribute("data-confirm-label") || "Confirm", function () {
            form.dataset.confirmDone = "1";
            if (typeof form.requestSubmit === "function") {
                form.requestSubmit();
            } else {
                form.submit();
            }
            delete form.dataset.confirmDone;
        });
    });

    // Button / link level confirmation
    document.addEventListener("click", function (event) {
        var el = event.target.closest("[data-confirm]");
        if (!el || el.tagName === "FORM" || el.closest("form[data-confirm]")) { return; }

        var message = el.getAttribute("data-confirm");
        if (!message) { return; }

        event.preventDefault();

        askConfirm(message, el.getAttribute("data-confirm-label") || "Confirm", function () {
            if (el.tagName === "A" && el.href) {
                window.location.href = el.href;
                return;
            }
            if (el.form) {
                el.form.dataset.confirmDone = "1";
                if (typeof el.form.requestSubmit === "function") {
                    el.form.requestSubmit();
                } else {
                    el.form.submit();
                }
                delete el.form.dataset.confirmDone;
            }
        });
    });

    // ---------------------------------------------------------------------
    // Busy submit buttons
    // ---------------------------------------------------------------------
    document.addEventListener("submit", function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) { return; }

        setTimeout(function () {
            if (event.defaultPrevented) { return; }
            var btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; }
        }, 0);
    });

    // ---------------------------------------------------------------------
    // Command palette
    // ---------------------------------------------------------------------
    var paletteDialog = $("#paletteDialog");
    var paletteInput = $("#paletteInput");
    var paletteResults = $("#paletteResults");
    var paletteItems = Array.isArray(ux.palette) ? ux.palette : [];
    var paletteFiltered = [];
    var paletteIndex = 0;

    function paletteRender(query) {
        if (!paletteResults) { return; }

        query = (query || "").trim().toLowerCase();
        paletteFiltered = paletteItems.filter(function (item) {
            return query === ""
                || item.label.toLowerCase().indexOf(query) !== -1
                || (item.meta || "").toLowerCase().indexOf(query) !== -1;
        }).slice(0, 40);

        paletteIndex = 0;
        paletteResults.innerHTML = "";

        if (!paletteFiltered.length) {
            var empty = document.createElement("div");
            empty.className = "palette-empty";
            empty.textContent = "No matching pages or servers.";
            paletteResults.appendChild(empty);
            return;
        }

        paletteFiltered.forEach(function (item, index) {
            var link = document.createElement("a");
            link.className = "palette-item" + (index === 0 ? " is-active" : "");
            link.href = item.href || "#";
            link.setAttribute("data-index", String(index));
            link.innerHTML = '<svg class="icon" aria-hidden="true"><use href="#i-' + (item.icon || "chevron-right") + '"></use></svg>'
                + '<span class="palette-item-label"></span><span class="palette-item-meta"></span>';
            link.querySelector(".palette-item-label").textContent = item.label;
            link.querySelector(".palette-item-meta").textContent = item.meta || "";
            paletteResults.appendChild(link);
        });
    }

    function paletteHighlight(index) {
        if (!paletteResults) { return; }
        var nodes = $$(".palette-item", paletteResults);
        if (!nodes.length) { return; }

        paletteIndex = (index + nodes.length) % nodes.length;
        nodes.forEach(function (node, i) {
            node.classList.toggle("is-active", i === paletteIndex);
        });
        nodes[paletteIndex].scrollIntoView({ block: "nearest" });
    }

    function openPalette() {
        if (!paletteDialog) { return; }
        paletteRender("");
        if (paletteInput) { paletteInput.value = ""; }
        if (typeof paletteDialog.showModal === "function") {
            if (!paletteDialog.open) { paletteDialog.showModal(); }
        } else {
            paletteDialog.setAttribute("open", "");
        }
        setTimeout(function () { if (paletteInput) { paletteInput.focus(); } }, 40);
    }

    function closePalette() {
        if (!paletteDialog) { return; }
        if (typeof paletteDialog.close === "function" && paletteDialog.open) {
            paletteDialog.close();
        } else {
            paletteDialog.removeAttribute("open");
        }
    }

    document.addEventListener("click", function (event) {
        if (event.target.closest("[data-palette-open]")) {
            event.preventDefault();
            openPalette();
            return;
        }

        var item = event.target.closest(".palette-item");
        if (item && paletteDialog && paletteDialog.contains(item)) {
            event.preventDefault();
            if (item.href) { window.location.href = item.href; }
        }
    });

    if (paletteInput) {
        paletteInput.addEventListener("input", function () {
            paletteRender(paletteInput.value);
        });

        paletteInput.addEventListener("keydown", function (event) {
            if (event.key === "ArrowDown") {
                event.preventDefault();
                paletteHighlight(paletteIndex + 1);
            } else if (event.key === "ArrowUp") {
                event.preventDefault();
                paletteHighlight(paletteIndex - 1);
            } else if (event.key === "Enter") {
                event.preventDefault();
                var active = paletteResults ? $(".palette-item.is-active", paletteResults) : null;
                if (active && active.href) { window.location.href = active.href; }
            }
        });
    }

    if (paletteDialog) {
        paletteDialog.addEventListener("click", function (event) {
            if (event.target === paletteDialog) { closePalette(); }
        });
    }

    document.addEventListener("keydown", function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
            event.preventDefault();
            openPalette();
            return;
        }

        if (event.key === "Escape" && paletteDialog && paletteDialog.open) {
            closePalette();
        }
    });

    // ---------------------------------------------------------------------
    // Small niceties
    // ---------------------------------------------------------------------
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && serverDialog && serverDialog.open) {
            closeServerDialog();
        }
        if (event.key === "Escape" && userDialog && userDialog.open) {
            closeUserDialog();
        }
    });

    document.addEventListener("submit", function (event) {
        if (event.target instanceof HTMLFormElement && event.target.method.toLowerCase() === "get") {
            // Keep GET forms tidy by dropping empty values before submit.
            $$("input, select", event.target).forEach(function (el) {
                if (el.name && el.value === "" && !el.disabled) { el.disabled = true; }
            });
        }
    });
})();
