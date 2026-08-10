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
        this.querySelector('#btnStartImport').textContent = 'Review & Start Import ➔';
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
                headers: 'Full Name, Reg Code, Department',
                sample: 'Mr. Baraka Test, TCH/202X/001, Mathematics',
                fields: ['Full Name (required)', 'Reg Code / Staff ID (required)', 'Department (optional)']
            },
            'students': {
                headers: 'Full Name, Reg Code',
                sample: 'Baraka Juma Mussa, STD/${new Date().getFullYear()}/001',
                fields: ['Full Name (required)', 'Reg Code / Student Reg No (required)']
            },
            'schools': {
                headers: 'name, type, region, headmaster_name, headmaster_phone',
                sample: 'Mlimani Primary, Primary, Dar es Salaam, Juma Ali, +255711000111',
                fields: ['School Name (required)', 'Type (Primary/Secondary)', 'Region', 'Headmaster Name', 'Headmaster Phone']
            },
            'general': {
                headers: 'Full Name, Reg Code',
                sample: 'Amani Hassan Juma, REG/2026/001',
                fields: ['Full Name', 'Reg Code']
            }
        };

        const spec = specs[type] || specs['general'];
        return `
            <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; margin-bottom: 16px;">
                <p style="margin: 0 0 6px 0; font-weight: 700; color: #1e293b;">📄 CSV / Excel File Guidelines:</p>
                <ul style="margin: 0; padding-left: 18px; color: #475569; line-height: 1.5;">
                    <li>File must be a valid <strong>CSV (.csv)</strong> format.</li>
                    <li>First row must contain Column Headers: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a;">${spec.headers}</code></li>
                    <li>Registration numbers (Staff ID / Reg No) will serve as default <strong>Initial Passwords</strong> for each user.</li>
                </ul>
            </div>
        `;
    }

    downloadSampleCsv() {
        const specs = {
            'teachers': "Full Name,Reg Code,Department\r\n\"Mr. Baraka Test\",\"TCH/202X/001\",\"Mathematics\"\r\n\"Ms. Asha Juma\",\"TCH/202X/002\",\"Sciences\"\r\n\"Mr. David John\",\"TCH/202X/003\",\"Languages\"\r\n",
            'students': "Full Name,Reg Code\r\n\"Baraka Juma Mussa\",\"STD/202X/001\"\r\n\"Amani Hassan Juma\",\"STD/202X/002\"\r\n\"Neema Charles Kimaro\",\"STD/202X/003\"\r\n",
            'general': "Full Name,Reg Code\r\n\"Sample Name\",\"REG/202X/001\"\r\n"
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
            <div class="card" style="width: 100%; max-width: 640px; background: white; border-radius: 12px; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden;">
                <!-- STICKY HEADER -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 24px 14px; border-bottom: 1px solid #e2e8f0; flex-shrink: 0;">
                    <h3 id="importModalTitle" style="margin: 0; font-size: 18px; font-weight: 800; color: #047857;">Bulk CSV Data Import</h3>
                    <button type="button" id="btnDownloadSample" class="btn btn-outline btn-sm" style="font-size: 12px; display: inline-flex; align-items: center; gap: 4px; font-weight: 700;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                        Download Sample CSV
                    </button>
                </div>

                <!-- SCROLLABLE BODY -->
                <div id="importScrollBody" style="flex: 1; overflow-y: auto; padding: 20px 24px;">

                    <div id="importInstructions"></div>

                    <div id="importAlert" class="alert alert-danger" style="display: none; margin-bottom: 16px;"></div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label" style="font-weight: 700;">Select CSV / Excel File</label>
                        <input type="file" id="csvFileInput" class="form-control" accept=".csv, .txt" style="height: 40px; padding: 4px 12px;">
                    </div>

                    <!-- Progress Bar Section -->
                    <div id="importProgressContainer" style="display: none; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                            <span id="importStatusText">Validating file &amp; database...</span>
                            <span id="importProgressText">0%</span>
                        </div>
                        <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden;">
                            <div id="importProgressBar" style="width: 0%; height: 100%; background: #047857; transition: width 0.2s ease;"></div>
                        </div>
                    </div>

                    <!-- Duplicate Conflict Preview Section -->
                    <div id="conflictSection" style="display: none; margin-bottom: 20px;">
                        <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 12px; border-radius: 8px; font-size: 13px; color: #92400e; margin-bottom: 12px;">
                            ⚠️ Detected <strong id="conflictCountNum">0</strong> account(s) that already exist in the system or are duplicated in the file. Choose action:
                        </div>

                        <div id="conflictTableWrapper" style="max-height: 160px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px; margin-bottom: 12px; background: #ffffff;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                <thead style="background: #f1f5f9; border-bottom: 1px solid #cbd5e1; text-align: left; position: sticky; top: 0;">
                                    <tr>
                                        <th style="padding: 6px 10px;">Line #</th>
                                        <th style="padding: 6px 10px;">Uploaded Name</th>
                                        <th style="padding: 6px 10px;">Reg ID</th>
                                        <th style="padding: 6px 10px;">Conflict Description</th>
                                    </tr>
                                </thead>
                                <tbody id="conflictTableBody">
                                </tbody>
                            </table>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; margin-bottom: 6px;">
                                <input type="radio" name="dup_handling" value="skip" checked>
                                <span>⏭️ <strong>Skip Existing:</strong> Import new users only and leave existing records unchanged.</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                                <input type="radio" name="dup_handling" value="update">
                                <span>🔄 <strong>Update Existing:</strong> Update information of existing users according to the file.</span>
                            </label>
                        </div>
                    </div>

                    <!-- Student Classroom Assignment Section -->
                    <div id="classroomSection" style="display: none; margin-bottom: 20px;">
                        <!-- Dynamically populated based on available classrooms -->
                    </div>

                </div><!-- end scrollable body -->

                <!-- STICKY FOOTER -->
                <div style="display: flex; gap: 8px; justify-content: flex-end; padding: 14px 24px; border-top: 1px solid #e2e8f0; flex-shrink: 0; background: white; border-radius: 0 0 12px 12px;">
                    <button type="button" id="btnCloseImportModal" class="btn btn-outline">Cancel</button>
                    <button type="button" id="btnStartImport" class="btn btn-primary" style="font-weight: 800;" disabled>Verify &amp; Start Import ➔</button>
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
                optionsHtml += `<option value="${c.id}">${c.grade_name} - ${c.classroom_name} (Open Seats: ${availableSeats} / ${c.capacity})</option>`;
            });

            container.innerHTML = `
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 14px; border-radius: 8px;">
                    <div style="font-weight: 800; font-size: 13px; color: #166534; margin-bottom: 8px;">
                        🏫 Allocate all new imported students to a classroom now?
                    </div>
                    <div style="font-size: 13px; margin-bottom: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; cursor: pointer;">
                            <input type="radio" name="assign_classroom_choice" value="none" checked id="radioAllocNone">
                            <span>🔘 <strong>No, keep unallocated (Unallocated Pool):</strong> You will assign classrooms later.</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="assign_classroom_choice" value="select" id="radioAllocSelect">
                            <span>🏫 <strong>Yes, assign all imported students to this classroom:</strong></span>
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
                    💡 <strong>Classrooms Notice:</strong> No classrooms have been created in your school yet. These students will be imported without a classroom (Unallocated Pool). You will need to go to the <strong>Classrooms Workspace</strong> to create classrooms and assign them later.
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
            const nameVal = rowObj['full name'] || rowObj['full_name'] || rowObj['name'] || rowObj['majina kamili'] || rowObj['jina'] || '';
            const codeVal = rowObj['reg code'] || rowObj['namba ya usajili'] || rowObj['user_code'] || rowObj['staff id'] || rowObj['reg no'] || rowObj['student id'] || rowObj['id'] || '';
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
                        alertBox.textContent = 'The CSV file contains no data or is incorrectly formatted. Ensure "Full Name" and "Reg Code" columns exist.';
                        alertBox.style.display = 'block';
                        return;
                    }

                    this._parsedRows = rows;
                    startBtn.disabled = true;
                    progressContainer.style.display = 'block';

                    statusText.textContent = 'Checking for existing duplicate accounts...';
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
                                    const msg = c.conflict ? c.conflict.message : 'Account already exists';
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

                            // Auto-scroll to reveal review sections
                            const scrollBody = this.querySelector('#importScrollBody');
                            if (scrollBody) setTimeout(() => { scrollBody.scrollTop = scrollBody.scrollHeight; }, 50);

                            startBtn.disabled = false;
                            startBtn.textContent = 'Complete User Import ➔';
                            statusText.textContent = `Validation complete. Review options below and click Complete.`;
                        } else {
                            alertBox.textContent = result.message || 'Error occurred while validating data.';
                            alertBox.style.display = 'block';
                            startBtn.disabled = false;
                        }
                    } catch (err) {
                        alertBox.textContent = 'Network error occurred while validating data.';
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
        statusText.textContent = 'Importing user accounts into the database...';

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

                alert(`🎉 User import completed successfully!\n\n` +
                      `• New Accounts Created: ${summary.inserted || 0}\n` +
                      `• Accounts Updated: ${summary.updated || 0}\n` +
                      `• Skipped (Duplicates): ${summary.skipped || 0}\n` +
                      allocMsg + `\n` +
                      `New users can now log in using:\n` +
                      `• Username: Full Name or Reg Code / Staff ID\n` +
                      `• Initial Password: The Reg Code / Staff ID itself.\n\n` +
                      `Upon first login, the system will prompt them to set a New Password and update contact details.`);
                this.close();
                window.location.reload();
            } else {
                alertBox.textContent = result.message || 'Error occurred while importing data.';
                alertBox.style.display = 'block';
                startBtn.disabled = false;
            }
        } catch (err) {
            alertBox.textContent = 'Network error occurred while importing data.';
            alertBox.style.display = 'block';
            startBtn.disabled = false;
        }
    }
}

customElements.define('app-csv-import-modal', AppCsvImportModal);
