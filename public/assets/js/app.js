document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-dismiss]').forEach((element) => {
        setTimeout(() => element.remove(), 4000);
    });
});
