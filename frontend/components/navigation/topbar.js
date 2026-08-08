class AppTopbar extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
    }

    get username() {
        return this.getAttribute('username') || 'User';
    }

    render() {
        const currentPath = window.location.pathname;
        const frontendIndex = currentPath.indexOf('/frontend/');
        const loginUrl = (frontendIndex !== -1) ? currentPath.substring(0, frontendIndex + '/frontend/'.length) + 'login.html' : '/login.html';

        const profileUrl = (frontendIndex !== -1) ? currentPath.substring(0, frontendIndex + '/frontend/'.length) + 'profile.html' : '/profile.html';

        this.innerHTML = `
            <header class="app-topbar">
                <div class="topbar-left">
                    <button class="topbar-btn" id="toggleSidebarBtn" title="Toggle Sidebar">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
                    </button>
                    <span style="font-weight: 500; display:flex; align-items:center;">
                        <svg style="width:16px;height:16px;margin-right:4px;vertical-align:middle" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                        Soma LMS Platform
                    </span>
                </div>
                <div class="topbar-right">
                    <span style="font-size: var(--text-sm);">Howdy, <strong>${this.username}</strong></span>
                    <a href="${loginUrl}" class="topbar-btn" style="color: var(--c-danger-text);" title="Logout">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                    </a>
                </div>
            </header>
        `;
    }

    setupEventListeners() {
        const toggleBtn = this.querySelector('#toggleSidebarBtn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                // Dispatch event so sidebar component can catch it
                document.dispatchEvent(new CustomEvent('toggle-sidebar'));
            });
        }
    }
}

customElements.define('app-topbar', AppTopbar);
