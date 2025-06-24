document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.querySelector('.wp-block-issue-grid');
    if (!wrapper) return;

    const perPage = parseInt(wrapper.dataset.itemsPerPage || 6);

    function loadPage(page) {
        const formData = new FormData();
        formData.append('action', 'issue_grid_ajax');
        formData.append('page', page);
        formData.append('per_page', perPage);

        fetch(IssueGridAjax.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(html => {
            wrapper.querySelector('.issue-grid-items').innerHTML = html;
            wrapper.querySelectorAll('.issue-page-link').forEach(link => {
                link.classList.toggle('current', parseInt(link.dataset.page) === page);
            });
            history.pushState({ page }, '', `?page=${page}`);
        });
    }

    wrapper.addEventListener('click', (e) => {
        if (e.target.matches('.issue-page-link')) {
            e.preventDefault();
            const page = parseInt(e.target.dataset.page);
            loadPage(page);
        }
    });

    window.addEventListener('popstate', (e) => {
        const page = e.state?.page || 1;
        loadPage(page);
    });
});
