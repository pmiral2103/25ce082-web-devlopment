<?php
require_once "db.php";

$total_events = 0;
if ($conn !== null) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM events");
    if ($res) {
        $total_events = $res->fetch_assoc()['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - PHP JSON API &amp; Fetch CRUD (Practical 13)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div id="toastContainer" class="toast-container"></div>

    <header id="header">
        <img src="image/charusatlogo.png" alt="CHARUSAT Logo">
        <div>StudentHub - JSON API &amp; AJAX CRUD System</div>
        <nav>
            <a href="index.php" class="active">Fetch API CRUD</a>
            <a href="setup_database.php">Setup DB</a>
        </nav>
    </header>

    <main class="main-content">
        <section class="container">
            <h2>Practical 13: Asynchronous CRUD with PHP JSON API &amp; Fetch API</h2>
            <div class="info-box">
                Features: RESTful PHP JSON API (<code>api.php</code>), HTTP Status Codes (200, 201, 400, 404, 500), Centralized Error Handling, Asynchronous List/Add/Update/Delete without Page Reload, Real-time Debounced Search (300ms), and Interactive Loading Spinners.
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Events in DB</div>
                    <div id="statTotal" class="stat-number"><?php echo $total_events; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Open Registrations</div>
                    <div id="statOpen" class="stat-number" style="color: #10b981;">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Closed Events</div>
                    <div id="statClosed" class="stat-number" style="color: #ef4444;">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Matching Query Results</div>
                    <div id="statMatch" class="stat-number" style="color: #2563eb;">-</div>
                </div>
            </div>

            <div class="search-filter-card">
                <div class="search-filter-form">
                    <input type="text" id="searchInput" class="search-input" placeholder="Type to search events in real-time (Debounced AJAX)...">

                    <select id="categoryFilter" class="filter-select">
                        <option value="">All Categories</option>
                        <option value="Technical">Technical</option>
                        <option value="Cultural">Cultural</option>
                        <option value="Sports">Sports</option>
                        <option value="Workshop">Workshop</option>
                        <option value="Seminar">Seminar</option>
                    </select>

                    <select id="statusFilter" class="filter-select" style="min-width: 140px;">
                        <option value="">All Statuses</option>
                        <option value="Open">Open Only</option>
                        <option value="Closed">Closed Only</option>
                    </select>

                    <button type="button" class="btn-secondary" onclick="resetFilters();">Reset</button>
                </div>
            </div>

            <div class="action-bar">
                <button type="button" class="btn-add" onclick="openCreateModal();">+ Add New Event (AJAX / Fetch API)</button>
                <div style="font-size: 13px; color: #475569;">
                    Displaying <strong id="resultCount">0</strong> events (Asynchronously loaded)
                </div>
            </div>

            <div style="position: relative;">
                <div id="loadingOverlay" class="loading-overlay">
                    <div class="spinner"></div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Event Name</th>
                                <th>Category</th>
                                <th>Date &amp; Time</th>
                                <th>Venue</th>
                                <th>Coordinator</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="eventsTableBody">
                            <tr>
                                <td colspan="8" style="padding: 25px; color: #64748b; font-size: 14px;">Loading events asynchronously from API...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="info-box" style="margin-top: 25px;">
                <strong>Viva &amp; Lab Notes on Asynchronous Fetch Integration:</strong>
                <ul style="margin: 5px 0 0 20px; padding: 0;">
                    <li><strong>No Page Reload:</strong> Open browser DevTools (Press <code>F12</code> &rarr; <code>Network</code> tab &rarr; Filter <code>Fetch/XHR</code>). Notice every search, insert, update, status toggle, and delete sends an asynchronous request to <code>api.php</code> without full page refresh.</li>
                    <li><strong>Debounce Search (Intermediate):</strong> Typing in the search bar waits 300ms after the last keypress before executing <code>fetch()</code>, preventing excessive server requests.</li>
                    <li><strong>Centralized API Structure (Advanced):</strong> Responses return a uniform JSON schema with HTTP response codes (201 for Created, 200 for OK, 400 for Validation Error, 404 for Not Found).</li>
                </ul>
            </div>
        </section>
    </main>

    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle" style="margin: 0; color: #1E3A8A;">Add New Event</h3>
                <button type="button" class="close-btn" onclick="closeModal();">&times;</button>
            </div>

            <div id="modalError" class="modal-error"></div>

            <form id="eventForm">
                <input type="hidden" id="eventId" value="">
                <input type="hidden" id="formAction" value="create">

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="f_name">Event Name / Title <span class="req">*</span></label>
                        <input type="text" id="f_name" required placeholder="e.g. CodeSprint 2026">
                    </div>

                    <div class="form-group">
                        <label for="f_category">Category <span class="req">*</span></label>
                        <select id="f_category" required>
                            <option value="Technical">Technical</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="f_status">Status <span class="req">*</span></label>
                        <select id="f_status" required>
                            <option value="Open">Open</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="f_event_date">Event Date <span class="req">*</span></label>
                        <input type="date" id="f_event_date" required>
                    </div>

                    <div class="form-group">
                        <label for="f_event_time">Event Time <span class="req">*</span></label>
                        <input type="text" id="f_event_time" required placeholder="e.g. 10:00 AM">
                    </div>

                    <div class="form-group">
                        <label for="f_venue">Venue <span class="req">*</span></label>
                        <input type="text" id="f_venue" required placeholder="e.g. Auditorium, CSPIT">
                    </div>

                    <div class="form-group">
                        <label for="f_coordinator">Coordinator <span class="req">*</span></label>
                        <input type="text" id="f_coordinator" required placeholder="e.g. Prof. Miral Patel">
                    </div>

                    <div class="form-group full-width">
                        <label for="f_description">Description / Overview</label>
                        <textarea id="f_description" rows="3" placeholder="Overview of the event agenda and registration requirements..."></textarea>
                    </div>
                </div>

                <div class="btn-row" style="margin-top: 25px;">
                    <button type="submit" class="btn-submit" style="flex: 1;">Save Event Asynchronously</button>
                    <button type="button" class="btn-secondary" onclick="closeModal();">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        MIRAL PATEL||25CE082||STUDENT HUB PORTAL
    </footer>

    <script src="app.js"></script>
</body>
</html>
