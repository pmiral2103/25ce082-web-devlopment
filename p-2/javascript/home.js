function closeNotification() {
    let banner = document.getElementById("notificationBanner");
    if (banner) {
        banner.style.display = "none";
    }
}

function toggleTheme() {
    document.body.classList.toggle("dark-mode");

    let themeBtn = document.getElementById("themeToggleBtn");
    if (document.body.classList.contains("dark-mode")) {
        localStorage.setItem("userTheme", "dark");
        if (themeBtn) themeBtn.innerText = "☀️ Light Mode";
    } else {
        localStorage.setItem("userTheme", "light");
        if (themeBtn) themeBtn.innerText = "🌙 Dark Mode";
    }
}

function loadSavedTheme() {
    let savedTheme = localStorage.getItem("userTheme");
    let themeBtn = document.getElementById("themeToggleBtn");
    if (savedTheme === "dark") {
        document.body.classList.add("dark-mode");
        if (themeBtn) themeBtn.innerText = "☀️ Light Mode";
    } else {
        if (themeBtn) themeBtn.innerText = "🌙 Dark Mode";
    }
}

function toggleMenu() {
    let nav = document.getElementById("navMenu");
    if (nav.style.display === "flex" || nav.style.display === "block") {
        nav.style.display = "none";
    } else {
        nav.style.display = "block";
    }
}

function Modalpopup() {
    let modal = document.getElementById("announcementModal");
    if (modal) {
        modal.style.display = "block";
    }
}

function closeModal() {
    let modal = document.getElementById("announcementModal");
    if (modal) {
        modal.style.display = "none";
    }
}

window.addEventListener("click", function (event) {
    let modal = document.getElementById("announcementModal");
    if (event.target === modal) {
        modal.style.display = "none";
    }
});

let currentSlide = 0;

function showSlide(index) {
    let slides = document.querySelectorAll(".slide");
    let dots = document.querySelectorAll(".dot");

    if (slides.length === 0) return;

    if (index >= slides.length) {
        currentSlide = 0;
    } else if (index < 0) {
        currentSlide = slides.length - 1;
    } else {
        currentSlide = index;
    }

    slides.forEach(function (slide) {
        slide.style.display = "none";
    });

    dots.forEach(function (dot) {
        dot.classList.remove("active-dot");
    });

    slides[currentSlide].style.display = "block";
    if (dots[currentSlide]) {
        dots[currentSlide].classList.add("active-dot");
    }
}

function changeSlide(step) {
    showSlide(currentSlide + step);
}

function setSlide(index) {
    showSlide(index);
}

document.addEventListener("DOMContentLoaded", function () {
    loadSavedTheme();
    showSlide(0);
});