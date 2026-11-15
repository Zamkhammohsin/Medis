/**
 * Medis - Point of Sale (POS) Client Script
 */

let cart = [];
let selectedPaymentMethod = 'cash';

document.addEventListener('DOMContentLoaded', function () {
    initPOS();
});

function initPOS() {
    const searchInput = document.getElementById('medSearchInput');
    const barcodeInput = document.getElementById('barcodeInput');
    const discountInput = document.getElementById('discountInput');
    const amountPaidInput = document.getElementById('amountPaidInput');

    // Debounced search
    let searchTimeout = null;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                document.getElementById('searchResults').innerHTML = '';
                document.getElementById('searchResults').style.display = 'none';
                return;
            }
            searchTimeout = setTimeout(() => searchMedicines(query), 250);
        });

        // Hide results on outside click
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !document.getElementById('searchResults').contains(e.target)) {
                document.getElementById('searchResults').style.display = 'none';
            }
        });
    }

    // Barcode scanner input (Enter key)
    if (barcodeInput) {
        barcodeInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = this.value.trim();
                if (code) {
                    searchByBarcode(code);
                    this.value = '';
                }
            }
        });
    }

    // Discount change
    if (discountInput) {
        discountInput.addEventListener('input', calculateTotals);
    }

    // Amount paid change (calculates change)
    if (amountPaidInput) {
        amountPaidInput.addEventListener('input', calculateChange);
    }

    // Payment method buttons
    const payBtns = document.querySelectorAll('.payment-btn');
    payBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            payBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            selectedPaymentMethod = this.dataset.method;

            const cashBox = document.getElementById('cashPaymentFields');
            if (cashBox) {
                cashBox.style.display = selectedPaymentMethod === 'cash' ? 'block' : 'none';
            }
            calculateTotals();
        });
    });

    // Hotkey: F2 to focus search, F4 to complete sale
    document.addEventListener('keydown', function (e) {
        if (e.key === 'F2') {
            e.preventDefault();
            if (searchInput) searchInput.focus();
        } else if (e.key === 'F4') {
            e.preventDefault();
            processSale();
        }
    });
}

function getSelectedBranchId() {
    const branchSelect = document.getElementById('posBranchSelect');
    if (branchSelect) return branchSelect.value;
    const branchHidden = document.getElementById('posBranchId');
    return branchHidden ? branchHidden.value : '';
}

async function searchMedicines(query) {
    const branchId = getSelectedBranchId();
    const resultsContainer = document.getElementById('searchResults');

    try {
        const response = await fetch(`${window.BASE_URL}/modules/medicines/api.php?search=${encodeURIComponent(query)}&branch_id=${branchId}`);
        const data = await response.json();

        if (!Array.isArray(data) || data.length === 0) {
            resultsContainer.innerHTML = '<div class="p-3 text-muted text-center">No matching medicines found in catalog.</div>';
            resultsContainer.style.display = 'block';
            return;
        }

        let html = '';
        data.forEach(med => {
            const stock = parseInt(med.available_stock || 0);
            const stockClass = stock <= 0 ? 'text-danger' : (stock < med.min_stock_level ? 'text-warning' : 'text-success');

            html += `
                <div class="search-result-item" onclick="addItemToCart(${JSON.stringify(med).replace(/"/g, '&quot;')})">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="d-block text-dark">${escapeHtml(med.name)}</strong>
                            <small class="text-muted">${escapeHtml(med.generic_name || '')} &bull; ${escapeHtml(med.category_name || '')}</small>
                        </div>
                        <div class="text-end">
                            <strong class="d-block text-primary">${formatCurrency(med.selling_price)}</strong>
                            <small class="${stockClass} fw-bold">Stock: ${stock}</small>
                        </div>
                    </div>
                </div>
            `;
        });

        resultsContainer.innerHTML = html;
        resultsContainer.style.display = 'block';
    } catch (e) {
        console.error(e);
        resultsContainer.innerHTML = '<div class="p-3 text-danger text-center">Failed to search medicines.</div>';
    }
}

async function searchByBarcode(barcode) {
    const branchId = getSelectedBranchId();
    try {
        const response = await fetch(`${window.BASE_URL}/modules/medicines/api.php?barcode=${encodeURIComponent(barcode)}&branch_id=${branchId}`);
        const data = await response.json();

        if (Array.isArray(data) && data.length > 0) {
            addItemToCart(data[0]);
        } else {
            showToast('No medicine found for barcode: ' + barcode, 'warning');
        }
    } catch (e) {
        console.error(e);
        showToast('Error reading barcode', 'error');
    }
}

function addItemToCart(medicine) {
    const availableStock = parseInt(medicine.available_stock || 0);
    if (availableStock <= 0) {
        showToast(`Cannot sell "${medicine.name}". Product has 0 non-expired stock in this branch.`, 'error');
        return;
    }

    const existingIndex = cart.findIndex(item => item.medicine_id === parseInt(medicine.id));
    if (existingIndex > -1) {
        const currentQty = cart[existingIndex].qty;
        if (currentQty + 1 > availableStock) {
            showToast(`Cannot exceed available stock of ${availableStock} units for "${medicine.name}".`, 'warning');
            return;
        }
        cart[existingIndex].qty += 1;
    } else {
        cart.push({
            medicine_id: parseInt(medicine.id),
            name: medicine.name,
            generic_name: medicine.generic_name || '',
            unit_price: parseFloat(medicine.selling_price),
            qty: 1,
            available_stock: availableStock
        });
    }

    // Hide search results and clear input
    const searchResults = document.getElementById('searchResults');
    if (searchResults) searchResults.style.display = 'none';
    const searchInput = document.getElementById('medSearchInput');
    if (searchInput) searchInput.value = '';

    renderCart();
}

function updateItemQty(index, change) {
    const item = cart[index];
    if (!item) return;

    const newQty = item.qty + change;
    if (newQty <= 0) {
        removeItemFromCart(index);
        return;
    }

    if (newQty > item.available_stock) {
        showToast(`Stock limit reached (${item.available_stock} units available).`, 'warning');
        return;
    }

    item.qty = newQty;
    renderCart();
}

function setItemQtyDirect(index, input) {
    const item = cart[index];
    if (!item) return;

    let val = parseInt(input.value);
    if (isNaN(val) || val <= 0) {
        val = 1;
    }

    if (val > item.available_stock) {
        showToast(`Only ${item.available_stock} units available in stock.`, 'warning');
        val = item.available_stock;
    }

    item.qty = val;
    renderCart();
}

function removeItemFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    if (confirm('Are you sure you want to clear the entire cart?')) {
        cart = [];
        renderCart();
    }
}

function renderCart() {
    const cartBody = document.getElementById('cartTableBody');
    const emptyState = document.getElementById('cartEmptyState');
    const itemCountBadge = document.getElementById('cartItemCount');

    if (!cartBody) return;

    if (cart.length === 0) {
        cartBody.innerHTML = '';
        if (emptyState) emptyState.style.display = 'block';
        if (itemCountBadge) itemCountBadge.innerText = '0 items';
        calculateTotals();
        return;
    }

    if (emptyState) emptyState.style.display = 'none';
    if (itemCountBadge) itemCountBadge.innerText = `${cart.length} item${cart.length > 1 ? 's' : ''}`;

    let html = '';
    cart.forEach((item, index) => {
        const itemSubtotal = item.qty * item.unit_price;
        html += `
            <tr>
                <td style="width: 45%;">
                    <strong class="d-block text-dark">${escapeHtml(item.name)}</strong>
                    <small class="text-muted">${formatCurrency(item.unit_price)} each &bull; Stock: ${item.available_stock}</small>
                </td>
                <td style="width: 25%;" class="text-center">
                    <div class="pos-qty-controls">
                        <button type="button" class="btn-qty" onclick="updateItemQty(${index}, -1)">-</button>
                        <input type="number" min="1" max="${item.available_stock}" class="pos-qty-input" value="${item.qty}"
                               onchange="setItemQtyDirect(${index}, this)">
                        <button type="button" class="btn-qty" onclick="updateItemQty(${index}, 1)">+</button>
                    </div>
                </td>
                <td style="width: 20%;" class="text-end">
                    <strong class="text-dark">${formatCurrency(itemSubtotal)}</strong>
                </td>
                <td style="width: 10%;" class="text-center">
                    <button type="button" class="btn-remove-item" onclick="removeItemFromCart(${index})" title="Remove item">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    cartBody.innerHTML = html;
    calculateTotals();
}

function calculateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.qty * item.unit_price), 0);
    const discountInput = document.getElementById('discountInput');
    let discount = 0;

    if (discountInput) {
        const val = discountInput.value.trim();
        if (val.endsWith('%')) {
            const pct = parseFloat(val.replace('%', '')) || 0;
            discount = (subtotal * pct) / 100;
        } else {
            discount = parseFloat(val) || 0;
        }
    }

    if (discount > subtotal) discount = subtotal;
    if (discount < 0) discount = 0;

    const grandTotal = Math.max(0, subtotal - discount);

    // Update UI elements
    const subtotalEl = document.getElementById('posSubtotal');
    const discountDisplayEl = document.getElementById('posDiscountDisplay');
    const grandTotalEl = document.getElementById('posGrandTotal');
    const completeBtnAmount = document.getElementById('btnPayAmount');

    if (subtotalEl) subtotalEl.innerText = formatCurrency(subtotal);
    if (discountDisplayEl) discountDisplayEl.innerText = formatCurrency(discount);
    if (grandTotalEl) grandTotalEl.innerText = formatCurrency(grandTotal);
    if (completeBtnAmount) completeBtnAmount.innerText = formatCurrency(grandTotal);

    // Auto-fill amount paid for cash if empty
    const amountPaidInput = document.getElementById('amountPaidInput');
    if (amountPaidInput && selectedPaymentMethod === 'cash' && (!amountPaidInput.value || parseFloat(amountPaidInput.value) < grandTotal)) {
        amountPaidInput.value = grandTotal.toFixed(2);
    }

    calculateChange();
}

function calculateChange() {
    const grandTotalEl = document.getElementById('posGrandTotal');
    const amountPaidInput = document.getElementById('amountPaidInput');
    const changeDisplayEl = document.getElementById('posChangeDisplay');

    if (!grandTotalEl || !amountPaidInput || !changeDisplayEl) return;

    const subtotal = cart.reduce((sum, item) => sum + (item.qty * item.unit_price), 0);
    const discountInput = document.getElementById('discountInput');
    let discount = 0;
    if (discountInput) {
        const val = discountInput.value.trim();
        if (val.endsWith('%')) {
            discount = (subtotal * (parseFloat(val) || 0)) / 100;
        } else {
            discount = parseFloat(val) || 0;
        }
    }
    const grandTotal = Math.max(0, subtotal - discount);
    const paid = parseFloat(amountPaidInput.value) || 0;

    const change = Math.max(0, paid - grandTotal);
    changeDisplayEl.innerText = formatCurrency(change);

    if (paid < grandTotal && selectedPaymentMethod === 'cash') {
        changeDisplayEl.classList.add('text-danger');
    } else {
        changeDisplayEl.classList.remove('text-danger');
    }
}

async function processSale() {
    if (cart.length === 0) {
        showToast('Please add items to cart first.', 'warning');
        return;
    }

    const branchId = getSelectedBranchId();
    if (!branchId) {
        showToast('Please select a branch.', 'error');
        return;
    }

    const subtotal = cart.reduce((sum, item) => sum + (item.qty * item.unit_price), 0);
    const discountInput = document.getElementById('discountInput');
    let discount = 0;
    if (discountInput) {
        const val = discountInput.value.trim();
        if (val.endsWith('%')) {
            discount = (subtotal * (parseFloat(val) || 0)) / 100;
        } else {
            discount = parseFloat(val) || 0;
        }
    }
    const grandTotal = Math.max(0, subtotal - discount);

    const amountPaidInput = document.getElementById('amountPaidInput');
    const amountPaid = selectedPaymentMethod === 'cash' ? (parseFloat(amountPaidInput.value) || grandTotal) : grandTotal;

    if (selectedPaymentMethod === 'cash' && amountPaid < grandTotal) {
        showToast('Paid amount is less than the total bill.', 'error');
        if (amountPaidInput) amountPaidInput.focus();
        return;
    }

    const completeBtn = document.getElementById('btnCompleteSale');
    if (completeBtn) {
        completeBtn.disabled = true;
        completeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Transaction...';
    }

    const payload = {
        branch_id: branchId,
        csrf_token: window.CSRF_TOKEN,
        cart: cart,
        discount: discount,
        payment_method: selectedPaymentMethod,
        amount_paid: amountPaid,
        notes: document.getElementById('posNotes') ? document.getElementById('posNotes').value : 'POS Sale'
    };

    try {
        const response = await fetch(`${window.BASE_URL}/modules/pos/process_sale.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            showToast(`Sale completed! Invoice: ${result.invoice_number}`, 'success');

            // Open printable receipt in pop-up
            const receiptUrl = `${window.BASE_URL}/modules/pos/receipt.php?id=${result.sale_id}&invoice=${encodeURIComponent(result.invoice_number)}`;
            window.open(receiptUrl, 'Receipt', 'width=450,height=650,menubar=no,toolbar=no,location=no');

            // Reset cart
            cart = [];
            if (discountInput) discountInput.value = '0';
            if (amountPaidInput) amountPaidInput.value = '';
            renderCart();

            // Refocus search
            const searchInput = document.getElementById('medSearchInput');
            if (searchInput) searchInput.focus();
        } else {
            showToast(result.message || 'Sale could not be completed.', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Network error processing sale. Please check server connection.', 'error');
    } finally {
        if (completeBtn) {
            completeBtn.disabled = false;
            completeBtn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale (<span id="btnPayAmount">' + formatCurrency(grandTotal) + '</span>)';
        }
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

