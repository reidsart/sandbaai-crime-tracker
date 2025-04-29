// Admin Dashboard Scripts for Sandbaai Crime Tracker

document.addEventListener("DOMContentLoaded", function () {
    // Confirmation for delete actions
    const deleteLinks = document.querySelectorAll("a[href*='delete']");
    deleteLinks.forEach(function (link) {
        link.addEventListener("click", function (event) {
            if (!confirm("Are you sure you want to delete this item?")) {
                event.preventDefault();
            }
        });
    });

    // Approve/Reject actions
    const approvalLinks = document.querySelectorAll("a[href*='approve'], a[href*='reject']");
    approvalLinks.forEach(function (link) {
        link.addEventListener("click", function (event) {
            if (!confirm("Are you sure about this action?")) {
                event.preventDefault();
            }
        });
    });
});