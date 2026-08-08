class AppCsvImportModal extends HTMLElement {
    constructor() {
        super();
        this._entityType = 'general';
        this._parsedRows = [];
        this._conflicts = [];
        this._availableClassrooms = [];
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
    }

    async open(entityType = 'general') {
        this._entityType = entityType;
        this.querySelector('#importModalTitle').textContent = `Import ${this.getEntityLabel(entityType)} CSV / Excel Data`;
        this.querySelector('#importInstructions').innerHTML = this.getInstructionsHtml(entityType);
        this.resetState();
        this.style.display = 'flex';

        if (entityType === 'students') {
            await this.fetchClassrooms();
        }
    }

    close() {
        this.style.display = 'none';
        this.resetState();
    }

    resetState() {
        this._parsedRows = [];
        this._conflicts = [];

        this.querySelector('#importAlert').style.display = 'none';
        this.querySelector('#importProgressContainer').style.display = 'none';
        this.querySelector('#conflictSection').style.display = 'none';
        this.querySelector('#classroomSection').style.display = 'none';
        this.querySelector('#importProgressBar').style.width = '0%';
        this.querySelector('#importProgressText').textContent = '0%';
        this.querySelector('#importStatusText').textContent = '';
        this.querySelector('#csvFileInput').value = '';
        this.querySelector('#btnStartImport').disabled = true;
        this.querySelector('#btnStartImport').textContent = 'Kagua & Anza Import ➔';
    }

    async fetchClassrooms() {
        try {
            const res = await fetch('/soma-lms/api/headmaster/people/import_users.php?action=list_classrooms', { credentials: 'include' });
            const result = await res.json();
            if (res.ok && result.success) {
                this._availableClassrooms = result.classrooms || [];
            }
        } catch (e) {
            this._availableClassrooms = [];
        }
    }

    getEntityLabel(type) {
        const labels = {
            'schools': 'Schools',
            'users': 'Platform Users',
            'teachers': 'Teachers / Staffs',
            'students': 'Students',
            'templates': 'Academic Templates',
            'general': 'Records'
        };
        return labels[type] || 'Records';
    }

    getInstructionsHtml(type) {
        const specs = {
            'teachers': {
                headers: 'Majina Kamili, Namba ya Usajili, Department',
                sample: 'Mwalimu Baraka Test, TCH/2026/001, Mathematics',
                fields: ['Majina Kamili (required)', 'Namba ya Usajili / Staff ID (required)', 'Department (optional)']
            },
            'students': {
                headers: 'Majina Kamili, Namba ya Usajili',
                sample: 'Baraka Juma Mussa, STD/2026/001',
                fields: ['Majina Kamili (required)', 'Namba ya Usajili / Student Reg No (required)']
            },
            'schools': {
                headers: 'name, type, region, headmaster_name, headmaster_phone',
                sample: 'Mlimani Primary, Primary, Dar es Salaam, Juma Ali, +255711000111',
                fields: ['School Name (required)', 'Type (Primary/Secondary)', 'Region', 'Headmaster Name', 'Headmaster Phone']
            },
            'general': {
                headers: 'Majina Kamili, Namba ya Usajili',
                sample: 'Amani Hassan Juma, REG/2026/001',
                fields: ['Majina Kamili', 'Namba ya Usajili']
            }
        };

        const spec = specs[type] || specs['general'];
        return `
            <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; margin-bottom: 16px;">
                <p style="margin: 0 0 6px 0; font-weight: 700; color: #1e293b;">📄 Vigezo vya Faili la CSV / Excel:</p>
                <ul style="margin: 0; padding-left: 18px; color: #475569; line-height: 1.5;">
                    <li>Faili lazima liwe la <strong>CSV (.csv)</strong> au Excel lililohifadhiwa kama CSV.</li>
                    <li>Safu ya kwanza lazima iwe na Vichwa: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a;">${spec.headers}</code></li>
                    <li>Namba za usajili (Staff ID / Reg No) ni za kudumu na zitakuwa <strong>Password za mwanzo</strong> kwa kila mtumiaji.</li>
                </ul>
            </div>
        `;
    }

    downloadSampleCsv() {
        const specs = {
            'teachers': "Majina Kamili,Namba ya Usajili,Department\r\n\"Mwalimu Baraka Test\",\"TCH/2026/001\",\"Mathematics\"\r\n\"Mwalimu Asha Juma\",\"TCH/2026/002\",\"Sciences\"\r\n\"Mwalimu David John\",\"TCH/2026/003\",\"Languages\"\r\n",
            'students': "Majina Kamili,Namba ya Usajili\r\n\"Baraka Juma Mussa\",\"STD/2026/001\"\r\n\"Amani Hassan Juma\",\"STD/2026/002\"\r\n\"Neema Charles Kimaro\",\"STD/2026/003\"\r\n",
            'general': "Majina Kamili,Namba ya Usajili\r\n\"Sample Name\",\"REG/2026/001\"\r\n"
        };

        const csvData = specs[this._entityType] || specs['general'];
        const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", `sample_import_${this._entityType}_template.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    render() {
        this.style.display = 'none';
        this.style.position = 'fixed';
        this.style.top = '0';
        this.style.left = '0';
        this.style.width = '100%';
        this.style.height = '100%';
        this.style.background = 'rgba(0,0,0,0.5)';
        this.style.zIndex = '99999';
        this.style.alignItems = 'center';
        this.style.justifyContent = 'center';

        this.innerHTML = `
            <div class="card" style="width: 100%; max-width: 620px; padding: var(--sp-6); background: white; border-radius: 12px; max-height: 90vh; overflow-y: auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
                    <h3 id="importModalTitle" style="margin: 0; font-size: 18px; font-weight: 800; color: #047857;">Bulk CSV Data Import</h3>
                    <button type="button" id="btnDownloadSample" class="btn btn-outline btn-sm" style="font-size: 12px; display: inline-flex; align-items: center; gap: 4px; font-weight: 700;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                        Download Sample CSV
                    </button>
                </div>

                <div id="importInstructions"></div>

                <div id="importAlert" class="alert alert-danger" style="display: none; margin-bottom: 16px;"></div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight: 700;">Chagua Faili la CSV / Excel</label>
                    <input type="file" id="csvFileInput" class="form-control" accept=".csv, .txt" style="height: 40px; padding: 4px 12px;">
                </div>

                <!-- Progress Bar Section -->
                <div id="importProgressContainer" style="display: none; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                        <span id="importStatusText">Inakagua mfumo...</span>
                        <span id="importProgressText">0%</span>
                    </div>
                    <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden;">
                        <div id="importProgressBar" style="width: 0%; height: 100%; background: #047857; transition: width 0.2s ease;"></div>
                    </div>
                </div>

                <!-- Duplicate Conflict Preview Section -->
                <div id="conflictSection" style="display: none; margin-bottom: 20px;">
                    <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 12px; border-radius: 8px; font-size: 13px; color: #92400e; margin-bottom: 12px;">
                        ⚠️ Imegundua <strong id="conflictCountNum">0</strong> akaunti ambazo tayari zipo kwenye mfumo au zimejirudia kwenye faili. Chagua hatua ya kuchukua:
                    </div>

                    <div id="conflictTableWrapper" style="max-height: 160px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px; margin-bottom: 12px; background: #ffffff;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                            <thead style="background: #f1f5f9; border-bottom: 1px solid #cbd5e1; text-align: left; position: sticky; top: 0;">
                                <tr>
                                    <th style="padding: 6px 10px;">Mstari #</th>
                                    <th style="padding: 6px 10px;">Jina Lililopakiwa</th>
                                    <th style="padding: 6px 10px;">Reg ID</th>
                                    <th style="padding: 6px 10px;">Maelezo ya Mgongano</th>
                                </tr>
                            </thead>
                            <tbody id="conflictTableBody">
                            </tbody>
                        </table>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; margin-bottom: 6px;">
                            <input type="radio" name="dup_handling" value="skip" checked>
                            <span>⏭️ <strong>Ruka (Skip Existing):</strong> Ingiza watumiaji wapya tu na kuacha waliopo.</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="dup_handling" value="update">
                            <span>🔄 <strong>Huisha (Update Existing):</strong> Rekebisha taarifa za watumiaji waliopo kulingana na faili.</span>
                        </label>
                    </div>
                </div>

                <!-- Student Classroom Assignment Section -->
                <div id="classroomSection" style="display: none; margin-bottom: 20px;">
                    <!-- Dynamically populated based on available classrooms -->
                </div>

                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button" id="btnCloseImportModal" class="btn btn-outline">Kughairi</button>
                    <button type="button" id="btnStartImport" class="btn btn-primary" style="font-weight: 800;" disabled>Kagua & Anza Import ➔</button>
                </div>
            </div>
        `;
    }

    renderClassroomPrompt() {
        const container = this.querySelector('#classroomSection');
        if (this._entityType !== 'students') {
            container.style.display = 'none';
            return;
        }

        if (this._availableClassrooms && this._availableClassrooms.length > 0) {
            let optionsHtml = '';
            this._availableClassrooms.forEach(c => {
                const availableSeats = Math.max(0, c.capacity - c.filled_count);
                optionsHtml += `<option value="${c.id}">${c.grade_name} - ${c.classroom_name} (Viti Wazi: ${availableSeats} / ${c.capacity})</option>`;
            });

            container.innerHTML = `
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 14px; border-radius: 8px;">
                    <div style="font-weight: 800; font-size: 13px; color: #166534; margin-bottom: 8px;">
                        🏫 Je, unataka wanafunzi hawa wote wapya watengewe Darasa Moja sasa hivi?
                    </div>
                    <div style="font-size: 13px; margin-bottom: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; cursor: pointer;">
                            <input type="radio" name="assign_classroom_choice" value="none" checked id="radioAllocNone">
                            <span>🔘 <strong>Hapana, waweke bila darasa (Unallocated Pool):</strong> Utawapangia madarasa baadae.</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="assign_classroom_choice" value="select" id="radioAllocSelect">
                            <span>🏫 <strong>Ndiyo, wapange wote moja kwa moja kwenye darasa hili:</strong></span>
                        </label>
                    </div>

                    <div id="classroomSelectWrapper" style="display: none; padding-left: 24px; margin-top: 8px;">
                        <select id="importClassroomSelect" class="form-control" style="font-weight: 700; height: 38px;">
                            ${optionsHtml}
                        </select>
                    </div>
                </div>
            `;

            container.style.display = 'block';

            // Add radio change event listener inside modal
            const radioNone = container.querySelector('#radioAllocNone');
            const radioSelect = container.querySelector('#radioAllocSelect');
            const selectWrapper = container.querySelector('#classroomSelectWrapper');

            radioNone.addEventListener('change', () => { selectWrapper.style.display = 'none'; });
            radioSelect.addEventListener('change', () => { selectWrapper.style.display = 'block'; });

        } else {
            container.innerHTML = `
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px; border-radius: 8px; font-size: 13px; color: #1e40af;">
                    💡 <strong>Taarifa ya Madarasa:</strong> Hakuna madarasa yaliyotengenezwa shuleni kwako bado. Wanafunzi hawa wataingizwa bila darasa (Unallocated Pool), na utatakiwa kwenda kwenye <strong>Classrooms Workspace</strong> kuyatengeneza na kuwaweka baadae.
                </div>
            `;
            container.style.display = 'block';
        }
    }

    parseCsvText(text) {
        const lines = text.split(/\r\n|\n/).map(l => l.trim()).filter(l => l.length > 0);
        if (lines.length <= 1) return [];

        const headers = lines[0].split(',').map(h => h.replace(/^"(.*)"$/, '$1').trim().toLowerCase());
        const rows = [];

        for (let i = 1; i < lines.length; i++) {
            const values = lines[i].split(',').map(v => v.replace(/^"(.*)"$/, '$1').trim());
            const rowObj = {};
            headers.forEach((h, idx) => {
                rowObj[h] = values[idx] || '';
            });

            // Map header synonyms
            const nameVal = rowObj['majina kamili'] || rowObj['full_name'] || rowObj['name'] || rowObj['jina'] || '';
            const codeVal = rowObj['namba ya usajili'] || rowObj['user_code'] || rowObj['staff id'] || rowObj['reg no'] || rowObj['student id'] || rowObj['id'] || '';
            const deptVal = rowObj['department'] || rowObj['idara'] || 'Academics';

            if (nameVal && codeVal) {
                rows.push({
                    full_name: nameVal,
                    user_code: codeVal,
                    department: deptVal
                });
            }
        }

        return rows;
    }

    setupEventListeners() {
        const closeBtn = this.querySelector('#btnCloseImportModal');
        const sampleBtn = this.querySelector('#btnDownloadSample');
        const fileInput = this.querySelector('#csvFileInput');
        const startBtn = this.querySelector('#btnStartImport');
        const alertBox = this.querySelector('#importAlert');

        closeBtn.addEventListener('click', () => this.close());
        sampleBtn.addEventListener('click', () => this.downloadSampleCsv());

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            startBtn.disabled = !file;
        });

        let isConflictState = false;

        startBtn.addEventListener('click', async () => {
            const file = fileInput.files[0];
            if (!file && this._parsedRows.length === 0) return;

            alertBox.style.display = 'none';

            const progressContainer = this.querySelector('#importProgressContainer');
            const progressBar = this.querySelector('#importProgressBar');
            const progressText = this.querySelector('#importProgressText');
            const statusText = this.querySelector('#importStatusText');
            const conflictSection = this.querySelector('#conflictSection');

            // STAGE 1: READ AND DETECT DUPLICATES
            if (!isConflictState) {
                const reader = new FileReader();
                reader.onload = async (evt) => {
                    const text = evt.target.result;
                    const rows = this.parseCsvText(text);

                    if (rows.length === 0) {
                        alertBox.textContent = 'Faili la CSV halina data au kuta zake hazijakaa vizuri. Hakikisha safu za "Majina Kamili" na "Namba ya Usajili" zipo.';
                        alertBox.style.display = 'block';
                        return;
                    }

                    this._parsedRows = rows;
                    startBtn.disabled = true;
                    progressContainer.style.display = 'block';

                    statusText.textContent = 'Inakagua wanafanana kwenye mfumo...';
                    progressBar.style.width = '50%';
                    progressText.textContent = '50%';

                    try {
                        const targetRole = (this._entityType === 'students') ? 'student' : 'teacher';
                        const res = await fetch('/soma-lms/api/headmaster/people/import_users.php?action=detect_duplicates', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ role: targetRole, rows: rows })
                        });

                        const result = await res.json();
                        progressBar.style.width = '100%';
                        progressText.textContent = '100%';

                        if (res.ok && result.success) {
                            isConflictState = true;
                            if (result.conflict_count > 0) {
                                this._conflicts = result.conflicts;
                                this.querySelector('#conflictCountNum').textContent = result.conflict_count;
                                
                                const tbody = this.querySelector('#conflictTableBody');
                                tbody.innerHTML = '';
                                result.conflicts.forEach(c => {
                                    const msg = c.conflict ? c.conflict.message : 'Akaunti tayari ipo';
                                    tbody.innerHTML += `
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 6px 10px; font-weight: 700; color: #475569;">#${c.row_index}</td>
                                            <td style="padding: 6px 10px; font-weight: 600; color: #0f172a;">${c.full_name}</td>
                                            <td style="padding: 6px 10px; font-family: monospace; font-size: 11px; color: #047857;">${c.user_code}</td>
                                            <td style="padding: 6px 10px; color: #92400e;">${msg}</td>
                                        </tr>
                                    `;
                                });
                                conflictSection.style.display = 'block';
                            }
                            
                            // Render classroom prompt for students
                            this.renderClassroomPrompt();

                            startBtn.disabled = false;
                            startBtn.textContent = 'Kamilisha Ku-Import Watumiaji ➔';
                            statusText.textContent = `Uhakiki umekamilika. Kagua sehemu zifuatazo kisha bonyeza Kamilisha.`;
                        } else {
                            alertBox.textContent = result.message || 'Hitilafu wakati wa kukagua data.';
                            alertBox.style.display = 'block';
                            startBtn.disabled = false;
                        }
                    } catch (err) {
                        alertBox.textContent = 'Hitilafu ya mtandao wakati wa kukagua data.';
                        alertBox.style.display = 'block';
                        startBtn.disabled = false;
                    }
                };
                reader.readAsText(file);
            } else {
                // STAGE 2: EXECUTE BATCH IMPORT WITH SELECTED DUPLICATE HANDLING & CLASSROOM SELECTION
                const dupRadio = this.querySelector('input[name="dup_handling"]:checked');
                const dupHandling = dupRadio ? dupRadio.value : 'skip';

                let selectedClassroomId = 0;
                if (this._entityType === 'students') {
                    const allocRadio = this.querySelector('input[name="assign_classroom_choice"]:checked');
                    if (allocRadio && allocRadio.value === 'select') {
                        const selectEl = this.querySelector('#importClassroomSelect');
                        if (selectEl) {
                            selectedClassroomId = parseInt(selectEl.value, 10) || 0;
                        }
                    }
                }

                await this.executeImportBatch(dupHandling, selectedClassroomId);
            }
        });
    }

    async executeImportBatch(dupHandling, classroomId = 0) {
        const startBtn = this.querySelector('#btnStartImport');
        const alertBox = this.querySelector('#importAlert');
        const statusText = this.querySelector('#importStatusText');
        const targetRole = (this._entityType === 'students') ? 'student' : 'teacher';

        startBtn.disabled = true;
        statusText.textContent = 'Inaingiza akaunti za watumiaji kwenye database...';

        try {
            const res = await fetch('/soma-lms/api/headmaster/people/import_users.php?action=execute_import', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    role: targetRole,
                    rows: this._parsedRows,
                    duplicate_handling: dupHandling,
                    classroom_id: classroomId
                })
            });

            const result = await res.json();

            if (res.ok && result.success) {
                const summary = result.summary || {};
                let allocMsg = '';
                if (summary.allocated > 0) {
                    allocMsg = `• Wanafunzi Walioingizwa Darasani Moja kwa Moja: ${summary.allocated}\n`;
                } else if (targetRole === 'student') {
                    allocMsg = `• Wanafunzi Wameingizwa bila darasa (Unallocated Pool). Utawapangia baadae kwenye Classrooms Workspace.\n`;
                }

                alert(`🎉 Mfumo umekamilisha ku-import watumiaji!\n\n` +
                      `• Akaunti Mpya Zilizotengenezwa: ${summary.inserted || 0}\n` +
                      `• Akaunti Zilizohuiswa: ${summary.updated || 0}\n` +
                      `• Zilizorukwa (Duplicates): ${summary.skipped || 0}\n` +
                      allocMsg + `\n` +
                      `Watumiaji wapya sasa wanaweza kuingia kwenye mfumo kwa kutumia:\n` +
                      `• Username: Jina Kamili au Reg Code / Staff ID\n` +
                      `• Password ya Mwanzo: Reg Code / Staff ID yenyewe.\n\n` +
                      `Mara ya kwanza wakiingia, mfumo utawalazimisha kuweka Nenosiri Jipya na Taarifa zao za Mawasiliano.`);
                this.close();
                window.location.reload();
            } else {
                alertBox.textContent = result.message || 'Hitilafu wakati wa ku-import data.';
                alertBox.style.display = 'block';
                startBtn.disabled = false;
            }
        } catch (err) {
            alertBox.textContent = 'Hitilafu ya mtandao wakati wa ku-import data.';
            alertBox.style.display = 'block';
            startBtn.disabled = false;
        }
    }
}

customElements.define('app-csv-import-modal', AppCsvImportModal);
