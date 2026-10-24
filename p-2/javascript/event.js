let events = [];
let filteredEvents = [];
let currentPage = 1;
let itemsPerPage = 5;

let defaultEvents = [
  {"id":1,"name":"NSS","date":"15-Aug-2026","time":"9:00 AM","venue":"Central Lawn","coordinator":"By NSS Cell","category":"Social"},
  {"id":2,"name":"Web Development Workshop","date":"18-Aug-2026","time":"10:00 AM","venue":"MAC Lab","coordinator":"By CE Department","category":"Workshop"},
  {"id":3,"name":"Sports Day","date":"25-Jan-2027","time":"8:30 AM","venue":"College Ground","coordinator":"Sports Committee","category":"Sports"},
  {"id":4,"name":"Cognizance","date":"05-Feb-2027","time":"6:00 PM","venue":"Campus","coordinator":"By University","category":"Cultural"},
  {"id":5,"name":"Coding Competition","date":"10-Feb-2027","time":"10:00 AM","venue":"Computer Lab","coordinator":"By CE Department","category":"Technical"},
  {"id":6,"name":"Hackathon","date":"15-Feb-2027","time":"9:00 AM","venue":"Innovation Center","coordinator":"Technical Cell","category":"Technical"},
  {"id":7,"name":"Cultural Fest","date":"20-Feb-2027","time":"5:00 PM","venue":"University Campus","coordinator":"Cultural Committee","category":"Cultural"},
  {"id":8,"name":"Tree Plantation Drive","date":"01-Mar-2027","time":"8:00 AM","venue":"University Garden","coordinator":"NSS Cell","category":"Social"},
  {"id":9,"name":"AI Workshop","date":"05-Mar-2027","time":"11:00 AM","venue":"Seminar Hall","coordinator":"AI Department","category":"Workshop"},
  {"id":10,"name":"Cricket Tournament","date":"10-Mar-2027","time":"8:00 AM","venue":"College Ground","coordinator":"Sports Committee","category":"Sports"},
  {"id":11,"name":"Tech Talk","date":"15-Mar-2027","time":"2:00 PM","venue":"Seminar Hall","coordinator":"CE Department","category":"Technical"},
  {"id":12,"name":"Annual Day","date":"20-Mar-2027","time":"6:00 PM","venue":"Auditorium","coordinator":"University","category":"Cultural"},
  {"id":13,"name":"Placement Seminar","date":"25-Mar-2027","time":"11:00 AM","venue":"Seminar Hall","coordinator":"Training and Placement Cell","category":"Seminar"},
  {"id":14,"name":"Photography Workshop","date":"01-Apr-2027","time":"10:00 AM","venue":"Media Lab","coordinator":"Media Club","category":"Workshop"},
  {"id":15,"name":"Farewell","date":"10-Apr-2027","time":"5:00 PM","venue":"Auditorium","coordinator":"Final Year Committee","category":"Cultural"}
];

document.addEventListener("DOMContentLoaded", function () {
    fetch("../json data/events.json")
        .then(response => {
            if (!response.ok) {
                throw new Error("HTTP error " + response.status);
            }
            return response.json();
        })
        .then(data => {
            events = data;
            initEvents();
        })
        .catch(error => {
            events = defaultEvents;
            initEvents();
        });
});

function initEvents() {
    filteredEvents = [...events];
    let loading = document.getElementById("loadingEvents");
    if (loading) loading.style.display = "none";
    createControls();
    renderEvents();
}

function createControls() {
    let section = document.getElementById("table");
    let table = section.querySelector("table");

    let controlsDiv = document.createElement("div");
    controlsDiv.id = "controlsDiv";

    let searchBox = document.createElement("input");
    searchBox.type = "text";
    searchBox.placeholder = "Search events...";
    searchBox.id = "searchBox";
    searchBox.addEventListener("input", searchEvents);

    let filter = document.createElement("select");
    filter.id = "categoryFilter";

    let categories = [...new Set(
        events.map(event => event.category)
    )];

    let allOption = document.createElement("option");
    allOption.value = "all";
    allOption.textContent = "All Categories";
    filter.appendChild(allOption);

    categories.forEach(category => {
        let option = document.createElement("option");
        option.value = category;
        option.textContent = category;
        filter.appendChild(option);
    });

    filter.addEventListener("change", filterEvents);

    let sortButton = document.createElement("button");
    sortButton.id = "sortBtn";
    sortButton.textContent = "Sort A-Z";
    sortButton.addEventListener("click", sortEvents);

    controlsDiv.appendChild(searchBox);
    controlsDiv.appendChild(filter);
    controlsDiv.appendChild(sortButton);

    section.insertBefore(controlsDiv, table);
}

function searchEvents() {
    let searchText = document.getElementById("searchBox").value.toLowerCase().trim();

    filteredEvents = events.filter(event =>
        event.name.toLowerCase().includes(searchText) ||
        event.venue.toLowerCase().includes(searchText) ||
        event.coordinator.toLowerCase().includes(searchText)
    );

    currentPage = 1;
    renderEvents();
}

function filterEvents() {
    let category = document.getElementById("categoryFilter").value;

    if (category === "all") {
        filteredEvents = [...events];
    } else {
        filteredEvents = events.filter(event =>
            event.category === category
        );
    }

    currentPage = 1;
    renderEvents();
}

function sortEvents() {
    filteredEvents.sort((a, b) =>
        a.name.localeCompare(b.name)
    );

    renderEvents();
}

function renderEvents() {
    let table = document.querySelector("table");

    while (table.rows.length > 1) {
        table.deleteRow(1);
    }

    let start = (currentPage - 1) * itemsPerPage;
    let end = start + itemsPerPage;
    let pageEvents = filteredEvents.slice(start, end);

    if (pageEvents.length === 0) {
        let row = table.insertRow();
        let cell = row.insertCell();
        cell.colSpan = 5;
        cell.textContent = "No events found.";
        cell.style.color = "gray";
        cell.style.padding = "15px";
        createPagination();
        return;
    }

    pageEvents.forEach(event => {
        let row = table.insertRow();

        let name = row.insertCell();
        let date = row.insertCell();
        let time = row.insertCell();
        let venue = row.insertCell();
        let coordinator = row.insertCell();

        name.textContent = event.name;
        date.textContent = event.date;
        time.textContent = event.time;
        venue.textContent = event.venue;
        coordinator.textContent = event.coordinator;
    });

    createPagination();
}

function createPagination() {
    let oldPagination = document.getElementById("pagination");
    if (oldPagination) {
        oldPagination.remove();
    }

    let pagination = document.createElement("div");
    pagination.id = "pagination";

    let totalPages = Math.ceil(filteredEvents.length / itemsPerPage);

    for (let i = 1; i <= totalPages; i++) {
        let button = document.createElement("button");
        button.textContent = i;
        button.className = "page-btn";

        if (i === currentPage) {
            button.classList.add("active-page");
        }

        button.addEventListener("click", function () {
            currentPage = i;
            renderEvents();
        });

        pagination.appendChild(button);
    }

    document.getElementById("table").appendChild(pagination);
}