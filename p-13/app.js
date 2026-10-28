var debounceTimer = null;

document.addEventListener("DOMContentLoaded", function () {
    fetchEvents();

    var searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                fetchEvents();
            }, 300);
        });
    }

    var categoryFilter = document.getElementById("categoryFilter");
    if (categoryFilter) {
        categoryFilter.addEventListener("change", function () {
            fetchEvents();
        });
    }

    var statusFilter = document.getElementById("statusFilter");
    if (statusFilter) {
        statusFilter.addEventListener("change", function () {
            fetchEvents();
        });
    }

    var eventForm = document.getElementById("eventForm");
    if (eventForm) {
        eventForm.addEventListener("submit", function (e) {
            e.preventDefault();
            submitEventForm();
        });
    }

    window.onclick = function (event) {
        var modal = document.getElementById("eventModal");
        if (event.target === modal) {
            closeModal();
        }
    };
});

function showLoading(show) {
    var overlay = document.getElementById("loadingOverlay");
    if (overlay) {
        overlay.style.display = show ? "flex" : "none";
    }
}

function showToast(message, type) {
    var container = document.getElementById("toastContainer");
    if (!container) return;

    var toast = document.createElement("div");
    toast.className = "toast " + (type === "error" ? "toast-error" : "toast-success");
    toast.innerHTML = (type === "error" ? "&cross; " : "&check; ") + message;

    container.appendChild(toast);

    setTimeout(function () {
        toast.style.transition = "opacity 0.4s ease-out, transform 0.4s ease-out";
        toast.style.opacity = "0";
        toast.style.transform = "translateX(100%)";
        setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }, 3500);
}

async function fetchEvents() {
    showLoading(true);

    var search = document.getElementById("searchInput") ? document.getElementById("searchInput").value.trim() : "";
    var category = document.getElementById("categoryFilter") ? document.getElementById("categoryFilter").value : "";
    var status = document.getElementById("statusFilter") ? document.getElementById("statusFilter").value : "";

    var url = "api.php?search=" + encodeURIComponent(search) +
              "&category=" + encodeURIComponent(category) +
              "&status=" + encodeURIComponent(status);

    try {
        var response = await fetch(url, { credentials: "same-origin" });
        var json = await response.json();

        if (json.success && Array.isArray(json.data)) {
            renderEventsTable(json.data);
            updateStats(json.data);
        } else {
            showToast(json.message || "Failed to fetch events list.", "error");
        }
    } catch (err) {
        showToast("Network / API Error: " + err.message, "error");
    } finally {
        showLoading(false);
    }
}

function renderEventsTable(events) {
    var tbody = document.getElementById("eventsTableBody");
    var resultCount = document.getElementById("resultCount");
    if (resultCount) {
        resultCount.innerText = events.length;
    }

    if (!tbody) return;

    if (events.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="padding: 25px; color: #64748b; font-size: 14px;">No events found matching your criteria. Try adjusting the search keywords.</td></tr>';
        return;
    }

    var rowsHtml = "";
    events.forEach(function (ev) {
        var catClass = "badge-tech";
        if (ev.category === "Cultural") catClass = "badge-cult";
        else if (ev.category === "Sports") catClass = "badge-sports";
        else if (ev.category === "Workshop") catClass = "badge-work";
        else if (ev.category === "Seminar") catClass = "badge-semi";

        var statusClass = ev.status === "Open" ? "badge-open" : "badge-closed";
        var toggleTitle = ev.status === "Open" ? "Click to Close" : "Click to Open";

        rowsHtml += "<tr>";
        rowsHtml += "<td>" + ev.id + "</td>";
        rowsHtml += '<td style="text-align: left; font-weight: bold;">' + escapeHtml(ev.name);
        if (ev.description) {
            rowsHtml += '<div style="font-size: 11px; color: #64748b; font-weight: normal; margin-top: 3px;">' + escapeHtml(ev.description.substring(0, 75)) + (ev.description.length > 75 ? "..." : "") + '</div>';
        }
        rowsHtml += "</td>";
        rowsHtml += '<td><span class="badge ' + catClass + '">' + escapeHtml(ev.category) + "</span></td>";
        rowsHtml += "<td><strong>" + escapeHtml(ev.event_date) + "</strong><div style='font-size: 11px; color: #64748b;'>" + escapeHtml(ev.event_time) + "</div></td>";
        rowsHtml += "<td>" + escapeHtml(ev.venue) + "</td>";
        rowsHtml += "<td>" + escapeHtml(ev.coordinator) + "</td>";
        rowsHtml += '<td><button type="button" class="btn-toggle" onclick="toggleStatus(' + ev.id + ')" title="' + toggleTitle + '"><span class="badge ' + statusClass + '">' + escapeHtml(ev.status) + "</span></button></td>";
        rowsHtml += '<td style="white-space: nowrap;">';
        rowsHtml += '<button type="button" class="btn-edit" onclick="openEditModal(' + ev.id + ')">Edit</button> ';
        rowsHtml += '<button type="button" class="btn-delete" onclick="deleteEvent(' + ev.id + ", '" + escapeQuotes(ev.name) + "')\">Delete</button>";
        rowsHtml += "</td>";
        rowsHtml += "</tr>";
    });

    tbody.innerHTML = rowsHtml;
}

function updateStats(events) {
    var openCount = 0;
    var closedCount = 0;

    events.forEach(function (e) {
        if (e.status === "Open") openCount++;
        else closedCount++;
    });

    var statTotal = document.getElementById("statTotal");
    var statOpen = document.getElementById("statOpen");
    var statClosed = document.getElementById("statClosed");
    var statMatch = document.getElementById("statMatch");

    if (statMatch) statMatch.innerText = events.length;
    if (statOpen) statOpen.innerText = openCount;
    if (statClosed) statClosed.innerText = closedCount;
}

function resetFilters() {
    var s = document.getElementById("searchInput");
    var c = document.getElementById("categoryFilter");
    var st = document.getElementById("statusFilter");
    if (s) s.value = "";
    if (c) c.value = "";
    if (st) st.value = "";
    fetchEvents();
}

function openCreateModal() {
    var modal = document.getElementById("eventModal");
    var form = document.getElementById("eventForm");
    var title = document.getElementById("modalTitle");
    var errBox = document.getElementById("modalError");

    if (form) form.reset();
    if (errBox) errBox.style.display = "none";
    document.getElementById("eventId").value = "";
    document.getElementById("formAction").value = "create";
    if (title) title.innerText = "Add New Event (Asynchronous POST)";

    if (modal) modal.style.display = "block";
}

async function openEditModal(id) {
    showLoading(true);
    var errBox = document.getElementById("modalError");
    if (errBox) errBox.style.display = "none";

    try {
        var response = await fetch("api.php?action=get&id=" + id);
        var json = await response.json();

        if (json.success && json.data) {
            var ev = json.data;
            document.getElementById("eventId").value = ev.id;
            document.getElementById("formAction").value = "update";
            document.getElementById("modalTitle").innerText = "Edit Event #" + ev.id + " (Asynchronous UPDATE)";

            document.getElementById("f_name").value = ev.name;
            document.getElementById("f_category").value = ev.category;
            document.getElementById("f_status").value = ev.status;
            document.getElementById("f_event_date").value = ev.event_date;
            document.getElementById("f_event_time").value = ev.event_time;
            document.getElementById("f_venue").value = ev.venue;
            document.getElementById("f_coordinator").value = ev.coordinator;
            document.getElementById("f_description").value = ev.description || "";

            document.getElementById("eventModal").style.display = "block";
        } else {
            showToast(json.message || "Could not load event details.", "error");
        }
    } catch (err) {
        showToast("Fetch Error: " + err.message, "error");
    } finally {
        showLoading(false);
    }
}

function closeModal() {
    var modal = document.getElementById("eventModal");
    if (modal) modal.style.display = "none";
}

async function submitEventForm() {
    var action = document.getElementById("formAction").value;
    var id = document.getElementById("eventId").value;
    var errBox = document.getElementById("modalError");
    if (errBox) errBox.style.display = "none";

    var payload = {
        action: action,
        name: document.getElementById("f_name").value.trim(),
        category: document.getElementById("f_category").value,
        status: document.getElementById("f_status").value,
        event_date: document.getElementById("f_event_date").value,
        event_time: document.getElementById("f_event_time").value.trim(),
        venue: document.getElementById("f_venue").value.trim(),
        coordinator: document.getElementById("f_coordinator").value.trim(),
        description: document.getElementById("f_description").value.trim()
    };

    if (action === "update") {
        payload.id = parseInt(id, 10);
    }

    try {
        var response = await fetch("api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        var json = await response.json();

        if (json.success) {
            closeModal();
            showToast(json.message, "success");
            fetchEvents();
        } else {
            if (errBox) {
                var errHtml = "<div>&cross; " + (json.message || "Validation failed.") + "</div>";
                if (json.errors) {
                    for (var key in json.errors) {
                        errHtml += "<div style='font-size: 12px; margin-top: 3px;'>&bull; " + escapeHtml(json.errors[key]) + "</div>";
                    }
                }
                errBox.innerHTML = errHtml;
                errBox.style.display = "block";
            }
            showToast(json.message || "Error saving event.", "error");
        }
    } catch (err) {
        showToast("Request Error: " + err.message, "error");
    }
}

async function toggleStatus(id) {
    showLoading(true);
    try {
        var response = await fetch("api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ action: "toggle_status", id: id })
        });
        var json = await response.json();

        if (json.success) {
            showToast(json.message, "success");
            fetchEvents();
        } else {
            showToast(json.message || "Failed to toggle status.", "error");
            showLoading(false);
        }
    } catch (err) {
        showToast("Toggle error: " + err.message, "error");
        showLoading(false);
    }
}

async function deleteEvent(id, name) {
    if (!confirm("Are you sure you want to delete event '" + name + "' asynchronously without page reload?")) {
        return;
    }

    showLoading(true);
    try {
        var response = await fetch("api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ action: "delete", id: id })
        });
        var json = await response.json();

        if (json.success) {
            showToast(json.message, "success");
            fetchEvents();
        } else {
            showToast(json.message || "Failed to delete event.", "error");
            showLoading(false);
        }
    } catch (err) {
        showToast("Delete error: " + err.message, "error");
        showLoading(false);
    }
}

function escapeHtml(text) {
    if (!text) return "";
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function escapeQuotes(text) {
    if (!text) return "";
    return text.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}
