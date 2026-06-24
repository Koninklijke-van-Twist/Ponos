(function () {
    const boot = window.PONOS_BOOT || {};
    const i18n = boot.i18n || {};
    const state = {
        company: boot.company || '',
        department: boot.department || '',
        project: boot.project || '',
        task: boot.task || '',
        navigation: null,
        tasks: [],
        users: [],
        activeTask: null,
        editMode: false,
        draggedTaskId: null,
    };

    const el = {
        company: document.getElementById('ponos-company'),
        sidebar: document.getElementById('ponos-sidebar-content'),
        alert: document.getElementById('ponos-alert'),
        board: document.getElementById('ponos-board'),
        empty: document.getElementById('ponos-empty'),
        newTask: document.getElementById('ponos-new-task'),
        projectTitle: document.getElementById('ponos-project-title'),
        projectSubtitle: document.getElementById('ponos-project-subtitle'),
        detail: document.getElementById('ponos-detail'),
        detailTitle: document.getElementById('ponos-detail-title'),
        detailMeta: document.getElementById('ponos-detail-meta'),
        detailBody: document.getElementById('ponos-detail-body'),
        closeDetail: document.getElementById('ponos-close-detail'),
        copyLink: document.getElementById('ponos-copy-link'),
        editTask: document.getElementById('ponos-edit-task'),
    };

    function apiUrl(action, params) {
        const url = new URL('ponos_api.php', window.location.href);
        url.searchParams.set('action', action);
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                url.searchParams.set(key, params[key]);
            }
        });
        return url;
    }

    function apiFetchUrl(action, params) {
        const url = apiUrl(action, params);
        url.searchParams.set('_t', String(Date.now()));
        return url;
    }

    function firstDepartmentCode() {
        const grouped = (state.navigation && state.navigation.projects_by_department) || {};
        const codes = Object.keys(grouped).sort(function (left, right) {
            return departmentLabel(left).localeCompare(departmentLabel(right), undefined, { sensitivity: 'base' });
        });

        return codes[0] || '';
    }

    function syncCompanySelect() {
        if (!el.company || !state.company) {
            return;
        }

        const options = Array.prototype.slice.call(el.company.options || []);
        const match = options.find(function (option) {
            return String(option.value) === String(state.company);
        });
        if (match) {
            el.company.value = match.value;
        }
    }

    function showAlert(message) {
        if (!el.alert) {
            return;
        }
        el.alert.hidden = false;
        el.alert.textContent = message;
    }

    function clearAlert() {
        if (el.alert) {
            el.alert.hidden = true;
            el.alert.textContent = '';
        }
    }

    function hashTextForColor(value) {
        let hash = 0;
        const text = String(value || '');
        for (let i = 0; i < text.length; i++) {
            hash = text.charCodeAt(i) + ((hash << 5) - hash);
        }
        return hash;
    }

    function colorFromText(text) {
        const normalized = String(text || '').toLowerCase().trim();
        if (normalized === '') {
            return { dark: '#64748b', light: '#94a3b8', border: '#cbd5e1', chipBackground: '#e2e8f0', cardBackground: '#ffffff', chipTextColor: '#334155' };
        }
        const hash = hashTextForColor(normalized);
        const hue = Math.abs(hash) % 360;
        const saturation = 72 + (Math.abs(hash >> 8) % 14);
        const lightness = 56 + (Math.abs(hash >> 16) % 10);
        const darkLightness = Math.max(lightness - 18, 32);
        const chipTextColor = lightness >= 58 ? '#1e293b' : '#ffffff';
        return {
            border: 'hsl(' + hue + ', ' + saturation + '%, ' + Math.max(lightness - 6, 48) + '%)',
            dark: 'hsl(' + hue + ', ' + saturation + '%, ' + darkLightness + '%)',
            light: 'hsl(' + hue + ', ' + saturation + '%, ' + lightness + '%)',
            chipBackground: 'hsl(' + hue + ', ' + saturation + '%, ' + lightness + '%)',
            cardBackground: 'hsl(' + hue + ', ' + Math.min(saturation, 48) + '%, 96%)',
            chipTextColor: chipTextColor,
        };
    }

    function syncUrl(replace) {
        const url = new URL(window.location.href);
        ['company', 'dept', 'project', 'task'].forEach(function (key) {
            url.searchParams.delete(key);
        });
        if (state.company) {
            url.searchParams.set('company', state.company);
        }
        if (state.department) {
            url.searchParams.set('dept', state.department);
        }
        if (state.project) {
            url.searchParams.set('project', state.project);
        }
        if (state.task) {
            url.searchParams.set('task', state.task);
        }
        const method = replace ? 'replaceState' : 'pushState';
        window.history[method]({}, '', url.pathname + url.search);
    }

    async function savePrefs() {
        const body = new URLSearchParams();
        body.set('action', 'save_prefs');
        body.set('company', state.company);
        body.set('department', state.department);
        await fetch(apiUrl('save_prefs'), { method: 'POST', body: body, credentials: 'same-origin' });
    }

    async function loadUsers() {
        try {
            const response = await fetch('getusers.php', { credentials: 'same-origin' });
            const data = await response.json();
            state.users = Array.isArray(data) ? data : [];
        } catch (error) {
            state.users = [];
        }
    }

    async function loadNavigation(options) {
        options = options || {};
        clearAlert();
        if (el.sidebar) {
            el.sidebar.textContent = '…';
        }

        const response = await fetch(apiFetchUrl('navigation', { company: state.company }), {
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.load_failed']);
            return false;
        }

        state.navigation = data;
        state.company = data.company || state.company;
        syncCompanySelect();

        if (options.applyPrefs !== false && !state.department && data.prefs && data.prefs.department) {
            state.department = data.prefs.department;
        }

        if (options.expandFirstDepartment && !state.department) {
            state.department = firstDepartmentCode();
        }

        renderSidebar();
        return true;
    }

    function departmentLabel(code) {
        if (code === '_none') {
            return i18n['ponos.sidebar.no_department'];
        }
        const departments = (state.navigation && state.navigation.departments) || [];
        const match = departments.find(function (item) { return item.code === code; });
        return match ? (match.label || match.code) : code;
    }

    function renderSidebar() {
        if (!el.sidebar || !state.navigation) {
            return;
        }

        const grouped = state.navigation.projects_by_department || {};
        const departmentCodes = Object.keys(grouped).sort(function (a, b) {
            return departmentLabel(a).localeCompare(departmentLabel(b), undefined, { sensitivity: 'base' });
        });

        if (departmentCodes.length === 0) {
            el.sidebar.textContent = i18n['ponos.empty.select_project'];
            return;
        }

        el.sidebar.innerHTML = '';
        departmentCodes.forEach(function (deptCode) {
            const projects = grouped[deptCode] || [];
            const section = document.createElement('div');
            section.className = 'ponos-dept';

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'ponos-dept-toggle' + (state.department === deptCode ? ' is-active' : '');
            toggle.textContent = departmentLabel(deptCode) + ' (' + projects.length + ')';
            toggle.addEventListener('click', function () {
                state.department = deptCode;
                savePrefs();
                renderSidebar();
            });

            const list = document.createElement('ul');
            list.className = 'ponos-project-list';
            if (state.department === deptCode) {
                projects.forEach(function (project) {
                    const item = document.createElement('li');
                    const link = document.createElement('a');
                    link.href = '#';
                    link.className = 'ponos-project-link' + (state.project === project.no ? ' is-active' : '');
                    link.textContent = project.no + (project.description ? ' — ' + project.description : '');
                    link.addEventListener('click', function (event) {
                        event.preventDefault();
                        openProject(deptCode, project.no, '');
                    });
                    item.appendChild(link);
                    list.appendChild(item);
                });
            }

            section.appendChild(toggle);
            if (state.department === deptCode) {
                section.appendChild(list);
            }
            el.sidebar.appendChild(section);
        });
    }

    async function openProject(department, projectNo, taskId) {
        state.department = department;
        state.project = projectNo;
        state.task = taskId || '';
        syncUrl(false);
        savePrefs();
        renderSidebar();
        await loadTasks();
        if (state.task) {
            await openTask(state.task);
        }
    }

    async function loadTasks() {
        clearAlert();
        if (!state.project) {
            el.board.hidden = true;
            el.newTask.hidden = true;
            el.empty.hidden = false;
            el.empty.textContent = i18n['ponos.empty.select_project'];
            el.projectTitle.textContent = '';
            el.projectSubtitle.textContent = '';
            return;
        }

        el.empty.hidden = true;
        el.newTask.hidden = false;
        el.board.hidden = false;
        el.projectTitle.textContent = state.project;
        el.projectSubtitle.textContent = departmentLabel(state.department);

        const response = await fetch(apiFetchUrl('list_tasks', { company: state.company, project: state.project }), {
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.load_failed']);
            return;
        }
        state.tasks = data.tasks || [];
        renderBoard();
    }

    function renderBoard() {
        if (!el.board) {
            return;
        }
        el.board.innerHTML = '';
        ['todo', 'in_progress', 'done'].forEach(function (status) {
            const column = document.createElement('section');
            column.className = 'ponos-column';
            column.dataset.status = status;

            const head = document.createElement('div');
            head.className = 'ponos-column-head';
            head.textContent = (boot.statuses && boot.statuses[status]) || status;

            const body = document.createElement('div');
            body.className = 'ponos-column-body';
            body.dataset.status = status;
            body.addEventListener('dragover', onColumnDragOver);
            body.addEventListener('dragleave', onColumnDragLeave);
            body.addEventListener('drop', onColumnDrop);

            state.tasks.filter(function (task) { return task.status === status; }).forEach(function (task) {
                body.appendChild(renderTaskCard(task));
            });

            column.appendChild(head);
            column.appendChild(body);
            el.board.appendChild(column);
        });

        if (state.tasks.length === 0) {
            el.empty.hidden = false;
            el.empty.textContent = i18n['ponos.empty.no_tasks'];
        }
    }

    function renderTaskCard(task) {
        const card = document.createElement('article');
        card.className = 'ponos-card';
        card.dataset.taskId = task.id;

        const colors = task.colors || colorFromText(task.category || '');
        const total = Number(task.checklist_total || 0);
        const done = Number(task.checklist_done || 0);
        const progress = total > 0 ? Math.round((done / total) * 100) : 0;

        const bar = document.createElement('div');
        bar.className = 'ponos-card-bar';
        bar.draggable = true;
        bar.style.setProperty('--ponos-bar-dark', colors.dark);
        bar.style.setProperty('--ponos-bar-light', colors.light);
        if (total > 0) {
            const fill = document.createElement('div');
            fill.className = 'ponos-card-bar-fill';
            fill.style.width = progress + '%';
            bar.appendChild(fill);
        }
        bar.addEventListener('dragstart', function (event) {
            state.draggedTaskId = task.id;
            event.dataTransfer.setData('text/plain', task.id);
        });
        bar.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        const body = document.createElement('div');
        body.className = 'ponos-card-body';
        body.addEventListener('click', function () {
            openTask(task.id);
        });

        const title = document.createElement('h3');
        title.className = 'ponos-card-title';
        title.textContent = task.title;

        const meta = document.createElement('div');
        meta.className = 'ponos-card-meta';
        meta.innerHTML = '<div>' + escapeHtml(task.category || '') + '</div>'
            + (task.assignee_email ? '<div>' + escapeHtml(task.assignee_email) + '</div>' : '')
            + (task.due_date ? '<div>' + escapeHtml(task.due_date) + '</div>' : '')
            + (total > 0 ? '<div>' + done + '/' + total + '</div>' : '');

        body.appendChild(title);
        body.appendChild(meta);
        card.appendChild(bar);
        card.appendChild(body);
        return card;
    }

    function onColumnDragOver(event) {
        event.preventDefault();
        event.currentTarget.classList.add('is-drop-target');
    }

    function onColumnDragLeave(event) {
        event.currentTarget.classList.remove('is-drop-target');
    }

    async function onColumnDrop(event) {
        event.preventDefault();
        event.currentTarget.classList.remove('is-drop-target');
        const status = event.currentTarget.dataset.status;
        const taskId = state.draggedTaskId || event.dataTransfer.getData('text/plain');
        if (!taskId || !status) {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'update_status');
        body.set('company', state.company);
        body.set('project', state.project);
        body.set('task', taskId);
        body.set('status', status);
        const response = await fetch(apiUrl('update_status'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        await loadTasks();
        if (state.task === taskId) {
            state.activeTask = data.task;
            renderTaskDetail();
        }
    }

    async function openTask(taskId) {
        state.task = taskId;
        syncUrl(true);
        const response = await fetch(apiFetchUrl('get_task', { company: state.company, project: state.project, task: taskId }), {
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.load_failed']);
            return;
        }
        state.activeTask = data.task;
        state.editMode = false;
        renderTaskDetail();
        el.detail.classList.add('is-open');
        el.detail.setAttribute('aria-hidden', 'false');
    }

    function closeTaskDetail() {
        state.task = '';
        state.activeTask = null;
        state.editMode = false;
        syncUrl(true);
        el.detail.classList.remove('is-open');
        el.detail.setAttribute('aria-hidden', 'true');
    }

    function renderTaskDetail() {
        const task = state.activeTask;
        if (!task) {
            return;
        }
        el.detailTitle.textContent = task.title;
        el.detailMeta.textContent = [task.category, task.assignee_email, task.due_date].filter(Boolean).join(' · ');

        if (state.editMode) {
            el.detailBody.innerHTML = buildTaskForm(task);
            wireTaskForm(task.id);
            return;
        }

        const colors = colorFromText(task.category || '');
        let html = '<div class="ponos-form"><div><strong>' + escapeHtml(i18n['ponos.field.description']) + '</strong><p>' + escapeHtml(task.description || '') + '</p></div>';

        if ((task.checklist || []).length > 0) {
            html += '<div><strong>' + escapeHtml(i18n['ponos.field.checklist']) + '</strong><div class="ponos-checklist">';
            task.checklist.forEach(function (item) {
                html += '<label class="ponos-checklist-item"><input type="checkbox" data-checklist-id="' + item.id + '"' + (item.done ? ' checked' : '') + '><span>' + escapeHtml(item.label) + '</span></label>';
            });
            html += '</div></div>';
        }

        if ((task.attachments || []).length > 0) {
            html += '<div class="ponos-attachments"><strong>' + escapeHtml(i18n['ponos.field.attachments']) + '</strong>';
            task.attachments.forEach(function (file) {
                html += '<a href="' + attachmentUrl(file.id) + '">' + escapeHtml(file.filename) + '</a>';
            });
            html += '</div>';
        }

        html += '<div><strong>' + escapeHtml(i18n['ponos.task.messages']) + '</strong><div class="ponos-messages" id="ponos-messages">';
        (task.messages || []).forEach(function (message) {
            html += renderMessageHtml(message);
        });
        html += '</div></div>';

        html += '<div class="ponos-message-compose"><label>' + escapeHtml(i18n['ponos.field.message']) + '<textarea id="ponos-message-text" rows="3"></textarea></label>';
        html += '<label>' + escapeHtml(i18n['ponos.field.attachments']) + '<input type="file" id="ponos-message-files" multiple></label>';
        html += '<button type="button" id="ponos-send-message" class="ponos-btn">' + escapeHtml(i18n['ponos.btn.send']) + '</button></div></div>';

        el.detailBody.innerHTML = html;

        el.detailBody.querySelectorAll('input[data-checklist-id]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                toggleChecklistItem(task.id, Number(checkbox.dataset.checklistId), checkbox.checked);
            });
        });

        const sendButton = document.getElementById('ponos-send-message');
        if (sendButton) {
            sendButton.addEventListener('click', function () {
                sendMessage(task.id);
            });
        }
    }

    function renderMessageHtml(message) {
        const emailColors = message.colors || colorFromText(message.email || '');
        const isSystem = message.kind === 'system';
        let html = '<article class="ponos-message' + (isSystem ? ' ponos-message--system' : '') + '" style="border-color:' + escapeHtml(emailColors.border) + ';background:' + escapeHtml(emailColors.cardBackground) + '">';
        if (!isSystem) {
            html += '<div class="ponos-message-meta"><span class="ponos-message-email" style="background:' + escapeHtml(emailColors.chipBackground) + ';color:' + escapeHtml(emailColors.chipTextColor) + '">' + escapeHtml(message.email || '') + '</span><span>' + escapeHtml(formatTimestamp(message.created_at)) + '</span></div>';
        }
        html += '<div>' + escapeHtml(message.text || '') + '</div>';
        if ((message.attachments || []).length > 0) {
            html += '<div class="ponos-attachments">';
            message.attachments.forEach(function (file) {
                html += '<a href="' + attachmentUrl(file.id) + '">' + escapeHtml(file.filename) + '</a>';
            });
            html += '</div>';
        }
        html += '</article>';
        return html;
    }

    function buildTaskForm(task) {
        const isNew = !task || !task.id;
        let checklistHtml = '';
        const items = (task && task.checklist) || [''];
        items.forEach(function (item) {
            const label = typeof item === 'string' ? item : (item.label || '');
            checklistHtml += '<div class="ponos-checklist-row" style="display:flex;gap:8px;"><input type="text" class="ponos-checklist-input" value="' + escapeAttr(label) + '"><button type="button" class="ponos-btn ponos-btn--ghost ponos-remove-checklist">−</button></div>';
        });

        let usersOptions = '<option value=""></option>';
        state.users.forEach(function (user) {
            const email = String(user.Email || '').toLowerCase();
            const selected = task && task.assignee_email === email ? ' selected' : '';
            usersOptions += '<option value="' + escapeAttr(email) + '"' + selected + '>' + escapeHtml(user.Naam || email) + '</option>';
        });

        let categories = '';
        (boot.categories || []).forEach(function (category) {
            const selected = task && task.category === category ? ' selected' : '';
            categories += '<option value="' + escapeAttr(category) + '"' + selected + '>' + escapeHtml(category) + '</option>';
        });

        return '<form class="ponos-form" id="ponos-task-form">'
            + '<label>' + escapeHtml(i18n['ponos.field.title']) + '<input name="title" required value="' + escapeAttr((task && task.title) || '') + '"></label>'
            + '<label>' + escapeHtml(i18n['ponos.field.description']) + '<textarea name="description" required rows="4">' + escapeHtml((task && task.description) || '') + '</textarea></label>'
            + '<label>' + escapeHtml(i18n['ponos.field.category']) + '<select name="category">' + categories + '</select></label>'
            + '<label>' + escapeHtml(i18n['ponos.field.assignee']) + '<select name="assignee_email">' + usersOptions + '</select></label>'
            + '<label>' + escapeHtml(i18n['ponos.field.due_date']) + '<input type="date" name="due_date" value="' + escapeAttr((task && task.due_date) || '') + '"></label>'
            + '<div><strong>' + escapeHtml(i18n['ponos.field.checklist']) + '</strong><div id="ponos-checklist-editor" class="ponos-checklist">' + checklistHtml + '</div>'
            + '<button type="button" id="ponos-add-checklist" class="ponos-btn ponos-btn--ghost">' + escapeHtml(i18n['ponos.field.checklist_add']) + '</button></div>'
            + '<label>' + escapeHtml(i18n['ponos.field.attachments']) + '<input type="file" name="attachments" multiple></label>'
            + '<div style="display:flex;gap:8px;"><button type="submit" class="ponos-btn">' + escapeHtml(i18n['ponos.btn.save']) + '</button>'
            + '<button type="button" id="ponos-cancel-edit" class="ponos-btn ponos-btn--ghost">' + escapeHtml(i18n['ponos.btn.cancel']) + '</button></div>'
            + '</form>';
    }

    function wireTaskForm(taskId) {
        const form = document.getElementById('ponos-task-form');
        const editor = document.getElementById('ponos-checklist-editor');
        const addButton = document.getElementById('ponos-add-checklist');
        const cancelButton = document.getElementById('ponos-cancel-edit');

        if (addButton && editor) {
            addButton.addEventListener('click', function () {
                const row = document.createElement('div');
                row.className = 'ponos-checklist-row';
                row.style.display = 'flex';
                row.style.gap = '8px';
                row.innerHTML = '<input type="text" class="ponos-checklist-input" value=""><button type="button" class="ponos-btn ponos-btn--ghost ponos-remove-checklist">−</button>';
                editor.appendChild(row);
            });
        }

        if (editor) {
            editor.addEventListener('click', function (event) {
                const target = event.target;
                if (target && target.classList.contains('ponos-remove-checklist')) {
                    const row = target.closest('.ponos-checklist-row');
                    if (row) {
                        row.remove();
                    }
                }
            });
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', function () {
                if (taskId) {
                    state.editMode = false;
                    renderTaskDetail();
                } else {
                    closeTaskDetail();
                }
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitTaskForm(taskId, form);
            });
        }
    }

    async function submitTaskForm(taskId, form) {
        const formData = new FormData(form);
        formData.set('action', taskId ? 'update_task' : 'create_task');
        formData.set('company', state.company);
        formData.set('project', state.project);
        if (taskId) {
            formData.set('task', taskId);
        }

        const checklist = [];
        form.querySelectorAll('.ponos-checklist-input').forEach(function (input) {
            const value = String(input.value || '').trim();
            if (value !== '') {
                checklist.push(value);
            }
        });
        formData.set('checklist', JSON.stringify(checklist));

        const response = await fetch('ponos_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }

        await loadTasks();
        state.activeTask = data.task;
        state.task = data.task.id;
        state.editMode = false;
        syncUrl(true);
        renderTaskDetail();
        el.detail.classList.add('is-open');
    }

    async function toggleChecklistItem(taskId, itemId, done) {
        const body = new URLSearchParams();
        body.set('action', 'toggle_checklist');
        body.set('company', state.company);
        body.set('project', state.project);
        body.set('task', taskId);
        body.set('item_id', String(itemId));
        body.set('done', done ? '1' : '0');
        const response = await fetch(apiUrl('toggle_checklist'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        state.activeTask = data.task;
        await loadTasks();
        if (!state.editMode) {
            renderTaskDetail();
        }
    }

    async function sendMessage(taskId) {
        const textEl = document.getElementById('ponos-message-text');
        const filesEl = document.getElementById('ponos-message-files');
        const text = textEl ? String(textEl.value || '').trim() : '';
        if (text === '') {
            return;
        }

        const formData = new FormData();
        formData.set('action', 'add_message');
        formData.set('company', state.company);
        formData.set('project', state.project);
        formData.set('task', taskId);
        formData.set('text', text);
        if (filesEl && filesEl.files) {
            Array.from(filesEl.files).forEach(function (file) {
                formData.append('attachments[]', file);
            });
        }

        const response = await fetch('ponos_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }

        await openTask(taskId);
    }

    function attachmentUrl(attachmentId) {
        return apiUrl('download_attachment', {
            company: state.company,
            project: state.project,
            attachment_id: attachmentId,
        }).toString();
    }

    function formatTimestamp(value) {
        if (!value) {
            return '';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }
        return date.toLocaleString();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/'/g, '&#039;');
    }

    function openNewTaskForm() {
        state.activeTask = { title: '', description: '', category: (boot.categories || [])[0] || '', checklist: [''] };
        state.editMode = true;
        state.task = '';
        el.detailTitle.textContent = i18n['ponos.btn.new_task'];
        el.detailMeta.textContent = '';
        renderTaskDetail();
        el.detail.classList.add('is-open');
        el.detail.setAttribute('aria-hidden', 'false');
    }

    async function init() {
        if (el.company) {
            el.company.addEventListener('change', async function () {
                state.company = el.company.value;
                state.department = '';
                state.project = '';
                state.task = '';
                state.navigation = null;
                state.tasks = [];
                syncUrl(true);
                closeTaskDetail();

                await savePrefs();
                const loaded = await loadNavigation({ applyPrefs: false, expandFirstDepartment: true });
                if (!loaded) {
                    return;
                }

                await loadTasks();
            });
        }

        if (el.newTask) {
            el.newTask.addEventListener('click', openNewTaskForm);
        }
        if (el.closeDetail) {
            el.closeDetail.addEventListener('click', closeTaskDetail);
        }
        if (el.detail) {
            el.detail.addEventListener('click', function (event) {
                if (event.target === el.detail) {
                    closeTaskDetail();
                }
            });
        }
        if (el.editTask) {
            el.editTask.addEventListener('click', function () {
                if (!state.activeTask) {
                    return;
                }
                state.editMode = true;
                renderTaskDetail();
            });
        }
        if (el.copyLink) {
            el.copyLink.addEventListener('click', async function () {
                const url = new URL(window.location.href);
                try {
                    await navigator.clipboard.writeText(url.toString());
                    el.copyLink.textContent = i18n['ponos.task.link_copied'];
                    window.setTimeout(function () {
                        el.copyLink.textContent = i18n['ponos.task.copy_link'];
                    }, 2000);
                } catch (error) {
                    showAlert(i18n['ponos.error.save_failed']);
                }
            });
        }

        await loadUsers();
        await loadNavigation();
        if (state.project) {
            await openProject(state.department, state.project, state.task);
        } else {
            await loadTasks();
        }
    }

    init();
})();
