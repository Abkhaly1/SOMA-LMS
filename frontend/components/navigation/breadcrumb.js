class AppBreadcrumb extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
    }

    get path() {
        // Expected format: "Dashboard,Students,Add New"
        return this.getAttribute('path') || 'Dashboard';
    }

    render() {
        const parts = this.path.split(',').map(p => p.trim());
        let breadcrumbHtml = `<div class="breadcrumb">`;
        
        parts.forEach((part, index) => {
            if (index === parts.length - 1) {
                // Last item (active)
                breadcrumbHtml += `<span class="breadcrumb-item">${part}</span>`;
            } else {
                // Link item (using # for demo, can be enhanced to accept real URLs)
                breadcrumbHtml += `
                    <a href="#" class="breadcrumb-link">${part}</a>
                    <span class="breadcrumb-separator">/</span>
                `;
            }
        });
        
        breadcrumbHtml += `</div>`;
        this.innerHTML = breadcrumbHtml;
    }
}

customElements.define('app-breadcrumb', AppBreadcrumb);
