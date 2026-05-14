        </div><!-- /page-content -->
    </div><!-- /main-wrap -->
</div><!-- /app-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
        </div><!-- /page-content -->
    </div><!-- /main-wrap -->
</div><!-- /app-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
'use strict';

/* ── Sidebar ── */
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sbBackdrop').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sbBackdrop').classList.remove('show');
}

/* ── Clock ── */
(function clock() {
    const el = document.getElementById('navClock');
    if (!el) return;
    function tick() {
        el.textContent = new Date().toLocaleTimeString([], {
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }
    tick();
    setInterval(tick, 1000);
})();

/* ── Row slide-out animation ── */
document.head.insertAdjacentHTML('beforeend', `
<style>
@keyframes rowOut {
    to {
        opacity: 0;
        transform: translateY(-8px);
        max-height: 0;
        padding: 0;
        margin: 0;
        overflow: hidden;
    }
}
</style>`);

/* ══════════════════════════════════════
   ADD / EDIT B/L — Dynamic Rows
══════════════════════════════════════ */
let rowCount = 0;

function addItemRow() {
    rowCount++;
    const wrap = document.getElementById('rowsWrap');
    if (!wrap) return;

    const div = document.createElement('div');
    div.className = 'item-row';
    div.id = `row-${rowCount}`;

    div.innerHTML = `
        <div class="row-head">
            <div class="row-num">
                <div class="row-num-badge">${rowCount}</div>
                <span>Container Row #${rowCount}</span>
            </div>
            ${rowCount > 1 ? `
            <button type="button" class="btn-remove" onclick="removeRow('row-${rowCount}')">
                <i class="fas fa-trash-can"></i> Remove
            </button>` : ''}
        </div>

        <div class="row g-3">

            <!-- Container Number -->
            <div class="col-xl-4 col-md-6 col-12">
                <label class="f-label">
                    Container No. <span class="req">*</span>
                </label>
                <input
                    type="text"
                    name="container_number[]"
                    class="f-input"
                    placeholder="e.g. MSCU1234567"
                    required
                    oninput="this.value = this.value.toUpperCase()"
                >
            </div>

            <!-- Agent Seal -->
            <div class="col-xl-4 col-md-6 col-12">
                <label class="f-label">Agent Seal No.</label>
                <input
                    type="text"
                    name="agent_seal_number[]"
                    class="f-input"
                    placeholder="e.g. AS-00123"
                >
            </div>

            <!-- Number of Bags -->
            <div class="col-xl-4 col-md-6 col-12">
                <label class="f-label">
                    No. of Bags <span class="req">*</span>
                </label>
                <input
                    type="number"
                    name="number_of_bags[]"
                    class="f-input"
                    placeholder="e.g. 500"
                    min="1"
                    required
                    oninput="calcTotals()"
                >
            </div>

            <!-- SGS Seal -->
            <div class="col-xl-4 col-md-6 col-12">
                <label class="f-label">SGS Seal No.</label>
                <input
                    type="text"
                    name="sgs_seal_number[]"
                    class="f-input"
                    placeholder="e.g. SGS-00456"
                >
            </div>

            <!-- Gross Weight MT -->
            <div class="col-xl-4 col-md-6 col-12">
                <label class="f-label">
                    Gross Weight <span class="req">*</span>
                </label>
                <div class="input-unit-wrap">
                    <input
                        type="number"
                        name="gross_weight[]"
                        class="f-input"
                        placeholder="e.g. 25.500"
                        step="0.001"
                        min="0"
                        required
                        oninput="calcTotals()"
                    >
                    <span class="input-unit-badge">MT</span>
                </div>
            </div>

            <!-- Net Weight MT -->
            <div class="col-xl-4 col-md-6 col-12">
                <label class="f-label">
                    Net Weight <span class="req">*</span>
                </label>
                <div class="input-unit-wrap">
                    <input
                        type="number"
                        name="net_weight[]"
                        class="f-input"
                        placeholder="e.g. 25.000"
                        step="0.001"
                        min="0"
                        required
                        oninput="calcTotals()"
                    >
                    <span class="input-unit-badge">MT</span>
                </div>
            </div>

        </div>
    `;

    wrap.appendChild(div);
    calcTotals();
    reNumber();
    div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function removeRow(id) {
    const row = document.getElementById(id);
    if (!row) return;
    row.style.animation = 'rowOut 0.25s ease forwards';
    setTimeout(() => {
        row.remove();
        calcTotals();
        reNumber();
    }, 240);
}

function reNumber() {
    document.querySelectorAll('.item-row').forEach((r, i) => {
        const badge = r.querySelector('.row-num-badge');
        const span  = r.querySelector('.row-num span');
        if (badge) badge.textContent = i + 1;
        if (span)  span.textContent  = `Container Row #${i + 1}`;
    });
}

function calcTotals() {
    let bags = 0, gross = 0, net = 0;

    document.querySelectorAll('input[name="number_of_bags[]"]').forEach(el => bags  += parseInt(el.value)   || 0);
    document.querySelectorAll('input[name="gross_weight[]"]').forEach(el   => gross += parseFloat(el.value) || 0);
    document.querySelectorAll('input[name="net_weight[]"]').forEach(el     => net   += parseFloat(el.value) || 0);

    const fmt = n => n.toLocaleString('en-US', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3
    });

    const setEl = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.textContent = v;
    };

    setEl('tRows',  document.querySelectorAll('.item-row').length);
    setEl('tBags',  bags.toLocaleString());
    setEl('tGross', fmt(gross) + ' MT');
    setEl('tNet',   fmt(net)   + ' MT');
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('rowsWrap')) {
        // Only auto-add row on add-bl.php, not edit-bl.php
        // edit-bl.php handles its own population
        if (!window._editMode) {
            addItemRow();
        }
    }
});

</script>
</body>
</html>