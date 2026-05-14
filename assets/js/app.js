/* ═══════════════════════════════════════════
   BL MANAGER — Main JS
═══════════════════════════════════════════ */

'use strict';

/* ── Sidebar Toggle ── */
function toggleSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar.classList.toggle('open');
    backdrop.classList.toggle('show');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}

function closeSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar.classList.remove('open');
    backdrop.classList.remove('show');
    document.body.style.overflow = '';
}

/* ── Number Formatter ── */
function formatNumber(n) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ══════════════════════════════════════════
   ADD B/L — Dynamic Rows
══════════════════════════════════════════ */
let rowCount = 0;

function addItemRow() {
    rowCount++;
    const wrapper = document.getElementById('itemRowsWrapper');
    const row = document.createElement('div');
    row.className = 'item-row';
    row.id = `row-${rowCount}`;
    row.innerHTML = `
        <div class="item-row-header">
            <div class="item-row-number">
                <div class="item-row-number-badge">${rowCount}</div>
                Container Row #${rowCount}
            </div>
            ${rowCount > 1 ? `
            <button type="button" class="btn-remove-row" onclick="removeRow('row-${rowCount}')">
                <i class="fas fa-trash-can"></i> Remove
            </button>` : ''}
        </div>

        <div class="row g-3">
            <!-- Container Number -->
            <div class="col-lg-4 col-md-6">
                <div class="field-group mb-0">
                    <label class="field-label">
                        Container No. <span class="req">*</span>
                    </label>
                    <input
                        type="text"
                        name="container_number[]"
                        class="field-input"
                        placeholder="e.g. MSCU1234567"
                        required
                        oninput="this.value = this.value.toUpperCase()"
                    >
                </div>
            </div>

            <!-- Agent Seal -->
            <div class="col-lg-4 col-md-6">
                <div class="field-group mb-0">
                    <label class="field-label">Agent Seal No.</label>
                    <input
                        type="text"
                        name="agent_seal_number[]"
                        class="field-input"
                        placeholder="e.g. AS-00123"
                    >
                </div>
            </div>

            <!-- Number of Bags -->
            <div class="col-lg-4 col-md-6">
                <div class="field-group mb-0">
                    <label class="field-label">
                        No. of Bags <span class="req">*</span>
                    </label>
                    <input
                        type="number"
                        name="number_of_bags[]"
                        class="field-input"
                        placeholder="e.g. 500"
                        min="1"
                        required
                        onchange="updateTotals()"
                    >
                </div>
            </div>

            <!-- SGS Seal -->
            <div class="col-lg-4 col-md-6">
                <div class="field-group mb-0">
                    <label class="field-label">SGS Seal No.</label>
                    <input
                        type="text"
                        name="sgs_seal_number[]"
                        class="field-input"
                        placeholder="e.g. SGS-00456"
                    >
                </div>
            </div>

            <!-- Gross Weight -->
            <div class="col-lg-4 col-md-6">
                <div class="field-group mb-0">
                    <label class="field-label">
                        Gross Weight (kg) <span class="req">*</span>
                    </label>
                    <input
                        type="number"
                        name="gross_weight[]"
                        class="field-input"
                        placeholder="e.g. 25000.00"
                        step="0.01"
                        min="0"
                        required
                        onchange="updateTotals()"
                    >
                </div>
            </div>

            <!-- Net Weight -->
            <div class="col-lg-4 col-md-6">
                <div class="field-group mb-0">
                    <label class="field-label">
                        Net Weight (kg) <span class="req">*</span>
                    </label>
                    <input
                        type="number"
                        name="net_weight[]"
                        class="field-input"
                        placeholder="e.g. 24500.00"
                        step="0.01"
                        min="0"
                        required
                        onchange="updateTotals()"
                    >
                </div>
            </div>
        </div>
    `;
    wrapper.appendChild(row);
    updateTotals();
    updateRowNumbers();
    row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function removeRow(id) {
    const row = document.getElementById(id);
    if (row) {
        row.style.animation = 'rowSlideOut 0.25s ease forwards';
        setTimeout(() => {
            row.remove();
            updateTotals();
            updateRowNumbers();
        }, 240);
    }
}

function updateRowNumbers() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach((row, i) => {
        const badge = row.querySelector('.item-row-number-badge');
        const label = row.querySelector('.item-row-number');
        if (badge) badge.textContent = i + 1;
        if (label) label.lastChild.textContent = ` Container Row #${i + 1}`;
    });
}

function updateTotals() {
    let totalBags  = 0;
    let totalGross = 0;
    let totalNet   = 0;

    document.querySelectorAll('input[name="number_of_bags[]"]').forEach(el => {
        totalBags += parseInt(el.value) || 0;
    });

    document.querySelectorAll('input[name="gross_weight[]"]').forEach(el => {
        totalGross += parseFloat(el.value) || 0;
    });

    document.querySelectorAll('input[name="net_weight[]"]').forEach(el => {
        totalNet += parseFloat(el.value) || 0;
    });

    const el_bags  = document.getElementById('totalBags');
    const el_gross = document.getElementById('totalGross');
    const el_net   = document.getElementById('totalNet');
    const el_rows  = document.getElementById('totalRows');

    if (el_bags)  el_bags.textContent  = totalBags.toLocaleString();
    if (el_gross) el_gross.textContent = formatNumber(totalGross) + ' kg';
    if (el_net)   el_net.textContent   = formatNumber(totalNet) + ' kg';
    if (el_rows)  el_rows.textContent  = document.querySelectorAll('.item-row').length;
}

/* Add slide out animation */
const style = document.createElement('style');
style.textContent = `
    @keyframes rowSlideOut {
        to { opacity: 0; transform: translateY(-10px); max-height: 0; padding: 0; margin: 0; overflow: hidden; }
    }
`;
document.head.appendChild(style);

/* ── Init on DOM ready ── */
document.addEventListener('DOMContentLoaded', () => {
    // Add first row automatically on add-bl page
    if (document.getElementById('itemRowsWrapper')) {
        addItemRow();
    }
});