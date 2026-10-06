
document.addEventListener("DOMContentLoaded", () => {
    const message = document.getElementById("message");
    const count = document.getElementById("count");
    const segments = document.getElementById("segments");
    const form = document.getElementById("sms-form");
    const resultContent = document.getElementById("resultContent");
    const resultModalElement = document.getElementById("resultModal");

    if (!message || !count || !segments) {
        return;
    }

    const updateMessageInfo = () => {
        const length = message.value.length;
        const smsCount = Math.max(1, Math.ceil(length / 160));

        count.textContent = length;
        segments.textContent = smsCount + (smsCount === 1 ? " SMS" : " SMS Segments");
    };

    message.addEventListener("input", updateMessageInfo);
    updateMessageInfo();

    if (!form || !resultContent || !resultModalElement || !window.bootstrap) {
        return;
    }

    const resultModal = new bootstrap.Modal(resultModalElement);
    const submitButton = form.querySelector("button[type=submit]");
    const defaultButtonText = submitButton.innerHTML;

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Sending...';

        try {
            const response = await fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                headers: { "X-Requested-With": "XMLHttpRequest" }
            });
            const html = await response.text();
            const resultDocument = new DOMParser().parseFromString(html, "text/html");
            const messageSent = response.ok && resultDocument.querySelector(".ok");

            resultContent.innerHTML = messageSent
                ? '<p class="text-success fw-semibold mb-0">Message Sent</p>'
                : '<p class="text-danger fw-semibold mb-0">Message Failed</p>';
            resultModal.show();

            if (messageSent) {
                form.reset();
                updateMessageInfo();
            }
        } catch (error) {
            resultContent.innerHTML = "<p class=\"text-danger mb-0\">Could not connect to the SMS server.</p>";
            resultModal.show();
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = defaultButtonText;
        }
    });
});