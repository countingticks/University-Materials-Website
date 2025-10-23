// Admin Panel Logic for UTCN Course Platform

// Local helpers for safe HTML/attribute rendering and date formatting
function __escapeHtml(value) {
    const s = String(value == null ? '' : value);
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
function __escapeAttr(value) {
    return __escapeHtml(value).replace(/\n/g, ' ');
}
function __fmtDate(value) {
    const d = new Date(value);
    if (isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('ro-RO').format(d);
}
function __fmtDateTime(value) {
    const d = new Date(value);
    if (isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('ro-RO', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(d);
}

// Global dataset cache and helpers accessible to all handlers
if (!window.__FULL_DATA) {
    window.__FULL_DATA = { users: null, faculties: null, materii: null, courses: null };
}
const __ADMIN_BASE = (function() {
    try {
        const p = window.location.pathname || '/admin';
        return p || '/admin';
    } catch(e) {
        return '/admin';
    }
})();

// Snapshot/restore original server-rendered tbody content
window.snapshotOriginalTbody = function(tbody) {
    if (tbody && !tbody.dataset.originalHtml) {
        tbody.dataset.originalHtml = tbody.innerHTML;
    }
}
window.restoreOriginalTbody = function(tbody) {
    if (tbody && tbody.dataset.originalHtml !== undefined) {
        tbody.innerHTML = tbody.dataset.originalHtml;
    }
}

// Fetchers for full datasets (cached)
window.fetchAllUsers = async function() {
    if (window.__FULL_DATA.users) return window.__FULL_DATA.users;
    const res = await fetch(`${__ADMIN_BASE}?action=get_all_users`);
    const json = await res.json();
    if (json && json.success) {
        window.__FULL_DATA.users = json.users || [];
        return window.__FULL_DATA.users;
    }
    return [];
}
window.fetchAllFaculties = async function() {
    if (window.__FULL_DATA.faculties) return window.__FULL_DATA.faculties;
    const res = await fetch(`${__ADMIN_BASE}?action=get_all_faculties`);
    const json = await res.json();
    if (json && json.success) {
        window.__FULL_DATA.faculties = json.faculties || [];
        return window.__FULL_DATA.faculties;
    }
    return [];
}
window.fetchAllMaterii = async function() {
    if (window.__FULL_DATA.materii) return window.__FULL_DATA.materii;
    const res = await fetch(`${__ADMIN_BASE}?action=get_all_materii`);
    const json = await res.json();
    if (json && json.success) {
        window.__FULL_DATA.materii = json.materii || [];
        return window.__FULL_DATA.materii;
    }
    return [];
}
window.fetchAllCourses = async function() {
    if (window.__FULL_DATA.courses) return window.__FULL_DATA.courses;
    const res = await fetch(`${__ADMIN_BASE}?action=get_all_courses`);
    const json = await res.json();
    if (json && json.success) {
        window.__FULL_DATA.courses = { cursuri: json.cursuri || [], laboratoare: json.laboratoare || [] };
        return window.__FULL_DATA.courses;
    }
    return { cursuri: [], laboratoare: [] };
}

document.addEventListener('DOMContentLoaded', function() {
    // Check if we are on the admin page (could be admin-body or index-body with admin URL)
    const isAdminPage = document.body.classList.contains('admin-body') || 
                       window.location.pathname.includes('/admin') ||
                       document.querySelector('.admin-page') !== null;
    
    if (!isAdminPage) {
        return;
    }

    // Attach snapshots after DOM is ready
    setTimeout(() => {
        ['#users-tab table.users-table tbody',
         '#faculties-tab table.users-table tbody',
         '#materii-tab table.users-table tbody',
         '#cursuri-courses-content table.lecture-table tbody',
         '#laboratoare-courses-content table.lecture-table tbody']
        .forEach(sel => {
            const tb = document.querySelector(sel);
            if (tb) window.snapshotOriginalTbody(tb);
        });
    }, 0);

    // Initialize tabs with delay to ensure DOM is ready
    setTimeout(() => {
        window.initializeTabs();
        // After tabs initialize, rebuild pagers for all tabs once
        setTimeout(() => {
            const pageSize = window.PAGE_SIZE || 8;
            // On initial load, keep server pagers unless filters are pre-filled
            const maybeInit = (containerSel, tbodySel, hasFilters) => {
                const container = document.querySelector(containerSel);
                const tbody = document.querySelector(tbodySel);
                if (!container || !tbody) return;
                setFilteredPagerMode(container, tbody, !!hasFilters, pageSize);
            };
            const usersHasFilters = !!((document.getElementById('usersRoleFilter')?.value)|| (document.getElementById('usersFacultyFilter')?.value) || (document.getElementById('usersSearch')?.value));
            const facHasFilters = !!(document.getElementById('facultiesSearch')?.value);
            const matHasFilters = !!((document.getElementById('materiiFacultyFilter')?.value) || (document.getElementById('materiiYearFilter')?.value) || (document.getElementById('materiiSemFilter')?.value) || (document.getElementById('materiiSearch')?.value));
            const courseHasFilters = !!((document.getElementById('courseScopeFilter')?.value) || (document.getElementById('courseSearch')?.value));
            maybeInit('#users-tab .users-table-container', '#users-tab table.users-table tbody', usersHasFilters);
            maybeInit('#faculties-tab .users-table-container', '#faculties-tab table.users-table tbody', facHasFilters);
            maybeInit('#materii-tab .users-table-container', '#materii-tab table.users-table tbody', matHasFilters);
            maybeInit('#cursuri-courses-content .lecture-table-container', '#cursuri-courses-content table.lecture-table tbody', courseHasFilters);
            maybeInit('#laboratoare-courses-content .lecture-table-container', '#laboratoare-courses-content table.lecture-table tbody', courseHasFilters);
        }, 150);
    }, 100);

    // Prevent default submit (Enter) on Faculties modals and use AJAX instead
    const addFacultyForm = document.getElementById('addFacultyForm');
    if (addFacultyForm) {
        addFacultyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitAddFacultyForm();
        });
    }
    const editFacultyForm = document.getElementById('editFacultyForm');
    if (editFacultyForm) {
        editFacultyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitEditFacultyForm();
        });
    }
});

// ===== Generic client-side pagination (used after filtering) =====
function getFilteredRows(tbody) {
    // Rows eligible for pagination: not hidden by filter
    return Array.from(tbody.querySelectorAll('tr')).filter(r => !r.classList.contains('is-filter-hidden'));
}

function computeCompactPages(totalPages, currentPage) {
    const pages = [1, Math.max(1, currentPage - 1), currentPage, Math.min(totalPages, currentPage + 1), totalPages]
        .filter((v, i, a) => v >= 1 && v <= totalPages && a.indexOf(v) === i)
        .sort((a, b) => a - b);
    return pages;
}

function renderRowsForPage(tbody, datasetRows, pageSize, pageIndex1) {
    const start = (pageIndex1 - 1) * pageSize;
    const end = start + pageSize;
    let shown = 0;
    // First hide all non-filter-hidden rows
    Array.from(tbody.querySelectorAll('tr')).forEach(tr => {
        if (!tr.classList.contains('is-filter-hidden')) {
            tr.style.display = 'none';
        }
    });
    // Then show only the slice for the requested page
    datasetRows.forEach((tr, idx) => {
        const show = idx >= start && idx < end;
        tr.style.display = show ? '' : 'none';
        if (show) shown++;
    });
    return shown;
}

function ensurePager(containerEl) {
    // Only manage our own JS pager; never touch server-rendered pagers
    let pager = containerEl.querySelector('.table-pager[data-js-pager="true"]');
    if (!pager) {
        pager = document.createElement('div');
        pager.className = 'table-pager';
        pager.setAttribute('data-js-pager', 'true');
        // Insert after any existing pager if present
        const existing = containerEl.querySelector('.table-pager');
        if (existing) {
            existing.insertAdjacentElement('afterend', pager);
        } else {
            containerEl.appendChild(pager);
        }
    }
    return pager;
}

function rebuildClientPager(containerEl, tbody, pageSize, currentPage = 1) {
    const datasetRows = getFilteredRows(tbody);
    const total = datasetRows.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));

    // If only one page, remove only our JS pager and exit; keep server pager intact
    if (totalPages <= 1) {
        const existing = containerEl.querySelector('.table-pager[data-js-pager="true"]');
        if (existing) existing.remove();
        return;
    }

    // Clamp currentPage
    let curr = Math.max(1, Math.min(totalPages, currentPage));

    // Render rows for the page
    renderRowsForPage(tbody, datasetRows, pageSize, curr);

    // Build pager UI
    const pager = ensurePager(containerEl);
    pager.innerHTML = '';

    const addBtn = (label, targetPage, disabled = false, isCurrent = false) => {
        const a = document.createElement('button');
        a.className = 'btn-secondary';
        a.textContent = label;
        a.style.fontSize = '0.85rem';
        if (isCurrent) a.setAttribute('aria-current', 'page');
        if (disabled || isCurrent) a.disabled = true;
        a.addEventListener('click', () => {
            rebuildClientPager(containerEl, tbody, pageSize, targetPage);
        });
        pager.appendChild(a);
    };

    const addEllipsis = () => {
        const span = document.createElement('span');
        span.className = 'pager-ellipsis';
        span.textContent = '…';
        pager.appendChild(span);
    };

    addBtn('‹ Anterior', curr - 1, curr === 1, false);
    const pages = computeCompactPages(totalPages, curr);
    let last = 0;
    pages.forEach(p => {
        if (last && p > last + 1) addEllipsis();
        addBtn(String(p), p, false, p === curr);
        last = p;
    });
    addBtn('Următor ›', curr + 1, curr === totalPages, false);
}

// Helpers to switch between server pager (links) and client pager (buttons) when filters are active
function getServerPager(containerEl) {
    return containerEl.querySelector('.table-pager:not([data-js-pager="true"])');
}

function removeJsPager(containerEl) {
    const jp = containerEl.querySelector('.table-pager[data-js-pager="true"]');
    if (jp) jp.remove();
}

function setFilteredPagerMode(containerEl, tbody, isFiltered, pageSize) {
    if (!containerEl || !tbody) return;
    const serverPager = getServerPager(containerEl);
    if (isFiltered) {
        if (serverPager) serverPager.style.display = 'none';
        rebuildClientPager(containerEl, tbody, pageSize, 1);
    } else {
        if (serverPager) serverPager.style.display = '';
        removeJsPager(containerEl);
    }
}

// ===== Faculties CRUD =====
window.openAddFacultyModal = function() {
    const modal = document.getElementById('addFacultyModal');
    if (modal) {
        modal.style.display = 'flex';
        const form = document.getElementById('addFacultyForm');
        if (form) form.reset();

        // Clear previous errors and set up inline validation
        const nameInput = document.getElementById('faculty_name');
        if (nameInput) FormValidator.removeError(nameInput);
        setupFacultyInlineValidation();
    }
}

window.closeAddFacultyModal = function() {
    const modal = document.getElementById('addFacultyModal');
    if (modal) modal.style.display = 'none';
}

window.submitAddFacultyForm = function() {
    const form = document.getElementById('addFacultyForm');
    const nameInput = document.getElementById('faculty_name');

    // Inline validation
    if (nameInput) FormValidator.removeError(nameInput);
    if (!FormValidator.isRequired(nameInput.value)) {
        FormValidator.addError(nameInput, 'Numele facultății este obligatoriu.');
        nameInput.focus();
        return;
    }
    const formData = new FormData(form);
    formData.append('action', 'add_faculty');
    fetch('/admin', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeAddFacultyModal();
                // Stay on faculties tab
                localStorage.setItem('activeAdminTab', 'faculties');
                window.location.reload();
            } else {
                Utils.showNotification(data.message || 'Eroare la adăugare facultate', 'error');
            }
        })
        .catch(() => Utils.showNotification('Eroare la adăugare facultate', 'error'));
}

window.editFaculty = function(id, name) {
    document.getElementById('edit_faculty_id_hidden').value = id;
    document.getElementById('edit_faculty_name').value = name;
    // Clear previous errors and set up inline validation
    const nameInput = document.getElementById('edit_faculty_name');
    if (nameInput) FormValidator.removeError(nameInput);
    setupFacultyInlineValidation();
    const modal = document.getElementById('editFacultyModal');
    if (modal) modal.style.display = 'flex';
}

window.closeEditFacultyModal = function() {
    const modal = document.getElementById('editFacultyModal');
    if (modal) modal.style.display = 'none';
}

window.submitEditFacultyForm = function() {
    const form = document.getElementById('editFacultyForm');
    const nameInput = document.getElementById('edit_faculty_name');
    // Inline validation
    if (nameInput) FormValidator.removeError(nameInput);
    if (!FormValidator.isRequired(nameInput.value)) {
        FormValidator.addError(nameInput, 'Numele facultății este obligatoriu.');
        nameInput.focus();
        return;
    }
    const formData = new FormData(form);
    formData.append('action', 'edit_faculty');
    
    fetch('/admin', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Utils.showNotification(data.message || 'Facultatea a fost actualizată', 'success');
                closeEditFacultyModal();
                // Stay on faculties tab
                localStorage.setItem('activeAdminTab', 'faculties');
                window.location.reload();
            } else {
                Utils.showNotification(data.message || 'Eroare la modificare facultate', 'error');
                // Keep modal open for correction
            }
        })
        .catch(error => {
            console.error('Edit Faculty Error:', error);
            Utils.showNotification('Eroare la modificare facultate', 'error');
        });
}

window.deleteFaculty = function(id, name) {
    window.showDeleteConfirmation(name, () => {
        const formData = new FormData();
        formData.append('action', 'delete_faculty');
        formData.append('id', id);
        fetch('/admin', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Stay on faculties tab
                    localStorage.setItem('activeAdminTab', 'faculties');
                    window.location.reload();
                } else {
                    Utils.showNotification(data.message || 'Eroare la ștergere facultate', 'error');
                }
            })
            .catch(() => Utils.showNotification('Eroare la ștergere facultate', 'error'));
    });
}

/**
 * Initializes the tab system.
 * Sets the active tab based on localStorage or defaults to 'users'.
 */
window.initializeTabs = function() {
    // Check if tab elements exist
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    if (tabButtons.length === 0 || tabContents.length === 0) {
        setTimeout(() => window.initializeTabs(), 200);
        return;
    }
    
    const savedTab = localStorage.getItem('activeAdminTab') || 'users';
    window.switchTab(savedTab);
}

/**
 * Switches the active tab and updates the UI accordingly.
 * @param {string} tabName - The name of the tab to switch to ('users' or 'courses').
 */
window.switchTab = function(tabName) {
    const tabContents = document.querySelectorAll('.tab-content');
    const tabButtons = document.querySelectorAll('.tab-btn');

    // Hide all tab content
    tabContents.forEach(content => content.classList.remove('active'));

    // Deactivate all tab buttons
    tabButtons.forEach(button => button.classList.remove('active'));

    // Show the selected tab content
    const selectedTabContent = document.getElementById(tabName + '-tab');
    if (selectedTabContent) {
        selectedTabContent.classList.add('active');
    } else {
        // Fallback to users tab if target tab not found
        if (tabName !== 'users') {
            return window.switchTab('users');
        }
    }

    // Highlight the selected tab button
    const selectedTabButton = document.querySelector(`.tab-btn[onclick="switchTab('${tabName}')"]`);
    if (selectedTabButton) {
        selectedTabButton.classList.add('active');
    }

    // Store the active tab
    localStorage.setItem('activeAdminTab', tabName);

    // Rebuild client-side pagination for the active tab
    const pageSize = window.PAGE_SIZE || 8;
    if (tabName === 'users') {
        const container = document.querySelector('#users-tab .users-table-container');
        const tbody = document.querySelector('#users-tab table.users-table tbody');
        if (container && tbody) rebuildClientPager(container, tbody, pageSize, 1);
    } else if (tabName === 'faculties') {
        const container = document.querySelector('#faculties-tab .users-table-container');
        const tbody = document.querySelector('#faculties-tab table.users-table tbody');
        if (container && tbody) rebuildClientPager(container, tbody, pageSize, 1);
    } else if (tabName === 'materii') {
        const container = document.querySelector('#materii-tab .users-table-container');
        const tbody = document.querySelector('#materii-tab table.users-table tbody');
        if (container && tbody) rebuildClientPager(container, tbody, pageSize, 1);
    } else if (tabName === 'courses') {
        const containerC = document.querySelector('#cursuri-courses-content .lecture-table-container');
        const tbodyC = document.querySelector('#cursuri-courses-content table.lecture-table tbody');
        if (containerC && tbodyC) rebuildClientPager(containerC, tbodyC, pageSize, 1);
        const containerL = document.querySelector('#laboratoare-courses-content .lecture-table-container');
        const tbodyL = document.querySelector('#laboratoare-courses-content table.lecture-table tbody');
        if (containerL && tbodyL) rebuildClientPager(containerL, tbodyL, pageSize, 1);

        // Restore last active course subtab (default to 'cursuri')
        try {
            const savedCourseSubtab = localStorage.getItem('activeCourseSubtab') || 'cursuri';
            if (typeof window.switchCourseSubtab === 'function') {
                window.switchCourseSubtab(savedCourseSubtab);
            }
        } catch (e) { /* no-op */ }
    }
}

/**
 * Switches the active course subtab in the courses management section.
 * @param {string} subtabName - The name of the subtab to switch to ('cursuri' or 'laboratoare').
 */
window.switchCourseSubtab = function(subtabName) {
    // Remove active class from all course subtab buttons
    const allCourseSubtabBtns = document.querySelectorAll('.course-subtab-btn');
    allCourseSubtabBtns.forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Add active class to clicked button
    const activeBtn = document.querySelector(`.course-subtab-btn[data-tab="${subtabName}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    } else {
        console.error(`Course subtab button for '${subtabName}' not found.`);
    }
    
    // Hide all course subtab content
    const allCourseSubtabContent = document.querySelectorAll('.course-subtab-content');
    allCourseSubtabContent.forEach(content => {
        content.classList.remove('active');
    });
    
    // Show selected course subtab content
    const activeContent = document.getElementById(`${subtabName}-courses-content`);
    if (activeContent) {
        activeContent.classList.add('active');
    } else {
        console.error(`Course subtab content for '${subtabName}' not found.`);
    }
    
    console.log(`Switched to course subtab: ${subtabName}`);
    // When switching subtabs, if filters are active, keep JS pager; otherwise show server pager
    const scopeFilter = document.getElementById('courseScopeFilter');
    const courseSearch = document.getElementById('courseSearch');
    const scopeValue = scopeFilter ? scopeFilter.value : '';
    const searchValue = courseSearch ? courseSearch.value : '';
    const hasFilters = (scopeValue && scopeValue.length) || (searchValue && searchValue.length);
    const pageSize = window.PAGE_SIZE || 8;
    const container = document.querySelector(`#${subtabName}-courses-content .lecture-table-container`);
    const tbody = document.querySelector(`#${subtabName}-courses-content table.lecture-table tbody`);
    if (container && tbody) setFilteredPagerMode(container, tbody, !!hasFilters, pageSize);

    // Persist selected course subtab so it survives reloads
    try { localStorage.setItem('activeCourseSubtab', subtabName); } catch(e) { /* ignore */ }
}

// Basic filtering for courses tables (client-side)
window.filterCourses = function() {
    const scopeSelect = document.getElementById('courseScopeFilter');
    const searchInput = document.getElementById('courseSearch');
    const scopeValue = scopeSelect ? scopeSelect.value : '';
    const query = (searchInput ? searchInput.value : '').trim().toLowerCase();

    const applyFilterToTable = (tableRootSelector) => {
        const table = document.querySelector(`${tableRootSelector} table.lecture-table`);
        if (!table) return;
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        let visible = 0;
        rows.forEach(row => {
            const titleCell = row.querySelector('.lecture-title-cell');
            const materiiCell = row.querySelector('[data-label="Materii"]');
            const titleText = titleCell ? titleCell.textContent.toLowerCase() : '';
            const materiiText = materiiCell ? materiiCell.textContent.toLowerCase() : '';

            let matchesScope = true;
            if (scopeValue && scopeValue.startsWith('materie::')) {
                const mat = scopeValue.substring('materie::'.length).toLowerCase();
                matchesScope = materiiText.includes(mat);
            }

            const matchesSearch = !query || titleText.includes(query) || materiiText.includes(query);
            const show = (matchesScope && matchesSearch);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        // Update server-like pagination indicator by slicing visible rows to PAGE_SIZE
        const pageSize = window.PAGE_SIZE || 8;
        const pager = table.closest('.lecture-table-container').querySelector('.table-pager');
        if (pager) {
            const totalPages = Math.max(1, Math.ceil(visible / pageSize));
            // Update middle info span if present; rebuild simple numbers if needed
            const infoSpan = pager.querySelector('span');
            // naive approach: if there is a middle span with 'Pagina', update it; otherwise leave server pager (numbers) as-is
            if (infoSpan && /Pagina\s+\d+\s+din\s+\d+/.test(infoSpan.textContent)) {
                const currentPage = 1; // client-side can't know server page; default to 1 after filtering
                infoSpan.textContent = `Pagina ${currentPage} din ${totalPages}`;
            }
        }
    };

    applyFilterToTable('#cursuri-courses-content');
    applyFilterToTable('#laboratoare-courses-content');

    const containerC = document.querySelector('#cursuri-courses-content .lecture-table-container');
    const tbodyC = document.querySelector('#cursuri-courses-content table.lecture-table tbody');
    const containerL = document.querySelector('#laboratoare-courses-content .lecture-table-container');
    const tbodyL = document.querySelector('#laboratoare-courses-content table.lecture-table tbody');
    const pageSize = window.PAGE_SIZE || 8;
    if (containerC && tbodyC) rebuildClientPager(containerC, tbodyC, pageSize, 1);
    if (containerL && tbodyL) rebuildClientPager(containerL, tbodyL, pageSize, 1);
};

window.clearFilters = function() {
    const scopeSelect = document.getElementById('courseScopeFilter');
    const searchInput = document.getElementById('courseSearch');
    if (scopeSelect) scopeSelect.value = '';
    if (searchInput) searchInput.value = '';

    const resetTable = (rootSel) => {
        const table = document.querySelector(`${rootSel} table.lecture-table`);
        if (!table) return;
        Array.from(table.querySelectorAll('tbody tr')).forEach(row => {
            row.style.display = '';
        });
    };

    resetTable('#cursuri-courses-content');
    resetTable('#laboratoare-courses-content');

    if (typeof setupPaginationForTables === 'function') {
        setupPaginationForTables();
    }
};

// Users filtering
window.filterUsers = function() {
    const roleSel = document.getElementById('usersRoleFilter');
    const facSel = document.getElementById('usersFacultyFilter');
    const searchInput = document.getElementById('usersSearch');
    const role = roleSel ? roleSel.value : '';
    const facultyId = facSel ? facSel.value : '';
    const query = (searchInput ? searchInput.value : '').trim().toLowerCase();

    const table = document.querySelector('#users-tab table.users-table');
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const hasFilters = (role && role.length) || (facultyId && facultyId.length) || (query && query.length);
    if (hasFilters) {
        // Use full dataset to filter across all pages
        fetchAllUsers().then(allUsers => {
            const selectedFacName = (facSel && facSel.options[facSel.selectedIndex] ? facSel.options[facSel.selectedIndex].text : '').toLowerCase();
            const filtered = allUsers.filter(u => {
                if (role && u.role !== role) return false;
                if (facultyId && selectedFacName && u.faculty_name) {
                    if (!String(u.faculty_name).toLowerCase().includes(selectedFacName)) return false;
                }
                if (query && !String(u.username).toLowerCase().includes(query)) return false;
                return true;
            });
            // Render rows
            restoreOriginalTbody(tbody); // ensure we start from a clean structure
            tbody.innerHTML = filtered.map(u => `
                <tr>
                    <td class="user-name-cell" data-label="Nume"><span>${__escapeHtml(u.username)}</span></td>
                    <td data-label="Rol"><span class="user-role ${u.role}">${u.role === 'admin' ? 'Administrator' : 'Student'}</span></td>
                    <td data-label="Facultate">${__escapeHtml(u.role === 'admin' ? '-' : (u.faculty_name || '-'))}</td>
                    <td data-label="Data Înregistrării">${u.created_at ? __fmtDate(u.created_at) : ''}</td>
                    <td data-label="Acțiuni">
                        <div class="user-actions">
                            <button class="btn-action btn-edit" onclick="editUser('${u.id}', '${__escapeAttr(u.username)}', '${u.role}', '${__escapeAttr(u.faculty_id || '')}', '${u.is_super_admin ? '1' : '0'}')">Editează</button>
                            ${u.is_super_admin ? '' : `<button class=\"btn-action btn-delete\" onclick=\"deleteUser('${u.id}', '${__escapeAttr(u.username)}')\">Șterge</button>`}
                        </div>
                    </td>
                </tr>
            `).join('');
            // Mark all rows as not filtered, paginate via JS pager
            Array.from(tbody.querySelectorAll('tr')).forEach(tr => tr.classList.remove('is-filter-hidden'));
            const container = document.querySelector('#users-tab .users-table-container');
            const pageSize = window.PAGE_SIZE || 8;
            setFilteredPagerMode(container, tbody, true, pageSize);
        });
    } else {
        // No filters: show original server-rendered rows
        restoreOriginalTbody(tbody);
        Array.from(tbody.querySelectorAll('tr')).forEach(row => { row.style.display = ''; row.classList.remove('is-filter-hidden'); });
        const container = document.querySelector('#users-tab .users-table-container');
        const pageSize = window.PAGE_SIZE || 8;
        setFilteredPagerMode(container, tbody, false, pageSize);
    }

    // Toggle server vs client pager based on filter state and rebuild
    // Pager mode handled in render branches above
};

window.clearUsersFilters = function() {
    const roleSel = document.getElementById('usersRoleFilter');
    const facSel = document.getElementById('usersFacultyFilter');
    const searchInput = document.getElementById('usersSearch');
    if (roleSel) roleSel.value = '';
    if (facSel) facSel.value = '';
    if (searchInput) searchInput.value = '';
    // Show all rows
    const table = document.querySelector('#users-tab table.users-table');
    if (table) Array.from(table.querySelectorAll('tbody tr')).forEach(row => row.style.display = '');
    // Restore server pager
    const container = document.querySelector('#users-tab .users-table-container');
    const tbody = document.querySelector('#users-tab table.users-table tbody');
    const pageSize = window.PAGE_SIZE || 8;
    if (container && tbody) setFilteredPagerMode(container, tbody, false, pageSize);
};

// Faculties filtering
window.filterFaculties = function() {
    const searchInput = document.getElementById('facultiesSearch');
    const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
    const table = document.querySelector('#faculties-tab table.users-table');
    if (!table) return;
    let visible = 0;
    Array.from(table.querySelectorAll('tbody tr')).forEach(row => {
        const nameCell = row.querySelector('[data-label="Nume Facultate"]');
        const nameText = nameCell ? nameCell.textContent.toLowerCase() : '';
        const show = !q || nameText.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const container = document.querySelector('#faculties-tab .users-table-container');
    const tbody = document.querySelector('#faculties-tab table.users-table tbody');
    const pageSize = window.PAGE_SIZE || 8;
    const hasFilters = q && q.length;
    if (container && tbody) setFilteredPagerMode(container, tbody, !!hasFilters, pageSize);
};

// Utils is now available from global.js - no need to redeclare

/**
 * Opens a modal for adding a new user.
 */
window.openAddUserModal = function() {
    const modal = document.getElementById('addUserModal');
    if (modal) {
        modal.style.display = 'flex';
        // Reset form
        document.getElementById('addUserForm').reset();
        // Initialize faculty visibility based on default role
        const roleSelect = document.getElementById('role');
        const facultyGroup = document.getElementById('add_faculty_group');
        if (roleSelect && facultyGroup) {
            facultyGroup.style.display = roleSelect.value === 'student' ? '' : 'none';
        }

        // Clear previous errors
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const passwordConfirmInput = document.getElementById('password_confirm');
        const facultySelect = document.getElementById('faculty_id');
        [usernameInput, passwordInput, passwordConfirmInput, roleSelect, facultySelect].forEach(el => {
            if (el) FormValidator.removeError(el);
        });
        // Setup inline validation for user forms
        setupUserInlineValidation();
    }
}

/**
 * Closes the add user modal.
 */
window.closeAddUserModal = function() {
    const modal = document.getElementById('addUserModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Submits the add user form via AJAX.
 */
window.submitAddUserForm = function() {
    const form = document.getElementById('addUserForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const roleSelect = document.getElementById('role');
    const facultySelect = document.getElementById('faculty_id');

    // Clear previous errors
    [usernameInput, passwordInput, passwordConfirmInput, roleSelect, facultySelect].forEach(el => {
        if (el) FormValidator.removeError(el);
    });

    let hasErrors = false;
    // Username required
    if (!FormValidator.isRequired(usernameInput.value)) {
        FormValidator.addError(usernameInput, 'Numele de utilizator este obligatoriu.');
        hasErrors = true;
    }
    // Password rules
    if (!FormValidator.isRequired(passwordInput.value)) {
        FormValidator.addError(passwordInput, 'Parola este obligatorie.');
        hasErrors = true;
    } else if (!FormValidator.minLength(passwordInput.value, 6)) {
        FormValidator.addError(passwordInput, 'Parola trebuie să aibă cel puțin 6 caractere.');
        hasErrors = true;
    }
    // Password confirm
    if (!FormValidator.isRequired(passwordConfirmInput.value)) {
        FormValidator.addError(passwordConfirmInput, 'Confirmarea parolei este obligatorie.');
        hasErrors = true;
    } else if (passwordInput.value !== passwordConfirmInput.value) {
        FormValidator.addError(passwordConfirmInput, 'Parolele nu se potrivesc.');
        hasErrors = true;
    }
    // Role required
    if (!FormValidator.isRequired(roleSelect.value)) {
        FormValidator.addError(roleSelect, 'Vă rugăm să selectați un rol.');
        hasErrors = true;
    }
    // Faculty if student
    if (roleSelect.value === 'student' && !FormValidator.isRequired(facultySelect.value)) {
        FormValidator.addError(facultySelect, 'Vă rugăm să selectați o facultate pentru student.');
        hasErrors = true;
    }

    if (hasErrors) {
        // Focus first error
        const firstError = [usernameInput, passwordInput, passwordConfirmInput, roleSelect, facultySelect].find(el => el && el.classList.contains('error'));
        if (firstError) firstError.focus();
        return;
    }

    const password = passwordInput.value;
    const passwordConfirm = passwordConfirmInput.value;
    
    // Validate password confirmation
    if (password !== passwordConfirm) {
        Utils.showNotification('Parolele nu se potrivesc', 'error');
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'add_user');
    
    // Disable submit button to prevent double submission
    const submitButton = document.querySelector('.btn-primary');
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Se adaugă...';
    submitButton.disabled = true;
    
    fetch('/admin', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Utils.showNotification(data.message, 'success');
            window.closeAddUserModal();
            // Stay on current tab
            localStorage.setItem('activeAdminTab', 'users');
            // Reload the page to show the new user
            window.location.reload();
        } else {
            Utils.showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Utils.showNotification('Eroare la adăugarea utilizatorului', 'error');
    })
    .finally(() => {
        // Re-enable submit button
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    });
}

// Toggle faculty field visibility on role change (Add User)
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'role') {
        const facultyGroup = document.getElementById('add_faculty_group');
        const isStudent = e.target.value === 'student';
        if (facultyGroup) facultyGroup.style.display = isStudent ? '' : 'none';
        if (!isStudent) {
            // Clear field when switching to admin
            const facultySelect = document.getElementById('faculty_id');
            if (facultySelect) facultySelect.value = '';
        }
    }
});

/**
 * Opens the edit user modal and populates it with user data.
 * @param {string} userId - The ID of the user to edit.
 * @param {string} username - The username of the user.
 * @param {string} role - The role of the user.
 * @param {string} facultyId - The faculty ID of the user.
 * @param {string} isSuperAdmin - Whether the user is a super admin ('1' or '0').
 */
window.editUser = function(userId, username, role, facultyId, isSuperAdmin) {
    // Populate the edit form with current user data
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_faculty_id').value = facultyId || '';
    // Initialize faculty visibility for edit modal
    const editFacultyGroup = document.getElementById('edit_faculty_group');
    if (editFacultyGroup) editFacultyGroup.style.display = role === 'student' ? '' : 'none';
    document.getElementById('edit_password').value = ''; // Clear password field
    document.getElementById('edit_password_confirm').value = ''; // Clear password confirmation field
    
    // Check if this is the current admin user trying to edit their own account
    const roleSelect = document.getElementById('edit_role');
    const usernameInput = document.getElementById('edit_username');
    const currentUserId = document.body.dataset.currentUserId;
    const editorIsSuperAdmin = document.body.dataset.currentUserIsDeleteProtected === '1';
    const targetIsSuperAdmin = isSuperAdmin === '1';
    
    if (role === 'admin') {
        // Role change rules:
        // - Own role cannot be changed
        // - Other admin's role can only be changed if editor is super admin and target is not super admin
        const isSelf = userId === currentUserId;
        const canChangeAdminRole = !isSelf && editorIsSuperAdmin && !targetIsSuperAdmin;
        roleSelect.disabled = !canChangeAdminRole;
        roleSelect.style.background = canChangeAdminRole ? '' : '#f5f5f5';
        roleSelect.style.cursor = canChangeAdminRole ? '' : 'not-allowed';

        // Username change rules:
        // - Own username always editable
        // - Other admin's username editable only if editor is super admin and target is not super admin
        const canEditAdminUsername = isSelf || (editorIsSuperAdmin && !targetIsSuperAdmin);
        usernameInput.disabled = !canEditAdminUsername;
        usernameInput.style.background = canEditAdminUsername ? '' : '#f5f5f5';
        usernameInput.style.cursor = canEditAdminUsername ? '' : 'not-allowed';

        // Password change rules:
        // - Super admins can change their own passwords
        // - Other users cannot change super admin passwords
        const canChangeAdminPassword = !targetIsSuperAdmin || isSelf;
        const passwordInput = document.getElementById('edit_password');
        const passwordConfirmInput = document.getElementById('edit_password_confirm');
        
        if (passwordInput) {
            passwordInput.disabled = !canChangeAdminPassword;
            passwordInput.style.background = canChangeAdminPassword ? '' : '#f5f5f5';
            passwordInput.style.cursor = canChangeAdminPassword ? '' : 'not-allowed';
            if (!canChangeAdminPassword) {
                passwordInput.placeholder = 'Doar proprietarul poate modifica această parolă';
            } else {
                passwordInput.placeholder = 'Lasă gol pentru a păstra parola actuală';
            }
        }
        
        if (passwordConfirmInput) {
            passwordConfirmInput.disabled = !canChangeAdminPassword;
            passwordConfirmInput.style.background = canChangeAdminPassword ? '' : '#f5f5f5';
            passwordConfirmInput.style.cursor = canChangeAdminPassword ? '' : 'not-allowed';
            if (!canChangeAdminPassword) {
                passwordConfirmInput.placeholder = 'Doar proprietarul poate modifica această parolă';
            } else {
                passwordConfirmInput.placeholder = 'Confirmă noua parolă';
            }
        }
    } else {
        // Enable role selection for other users
        roleSelect.disabled = false;
        roleSelect.style.background = '';
        roleSelect.style.cursor = '';
        
        // Enable username editing for non-admin users
        usernameInput.disabled = false;
        usernameInput.style.background = '';
        usernameInput.style.cursor = '';
        
        // Enable password editing for non-admin users
        const passwordInput = document.getElementById('edit_password');
        const passwordConfirmInput = document.getElementById('edit_password_confirm');
        
        if (passwordInput) {
            passwordInput.disabled = false;
            passwordInput.style.background = '';
            passwordInput.style.cursor = '';
            passwordInput.placeholder = 'Lăsați gol pentru a păstra parola actuală';
        }
        
        if (passwordConfirmInput) {
            passwordConfirmInput.disabled = false;
            passwordConfirmInput.style.background = '';
            passwordConfirmInput.style.cursor = '';
            passwordConfirmInput.placeholder = 'Confirmați parola nouă';
        }
        
        // Hide role notice if it exists
        const notice = document.getElementById('admin-role-notice');
        if (notice) {
            notice.style.display = 'none';
        }
    }

    // Attach change handler for edit role select to toggle faculty field
    roleSelect.onchange = function() {
        const isStudent = this.value === 'student';
        const ef = document.getElementById('edit_faculty_group');
        if (ef) ef.style.display = isStudent ? '' : 'none';
        if (!isStudent) {
            const facultySelect = document.getElementById('edit_faculty_id');
            if (facultySelect) facultySelect.value = '';
        }
    };
    
    // Show the modal
    const modal = document.getElementById('editUserModal');
    if (modal) {
        modal.style.display = 'flex';
    }

    // Clear previous errors and set up inline validation
    const usernameInput2 = document.getElementById('edit_username');
    const passwordInput2 = document.getElementById('edit_password');
    const passwordConfirmInput2 = document.getElementById('edit_password_confirm');
    const roleSelect2 = document.getElementById('edit_role');
    const facultySelect2 = document.getElementById('edit_faculty_id');
    [usernameInput2, passwordInput2, passwordConfirmInput2, roleSelect2, facultySelect2].forEach(el => {
        if (el) FormValidator.removeError(el);
    });
    setupUserInlineValidation(true);
}

/**
 * Closes the edit user modal.
 */
window.closeEditUserModal = function() {
    const modal = document.getElementById('editUserModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Reset form
    document.getElementById('editUserForm').reset();
    
    // Reset notices to default state
    const roleNotice = document.getElementById('admin-role-notice');
    const roleSelect = document.getElementById('edit_role');
    
    if (roleNotice) {
        roleNotice.style.display = 'none';
    }
    
    if (roleSelect) {
        roleSelect.disabled = false;
        roleSelect.style.background = '';
        roleSelect.style.cursor = '';
    }
}

/**
 * Submits the edit user form via AJAX.
 */
window.submitEditUserForm = function() {
    const form = document.getElementById('editUserForm');
    const usernameInput = document.getElementById('edit_username');
    const passwordInput = document.getElementById('edit_password');
    const passwordConfirmInput = document.getElementById('edit_password_confirm');
    const roleSelect = document.getElementById('edit_role');
    const facultySelect = document.getElementById('edit_faculty_id');

    [usernameInput, passwordInput, passwordConfirmInput, roleSelect, facultySelect].forEach(el => {
        if (el) FormValidator.removeError(el);
    });

    let hasErrors = false;
    if (!usernameInput.disabled && !FormValidator.isRequired(usernameInput.value)) {
        FormValidator.addError(usernameInput, 'Numele de utilizator este obligatoriu.');
        hasErrors = true;
    }
    if (passwordInput.value && !FormValidator.minLength(passwordInput.value, 6)) {
        FormValidator.addError(passwordInput, 'Parola trebuie să aibă cel puțin 6 caractere.');
        hasErrors = true;
    }
    if (passwordInput.value && passwordInput.value !== passwordConfirmInput.value) {
        FormValidator.addError(passwordConfirmInput, 'Parolele nu se potrivesc.');
        hasErrors = true;
    }
    if (!roleSelect.disabled && !FormValidator.isRequired(roleSelect.value)) {
        FormValidator.addError(roleSelect, 'Vă rugăm să selectați un rol.');
        hasErrors = true;
    }
    if (roleSelect.value === 'student' && !FormValidator.isRequired(facultySelect.value)) {
        FormValidator.addError(facultySelect, 'Vă rugăm să selectați o facultate pentru student.');
        hasErrors = true;
    }
    if (hasErrors) {
        const firstError = [usernameInput, passwordInput, passwordConfirmInput, roleSelect, facultySelect].find(el => el && el.classList.contains('error'));
        if (firstError) firstError.focus();
        return;
    }

    const password = passwordInput.value;
    const passwordConfirm = passwordConfirmInput.value;
    
    // Validate password confirmation if password is being changed
    if (password && password !== passwordConfirm) {
        Utils.showNotification('Parolele nu se potrivesc', 'error');
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'edit_user');
    
    // Disable submit button to prevent double submission
    const submitButton = document.querySelector('#editUserModal .btn-primary');
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Se salvează...';
    submitButton.disabled = true;
    
    fetch('/admin', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Utils.showNotification(data.message, 'success');
            window.closeEditUserModal();
            // Stay on current tab
            localStorage.setItem('activeAdminTab', 'users');
            // Reload the page to show the updated user
            window.location.reload();
        } else {
            Utils.showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Utils.showNotification('Eroare la modificarea utilizatorului', 'error');
    })
    .finally(() => {
        // Re-enable submit button
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    });
}

/**
 * Shows a nice confirmation dialog for delete actions
 * @param {string} username - The username to display in the confirmation
 * @param {function} onConfirm - Callback function to execute if user confirms
 */
window.showDeleteConfirmation = function(username, onConfirm) {
    // Create modal backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'delete-modal-backdrop';
    backdrop.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    // Create modal content
    const modal = document.createElement('div');
    modal.className = 'delete-modal';
    modal.style.cssText = `
        background: white;
        border-radius: 12px;
        padding: 0;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        transform: scale(0.7);
        transition: transform 0.3s ease;
    `;
    
    modal.innerHTML = `
        <div style="padding: 24px; text-align: center;">
            <div style="width: 60px; height: 60px; background: #fee2e2; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;">
                <svg width="50" height="50" fill="#dc2626" stroke="#dc2626" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01"/>
                </svg>
            </div>
            <h3 style="margin: 0 0 8px; color: #111827; font-size: 18px; font-weight: 600;">
                Confirmare Ștergere
            </h3>
            <p style="margin: 0 0 24px; color: #6b7280; font-size: 14px; line-height: 1.5;">
                Sunteți sigur că doriți să ștergeți utilizatorul<br>
                <strong style="color: #111827;">"${username}"</strong>?<br>
                <span style="color: #ef4444; font-size: 12px;">Această acțiune nu poate fi anulată.</span>
            </p>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button class="cancel-btn" style="
                    background: #f3f4f6;
                    color: #374151;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background 0.2s;
                ">
                    Anulează
                </button>
                <button class="confirm-btn" style="
                    background: #dc2626;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background 0.2s;
                ">
                    Da, Șterge
                </button>
            </div>
        </div>
    `;
    
    // Add hover effects
    const cancelBtn = modal.querySelector('.cancel-btn');
    const confirmBtn = modal.querySelector('.confirm-btn');
    
    cancelBtn.addEventListener('mouseenter', () => {
        cancelBtn.style.background = '#e5e7eb';
    });
    cancelBtn.addEventListener('mouseleave', () => {
        cancelBtn.style.background = '#f3f4f6';
    });
    
    confirmBtn.addEventListener('mouseenter', () => {
        confirmBtn.style.background = '#b91c1c';
    });
    confirmBtn.addEventListener('mouseleave', () => {
        confirmBtn.style.background = '#dc2626';
    });
    
    // Add event handlers
    function closeModal() {
        backdrop.style.opacity = '0';
        modal.style.transform = 'scale(0.7)';
        setTimeout(() => {
            document.body.removeChild(backdrop);
        }, 300);
    }
    
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) closeModal();
    });
    
    confirmBtn.addEventListener('click', () => {
        closeModal();
        onConfirm();
    });
    
    // Handle ESC key
    function handleEscape(e) {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', handleEscape);
        }
    }
    document.addEventListener('keydown', handleEscape);
    
    // Add to DOM and animate in
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);
    
    // Trigger animation
    requestAnimationFrame(() => {
        backdrop.style.opacity = '1';
        modal.style.transform = 'scale(1)';
    });
}

/**
 * Deletes a user via AJAX.
 * @param {string} userId - The ID of the user to delete.
 * @param {string} username - The username of the user to delete.
 */
window.deleteUser = function(userId, username) {
    window.showDeleteConfirmation(username, () => {
        // Show loading state
        Utils.showNotification('Se șterge utilizatorul...', 'info');
        
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);
        
        fetch('/admin', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Utils.showNotification(data.message, 'success');
                // Stay on current tab
                localStorage.setItem('activeAdminTab', 'users');
                // Reload the page to update the user list
                setTimeout(() => {
                window.location.reload();
                }, 1000);
            } else {
                Utils.showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Utils.showNotification('Eroare la ștergerea utilizatorului', 'error');
        });
    });
}

/**
 * Opens a modal for adding a new lecture/course.
 */
window.openAddLectureModal = function() {
    Utils.showNotification('Opening add lecture modal...', 'info');
}

/**
 * "Views" a lecture.
 * @param {number} lectureId - The ID of the lecture to view.
 */
window.viewLecture = function(lectureId) {
    Utils.showNotification(`Viewing lecture with ID: ${lectureId}`, 'info');
}

/**
 * "Edits" a lecture.
 * @param {number} lectureId - The ID of the lecture to edit.
 */
window.editLecture = function(lectureId) {
    Utils.showNotification(`Editing lecture with ID: ${lectureId}`, 'info');
}

/**
 * "Deletes" a lecture.
 * @param {number} lectureId - The ID of the lecture to delete.
 */
window.deleteLecture = function(lectureId) {
    if (confirm('Are you sure you want to delete this lecture?')) {
        Utils.showNotification(`Deleting lecture with ID: ${lectureId}`, 'success');
    }
}

// Admin dashboard functionality - moved to DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    if (!document.body.classList.contains('admin-body')) {
        return;
    }
    
    const adminCards = document.querySelectorAll('.admin-card');
    const systemInfo = document.querySelector('.admin-card');

    // Add interactive features to admin cards
    adminCards.forEach(card => {
        // Add click animation
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });

        // Add keyboard navigation
        card.setAttribute('tabindex', '0');
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                this.click();
            }
        });
    });

    // Real-time clock update
    function updateClock() {
        const clockElements = document.querySelectorAll('.live-time');
        clockElements.forEach(element => {
            element.textContent = Utils.formatTime(new Date());
        });
    }

    // Update clock every second
    setInterval(updateClock, 1000);

    // Add live time to login time display
    const allListItems = document.querySelectorAll('li');
    allListItems.forEach(li => {
        if (li.textContent.includes('Timp Conectare')) {
            const timeText = li.textContent;
            li.innerHTML = timeText.replace(
                /(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2})/,
                '$1 <span class="live-time">' + Utils.formatTime(new Date()) + '</span>'
            );
        }
    });

    // System information refresh
    function refreshSystemInfo() {
        // This would typically make an AJAX call to get updated system info
        console.log('Refreshing system information...');
        
        // Simulate loading
        const systemCard = document.querySelector('.admin-card');
        if (systemCard) {
            LoadingStates.show(systemCard);
            
            setTimeout(() => {
                LoadingStates.hide(systemCard);
                Utils.showNotification('Informațiile sistemului au fost actualizate', 'success');
            }, 1000);
        }
    }

    // Add refresh button to system info card
    const systemInfoCard = document.querySelector('.admin-card h3');
    if (systemInfoCard && systemInfoCard.textContent.includes('Informații Sistem')) {
        const refreshBtn = document.createElement('button');
        refreshBtn.innerHTML = '🔄';
        refreshBtn.title = 'Actualizează informațiile sistemului';
        refreshBtn.style.marginLeft = '10px';
        refreshBtn.style.border = 'none';
        refreshBtn.style.background = 'none';
        refreshBtn.style.cursor = 'pointer';
        refreshBtn.style.fontSize = '1rem';
        refreshBtn.addEventListener('click', refreshSystemInfo);
        
        systemInfoCard.appendChild(refreshBtn);
    }

    // Quick actions functionality
    const quickActions = {
        // Clear cache
        clearCache: function() {
            if (confirm('Sigur doriți să ștergeți cache-ul?')) {
                LoadingStates.show(document.body);
                setTimeout(() => {
                    LoadingStates.hide(document.body);
                    Utils.showNotification('Cache-ul a fost șters cu succes', 'success');
                }, 2000);
            }
        },

        // View logs
        viewLogs: function() {
            Utils.showNotification('Deschiderea jurnalelor...', 'info');
            // Would typically open logs in a modal or new window
        },

        // Export data
        exportData: function() {
            Utils.showNotification('Exportul datelor a început...', 'info');
            // Would typically trigger a download
        }
    };

    // Add quick action buttons
    const quickActionsCard = Array.from(adminCards).find(card => 
        card.querySelector('h3').textContent.includes('Acțiuni Rapide')
    );

    if (quickActionsCard) {
        const actionsHTML = `
            <div class="quick-actions-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <button onclick="quickActions.clearCache()" class="btn btn-primary" style="padding: 0.5rem; font-size: 0.85rem;">
                    🗑️ Șterge Cache
                </button>
                <button onclick="quickActions.viewLogs()" class="btn btn-primary" style="padding: 0.5rem; font-size: 0.85rem;">
                    📋 Vezi Loguri
                </button>
                <button onclick="quickActions.exportData()" class="btn btn-primary" style="padding: 0.5rem; font-size: 0.85rem;">
                    📥 Export Date
                </button>
            </div>
        `;
        
        quickActionsCard.querySelector('p').innerHTML = actionsHTML;
    }

    // Make quickActions available globally
    window.quickActions = quickActions;

    // Initialize pagination after content load
    if (typeof setupPaginationForTables === 'function') {
        setupPaginationForTables();
    }

    // Session management
    let sessionWarningShown = false;
    
    function checkSession() {
        // In a real app, this would check with the server
        const loginTime = new Date(); // This would come from session data
        const now = new Date();
        const timeDiff = (now - loginTime) / (1000 * 60); // minutes

        // Warn after 25 minutes of inactivity
        if (timeDiff > 25 && !sessionWarningShown) {
            sessionWarningShown = true;
            Utils.showNotification('Sesiunea va expira în curând. Salvați modificările.', 'info');
        }
    }

    // Check session every 5 minutes
    setInterval(checkSession, 5 * 60 * 1000);

    // Activity tracking
    let lastActivity = Date.now();
    
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, () => {
            lastActivity = Date.now();
            sessionWarningShown = false; // Reset warning
        }, { passive: true });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+R for refresh
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshSystemInfo();
        }
        
        // Escape to clear any active states
        if (e.key === 'Escape') {
            document.querySelectorAll('.loading').forEach(el => {
                LoadingStates.hide(el);
            });
        }
    });

    // Performance monitoring
    function showPerformanceInfo() {
        if ('performance' in window) {
            const perfData = performance.getEntriesByType('navigation')[0];
            const loadTime = Math.round(perfData.loadEventEnd - perfData.loadEventStart);
            
            if (loadTime > 0) {
                console.log(`Timpul de încărcare a paginii: ${loadTime}ms`);
            }
        }
    }

    // Show performance info after page load
    window.addEventListener('load', showPerformanceInfo);

// Sidebar Action Functions
function exportUsers() {
    Utils.showNotification('Exportul utilizatorilor a început...', 'info');
}

function importUsers() {
    Utils.showNotification('Funcția de import utilizatori va fi disponibilă curând...', 'info');
}

function sendBulkEmail() {
    Utils.showNotification('Deschidere formular email masiv...', 'info');
}

function exportCourses() {
    Utils.showNotification('Exportul cursurilor a început...', 'info');
}

function bulkEditCourses() {
    Utils.showNotification('Funcția de editare masivă va fi disponibilă curând...', 'info');
}

function generateReport() {
    Utils.showNotification('Generarea raportului a început...', 'info');
}

});

// ==================== COURSES MANAGEMENT ====================
// These functions need to be outside DOMContentLoaded to be accessible from onclick attributes

/**
 * Opens the add course modal
 */
window.openAddCourseModal = function() {
    // Reset form
    document.getElementById('addCourseForm').reset();
    
    // Clear all materii selections
    const materiiSelect = document.getElementById('course_materii');
    if (materiiSelect) {
        Array.from(materiiSelect.options).forEach(option => {
            option.selected = false;
        });
    }
    
    // Clear any previous errors
    const titleInput = document.getElementById('course_title');
    const fileInput = document.getElementById('course_file');
    
    FormValidator.removeError(titleInput);
    FormValidator.removeError(materiiSelect);
    FormValidator.removeError(fileInput);
    
    // Add real-time validation
    setupCourseFormValidation();
    
    // Show the modal
    document.getElementById('addCourseModal').style.display = 'flex';
};

/**
 * Closes the add course modal
 */
window.closeAddCourseModal = function() {
    document.getElementById('addCourseModal').style.display = 'none';
};

/**
 * Submits the add course form
 */
window.submitAddCourseForm = function() {
    const form = document.getElementById('addCourseForm');
    const formData = new FormData(form);
    formData.append('action', 'add_course');
    
    // Clear any previous errors
    const titleInput = document.getElementById('course_title');
    const materiiSelect = document.getElementById('course_materii');
    const fileInput = document.getElementById('course_file');
    
    FormValidator.removeError(titleInput);
    FormValidator.removeError(materiiSelect);
    FormValidator.removeError(fileInput);
    
    let hasErrors = false;
    
    // Check if title is provided
    if (!FormValidator.isRequired(titleInput.value)) {
        FormValidator.addError(titleInput, 'Titlul cursului este obligatoriu.');
        hasErrors = true;
    }
    
    // Check if at least one materie is selected
    const selectedMaterii = Array.from(materiiSelect.selectedOptions);
    if (selectedMaterii.length === 0) {
        FormValidator.addError(materiiSelect, 'Vă rugăm să selectați cel puțin o materie.');
        hasErrors = true;
    }
    
    // Check if file is selected
    if (!fileInput.files.length) {
        FormValidator.addError(fileInput, 'Vă rugăm să selectați un fișier PDF.');
        hasErrors = true;
    } else {
        // Check file size using dynamic limit
        if (fileInput.files[0].size > window.MAX_FILE_SIZE) {
            FormValidator.addError(fileInput, `Fișierul este prea mare. Mărimea maximă este ${window.MAX_FILE_SIZE_MB}MB.`);
            hasErrors = true;
        }
    }
    
    // If there are errors, focus on the first error field and return
    if (hasErrors) {
        if (titleInput.classList.contains('error')) {
            titleInput.focus();
        } else if (materiiSelect.classList.contains('error')) {
            materiiSelect.focus();
        } else if (fileInput.classList.contains('error')) {
            fileInput.focus();
        }
        return;
    }
    
    // Submit form
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Store active tab and reload page
            localStorage.setItem('activeAdminTab', 'courses');
            window.location.reload();
        } else {
            alert(data.message || 'Eroare la adăugarea cursului.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Eroare la adăugarea cursului.');
    });
    if (typeof setupPaginationForTables === 'function') {
        setupPaginationForTables();
    }
};

/**
 * Opens the edit course modal and populates it with course data
 */
window.editCourse = function(courseId, title, type) {
    // Populate the edit form
    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_course_title').value = title;
    document.getElementById('edit_course_type').value = type;
    
    // Get current materii assignments for this course
    fetch(`${window.location.pathname}?action=get_course_materii&course_id=${courseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear all selections
                const materiiSelect = document.getElementById('edit_course_materii');
                Array.from(materiiSelect.options).forEach(option => {
                    option.selected = false;
                });
                
                // Select current materii
                data.materii.forEach(materie => {
                    const option = materiiSelect.querySelector(`option[value="${materie.id}"]`);
                    if (option) {
                        option.selected = true;
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading course materii:', error);
        });
    
    // Clear any previous errors
    const editTitleInput = document.getElementById('edit_course_title');
    const editMateriiSelect = document.getElementById('edit_course_materii');
    
    FormValidator.removeError(editTitleInput);
    FormValidator.removeError(editMateriiSelect);
    
    // Add real-time validation
    setupCourseFormValidation();
    
    // Show the modal
    document.getElementById('editCourseModal').style.display = 'flex';
};

/**
 * Closes the edit course modal
 */
window.closeEditCourseModal = function() {
    document.getElementById('editCourseModal').style.display = 'none';
};

/**
 * Submits the edit course form
 */
window.submitEditCourseForm = function() {
    const form = document.getElementById('editCourseForm');
    const formData = new FormData(form);
    formData.append('action', 'edit_course');
    
    // Clear any previous errors
    const titleInput = document.getElementById('edit_course_title');
    const materiiSelect = document.getElementById('edit_course_materii');
    
    FormValidator.removeError(titleInput);
    FormValidator.removeError(materiiSelect);
    
    let hasErrors = false;
    
    // Check if title is provided
    if (!FormValidator.isRequired(titleInput.value)) {
        FormValidator.addError(titleInput, 'Titlul cursului este obligatoriu.');
        hasErrors = true;
    }
    
    // Check if at least one materie is selected
    const selectedMaterii = Array.from(materiiSelect.selectedOptions);
    if (selectedMaterii.length === 0) {
        FormValidator.addError(materiiSelect, 'Vă rugăm să selectați cel puțin o materie.');
        hasErrors = true;
    }
    
    // If there are errors, focus on the first error field and return
    if (hasErrors) {
        if (titleInput.classList.contains('error')) {
            titleInput.focus();
        } else if (materiiSelect.classList.contains('error')) {
            materiiSelect.focus();
        }
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Store active tab and reload page
            localStorage.setItem('activeAdminTab', 'courses');
            window.location.reload();
        } else {
            alert(data.message || 'Eroare la actualizarea cursului.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Eroare la actualizarea cursului.');
    });
    if (typeof setupPaginationForTables === 'function') {
        setupPaginationForTables();
    }
};

/**
 * Downloads a course file
 */
window.downloadCourse = function(courseId) {
    window.open(`/src/admin/download.php?id=${courseId}`, '_blank');
};

/**
 * Views a course file (PDF) in a modal
 */
window.viewCourse = function(courseId) {
    openPDFModal(courseId);
};

/**
 * Deletes a course
 */
window.deleteCourse = function(courseId, title) {
    window.showDeleteConfirmation(title, () => {
        const formData = new FormData();
        formData.append('action', 'delete_course');
        formData.append('course_id', courseId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Store active tab and reload page
                localStorage.setItem('activeAdminTab', 'courses');
                window.location.reload();
            } else {
                Utils.showNotification(data.message || 'Eroare la ștergerea cursului.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Utils.showNotification('Eroare la ștergerea cursului.', 'error');
        });
    });
};

/**
 * Filters materii by faculty, year, semester and name
 */
window.filterMaterii = function() {
    const facultyFilter = document.getElementById('materiiFacultyFilter');
    const yearFilter = document.getElementById('materiiYearFilter');
    const semFilter = document.getElementById('materiiSemFilter');
    const searchInput = document.getElementById('materiiSearch');
    const selectedFaculty = facultyFilter ? facultyFilter.value.toLowerCase() : '';
    const selectedYear = yearFilter ? yearFilter.value : '';
    const selectedSem = semFilter ? semFilter.value : '';
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';

    const tbody = document.querySelector('#materii-tab table.users-table tbody');
    if (!tbody) return;

    const rows = tbody.querySelectorAll('tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const nameCell = row.cells[0];
        const yearCell = row.cells[1];
        const semCell = row.cells[2];
        const facultyCell = row.cells[4];
        
        if (!nameCell || !yearCell || !semCell || !facultyCell) return;

        const nameText = nameCell.textContent.toLowerCase();
        const yearText = (yearCell.textContent.match(/(\d+)/) || ['',''])[1];
        const semText = (semCell.textContent.match(/(\d+)/) || ['',''])[1];
        const facultyText = facultyCell.textContent.toLowerCase();

        const matchesFaculty = selectedFaculty === '' || facultyText.includes(selectedFaculty);
        const matchesYear = selectedYear === '' || yearText === selectedYear;
        const matchesSem = selectedSem === '' || semText === selectedSem;
        const matchesSearch = searchTerm === '' || nameText.includes(searchTerm);

        if (matchesFaculty && matchesYear && matchesSem && matchesSearch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show message if no materii found
    const tableContainer = document.querySelector('#materii-tab .users-table-container');
    let noResultsMessage = tableContainer.querySelector('.no-results-message');
    
    if (visibleCount === 0 && (selectedFaculty !== '' || selectedYear !== '' || selectedSem !== '' || searchTerm !== '')) {
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-results-message';
            noResultsMessage.style.cssText = 'text-align: center; padding: 20px; color: #666; font-style: italic;';
            noResultsMessage.innerHTML = `Nu s-au găsit materii care să corespundă criteriilor de căutare.`;
            tableContainer.appendChild(noResultsMessage);
        }
        noResultsMessage.style.display = 'block';
    } else if (noResultsMessage) {
        noResultsMessage.style.display = 'none';
    }
    const pageSize = window.PAGE_SIZE || 8;
    const hasFilters = (selectedFaculty && selectedFaculty.length) || (selectedYear && selectedYear.length) || (selectedSem && selectedSem.length) || (searchTerm && searchTerm.length);
    if (tableContainer && tbody) setFilteredPagerMode(tableContainer, tbody, !!hasFilters, pageSize);
};

/**
 * Clears all materii filters and shows all materii
 */
window.clearMateriiFilters = function() {
    const facultyFilter = document.getElementById('materiiFacultyFilter');
    const yearFilter = document.getElementById('materiiYearFilter');
    const semFilter = document.getElementById('materiiSemFilter');
    const searchInput = document.getElementById('materiiSearch');
    
    if (facultyFilter) facultyFilter.selectedIndex = 0;
    if (yearFilter) yearFilter.selectedIndex = 0;
    if (semFilter) semFilter.selectedIndex = 0;
    if (searchInput) searchInput.value = '';
    
    // Show all rows
    const table = document.querySelector('#materii-tab table.users-table tbody');
    if (table) {
        const rows = table.querySelectorAll('tr');
        rows.forEach(row => {
            row.style.display = '';
        });
    }
    
    // Hide no results message
    const tableContainer = document.querySelector('#materii-tab .users-table-container');
    const noResultsMessage = tableContainer.querySelector('.no-results-message');
    if (noResultsMessage) {
        noResultsMessage.style.display = 'none';
    }
    const container = document.querySelector('#materii-tab .users-table-container');
    const tbody = document.querySelector('#materii-tab table.users-table tbody');
    const pageSize = window.PAGE_SIZE || 8;
    if (container && tbody) setFilteredPagerMode(container, tbody, false, pageSize);
};

/**
 * Filters courses by faculty and title search
 */
window.filterCourses = function() {
    const scopeFilter = document.getElementById('courseScopeFilter');
    const courseSearch = document.getElementById('courseSearch');
    const scopeValue = scopeFilter ? scopeFilter.value : '';
    const [scopeType, scopeTermRaw] = scopeValue.includes('::') ? scopeValue.split('::') : ['', ''];
    const scopeTerm = (scopeTermRaw || '').toLowerCase();
    const searchTerm = courseSearch ? courseSearch.value.toLowerCase() : '';
    
    const tables = ['cursuri', 'laboratoare'];
    const hasFilters = (scopeTerm !== '' || searchTerm !== '');
    if (hasFilters) {
        fetchAllCourses().then(({cursuri, laboratoare}) => {
            const dataset = { cursuri, laboratoare };
            tables.forEach(name => {
                const all = dataset[name] || [];
                const filtered = all.filter(c => {
                    const titleText = String(c.title || '').toLowerCase();
                    const materiiText = String(c.materii_names || '').toLowerCase();
                    const matchesMaterie = scopeType !== 'materie' || scopeTerm === '' || materiiText.split(',').map(s => s.trim()).includes(scopeTerm);
                    const matchesSearch = !searchTerm || titleText.includes(searchTerm);
                    return matchesMaterie && matchesSearch;
                });
                const tbody = document.querySelector(`#${name}-courses-content table.lecture-table tbody`);
                if (!tbody) return;
                restoreOriginalTbody(tbody);
                tbody.innerHTML = filtered.map(c => `
                    <tr>
                        <td class="lecture-title-cell" data-label="Titlu ${name === 'cursuri' ? 'Curs' : 'Laborator'}">${__escapeHtml(c.title || '')}</td>
                        <td class="lecture-meta-cell" data-label="Tip">${__escapeHtml(c.type || '')}</td>
                        <td class="lecture-meta-cell" data-label="Materii">${__escapeHtml(c.materii_names || '-')}</td>
                        <td class="lecture-meta-cell" data-label="Fișier">
                            <span title="${__escapeAttr(c.file_name || '')}">${__escapeHtml(c.file_name || '')}</span><br>
                            <small>${(c.file_size ? (c.file_size/1024/1024).toFixed(2) : '0.00')} MB</small>
                        </td>
                        <td class="lecture-meta-cell" data-label="Data Încărcării">${c.created_at ? __fmtDateTime(c.created_at) : ''}</td>
                        <td data-label="Acțiuni">
                            <div class="lecture-actions">
                                <button class="btn-action btn-view" onclick="viewCourse('${c.id}')">Vezi</button>
                                <button class="btn-action btn-download" onclick="downloadCourse('${c.id}')">Descarcă</button>
                                <button class="btn-action btn-edit" onclick="editCourse('${c.id}', '${__escapeAttr(c.title || '')}', '${__escapeAttr(c.type || '')}')">Editează</button>
                                <button class="btn-action btn-delete" onclick="deleteCourse('${c.id}', '${__escapeAttr(c.title || '')}')">Șterge</button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                // Mark all rows as not filter-hidden so JS pager paginates this full filtered dataset
                Array.from(tbody.querySelectorAll('tr')).forEach(tr => tr.classList.remove('is-filter-hidden'));
                const container = document.querySelector(`#${name}-courses-content .lecture-table-container`);
                const pageSize = window.PAGE_SIZE || 8;
                setFilteredPagerMode(container, tbody, true, pageSize);
            });
        });
    } else {
        tables.forEach(name => {
            const tbody = document.querySelector(`#${name}-courses-content table.lecture-table tbody`);
            if (!tbody) return;
            restoreOriginalTbody(tbody);
            Array.from(tbody.querySelectorAll('tr')).forEach(r => { r.style.display = ''; r.classList.remove('is-filter-hidden'); });
            const container = document.querySelector(`#${name}-courses-content .lecture-table-container`);
            const pageSize = window.PAGE_SIZE || 8;
            setFilteredPagerMode(container, tbody, false, pageSize);
        });
    }
};

/**
 * Clears all filters and shows all courses
 */
window.clearFilters = function() {
    const scopeFilter = document.getElementById('courseScopeFilter');
    const courseSearch = document.getElementById('courseSearch');
    
    if (scopeFilter) scopeFilter.selectedIndex = 0;
    if (courseSearch) courseSearch.value = '';
    
    // Show all rows
    const tables = ['cursuri-courses-content', 'laboratoare-courses-content'];
    tables.forEach(tableId => {
        const tableContainer = document.getElementById(tableId);
        if (!tableContainer) return;
        
        const rows = tableContainer.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.style.display = '';
        });
        
        // Hide no results message
        const noResultsMessage = tableContainer.querySelector('.no-results-message');
        if (noResultsMessage) {
            noResultsMessage.style.display = 'none';
        }
        // Restore server pager and remove JS pager
        const pageSize = window.PAGE_SIZE || 8;
        const container = tableContainer.querySelector('.lecture-table-container');
        const tbody = tableContainer.querySelector('table.lecture-table tbody');
        if (container && tbody) setFilteredPagerMode(container, tbody, false, pageSize);
    });
};
// No dependent options needed with combined scope filter

/**
 * Sets up real-time validation for course forms
 */
function setupCourseFormValidation() {
    // Add course form validation
    const titleInput = document.getElementById('course_title');
    const materiiSelect = document.getElementById('course_materii');
    const fileInput = document.getElementById('course_file');
    
    if (titleInput) {
        titleInput.addEventListener('input', function() {
            FormValidator.removeError(this);
        });
    }
    
    if (materiiSelect) {
        materiiSelect.addEventListener('change', function() {
            FormValidator.removeError(this);
        });
    }
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            FormValidator.removeError(this);
        });
    }
    
    // Edit course form validation
    const editTitleInput = document.getElementById('edit_course_title');
    const editMateriiSelect = document.getElementById('edit_course_materii');
    
    if (editTitleInput) {
        editTitleInput.addEventListener('input', function() {
            FormValidator.removeError(this);
        });
    }
    
    if (editMateriiSelect) {
        editMateriiSelect.addEventListener('change', function() {
            FormValidator.removeError(this);
        });
    }
}

// ==================== MATERII MANAGEMENT ====================

/**
 * Simple client-side pagination: max 8 rows per table page
 */
function setupPaginationForTables() {
    const MAX_ROWS = 8;
    // Server now renders pagination for Courses subtabs; disable JS pager to avoid conflicts
    const tableConfigs = [];

    tableConfigs.forEach(cfg => {
        const container = document.querySelector(cfg.container);
        const table = document.querySelector(cfg.table);
        if (!container || !table) return;

        // No-op since we no longer inject pagers here

        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));

        // Consider only visible rows
        const visibleRows = rows.filter(r => r.style.display !== 'none');
        const total = visibleRows.length;
        const pages = Math.max(1, Math.ceil(total / MAX_ROWS));

        function renderPage(pageIndex) {
            const start = pageIndex * MAX_ROWS;
            const end = start + MAX_ROWS;
            visibleRows.forEach((row, idx) => {
                row.style.display = (idx >= start && idx < end) ? '' : 'none';
            });
        }

        // No-op; server handles pagination

        // Build pager
        const pager = document.createElement('div');
        pager.className = 'table-pager';
        pager.setAttribute('data-js-pager', 'true');
        pager.style.cssText = 'display:flex;gap:8px;align-items:center;justify-content:flex-end;padding:10px;border-top:1px solid #eee;';

        let current = 0;
        const prevBtn = document.createElement('button');
        prevBtn.textContent = '‹ Anterior';
        prevBtn.className = 'btn-secondary';
        prevBtn.style.fontSize = '0.85rem';
        const nextBtn = document.createElement('button');
        nextBtn.textContent = 'Următor ›';
        nextBtn.className = 'btn-secondary';
        nextBtn.style.fontSize = '0.85rem';
        const info = document.createElement('span');
        info.style.cssText = 'color:#6b7280;font-size:0.85rem;';

        function update() {
            renderPage(current);
            info.textContent = `Pagina ${current + 1} din ${pages}`;
            prevBtn.disabled = current === 0;
            nextBtn.disabled = current >= pages - 1;
        }

        prevBtn.onclick = () => { if (current > 0) { current--; update(); } };
        nextBtn.onclick = () => { if (current < pages - 1) { current++; update(); } };

        pager.appendChild(prevBtn);
        pager.appendChild(info);
        pager.appendChild(nextBtn);
        container.appendChild(pager);

        update();
    });
}

/**
 * Opens the add materie modal
 */
window.openAddMaterieModal = function() {
    // Reset form and show modal
    document.getElementById('addMaterieForm').reset();
    document.getElementById('addMaterieModal').style.display = 'flex';
};

/**
 * Closes the add materie modal
 */
window.closeAddMaterieModal = function() {
    document.getElementById('addMaterieModal').style.display = 'none';
};

/**
 * Submits the add materie form
 */
window.submitAddMaterieForm = function() {
    const form = document.getElementById('addMaterieForm');
    const formData = new FormData(form);
    formData.append('action', 'add_materie');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            localStorage.setItem('activeAdminTab', 'materii');
            window.location.reload();
        } else {
            alert(data.message || 'Eroare la adăugarea materiei.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Eroare la adăugarea materiei.');
    });
};

/**
 * Opens the edit materie modal
 */
window.editMaterie = function(materieId, name, year, semester, credits) {
    document.getElementById('edit_materie_id').value = materieId;
    document.getElementById('edit_materie_name').value = name;
    document.getElementById('edit_materie_year').value = year;
    document.getElementById('edit_materie_semester').value = semester;
    document.getElementById('edit_materie_credits').value = credits;
    
    // Get current faculty assignments for this materie
    fetch(`${window.location.pathname}?action=get_materie_faculties&materie_id=${materieId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const facultySelect = document.getElementById('edit_materie_faculties');
                Array.from(facultySelect.options).forEach(option => {
                    option.selected = false;
                });
                
                data.faculties.forEach(faculty => {
                    const option = facultySelect.querySelector(`option[value="${faculty.id}"]`);
                    if (option) {
                        option.selected = true;
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading materie faculties:', error);
        });
    
    document.getElementById('editMaterieModal').style.display = 'flex';
};

/**
 * Closes the edit materie modal
 */
window.closeEditMaterieModal = function() {
    document.getElementById('editMaterieModal').style.display = 'none';
};

/**
 * Submits the edit materie form
 */
window.submitEditMaterieForm = function() {
    const form = document.getElementById('editMaterieForm');
    const formData = new FormData(form);
    formData.append('action', 'edit_materie');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            localStorage.setItem('activeAdminTab', 'materii');
            window.location.reload();
        } else {
            alert(data.message || 'Eroare la actualizarea materiei.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Eroare la actualizarea materiei.');
    });
};

/**
 * Deletes a materie
 */
window.deleteMaterie = function(materieId, name) {
    window.showDeleteConfirmation(name, () => {
        const formData = new FormData();
        formData.append('action', 'delete_materie');
        formData.append('materie_id', materieId);
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    localStorage.setItem('activeAdminTab', 'materii');
                    window.location.reload();
                } else {
                    Utils.showNotification(data.message || 'Eroare la ștergerea materiei', 'error');
                }
            })
            .catch(() => Utils.showNotification('Eroare la ștergerea materiei', 'error'));
    });
};

// ==================== SEMESTER MANAGEMENT ====================

/**
 * Opens the add semester modal
 */
window.openAddSemesterModal = function() {
    document.getElementById('addSemesterForm').reset();
    document.getElementById('addSemesterModal').style.display = 'flex';
};

/**
 * Closes the add semester modal
 */
window.closeAddSemesterModal = function() {
    document.getElementById('addSemesterModal').style.display = 'none';
};

/**
 * Submits the add semester form
 */
window.submitAddSemesterForm = function() {
    const form = document.getElementById('addSemesterForm');
    const formData = new FormData(form);
    formData.append('action', 'add_semester');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            localStorage.setItem('activeAdminTab', 'general');
            window.location.reload();
        } else {
            alert(data.message || 'Eroare la adăugarea semestrului.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Eroare la adăugarea semestrului.');
    });
};

/**
 * Opens the edit semester modal
 */
window.editSemester = function(semesterId, academicYear, semesterNumber, startDate, endDate) {
    document.getElementById('edit_semester_id').value = semesterId;
    document.getElementById('edit_semester_academic_year').value = academicYear;
    document.getElementById('edit_semester_number').value = semesterNumber;
    document.getElementById('edit_semester_start_date').value = startDate;
    document.getElementById('edit_semester_end_date').value = endDate;
    
    document.getElementById('editSemesterModal').style.display = 'flex';
};

/**
 * Closes the edit semester modal
 */
window.closeEditSemesterModal = function() {
    document.getElementById('editSemesterModal').style.display = 'none';
};

/**
 * Submits the edit semester form
 */
window.submitEditSemesterForm = function() {
    const form = document.getElementById('editSemesterForm');
    const formData = new FormData(form);
    formData.append('action', 'edit_semester');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            localStorage.setItem('activeAdminTab', 'general');
            window.location.reload();
        } else {
            alert(data.message || 'Eroare la actualizarea semestrului.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Eroare la actualizarea semestrului.');
    });
};

/**
 * Deletes a semester
 */
window.deleteSemester = function(semesterId, name) {
    window.showDeleteConfirmation(name, () => {
        const formData = new FormData();
        formData.append('action', 'delete_semester');
        formData.append('semester_id', semesterId);
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    localStorage.setItem('activeAdminTab', 'general');
                    window.location.reload();
                } else {
                    Utils.showNotification(data.message || 'Eroare la ștergerea semestrului', 'error');
                }
            })
            .catch(() => Utils.showNotification('Eroare la ștergerea semestrului', 'error'));
    });
};

/**
 * Inline validation setup for user and faculty forms
 */
function setupUserInlineValidation(isEdit = false) {
    const prefix = isEdit ? 'edit_' : '';
    const usernameInput = document.getElementById(`${prefix}username`);
    const passwordInput = document.getElementById(`${prefix}password`);
    const passwordConfirmInput = document.getElementById(`${prefix}password_confirm`);
    const roleSelect = document.getElementById(`${isEdit ? 'edit_role' : 'role'}`);
    const facultySelect = document.getElementById(`${isEdit ? 'edit_faculty_id' : 'faculty_id'}`);

    if (usernameInput) usernameInput.addEventListener('input', () => FormValidator.removeError(usernameInput));
    if (passwordInput) passwordInput.addEventListener('input', () => FormValidator.removeError(passwordInput));
    if (passwordConfirmInput) passwordConfirmInput.addEventListener('input', () => FormValidator.removeError(passwordConfirmInput));
    if (roleSelect) roleSelect.addEventListener('change', () => FormValidator.removeError(roleSelect));
    if (facultySelect) facultySelect.addEventListener('change', () => FormValidator.removeError(facultySelect));
}

function setupFacultyInlineValidation() {
    const nameInput = document.getElementById('faculty_name');
    if (nameInput) nameInput.addEventListener('input', () => FormValidator.removeError(nameInput));
    const editNameInput = document.getElementById('edit_faculty_name');
    if (editNameInput) editNameInput.addEventListener('input', () => FormValidator.removeError(editNameInput));
}

// ==================== PDF MODAL FUNCTIONS ====================

let currentPDFCourseId = null;

/**
 * Detects if the user is on a mobile device
 */
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
           (window.innerWidth <= 768);
}

/**
 * Opens the PDF modal and loads the PDF
 * @param {string} courseId - The ID of the course to view
 */
window.openPDFModal = function(courseId) {
    currentPDFCourseId = courseId;
    const modal = document.getElementById('pdfModal');
    const loading = document.getElementById('pdfLoading');
    const viewer = document.getElementById('pdfViewer');
    const error = document.getElementById('pdfError');
    
    // Show modal
    modal.classList.add('active');
    
    // Reset states
    loading.style.display = 'flex';
    viewer.style.display = 'none';
    error.style.display = 'none';
    
    // Check if mobile device - PDFs often don't work well in iframes on mobile
    if (isMobileDevice()) {
        // On mobile, directly open PDF in new tab for native viewing
        const pdfUrl = `/src/admin/view.php?id=${courseId}`;
        window.open(pdfUrl, '_blank');
        // Close the modal since we're opening in new tab
        modal.classList.remove('active');
        document.body.style.overflow = '';
        return;
    }
    
    // Load PDF for desktop
    const pdfUrl = `/src/admin/view.php?id=${courseId}`;
    viewer.src = pdfUrl;
    
    // Handle iframe load
    viewer.onload = function() {
        loading.style.display = 'none';
        viewer.style.display = 'block';
    };
    
    // Handle iframe error
    viewer.onerror = function() {
        loading.style.display = 'none';
        error.style.display = 'block';
    };
    
    // Set a timeout to show error if PDF doesn't load within 5 seconds
    setTimeout(() => {
        if (loading.style.display === 'flex') {
            loading.style.display = 'none';
            error.style.display = 'block';
        }
    }, 5000);
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
};



/**
 * Closes the PDF modal
 */
window.closePDFModal = function() {
    const modal = document.getElementById('pdfModal');
    const viewer = document.getElementById('pdfViewer');
    
    modal.classList.remove('active');
    
    // Clear the iframe source to stop loading
    viewer.src = '';
    
    // Reset current course ID
    currentPDFCourseId = null;
    
    // Restore body scroll
    document.body.style.overflow = '';
};

/**
 * Downloads the currently viewed PDF
 */
window.downloadCurrentPDF = function() {
    if (currentPDFCourseId) {
        window.downloadCourse(currentPDFCourseId);
    }
};

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('pdfModal');
    if (e.target === modal) {
        closePDFModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('pdfModal');
        if (modal && modal.classList.contains('active')) {
            closePDFModal();
        }
    }
}); 
