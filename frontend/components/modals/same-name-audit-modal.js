class AppSameNameAuditModal extends HTMLElement {
    constructor() {
        super();
        this._role = 'all';
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
    }

    async open(role = 'all') {
        this._role = role;
        this.style.display = 'flex';
        this.querySelector('#sameNameBody').innerHTML = '<div style="padding: 30px; text-align: center; color: #64748b;">Inakagua watumiaji wenye majina yanayofanana shuleni kwako...</div>';
        await this.loadAuditData();
    }

    close() {
        this.style.display = 'none';
    }

    async loadAuditData() {
        try {
            const res = await fetch(`/soma-lms/api/headmaster/people/duplicate_names_api.php?role=${this._role}`, { credentials: 'include' });
            const result = await res.json();

            const body = this.querySelector('#sameNameBody');

            if (res.ok && result.success) {
                const groups = result.groups || [];
                this.querySelector('#sameNameBadge').textContent = `${result.group_count} Groups Found`;

                if (groups.length === 0) {
                    body.innerHTML = `
                        <div style="padding: 40px 20px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 12px;">✅</div>
                            <h4 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 800; color: #047857;">Hakuna Majina Yaliyofanana!</h4>
                            <p style="margin: 0; font-size: 13px; color: #64748b;">Watumiaji wote waliosajiliwa shuleni kwako wana majina tofauti ya kipekee.</p>
                        </div>
                    `;
                    return;
                }

                let html = '';
                groups.forEach((g, gIdx) => {
                    let userCards = '';
                    g.users.forEach(u => {
                        const roleLabel = u.role === 'teacher' ? '👨‍🏫 Mwalimu' : '🎓 Mwanafunzi';
                        const classDept = u.classroom_name ? `${u.grade_name || ''} - ${u.classroom_name}` : (u.department || 'Academics');
                        const phone = u.phone || 'Haipatikani';
                        const email = u.email || 'Haipatikani';
                        const gender = u.gender || 'N/A';

                        userCards += `
                            <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                <div>
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                        <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 11px;">${roleLabel}</span>
                                        <span style="font-size: 11px; font-weight: 600; color: #64748b;">Status: <strong style="color:#059669;">${u.status}</strong></span>
                                    </div>
                                    <h5 style="margin: 0 0 6px 0; font-size: 15px; font-weight: 800; color: #0f172a;">${u.full_name}</h5>
                                    
                                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 6px 10px; margin-bottom: 10px; display: inline-flex; align-items: center; gap: 6px;">
                                        <span style="font-size: 11px; font-weight: 600; color: #065f46;">ID / Reg Code:</span>
                                        <code style="font-weight: 800; font-size: 13px; color: #047857;">${u.user_code || 'N/A'}</code>
                                    </div>

                                    <div style="font-size: 12px; color: #475569; display: flex; flex-direction: column; gap: 4px;">
                                        <div>🏫 <strong>Darasa/Idara:</strong> ${classDept}</div>
                                        <div>📱 <strong>Simu:</strong> ${phone}</div>
                                        <div>✉️ <strong>Email:</strong> ${email}</div>
                                        <div>👤 <strong>Jinsia:</strong> ${gender}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    html += `
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 18px;">👥</span>
                                    <h4 style="margin: 0; font-size: 16px; font-weight: 800; color: #1e293b;">
                                        Kundi #${gIdx + 1}: <span style="color: #047857;">${g.full_name}</span>
                                    </h4>
                                </div>
                                <span class="badge" style="background: #fef3c7; color: #92400e; font-weight: 800; padding: 4px 10px;">
                                    Watu ${g.count} wenye Jina Hili
                                </span>
                            </div>

                            <p style="margin: 0 0 12px 0; font-size: 12px; color: #64748b;">
                                Kagua Namba zao za Usajili (Reg IDs), Simu na Madarasa hapo chini ili kujiridhisha kama ni watu tofauti:
                            </p>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
                                ${userCards}
                            </div>

                            <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #cbd5e1; display: flex; justify-content: flex-end;">
                                <button type="button" class="btn btn-outline btn-sm btn-mark-verified" style="font-weight: 700; color: #047857; border-color: #047857;">
                                    ✔️ Nimeridhika Ni Watu Tofauti
                                </button>
                            </div>
                        </div>
                    `;
                });

                body.innerHTML = html;

                // Add event listeners to verify buttons
                body.querySelectorAll('.btn-mark-verified').forEach(btn => {
                    btn.addEventListener('click', (evt) => {
                        const card = evt.target.closest('div[style*="background: #f8fafc"]');
                        if (card) {
                            card.style.opacity = '0.6';
                            evt.target.disabled = true;
                            evt.target.textContent = '✔️ Imethibitishwa (Verified)';
                        }
                    });
                });

            } else {
                body.innerHTML = `<div style="padding: 20px; color: #dc2626; text-align: center;">Error: ${result.message || 'Unable to fetch audit data.'}</div>`;
            }
        } catch (e) {
            this.querySelector('#sameNameBody').innerHTML = '<div style="padding: 20px; color: #dc2626; text-align: center;">Network or server error while performing audit.</div>';
        }
    }

    render() {
        this.style.display = 'none';
        this.style.position = 'fixed';
        this.style.top = '0';
        this.style.left = '0';
        this.style.width = '100%';
        this.style.height = '100%';
        this.style.background = 'rgba(0,0,0,0.6)';
        this.style.zIndex = '99999';
        this.style.alignItems = 'center';
        this.style.justifyContent = 'center';

        this.innerHTML = `
            <div class="card" style="width: 100%; max-width: 720px; padding: var(--sp-6); background: white; border-radius: 12px; max-height: 90vh; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #047857;">👥 Uhakiki wa Watu Wenye Majina Sawa (Same-Name Audit)</h3>
                            <span id="sameNameBadge" class="badge" style="background: #047857; color: white; font-weight: 800;">-</span>
                        </div>
                        <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">
                            Kagua walimu na wanafunzi wenye majina yaliyofanana kabisa (Full Name) ili kuthibisha ID namba zao.
                        </p>
                    </div>
                    <button type="button" id="btnCloseAuditModalX" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b;">&times;</button>
                </div>

                <div id="sameNameBody" style="flex: 1; overflow-y: auto; padding-right: 4px;">
                    <div style="padding: 30px; text-align: center; color: #64748b;">Inapakia data za uhakiki...</div>
                </div>

                <div style="display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 12px;">
                    <button type="button" id="btnCloseAuditModal" class="btn btn-primary" style="font-weight: 700;">Kamilisha Uhakiki (Done)</button>
                </div>
            </div>
        `;
    }

    setupEventListeners() {
        const closeX = this.querySelector('#btnCloseAuditModalX');
        const closeBtn = this.querySelector('#btnCloseAuditModal');

        closeX.addEventListener('click', () => this.close());
        closeBtn.addEventListener('click', () => this.close());
    }
}

customElements.define('app-same-name-audit-modal', AppSameNameAuditModal);
