let students = [];

let defaultStudents = [
  {"id":1,"id_no":"25CE082","name":"Miral Patel","department":"CE","year":"2nd","email":"25ce082@charusat.edu.in","contact":"9876543210"},
  {"id":2,"id_no":"25CE083","name":"omi Patel","department":"CE","year":"2nd","email":"25ce083@charusat.edu.in","contact":"9823456781"},
  {"id":3,"id_no":"25IT005","name":"sneha Joshi","department":"IT","year":"2nd","email":"25it005@charusat.edu.in","contact":"9898123456"},
  {"id":4,"id_no":"25CE023","name":"Dhruva Mehta","department":"CE","year":"2nd","email":"25ce023@charusat.edu.in","contact":"9712345678"},
  {"id":5,"id_no":"25CS014","name":"isha Shah","department":"CS","year":"2nd","email":"25cs014@charusat.edu.in","contact":"9909876543"},
  {"id":6,"id_no":"25AIML08","name":"Henshi Trivedi","department":"AIML","year":"2nd","email":"25aiml08@charusat.edu.in","contact":"9845123456"},
  {"id":7,"id_no":"25CE035","name":"Isha Prajapati","department":"CE","year":"2nd","email":"25ce035@charusat.edu.in","contact":"9723456789"},
  {"id":8,"id_no":"25IT021","name":"Kriya Dave","department":"IT","year":"2nd","email":"25it021@charusat.edu.in","contact":"9638527410"},
  {"id":9,"id_no":"25CE050","name":"Meshva Soni","department":"CE","year":"2nd","email":"25ce050@charusat.edu.in","contact":"9871234560"},
  {"id":10,"id_no":"25CE001","name":"Mishti Patel","department":"CE","year":"2nd","email":"25ce001@charusat.edu.in","contact":"9924512345"},
  {"id":11,"id_no":"25CS042","name":"Neha Varma","department":"CS","year":"2nd","email":"25cs042@charusat.edu.in","contact":"9898765432"},
  {"id":12,"id_no":"25IT038","name":"Pooja Desai","department":"IT","year":"2nd","email":"25it038@charusat.edu.in","contact":"9714253647"},
  {"id":13,"id_no":"25AIML19","name":"Rohan Bhatt","department":"AIML","year":"2nd","email":"25aiml19@charusat.edu.in","contact":"9825143657"},
  {"id":14,"id_no":"25CE095","name":"Smit Pandya","department":"CE","year":"2nd","email":"25ce095@charusat.edu.in","contact":"9904123789"},
  {"id":15,"id_no":"25CS060","name":"Tanvi Shah","department":"CS","year":"2nd","email":"25cs060@charusat.edu.in","contact":"9879543218"}
];

document.addEventListener("DOMContentLoaded", function () {
    fetchStudents();

    let searchInput = document.getElementById("studentSearch");
    if (searchInput) {
        searchInput.addEventListener("input", filterStudents);
    }

    let deptFilter = document.getElementById("deptFilter");
    if (deptFilter) {
        deptFilter.addEventListener("change", filterStudents);
    }
});

function fetchStudents() {
    let loading = document.getElementById("loadingStudents");
    if (loading) loading.style.display = "block";

    fetch("../json data/students.json")
        .then(function (response) {
            if (!response.ok) {
                throw new Error("HTTP error " + response.status);
            }
            return response.json();
        })
        .then(function (data) {
            students = data;
            if (loading) loading.style.display = "none";
            renderStudents(students);
        })
        .catch(function (error) {
            students = defaultStudents;
            if (loading) loading.style.display = "none";
            renderStudents(students);
        });
}

function renderStudents(studentList) {
    let tableBody = document.getElementById("studentsTableBody");
    if (!tableBody) return;

    tableBody.innerHTML = "";

    if (studentList.length === 0) {
        let row = tableBody.insertRow();
        let cell = row.insertCell();
        cell.colSpan = 6;
        cell.textContent = "No student records found.";
        cell.style.color = "gray";
        cell.style.padding = "15px";
        cell.style.textAlign = "center";
        return;
    }

    studentList.forEach(function (student) {
        let row = tableBody.insertRow();

        let idCell = row.insertCell();
        let nameCell = row.insertCell();
        let deptCell = row.insertCell();
        let yearCell = row.insertCell();
        let emailCell = row.insertCell();
        let contactCell = row.insertCell();

        idCell.textContent = student.id_no;
        nameCell.textContent = student.name;
        deptCell.textContent = student.department;
        yearCell.textContent = student.year;
        emailCell.textContent = student.email;
        contactCell.textContent = student.contact;
    });
}

function filterStudents() {
    let query = document.getElementById("studentSearch").value.toLowerCase().trim();
    let selectedDept = document.getElementById("deptFilter").value;

    let filtered = students.filter(function (student) {
        let matchesQuery = student.name.toLowerCase().includes(query) ||
                           student.id_no.toLowerCase().includes(query) ||
                           student.email.toLowerCase().includes(query);

        let matchesDept = (selectedDept === "all") || (student.department === selectedDept);

        return matchesQuery && matchesDept;
    });

    renderStudents(filtered);
}
