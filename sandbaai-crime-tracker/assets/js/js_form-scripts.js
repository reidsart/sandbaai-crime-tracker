// Frontend Crime Reporting Form Scripts

document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("#sandcrime-report-form");

    if (form) {
        // Validate form before submission
        form.addEventListener("submit", function (event) {
            const requiredFields = form.querySelectorAll("[required]");
            let isValid = true;

            requiredFields.forEach(function (field) {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add("error");
                } else {
                    field.classList.remove("error");
                }
            });

            if (!isValid) {
                event.preventDefault();
                alert("Please fill out all required fields.");
            }
        });

        // Add interactivity for zone dropdown
        const locationInput = document.querySelector("#location");
        const zoneSelect = document.querySelector("#zone");

        if (locationInput && zoneSelect) {
            zoneSelect.addEventListener("change", function () {
                if (zoneSelect.value) {
                    locationInput.value = ""; // Clear address if zone is selected
                }
            });

            locationInput.addEventListener("input", function () {
                if (locationInput.value.trim()) {
                    zoneSelect.value = ""; // Clear zone if address is entered
                }
            });
        }
    }
});