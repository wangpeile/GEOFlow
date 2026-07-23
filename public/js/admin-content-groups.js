(function () {
    document.querySelectorAll('[data-content-group-create-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const select = form.querySelector('[data-content-group-article-select]');
            if (!select || !select.value) {
                event.preventDefault();
                return;
            }

            form.action = select.value;
        });
    });
})();
