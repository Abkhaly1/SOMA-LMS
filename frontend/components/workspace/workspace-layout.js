class AppWorkspaceLayout extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
    }

    get backUrl() {
        return this.getAttribute('back-url') || '#';
    }
    
    get backLabel() {
        return this.getAttribute('back-label') || 'Back';
    }

    get hideBack() {
        return this.hasAttribute('hide-back');
    }

    render() {
        if (this.querySelector('.workspace-wrapper')) return;
        const originalContent = this.innerHTML;
        const backHtml = this.hideBack ? '' : `
            <div class="workspace-top-bar" style="display: flex; align-items: center; gap: 14px; margin-bottom: 8px;">
                <a href="${this.backUrl}" onclick="if(this.getAttribute('href')==='#'){window.history.back(); return false;}" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none; font-weight: 700; white-space: nowrap; height: 34px; border-radius: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    ${this.backLabel}
                </a>
            </div>
        `;

        this.innerHTML = `
            <div class="workspace-wrapper" style="max-width: 1400px; margin: 0 auto; padding-top: 0;">
                ${backHtml}
                <div class="workspace-container">
                    ${originalContent}
                </div>
            </div>
        `;
    }
}

customElements.define('app-workspace-layout', AppWorkspaceLayout);
