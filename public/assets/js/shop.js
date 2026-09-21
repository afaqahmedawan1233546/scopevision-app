(function () {
    'use strict';

    // Auto-apply filters when a checkbox changes, keeping the page behaviour
    // usable without JavaScript (the Apply button still works).
    var filterForm = document.getElementById('filter-form');
    if (filterForm) {
        filterForm.addEventListener('change', function (event) {
            if (event.target.type === 'checkbox') {
                filterForm.submit();
            }
        });
    }
})();
