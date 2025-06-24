document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.querySelector('.wp-block-taxonomy-grid');
    if (!wrapper) return;

    const taxonomy = wrapper.dataset.taxonomy;
    const perPage = parseInt(wrapper.dataset.itemsPerPage || 6);

    function loadPage(page) {
        const formData = new FormData();
        formData.append('action', 'taxonomy_grid_ajax');
        formData.append('taxonomy', taxonomy);
        formData.append('page', page);
        formData.append('per_page', perPage);

        fetch(TaxonomyGridAjax.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(html => {
            wrapper.querySelector('.taxonomy-grid-items').innerHTML = html;
            wrapper.querySelectorAll('.taxonomy-page-link').forEach(link => {
                link.classList.toggle('current', parseInt(link.dataset.page) === page);
            });
            history.pushState({ page }, '', `?page=${page}`);
        });
    }

    wrapper.addEventListener('click', (e) => {
        if (e.target.matches('.taxonomy-page-link')) {
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
