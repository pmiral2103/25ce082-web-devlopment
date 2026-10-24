let allFaqs = [];

let defaultFaqs = [
  {"id":1,"question":"How can I register on StudentHub?","answer":"Go to the Registration page from the menu, fill in your personal and academic details, and click Submit."},
  {"id":2,"question":"How do I log in to my account?","answer":"Click on Login in the menu, enter your college email ID and password, select your role, and click Login."},
  {"id":3,"question":"How can I check the class timetable?","answer":"Navigate to the Dashboard and click on the TimeTable card to view your weekly lecture schedule."},
  {"id":4,"question":"Where can I view upcoming college events?","answer":"Open the Events page from Dashboard or Menu to see upcoming workshops, technical events, and sports."},
  {"id":5,"question":"How do I submit feedback about a course or facility?","answer":"Visit the Feedback page, enter your name, email, and detailed remarks, then click Submit."},
  {"id":6,"question":"What should I do if I forget my password?","answer":"Contact the college IT cell or StudentHub administrator with your student ID card to reset your password."},
  {"id":7,"question":"Is attendance compulsory for all events?","answer":"Attendance is mandatory for academic workshops and department seminars. Cultural fests are open."},
  {"id":8,"question":"How do I register for coding competitions or hackathons?","answer":"On the Events page, click on the specific event details or contact the faculty coordinator listed for registration."},
  {"id":9,"question":"Can I update my profile details after registration?","answer":"Yes, you can edit basic details like mobile number and address from the Profile or Admin section."},
  {"id":10,"question":"What are the college library timings?","answer":"The central library is open Monday to Saturday from 8:30 AM to 7:00 PM."},
  {"id":11,"question":"Where can I find mid-semester exam seating arrangements?","answer":"Seating arrangements are posted on the Notice Board and published under Announcements on the Home page."},
  {"id":12,"question":"How do I join student clubs and NSS?","answer":"Visit the Student Activity Center or register during the Club Orientation event held at the start of the semester."},
  {"id":13,"question":"How can I pay my semester fees online?","answer":"Use the official university fee portal link provided in the StudentHub dashboard under Academic Services."},
  {"id":14,"question":"Who can I contact for technical issues on the portal?","answer":"You can reach out to the Web Development Cell or email support at studenthub@charusat.edu.in."},
  {"id":15,"question":"How do I download my academic transcript or grade sheet?","answer":"Grade sheets can be downloaded from the university examination portal once end-semester results are declared."}
];

document.addEventListener("DOMContentLoaded", function () {
    fetchFaqs();

    let searchBox = document.getElementById("faqSearch");
    if (searchBox) {
        searchBox.addEventListener("input", filterFaqs);
    }
});

function fetchFaqs() {
    let loading = document.getElementById("loading");
    if (loading) loading.style.display = "block";

    fetch("../json data/faqs.json")
        .then(function (response) {
            if (!response.ok) {
                throw new Error("HTTP error " + response.status);
            }
            return response.json();
        })
        .then(function (data) {
            allFaqs = data;
            if (loading) loading.style.display = "none";
            renderFaqs(allFaqs);
        })
        .catch(function (error) {
            allFaqs = defaultFaqs;
            if (loading) loading.style.display = "none";
            renderFaqs(allFaqs);
        });
}

function renderFaqs(faqsToDisplay) {
    let container = document.getElementById("faqContainer");
    if (!container) return;

    container.innerHTML = "";

    if (faqsToDisplay.length === 0) {
        container.innerHTML = "<p style='color: gray; font-style: italic;'>No matching questions found.</p>";
        return;
    }

    faqsToDisplay.forEach(function (item) {
        let faqCard = document.createElement("div");
        faqCard.className = "faq-card";

        let questionHeader = document.createElement("div");
        questionHeader.className = "faq-question";
        questionHeader.innerHTML = "<span>" + item.id + ". " + item.question + "</span> <span class='icon'>+</span>";

        let answerBody = document.createElement("div");
        answerBody.className = "faq-answer";
        answerBody.textContent = item.answer;
        answerBody.style.display = "none";

        questionHeader.addEventListener("click", function () {
            let isOpen = (answerBody.style.display === "block");
            
            if (isOpen) {
                answerBody.style.display = "none";
                questionHeader.querySelector(".icon").textContent = "+";
                faqCard.classList.remove("active");
            } else {
                answerBody.style.display = "block";
                questionHeader.querySelector(".icon").textContent = "-";
                faqCard.classList.add("active");
            }
        });

        faqCard.appendChild(questionHeader);
        faqCard.appendChild(answerBody);
        container.appendChild(faqCard);
    });
}

function filterFaqs() {
    let searchTerm = document.getElementById("faqSearch").value.toLowerCase().trim();

    let filtered = allFaqs.filter(function (item) {
        return item.question.toLowerCase().includes(searchTerm) ||
               item.answer.toLowerCase().includes(searchTerm);
    });

    renderFaqs(filtered);
}
