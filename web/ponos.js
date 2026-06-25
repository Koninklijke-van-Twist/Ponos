(function () {
    const boot = window.PONOS_BOOT || {};
    const i18n = boot.i18n || {};
    const MY_TASKS = '__my_tasks__';

    const state = {
        group: boot.group || '',
        task: boot.task || '',
        isAdmin: !!boot.isAdmin,
        navigation: null,
        tasks: [],
        users: [],
        activeTask: null,
        editMode: false,
        draggedTaskId: null,
        pendingDeleteGroup: null,
        pendingGroupEdit: null,
        emailPrefs: {},
        pinnedGroups: [],
        categories: [],
        pendingCategoryEdit: null,
        groupAccess: null,
        adminTab: 'categories',
        skipTaskReminderConfirm: false,
        pendingReminderTask: null,
        archivePage: 1,
        archiveTotalPages: 1,
    };

    const el = {
        sidebar: document.getElementById('ponos-sidebar-content'),
        alert: document.getElementById('ponos-alert'),
        board: document.getElementById('ponos-board'),
        empty: document.getElementById('ponos-empty'),
        newTask: document.getElementById('ponos-new-task'),
        groupTitle: document.getElementById('ponos-project-title'),
        groupSubtitle: document.getElementById('ponos-project-subtitle'),
        groupPin: document.getElementById('ponos-group-pin'),
        groupAdmin: document.getElementById('ponos-group-admin'),
        categoryAdminModal: document.getElementById('ponos-category-admin-modal'),
        categoryAdminContent: document.getElementById('ponos-category-admin-content'),
        categoryAdminClose: document.getElementById('ponos-category-admin-close'),
        categoryAdd: document.getElementById('ponos-category-add'),
        categoryModal: document.getElementById('ponos-category-modal'),
        categoryForm: document.getElementById('ponos-category-form'),
        categoryModalTitle: document.getElementById('ponos-category-modal-title'),
        categoryNameInput: document.getElementById('ponos-category-name'),
        categoryCancel: document.getElementById('ponos-category-cancel'),
        adminTabCategories: document.getElementById('ponos-admin-tab-categories'),
        adminTabAccess: document.getElementById('ponos-admin-tab-access'),
        adminPanelCategories: document.getElementById('ponos-admin-panel-categories'),
        adminPanelAccess: document.getElementById('ponos-admin-panel-access'),
        accessOpen: document.getElementById('ponos-access-open'),
        accessMembers: document.getElementById('ponos-access-members'),
        accessMemberList: document.getElementById('ponos-access-member-list'),
        accessUserSelect: document.getElementById('ponos-access-user-select'),
        accessAddMember: document.getElementById('ponos-access-add-member'),
        userName: document.getElementById('ponos-user-name'),
        settingsBtn: document.getElementById('ponos-settings-btn'),
        settingsModal: document.getElementById('ponos-settings-modal'),
        settingsForm: document.getElementById('ponos-settings-form'),
        settingsCancel: document.getElementById('ponos-settings-cancel'),
        detail: document.getElementById('ponos-detail'),
        detailTitle: document.getElementById('ponos-detail-title'),
        detailMeta: document.getElementById('ponos-detail-meta'),
        detailBody: document.getElementById('ponos-detail-body'),
        closeDetail: document.getElementById('ponos-close-detail'),
        copyLink: document.getElementById('ponos-copy-link'),
        editTask: document.getElementById('ponos-edit-task'),
        dangerModal: document.getElementById('ponos-danger-modal'),
        dangerConfirm: document.getElementById('ponos-danger-confirm'),
        dangerCancel: document.getElementById('ponos-danger-cancel'),
        groupModal: document.getElementById('ponos-group-modal'),
        groupForm: document.getElementById('ponos-group-form'),
        groupModalTitle: document.getElementById('ponos-group-modal-title'),
        groupNameInput: document.getElementById('ponos-group-name'),
        groupCancel: document.getElementById('ponos-group-cancel'),
        reminderModal: document.getElementById('ponos-reminder-modal'),
        reminderModalTitle: document.getElementById('ponos-reminder-modal-title'),
        reminderYes: document.getElementById('ponos-reminder-yes'),
        reminderYesAlways: document.getElementById('ponos-reminder-yes-always'),
        reminderNo: document.getElementById('ponos-reminder-no'),
        archiveModal: document.getElementById('ponos-archive-modal'),
        archiveContent: document.getElementById('ponos-archive-content'),
        archivePrev: document.getElementById('ponos-archive-prev'),
        archiveNext: document.getElementById('ponos-archive-next'),
        archivePageInfo: document.getElementById('ponos-archive-page-info'),
        archiveClose: document.getElementById('ponos-archive-close'),
        unarchiveTask: document.getElementById('ponos-unarchive-task'),
    };

    function formatI18n(key) {
        let text = i18n[key] || key;
        for (let index = 1; index < arguments.length; index++) {
            text = text.replace('%s', String(arguments[index]));
        }
        return text;
    }

    function taskCategoryDisplay(task) {
        if (task && task.category_display) {
            return task.category_display;
        }
        const label = String((task && task.category_label) || '').trim();
        return label !== '' ? label : (i18n['ponos.category.uncategorized'] || 'Ongecategoriseerd');
    }

    function userNameByEmail(email) {
        const normalized = String(email || '').toLowerCase();
        if (normalized === '') {
            return '';
        }
        const match = (state.users || []).find(function (user) {
            return String(user.Email || '').toLowerCase() === normalized;
        });
        if (match && match.Naam) {
            return String(match.Naam);
        }
        return '';
    }

    function userDisplayName() {
        const email = String((boot.userEmail || state.navigation && state.navigation.user_email) || '').toLowerCase();
        return userNameByEmail(email) || email || '';
    }

    function isGroupPinned(groupId) {
        return (state.pinnedGroups || []).indexOf(groupId) >= 0;
    }

    function updateUserFooter() {
        if (el.userName) {
            el.userName.textContent = userDisplayName();
        }
    }

    function updateGroupAdminButton() {
        if (!el.groupAdmin) {
            return;
        }
        const group = currentGroup();
        const show = state.isAdmin && group && !group.virtual && !!state.group;
        el.groupAdmin.hidden = !show;
        if (show) {
            el.groupAdmin.title = i18n['ponos.group.admin_title'];
        }
    }

    function currentGroupMeta() {
        return currentGroup();
    }

    function groupHasOpenAccess(group) {
        group = group || currentGroupMeta();
        return !!(group && (group.open_access || group.virtual));
    }

    function groupMemberEmails(group) {
        group = group || currentGroupMeta();
        if (!group || group.virtual) {
            return [];
        }
        return Array.isArray(group.member_emails) ? group.member_emails : [];
    }

    function assignableUsersForGroup(group) {
        group = group || currentGroupMeta();
        const users = state.users || [];
        if (!group || group.virtual) {
            return users;
        }
        if (groupHasOpenAccess(group)) {
            return users;
        }
        const allowed = new Set(groupMemberEmails(group).map(function (email) {
            return String(email || '').toLowerCase();
        }));
        return users.filter(function (user) {
            return allowed.has(String(user.Email || '').toLowerCase());
        });
    }

    function updateGroupPinButton() {
        if (!el.groupPin) {
            return;
        }
        const group = currentGroup();
        const showPin = group && !group.virtual && state.group;
        el.groupPin.hidden = !showPin;
        if (!showPin) {
            return;
        }
        const pinned = isGroupPinned(state.group);
        el.groupPin.classList.toggle('is-pinned', pinned);
        el.groupPin.title = pinned ? i18n['ponos.pin.unpin'] : i18n['ponos.pin.pin'];
    }

    function isMyTasksGroup(groupId) {
        return String(groupId || '') === MY_TASKS;
    }

    function currentGroup() {
        const groups = (state.navigation && state.navigation.groups) || [];
        return groups.find(function (item) { return item.id === state.group; }) || null;
    }

    function editableGroups() {
        return ((state.navigation && state.navigation.groups) || []).filter(function (group) {
            return !group.virtual && group.can_create_tasks !== false;
        });
    }

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
        ['group', 'task', 'company', 'dept', 'department', 'project'].forEach(function (key) {
            url.searchParams.delete(key);
        });
        if (state.group) {
            url.searchParams.set('group', state.group);
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
        body.set('group', state.group);
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

        const response = await fetch(apiFetchUrl('navigation'), {
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.load_failed']);
            return false;
        }

        state.navigation = data;
        state.isAdmin = !!data.is_admin;
        state.pinnedGroups = (data.prefs && data.prefs.pinned_groups) || [];
        state.emailPrefs = (data.prefs && data.prefs.email_prefs) || {};
        state.skipTaskReminderConfirm = !!(data.prefs && data.prefs.skip_task_reminder_confirm);

        if (options.applyPrefs !== false && !state.group && data.prefs && data.prefs.group) {
            state.group = data.prefs.group;
        }

        if (options.selectFirstGroup && !state.group) {
            const groups = data.groups || [];
            if (groups.length > 0) {
                state.group = groups[0].id;
            }
        }

        const visibleGroupIds = (data.groups || []).map(function (group) { return group.id; });
        if (state.group && visibleGroupIds.indexOf(state.group) < 0) {
            state.group = visibleGroupIds[0] || '';
            state.task = '';
            state.activeTask = null;
        }

        renderSidebar();
        updateUserFooter();
        updateGroupPinButton();
        updateGroupAdminButton();
        return true;
    }

    async function loadCategories() {
        if (!state.group || isMyTasksGroup(state.group)) {
            state.categories = [];
            return [];
        }
        const response = await fetch(apiFetchUrl('list_categories', { group: state.group }), {
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const data = await response.json();
        state.categories = data.ok ? (data.categories || []) : [];
        return state.categories;
    }

    function renderSidebar() {
        if (!el.sidebar || !state.navigation) {
            return;
        }

        const groups = state.navigation.groups || [];
        el.sidebar.innerHTML = '';

        if (state.isAdmin) {
            const adminBar = document.createElement('div');
            adminBar.className = 'ponos-sidebar-admin';
            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'ponos-btn ponos-btn--small';
            addButton.textContent = i18n['ponos.btn.new_group'];
            addButton.addEventListener('click', function () {
                showGroupForm();
            });
            adminBar.appendChild(addButton);
            el.sidebar.appendChild(adminBar);
        }

        if (groups.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'ponos-muted';
            empty.textContent = i18n['ponos.empty.no_groups'];
            el.sidebar.appendChild(empty);
            return;
        }

        const list = document.createElement('ul');
        list.className = 'ponos-group-list';

        groups.forEach(function (group) {
            const item = document.createElement('li');
            item.className = 'ponos-group-item';

            const link = document.createElement('button');
            link.type = 'button';
            const pinned = !group.virtual && (group.pinned || isGroupPinned(group.id));
            let linkClass = 'ponos-group-link';
            if (state.group === group.id) {
                linkClass += ' is-active';
            }
            if (pinned) {
                linkClass += ' is-pinned';
            }
            link.className = linkClass;
            const count = group.task_count != null ? group.task_count : 0;
            const label = group.name + ' (' + count + ')';
            if (pinned) {
                const pinIcon = document.createElement('span');
                pinIcon.className = 'ponos-group-pin';
                pinIcon.textContent = '📌';
                pinIcon.setAttribute('aria-hidden', 'true');
                const labelSpan = document.createElement('span');
                labelSpan.className = 'ponos-group-link-label';
                labelSpan.textContent = label;
                link.appendChild(pinIcon);
                link.appendChild(labelSpan);
            } else {
                link.textContent = label;
            }
            link.addEventListener('click', function () {
                openGroup(group.id, '');
            });
            item.appendChild(link);

            if (state.isAdmin && !group.virtual) {
                const actions = document.createElement('div');
                actions.className = 'ponos-group-actions';

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'ponos-icon-btn';
                editBtn.title = i18n['ponos.btn.edit'];
                editBtn.textContent = '✎';
                editBtn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    editGroup(group);
                });

                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'ponos-icon-btn ponos-icon-btn--danger';
                deleteBtn.title = i18n['ponos.btn.delete'];
                deleteBtn.textContent = '✕';
                deleteBtn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    requestDeleteGroup(group);
                });

                actions.appendChild(editBtn);
                actions.appendChild(deleteBtn);
                item.appendChild(actions);
            }

            list.appendChild(item);
        });

        el.sidebar.appendChild(list);
    }

    function showGroupForm(existingGroup) {
        state.pendingGroupEdit = existingGroup || null;
        if (el.groupModalTitle) {
            el.groupModalTitle.textContent = existingGroup
                ? i18n['ponos.group.rename_title']
                : i18n['ponos.group.new_title'];
        }
        if (el.groupNameInput) {
            el.groupNameInput.value = existingGroup ? existingGroup.name : '';
        }
        if (el.groupModal) {
            el.groupModal.hidden = false;
            el.groupModal.setAttribute('aria-hidden', 'false');
        }
        if (el.groupNameInput) {
            window.setTimeout(function () {
                el.groupNameInput.focus();
                el.groupNameInput.select();
            }, 0);
        }
    }

    function hideGroupModal() {
        state.pendingGroupEdit = null;
        if (el.groupForm) {
            el.groupForm.reset();
        }
        if (el.groupModal) {
            el.groupModal.hidden = true;
            el.groupModal.setAttribute('aria-hidden', 'true');
        }
    }

    async function submitGroupForm(event) {
        if (event) {
            event.preventDefault();
        }
        const trimmed = el.groupNameInput ? String(el.groupNameInput.value || '').trim() : '';
        if (trimmed === '') {
            return;
        }

        const existingGroup = state.pendingGroupEdit;
        hideGroupModal();

        if (existingGroup) {
            await updateGroupName(existingGroup.id, trimmed);
        } else {
            await createGroup(trimmed);
        }
    }

    function editGroup(group) {
        showGroupForm(group);
    }

    async function createGroup(name) {
        const body = new URLSearchParams();
        body.set('action', 'create_group');
        body.set('name', name);
        const response = await fetch(apiUrl('create_group'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        await loadNavigation({ applyPrefs: false });
        await openGroup(data.group.id, '');
    }

    async function updateGroupName(groupId, name) {
        const body = new URLSearchParams();
        body.set('action', 'update_group');
        body.set('group', groupId);
        body.set('name', name);
        const response = await fetch(apiUrl('update_group'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        await loadNavigation({ applyPrefs: false });
        await loadTasks();
    }

    function requestDeleteGroup(group) {
        state.pendingDeleteGroup = group;
        deleteGroup(false);
    }

    function showDangerModal() {
        if (!el.dangerModal) {
            return;
        }
        el.dangerModal.hidden = false;
        el.dangerModal.setAttribute('aria-hidden', 'false');
    }

    function hideDangerModal() {
        if (!el.dangerModal) {
            return;
        }
        el.dangerModal.hidden = true;
        el.dangerModal.setAttribute('aria-hidden', 'true');
        state.pendingDeleteGroup = null;
    }

    async function deleteGroup(confirmed) {
        const group = state.pendingDeleteGroup;
        if (!group) {
            return;
        }

        const body = new URLSearchParams();
        body.set('action', 'delete_group');
        body.set('group', group.id);
        if (confirmed) {
            body.set('confirm', '1');
        }

        const response = await fetch(apiUrl('delete_group'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();

        if (!data.ok && data.needs_confirm) {
            showDangerModal();
            return;
        }

        hideDangerModal();

        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }

        if (state.group === group.id) {
            state.group = MY_TASKS;
            state.task = '';
            closeTaskDetail();
        }

        await loadNavigation({ applyPrefs: false });
        if (!state.group) {
            const groups = (state.navigation && state.navigation.groups) || [];
            state.group = groups.length > 0 ? groups[0].id : '';
        }
        syncUrl(true);
        savePrefs();
        await loadTasks();
    }

    async function openGroup(groupId, taskId) {
        state.group = groupId;
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
        const group = currentGroup();

        if (!state.group) {
            el.board.hidden = true;
            el.newTask.hidden = true;
            el.empty.hidden = false;
            el.empty.textContent = i18n['ponos.empty.select_group'];
            el.groupTitle.textContent = '';
            el.groupSubtitle.textContent = '';
            return;
        }

        el.empty.hidden = true;
        el.board.hidden = false;
        el.groupTitle.textContent = group ? group.name : state.group;
        el.groupSubtitle.textContent = isMyTasksGroup(state.group)
            ? i18n['ponos.group.my_tasks_hint']
            : '';
        updateGroupPinButton();
        updateGroupAdminButton();

        const canCreate = group && group.can_create_tasks !== false && !group.virtual;
        el.newTask.hidden = !canCreate;

        await loadCategories();

        const response = await fetch(apiFetchUrl('list_tasks', { group: state.group }), {
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

            const headTitle = document.createElement('span');
            headTitle.className = 'ponos-column-head-title';
            headTitle.textContent = (boot.statuses && boot.statuses[status]) || status;
            head.appendChild(headTitle);

            if (status === 'done') {
                const archiveBtn = document.createElement('button');
                archiveBtn.type = 'button';
                archiveBtn.className = 'ponos-archive-btn';
                archiveBtn.textContent = i18n['ponos.btn.archive'];
                archiveBtn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    openArchiveModal(1);
                });
                head.appendChild(archiveBtn);
            }

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

    function taskColorKey(task) {
        const label = String(task.category_label || '').trim();
        if (label !== '') {
            return label;
        }
        return '';
    }

    function renderCategoryAdminList() {
        if (!el.categoryAdminContent) {
            return;
        }
        const categories = state.categories || [];
        el.categoryAdminContent.innerHTML = '';
        if (categories.length === 0) {
            el.categoryAdminContent.className = 'ponos-muted';
            el.categoryAdminContent.textContent = i18n['ponos.empty.no_categories'];
            return;
        }

        el.categoryAdminContent.className = '';
        const list = document.createElement('ul');
        list.className = 'ponos-category-admin-list';
        categories.forEach(function (category) {
            const item = document.createElement('li');
            item.className = 'ponos-category-admin-item';
            const colors = colorFromText(category.name);
            const label = document.createElement('div');
            label.innerHTML = '<span class="ponos-category-swatch" style="background:' + escapeHtml(colors.dark) + '"></span>'
                + escapeHtml(category.name);

            const actions = document.createElement('div');
            actions.className = 'ponos-group-actions';
            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'ponos-icon-btn';
            editBtn.title = i18n['ponos.btn.edit'];
            editBtn.textContent = '✎';
            editBtn.addEventListener('click', function () {
                showCategoryForm(category);
            });
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'ponos-icon-btn ponos-icon-btn--danger';
            deleteBtn.title = i18n['ponos.btn.delete'];
            deleteBtn.textContent = '✕';
            deleteBtn.addEventListener('click', function () {
                deleteCategory(category);
            });
            actions.appendChild(editBtn);
            actions.appendChild(deleteBtn);
            item.appendChild(label);
            item.appendChild(actions);
            list.appendChild(item);
        });
        el.categoryAdminContent.appendChild(list);
    }

    function currentUserEmail() {
        return String((boot.userEmail || (state.navigation && state.navigation.user_email) || '')).toLowerCase();
    }

    function showAdminTab(tab) {
        state.adminTab = tab;
        const isCategories = tab === 'categories';
        if (el.adminTabCategories) {
            el.adminTabCategories.classList.toggle('is-active', isCategories);
            el.adminTabCategories.setAttribute('aria-selected', isCategories ? 'true' : 'false');
        }
        if (el.adminTabAccess) {
            el.adminTabAccess.classList.toggle('is-active', !isCategories);
            el.adminTabAccess.setAttribute('aria-selected', !isCategories ? 'true' : 'false');
        }
        if (el.adminPanelCategories) {
            el.adminPanelCategories.hidden = !isCategories;
        }
        if (el.adminPanelAccess) {
            el.adminPanelAccess.hidden = isCategories;
        }
    }

    async function loadGroupAccess() {
        if (!state.group || isMyTasksGroup(state.group)) {
            state.groupAccess = null;
            return null;
        }
        const response = await fetch(apiFetchUrl('get_group_access', { group: state.group }), {
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.load_failed']);
            return null;
        }
        state.groupAccess = data.access || { open_access: false, members: [] };
        return state.groupAccess;
    }

    function renderAccessAdminPanel() {
        const access = state.groupAccess || { open_access: false, members: [] };
        const openAccess = !!access.open_access;

        if (el.accessOpen) {
            el.accessOpen.checked = openAccess;
        }
        if (el.accessMembers) {
            el.accessMembers.classList.toggle('is-disabled', openAccess);
        }

        if (el.accessMemberList) {
            el.accessMemberList.innerHTML = '';
            const members = access.members || [];
            if (members.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'ponos-muted';
                empty.textContent = i18n['ponos.empty.no_members'];
                el.accessMemberList.appendChild(empty);
            } else {
                members.forEach(function (email) {
                    const item = document.createElement('li');
                    item.className = 'ponos-access-member-item';
                    const label = document.createElement('div');
                    const displayName = userNameByEmail(email);
                    label.textContent = displayName !== '' ? displayName + ' (' + email + ')' : email;

                    item.appendChild(label);
                    if (String(email).toLowerCase() !== currentUserEmail()) {
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'ponos-btn ponos-btn--ghost';
                        removeBtn.textContent = i18n['ponos.access.remove_member'];
                        removeBtn.addEventListener('click', function () {
                            removeGroupMember(email);
                        });
                        item.appendChild(removeBtn);
                    }
                    el.accessMemberList.appendChild(item);
                });
            }
        }

        if (el.accessUserSelect) {
            const memberSet = new Set((access.members || []).map(function (email) {
                return String(email || '').toLowerCase();
            }));
            let options = '<option value="">' + escapeHtml(i18n['ponos.access.add_member']) + '</option>';
            (state.users || []).forEach(function (user) {
                const email = String(user.Email || '').toLowerCase();
                if (email === '' || memberSet.has(email)) {
                    return;
                }
                options += '<option value="' + escapeAttr(email) + '">' + escapeHtml(user.Naam || email) + '</option>';
            });
            el.accessUserSelect.innerHTML = options;
        }
    }

    async function saveGroupOpenAccess(openAccess) {
        if (!state.group) {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'set_group_open_access');
        body.set('group', state.group);
        body.set('open_access', openAccess ? '1' : '0');
        const response = await fetch(apiUrl('set_group_open_access'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        state.groupAccess = data.access || state.groupAccess;
        await loadNavigation({ applyPrefs: false });
        renderAccessAdminPanel();
    }

    async function addGroupMember() {
        if (!state.group || !el.accessUserSelect) {
            return;
        }
        const email = String(el.accessUserSelect.value || '').trim();
        if (email === '') {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'add_group_member');
        body.set('group', state.group);
        body.set('email', email);
        const response = await fetch(apiUrl('add_group_member'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        state.groupAccess = data.access || state.groupAccess;
        await loadNavigation({ applyPrefs: false });
        renderAccessAdminPanel();
    }

    async function removeGroupMember(email) {
        if (!state.group || !email) {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'remove_group_member');
        body.set('group', state.group);
        body.set('email', email);
        const response = await fetch(apiUrl('remove_group_member'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        state.groupAccess = data.access || state.groupAccess;
        await loadNavigation({ applyPrefs: false });
        renderAccessAdminPanel();
    }

    async function showCategoryAdminModal() {
        if (!el.categoryAdminModal) {
            return;
        }
        showAdminTab('categories');
        await loadCategories();
        renderCategoryAdminList();
        await loadGroupAccess();
        renderAccessAdminPanel();
        el.categoryAdminModal.hidden = false;
        el.categoryAdminModal.setAttribute('aria-hidden', 'false');
    }

    function hideCategoryAdminModal() {
        if (!el.categoryAdminModal) {
            return;
        }
        el.categoryAdminModal.hidden = true;
        el.categoryAdminModal.setAttribute('aria-hidden', 'true');
    }

    function showCategoryForm(existingCategory) {
        state.pendingCategoryEdit = existingCategory || null;
        if (el.categoryModalTitle) {
            el.categoryModalTitle.textContent = existingCategory
                ? i18n['ponos.category.rename_title']
                : i18n['ponos.category.new_title'];
        }
        if (el.categoryNameInput) {
            el.categoryNameInput.value = existingCategory ? existingCategory.name : '';
        }
        if (el.categoryModal) {
            el.categoryModal.hidden = false;
            el.categoryModal.setAttribute('aria-hidden', 'false');
        }
        if (el.categoryNameInput) {
            window.setTimeout(function () {
                el.categoryNameInput.focus();
                el.categoryNameInput.select();
            }, 0);
        }
    }

    function hideCategoryModal() {
        state.pendingCategoryEdit = null;
        if (el.categoryForm) {
            el.categoryForm.reset();
        }
        if (el.categoryModal) {
            el.categoryModal.hidden = true;
            el.categoryModal.setAttribute('aria-hidden', 'true');
        }
    }

    async function submitCategoryForm(event) {
        if (event) {
            event.preventDefault();
        }
        const trimmed = el.categoryNameInput ? String(el.categoryNameInput.value || '').trim() : '';
        if (trimmed === '' || !state.group) {
            return;
        }
        const existing = state.pendingCategoryEdit;
        hideCategoryModal();

        const body = new URLSearchParams();
        body.set('group', state.group);
        body.set('name', trimmed);
        if (existing) {
            body.set('action', 'update_category');
            body.set('category', existing.id);
        } else {
            body.set('action', 'create_category');
        }

        const response = await fetch(apiUrl(existing ? 'update_category' : 'create_category'), {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }

        await loadCategories();
        renderCategoryAdminList();
        await loadTasks();
    }

    async function deleteCategory(category) {
        if (!state.group || !category) {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'delete_category');
        body.set('group', state.group);
        body.set('category', category.id);
        const response = await fetch(apiUrl('delete_category'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        await loadCategories();
        renderCategoryAdminList();
    }

    function reminderAssigneeName(task) {
        const email = String(task.assignee_email || '').trim();
        const name = userNameByEmail(email);
        return name !== '' ? name : email;
    }

    function handleReminderBellClick(task, bell, assigneeWrap) {
        if (!task || !task.can_remind) {
            return;
        }
        if (state.skipTaskReminderConfirm) {
            sendTaskReminder(task, false, bell, assigneeWrap);
            return;
        }
        state.pendingReminderTask = { task: task, bell: bell, assigneeWrap: assigneeWrap };
        if (el.reminderModalTitle) {
            el.reminderModalTitle.textContent = formatI18n('ponos.reminder.confirm', reminderAssigneeName(task));
        }
        if (el.reminderModal) {
            el.reminderModal.hidden = false;
            el.reminderModal.setAttribute('aria-hidden', 'false');
        }
    }

    function hideReminderModal() {
        state.pendingReminderTask = null;
        if (el.reminderModal) {
            el.reminderModal.hidden = true;
            el.reminderModal.setAttribute('aria-hidden', 'true');
        }
    }

    async function sendTaskReminder(task, skipConfirmAlways, bell, assigneeWrap) {
        hideReminderModal();
        if (!task || !state.group) {
            return;
        }

        const body = new URLSearchParams();
        body.set('action', 'send_task_reminder');
        body.set('group', state.group);
        body.set('task', task.id);
        if (skipConfirmAlways) {
            body.set('skip_confirm', '1');
        }

        const response = await fetch(apiUrl('send_task_reminder'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }

        if (data.skip_task_reminder_confirm) {
            state.skipTaskReminderConfirm = true;
        }

        task.can_remind = false;
        task.last_reminder_at = data.last_reminder_at || '';
        const taskIndex = state.tasks.findIndex(function (item) { return item.id === task.id; });
        if (taskIndex >= 0) {
            state.tasks[taskIndex].can_remind = false;
            state.tasks[taskIndex].last_reminder_at = task.last_reminder_at;
        }
        if (state.activeTask && state.activeTask.id === task.id) {
            state.activeTask.can_remind = false;
            state.activeTask.last_reminder_at = task.last_reminder_at;
        }

        showReminderFeedback(bell, assigneeWrap);
    }

    function showReminderFeedback(bell, assigneeWrap) {
        if (!bell || !assigneeWrap) {
            return;
        }

        bell.classList.add('is-ringing');
        bell.classList.remove('is-hidden');

        let balloon = assigneeWrap.querySelector('.ponos-remind-balloon');
        if (!balloon) {
            balloon = document.createElement('div');
            balloon.className = 'ponos-remind-balloon';
            assigneeWrap.appendChild(balloon);
        }
        balloon.textContent = i18n['ponos.reminder.sent'] || 'Emailreminder verstuurd.';

        window.setTimeout(function () {
            bell.classList.remove('is-ringing');
            bell.classList.add('is-hidden');
            if (balloon && balloon.parentNode) {
                balloon.parentNode.removeChild(balloon);
            }
        }, 5000);
    }

    function confirmReminder(skipAlways) {
        const pending = state.pendingReminderTask;
        if (!pending || !pending.task) {
            hideReminderModal();
            return;
        }
        sendTaskReminder(pending.task, skipAlways, pending.bell, pending.assigneeWrap);
    }

    async function openArchiveModal(page) {
        if (!state.group || !el.archiveModal) {
            return;
        }
        state.archivePage = Math.max(1, page || 1);
        await loadArchivePage(state.archivePage);
        el.archiveModal.hidden = false;
        el.archiveModal.setAttribute('aria-hidden', 'false');
    }

    function hideArchiveModal() {
        if (!el.archiveModal) {
            return;
        }
        el.archiveModal.hidden = true;
        el.archiveModal.setAttribute('aria-hidden', 'true');
    }

    async function loadArchivePage(page) {
        if (!state.group || !el.archiveContent) {
            return;
        }

        const response = await fetch(apiFetchUrl('list_archived_tasks', { group: state.group, page: String(page) }), {
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.load_failed']);
            return;
        }

        state.archivePage = Number(data.page || page);
        state.archiveTotalPages = Number(data.total_pages || 1);
        renderArchiveList(data.tasks || []);
        updateArchivePagination();
    }

    function renderArchiveList(tasks) {
        if (!el.archiveContent) {
            return;
        }

        el.archiveContent.innerHTML = '';
        if (!tasks.length) {
            el.archiveContent.className = 'ponos-muted';
            el.archiveContent.textContent = i18n['ponos.empty.no_archived_tasks'];
            return;
        }

        el.archiveContent.className = '';
        const list = document.createElement('ul');
        list.className = 'ponos-archive-list';
        tasks.forEach(function (task) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'ponos-archive-item';
            const title = document.createElement('div');
            title.className = 'ponos-archive-item-title';
            title.textContent = task.title;
            const meta = document.createElement('div');
            meta.className = 'ponos-archive-item-meta';
            const metaParts = [];
            if (isMyTasksGroup(state.group) && task.home_group_name) {
                metaParts.push(task.home_group_name);
            }
            metaParts.push(taskCategoryDisplay(task));
            if (task.due_date) {
                metaParts.push(formatDisplayDate(task.due_date));
            }
            meta.textContent = metaParts.join(' · ');
            item.appendChild(title);
            item.appendChild(meta);
            item.addEventListener('click', function () {
                hideArchiveModal();
                openTask(task.id);
            });
            const li = document.createElement('li');
            li.appendChild(item);
            list.appendChild(li);
        });
        el.archiveContent.appendChild(list);
    }

    function updateArchivePagination() {
        if (el.archivePageInfo) {
            el.archivePageInfo.textContent = state.archivePage + ' / ' + state.archiveTotalPages;
        }
        if (el.archivePrev) {
            el.archivePrev.disabled = state.archivePage <= 1;
        }
        if (el.archiveNext) {
            el.archiveNext.disabled = state.archivePage >= state.archiveTotalPages;
        }
    }

    async function unarchiveActiveTask() {
        const task = state.activeTask;
        if (!task || !state.group) {
            return;
        }

        const body = new URLSearchParams();
        body.set('action', 'unarchive_task');
        body.set('group', state.group);
        body.set('task', task.id);
        const response = await fetch(apiUrl('unarchive_task'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }

        state.activeTask = data.task;
        await loadTasks();
        renderTaskDetail();
    }

    function dateDisplayLocale() {
        return boot.dateLocale || document.documentElement.lang || 'nl-NL';
    }

    function formatDisplayDate(value) {
        const date = parseDueDate(value);
        if (!date) {
            return String(value || '');
        }

        return new Intl.DateTimeFormat(dateDisplayLocale(), {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(date);
    }

    function parseDueDate(dueDateStr) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(dueDateStr || '').trim());
        if (!match) {
            return null;
        }

        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    function startOfLocalDay(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function dueDateUrgencyClass(dueDateStr, status) {
        if (status === 'done' || !dueDateStr) {
            return '';
        }

        const due = parseDueDate(dueDateStr);
        if (!due) {
            return '';
        }

        const today = startOfLocalDay(new Date());
        const dueDay = startOfLocalDay(due);
        if (dueDay <= today) {
            return 'ponos-due--today';
        }

        const isoDay = today.getDay() === 0 ? 7 : today.getDay();
        const endOfWeek = new Date(today);
        endOfWeek.setDate(endOfWeek.getDate() + (7 - isoDay));
        if (dueDay <= startOfLocalDay(endOfWeek)) {
            return 'ponos-due--week';
        }

        return '';
    }

    function renderTaskCard(task) {
        const card = document.createElement('article');
        card.className = 'ponos-card';
        card.dataset.taskId = task.id;

        const colors = task.colors || colorFromText(taskColorKey(task));
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

        const main = document.createElement('div');
        main.className = 'ponos-card-main';

        const title = document.createElement('h3');
        title.className = 'ponos-card-title';
        title.textContent = task.title;

        const descriptionText = String(task.description || '').trim();
        main.appendChild(title);
        if (descriptionText !== '') {
            const description = document.createElement('p');
            description.className = 'ponos-card-description';
            description.innerHTML = formatDescriptionHtml(descriptionText);
            main.appendChild(description);
        }

        const meta = document.createElement('div');
        meta.className = 'ponos-card-meta';
        let metaHtml = '';
        if (isMyTasksGroup(state.group) && task.home_group_name) {
            metaHtml += '<div>' + escapeHtml(task.home_group_name) + '</div>';
        }
        metaHtml += '<div>' + escapeHtml(taskCategoryDisplay(task)) + '</div>';
        if (task.due_date) {
            const dueClass = dueDateUrgencyClass(task.due_date, task.status);
            metaHtml += '<div' + (dueClass ? ' class="' + dueClass + '"' : '') + '>' + escapeHtml(formatDisplayDate(task.due_date)) + '</div>';
        }
        metaHtml += (total > 0 ? '<div>' + done + '/' + total + '</div>' : '');
        meta.innerHTML = metaHtml;
        if (metaHtml !== '') {
            main.appendChild(meta);
        }

        body.appendChild(main);

        const assigneeEmail = String(task.assignee_email || '').trim();
        if (assigneeEmail !== '') {
            const assignee = document.createElement('div');
            assignee.className = 'ponos-card-assignee';

            if (task.can_remind) {
                const bell = document.createElement('button');
                bell.type = 'button';
                bell.className = 'ponos-card-remind-bell is-available';
                bell.setAttribute('aria-label', i18n['ponos.reminder.yes'] || 'Reminder');
                bell.textContent = '🔔';
                bell.addEventListener('click', function (event) {
                    event.stopPropagation();
                    handleReminderBellClick(task, bell, assignee);
                });
                assignee.appendChild(bell);
            }

            const assigneeText = document.createElement('div');
            assigneeText.className = 'ponos-card-assignee-text';
            const displayName = userNameByEmail(assigneeEmail);
            if (displayName !== '') {
                const nameEl = document.createElement('span');
                nameEl.className = 'ponos-card-assignee-name';
                nameEl.textContent = displayName;
                assigneeText.appendChild(nameEl);
            }
            const emailEl = document.createElement('span');
            emailEl.className = 'ponos-card-assignee-email';
            emailEl.textContent = assigneeEmail;
            assigneeText.appendChild(emailEl);
            assignee.appendChild(assigneeText);
            body.appendChild(assignee);
        }

        const unreadCount = Number(task.unread_count || 0);
        if (unreadCount > 0) {
            const badge = document.createElement('span');
            badge.className = 'ponos-unread-badge';
            badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            badge.title = String(unreadCount);
            body.appendChild(badge);
        }

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
        body.set('group', state.group);
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
        const response = await fetch(apiFetchUrl('get_task', { group: state.group, task: taskId }), {
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
        const taskIndex = state.tasks.findIndex(function (item) { return item.id === taskId; });
        if (taskIndex >= 0) {
            state.tasks[taskIndex].unread_count = 0;
            renderBoard();
        }
        renderTaskDetail();
        el.detail.classList.add('is-open');
        el.detail.setAttribute('aria-hidden', 'false');
    }

    function closeTaskDetail() {
        state.task = '';
        state.activeTask = null;
        state.editMode = false;
        if (el.unarchiveTask) {
            el.unarchiveTask.hidden = true;
        }
        syncUrl(true);
        el.detail.classList.remove('is-open');
        el.detail.setAttribute('aria-hidden', 'true');
    }

    function renderTaskDetail() {
        const task = state.activeTask;
        if (!task) {
            return;
        }

        const metaParts = [];
        if (isMyTasksGroup(state.group) && task.home_group_name) {
            metaParts.push(task.home_group_name);
        }
        if (task.assignee_email) {
            metaParts.push(task.assignee_email);
        }
        if (task.due_date) {
            metaParts.push(formatDisplayDate(task.due_date));
        }
        metaParts.push(taskCategoryDisplay(task));

        el.detailTitle.textContent = task.title;
        el.detailMeta.textContent = metaParts.join(' · ');

        if (el.unarchiveTask) {
            el.unarchiveTask.hidden = !task.is_archived;
        }

        if (state.editMode) {
            el.detailBody.innerHTML = '<div class="ponos-detail-layout ponos-detail-layout--edit">'
                + '<div class="ponos-detail-main ponos-detail-main--full">' + buildTaskForm(task) + '</div>'
                + '</div>';
            wireTaskForm(task.id);
            return;
        }

        let mainHtml = '<div class="ponos-form">';
        mainHtml += '<div><strong>' + escapeHtml(i18n['ponos.field.description']) + '</strong><p>' + formatDescriptionHtml(task.description || '') + '</p></div>';

        if ((task.checklist || []).length > 0) {
            mainHtml += '<div><strong>' + escapeHtml(i18n['ponos.field.checklist']) + '</strong><div class="ponos-checklist">';
            task.checklist.forEach(function (item) {
                mainHtml += '<label class="ponos-checklist-item"><input type="checkbox" data-checklist-id="' + item.id + '"' + (item.done ? ' checked' : '') + '><span>' + escapeHtml(item.label) + '</span></label>';
            });
            mainHtml += '</div></div>';
        }

        if ((task.attachments || []).length > 0) {
            mainHtml += '<div class="ponos-attachments"><strong>' + escapeHtml(i18n['ponos.field.attachments']) + '</strong>';
            task.attachments.forEach(function (file) {
                mainHtml += '<a href="' + attachmentUrl(file.id, task.id) + '">' + escapeHtml(file.filename) + '</a>';
            });
            mainHtml += '</div>';
        }
        mainHtml += '</div>';

        let chatHtml = '<div class="ponos-detail-chat">';
        chatHtml += '<h3 class="ponos-detail-chat-title">' + escapeHtml(i18n['ponos.task.messages']) + '</h3>';
        chatHtml += '<div class="ponos-messages" id="ponos-messages">';
        (task.messages || []).forEach(function (message) {
            chatHtml += renderMessageHtml(message, task.id);
        });
        chatHtml += '</div>';
        chatHtml += '<div class="ponos-message-compose">';
        chatHtml += '<label class="ponos-compose-label" for="ponos-message-text">' + escapeHtml(i18n['ponos.field.message']) + '</label>';
        chatHtml += '<textarea id="ponos-message-text" class="ponos-message-input" rows="1"></textarea>';
        chatHtml += '<label class="ponos-compose-label" for="ponos-message-files">' + escapeHtml(i18n['ponos.field.attachments']) + '</label>';
        chatHtml += '<input type="file" id="ponos-message-files" multiple>';
        chatHtml += '<button type="button" id="ponos-send-message" class="ponos-btn">' + escapeHtml(i18n['ponos.btn.send']) + '</button></div>';
        chatHtml += '</div>';

        el.detailBody.innerHTML = '<div class="ponos-detail-layout">'
            + '<div class="ponos-detail-main">' + mainHtml + '</div>'
            + chatHtml
            + '</div>';

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

        wireMessageTextarea();
        scrollMessagesToEnd();
    }

    function wireMessageTextarea() {
        const textarea = document.getElementById('ponos-message-text');
        if (!textarea) {
            return;
        }

        function resize() {
            const maxHeight = Math.min(window.innerHeight * 0.32, 280);
            textarea.style.height = 'auto';
            const nextHeight = Math.min(textarea.scrollHeight, maxHeight);
            textarea.style.height = nextHeight + 'px';
            textarea.style.overflowY = textarea.scrollHeight > maxHeight ? 'auto' : 'hidden';
        }

        textarea.addEventListener('input', resize);
        resize();
    }

    function scrollMessagesToEnd() {
        const log = document.getElementById('ponos-messages');
        if (!log) {
            return;
        }

        window.requestAnimationFrame(function () {
            log.scrollTop = log.scrollHeight;
        });
    }

    function renderMessageHtml(message, taskId) {
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
                html += '<a href="' + attachmentUrl(file.id, taskId) + '">' + escapeHtml(file.filename) + '</a>';
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
        const homeGroupId = task && (task.home_group_id || state.group);
        const formGroup = editableGroups().find(function (group) { return group.id === (homeGroupId || state.group); }) || currentGroupMeta();
        const assignableUsers = assignableUsersForGroup(formGroup);
        const assignableEmails = new Set(assignableUsers.map(function (user) {
            return String(user.Email || '').toLowerCase();
        }));
        const currentAssignee = task && String(task.assignee_email || '').toLowerCase();
        if (currentAssignee && !assignableEmails.has(currentAssignee)) {
            usersOptions += '<option value="' + escapeAttr(currentAssignee) + '" selected>' + escapeHtml(userNameByEmail(currentAssignee) || currentAssignee) + '</option>';
        }
        assignableUsers.forEach(function (user) {
            const email = String(user.Email || '').toLowerCase();
            const selected = task && task.assignee_email === email ? ' selected' : '';
            usersOptions += '<option value="' + escapeAttr(email) + '"' + selected + '>' + escapeHtml(user.Naam || email) + '</option>';
        });

        let groupOptions = '';
        editableGroups().forEach(function (group) {
            const selected = homeGroupId === group.id ? ' selected' : '';
            groupOptions += '<option value="' + escapeAttr(group.id) + '"' + selected + '>' + escapeHtml(group.name) + '</option>';
        });

        let groupField = '';
        if (!isNew && groupOptions) {
            groupField = '<label>' + escapeHtml(i18n['ponos.field.group']) + '<select name="target_group">' + groupOptions + '</select></label>';
        }

        let categoryOptions = '<option value=""></option>';
        const selectedCategoryId = task && task.category_id ? task.category_id : '';
        (state.categories || []).forEach(function (category) {
            const selected = selectedCategoryId === category.id ? ' selected' : '';
            categoryOptions += '<option value="' + escapeAttr(category.id) + '"' + selected + '>' + escapeHtml(category.name) + '</option>';
        });
        if (task && task.category_label && !selectedCategoryId) {
            categoryOptions += '<option value="" selected>' + escapeHtml(task.category_label) + ' (legacy)</option>';
        }

        return '<form class="ponos-form" id="ponos-task-form">'
            + '<label>' + escapeHtml(i18n['ponos.field.title']) + '<input name="title" required value="' + escapeAttr((task && task.title) || '') + '"></label>'
            + '<label>' + escapeHtml(i18n['ponos.field.description']) + '<textarea name="description" required rows="4">' + escapeHtml((task && task.description) || '') + '</textarea></label>'
            + groupField
            + '<label>' + escapeHtml(i18n['ponos.field.category']) + '<select name="category_id">' + categoryOptions + '</select></label>'
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
        formData.set('group', state.group);
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

        await loadNavigation({ applyPrefs: false });
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
        body.set('group', state.group);
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
        formData.set('group', state.group);
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

    function attachmentUrl(attachmentId, taskId) {
        return apiUrl('download_attachment', {
            group: state.group,
            attachment_id: attachmentId,
            task: taskId || state.task,
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

    function formatDescriptionHtml(value) {
        return escapeHtml(value).replace(/\r\n|\r|\n/g, '<br/>');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/'/g, '&#039;');
    }

    function openNewTaskForm() {
        if (isMyTasksGroup(state.group)) {
            return;
        }
        state.activeTask = { title: '', description: '', checklist: [''] };
        state.editMode = true;
        state.task = '';
        el.detailTitle.textContent = i18n['ponos.btn.new_task'];
        el.detailMeta.textContent = '';
        renderTaskDetail();
        el.detail.classList.add('is-open');
        el.detail.setAttribute('aria-hidden', 'false');
    }

    function showSettingsModal() {
        if (!el.settingsForm) {
            return;
        }
        ['assigned', 'status_changed', 'message', 'checklist', 'daily_reminder'].forEach(function (key) {
            const input = el.settingsForm.querySelector('input[name="' + key + '"]');
            if (input) {
                input.checked = !!state.emailPrefs[key];
            }
        });
        if (el.settingsModal) {
            el.settingsModal.hidden = false;
            el.settingsModal.setAttribute('aria-hidden', 'false');
        }
    }

    function hideSettingsModal() {
        if (el.settingsModal) {
            el.settingsModal.hidden = true;
            el.settingsModal.setAttribute('aria-hidden', 'true');
        }
    }

    async function submitSettingsForm(event) {
        if (event) {
            event.preventDefault();
        }
        const body = new URLSearchParams();
        body.set('action', 'save_email_prefs');
        ['assigned', 'status_changed', 'message', 'checklist', 'daily_reminder'].forEach(function (key) {
            const input = el.settingsForm ? el.settingsForm.querySelector('input[name="' + key + '"]') : null;
            body.set(key, input && input.checked ? '1' : '0');
        });
        const response = await fetch(apiUrl('save_email_prefs'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        state.emailPrefs = data.email_prefs || {};
        hideSettingsModal();
    }

    async function toggleGroupPin() {
        if (!state.group || isMyTasksGroup(state.group)) {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'toggle_group_pin');
        body.set('group', state.group);
        const response = await fetch(apiUrl('toggle_group_pin'), { method: 'POST', body: body, credentials: 'same-origin' });
        const data = await response.json();
        if (!data.ok) {
            showAlert(data.error || i18n['ponos.error.save_failed']);
            return;
        }
        state.pinnedGroups = data.pinned_groups || [];
        if (state.navigation) {
            state.navigation.groups = data.groups || state.navigation.groups;
        }
        updateGroupPinButton();
        renderSidebar();
    }

    async function init() {
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
        if (el.dangerConfirm) {
            el.dangerConfirm.addEventListener('click', function () {
                deleteGroup(true);
            });
        }
        if (el.dangerCancel) {
            el.dangerCancel.addEventListener('click', hideDangerModal);
        }
        if (el.dangerModal) {
            el.dangerModal.addEventListener('click', function (event) {
                if (event.target === el.dangerModal) {
                    hideDangerModal();
                }
            });
        }
        if (el.groupForm) {
            el.groupForm.addEventListener('submit', submitGroupForm);
        }
        if (el.groupCancel) {
            el.groupCancel.addEventListener('click', hideGroupModal);
        }
        if (el.groupModal) {
            el.groupModal.addEventListener('click', function (event) {
                if (event.target === el.groupModal) {
                    hideGroupModal();
                }
            });
        }
        if (el.settingsBtn) {
            el.settingsBtn.addEventListener('click', showSettingsModal);
        }
        if (el.settingsForm) {
            el.settingsForm.addEventListener('submit', submitSettingsForm);
        }
        if (el.settingsCancel) {
            el.settingsCancel.addEventListener('click', hideSettingsModal);
        }
        if (el.settingsModal) {
            el.settingsModal.addEventListener('click', function (event) {
                if (event.target === el.settingsModal) {
                    hideSettingsModal();
                }
            });
        }
        if (el.groupPin) {
            el.groupPin.addEventListener('click', function (event) {
                event.stopPropagation();
                toggleGroupPin();
            });
        }
        if (el.groupAdmin) {
            el.groupAdmin.addEventListener('click', function (event) {
                event.stopPropagation();
                showCategoryAdminModal();
            });
        }
        if (el.adminTabCategories) {
            el.adminTabCategories.addEventListener('click', function () {
                showAdminTab('categories');
            });
        }
        if (el.adminTabAccess) {
            el.adminTabAccess.addEventListener('click', function () {
                showAdminTab('access');
            });
        }
        if (el.accessOpen) {
            el.accessOpen.addEventListener('change', function () {
                saveGroupOpenAccess(el.accessOpen.checked);
            });
        }
        if (el.accessAddMember) {
            el.accessAddMember.addEventListener('click', addGroupMember);
        }
        if (el.categoryAdminClose) {
            el.categoryAdminClose.addEventListener('click', hideCategoryAdminModal);
        }
        if (el.categoryAdd) {
            el.categoryAdd.addEventListener('click', function () {
                showCategoryForm();
            });
        }
        if (el.categoryForm) {
            el.categoryForm.addEventListener('submit', submitCategoryForm);
        }
        if (el.categoryCancel) {
            el.categoryCancel.addEventListener('click', hideCategoryModal);
        }
        if (el.categoryModal) {
            el.categoryModal.addEventListener('click', function (event) {
                if (event.target === el.categoryModal) {
                    hideCategoryModal();
                }
            });
        }
        if (el.categoryAdminModal) {
            el.categoryAdminModal.addEventListener('click', function (event) {
                if (event.target === el.categoryAdminModal) {
                    hideCategoryAdminModal();
                }
            });
        }
        if (el.reminderYes) {
            el.reminderYes.addEventListener('click', function () {
                confirmReminder(false);
            });
        }
        if (el.reminderYesAlways) {
            el.reminderYesAlways.addEventListener('click', function () {
                confirmReminder(true);
            });
        }
        if (el.reminderNo) {
            el.reminderNo.addEventListener('click', hideReminderModal);
        }
        if (el.reminderModal) {
            el.reminderModal.addEventListener('click', function (event) {
                if (event.target === el.reminderModal) {
                    hideReminderModal();
                }
            });
        }
        if (el.archiveClose) {
            el.archiveClose.addEventListener('click', hideArchiveModal);
        }
        if (el.archiveModal) {
            el.archiveModal.addEventListener('click', function (event) {
                if (event.target === el.archiveModal) {
                    hideArchiveModal();
                }
            });
        }
        if (el.archivePrev) {
            el.archivePrev.addEventListener('click', function () {
                if (state.archivePage > 1) {
                    loadArchivePage(state.archivePage - 1);
                }
            });
        }
        if (el.archiveNext) {
            el.archiveNext.addEventListener('click', function () {
                if (state.archivePage < state.archiveTotalPages) {
                    loadArchivePage(state.archivePage + 1);
                }
            });
        }
        if (el.unarchiveTask) {
            el.unarchiveTask.addEventListener('click', unarchiveActiveTask);
        }

        await loadUsers();
        updateUserFooter();
        await loadNavigation({ selectFirstGroup: !state.group });
        if (state.group) {
            await openGroup(state.group, state.task);
        } else {
            await loadTasks();
        }
    }

    init();
})();
