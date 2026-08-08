class AppWorkspaceHeader extends HTMLElement {
    static get observedAttributes() {
        return ['title', 'subtitle', 'status', 'status-color', 'avatar-text', 'initials'];
    }

    constructor() {
        super();
        this._metaContent = '';
        this._rendered = false;
    }

    connectedCallback() {
        if (!this._rendered) {
            this._metaContent = this.innerHTML;
            this.render();
            this._rendered = true;
        }
    }

    attributeChangedCallback() {
        this.updateContent();
    }

    get avatarText() {
        return this.getAttribute('initials') || this.getAttribute('avatar-text') || 'S';
    }

    get title() {
        return this.getAttribute('title') || 'Entity Name';
    }

    get subtitle() {
        return this.getAttribute('subtitle') || '';
    }

    get status() {
        return this.getAttribute('status') || '';
    }
    
    get statusColor() {
        return this.getAttribute('status-color') || 'success';
    }

    render() {
        let badgeHtml = this.status ? `<span class="badge badge-${this.statusColor}" id="headerStatusBadge" style="margin-left: 12px;">${this.status}</span>` : '';
        let subtitleHtml = this.subtitle ? `<div id="headerSubtitle" style="color: var(--c-text-muted); font-size: var(--text-sm); margin-top: 4px;">${this.subtitle}</div>` : '';
        const metaContent = this._metaContent || '';

        this.innerHTML = `
            <div class="workspace-header" style="display: flex; align-items: flex-start; justify-content: space-between; padding: var(--sp-6); background: var(--c-bg-surface); border: 1px solid var(--c-border-light); border-radius: var(--radius-lg); margin-bottom: var(--sp-4);">
                <div style="display: flex; align-items: center; gap: var(--sp-4);">
                    <div class="workspace-avatar" id="headerAvatar" style="width: 64px; height: 64px; border-radius: 50%; background: var(--c-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;">
                        ${this.avatarText}
                    </div>
                    <div>
                        <h2 style="margin: 0; font-size: 24px; font-weight: 600; display: flex; align-items: center;" id="headerTitleContainer">
                            <span id="headerTitleText">${this.title}</span>
                            ${badgeHtml}
                        </h2>
                        ${subtitleHtml}
                    </div>
                </div>
                ${metaContent ? `<div class="workspace-meta" style="display: flex; gap: var(--sp-4); text-align: right;">${metaContent}</div>` : ''}
            </div>
        `;
    }

    updateContent() {
        const titleEl = this.querySelector('#headerTitleText');
        const subtitleEl = this.querySelector('#headerSubtitle');
        const avatarEl = this.querySelector('#headerAvatar');
        const badgeEl = this.querySelector('#headerStatusBadge');

        if (titleEl) titleEl.textContent = this.title;
        if (subtitleEl) subtitleEl.textContent = this.subtitle;
        if (avatarEl) avatarEl.textContent = this.avatarText;
        if (badgeEl) {
            badgeEl.textContent = this.status;
            badgeEl.className = `badge badge-${this.statusColor}`;
        }
    }
}

customElements.define('app-workspace-header', AppWorkspaceHeader);
