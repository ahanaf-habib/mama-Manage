// SAMS Main Script
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('sidebar-open');
    }
}

function confirmDelete(message = 'Are you sure you want to delete this record? This action cannot be undone.') {
    return confirm(message);
}

// Auto dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    }

    // Notice Target Type Switcher
    const noticeTargetType = document.getElementById('target_type');
    if (noticeTargetType) {
        const aptGroup = document.getElementById('apartment_field_group');
        const floorGroup = document.getElementById('floor_field_group');
        const aptSelect = document.getElementById('apartment_id');
        const floorSelect = document.getElementById('floor_number');

        function updateNoticeFields() {
            const val = noticeTargetType.value;
            if (val === 'Apartment') {
                if (aptGroup) aptGroup.style.display = 'block';
                if (floorGroup) floorGroup.style.display = 'none';
                if (aptSelect) aptSelect.required = true;
                if (floorSelect) { floorSelect.required = false; floorSelect.value = ''; }
            } else if (val === 'Floor') {
                if (aptGroup) aptGroup.style.display = 'none';
                if (floorGroup) floorGroup.style.display = 'block';
                if (aptSelect) { aptSelect.required = false; aptSelect.value = ''; }
                if (floorSelect) floorSelect.required = true;
            } else {
                if (aptGroup) aptGroup.style.display = 'none';
                if (floorGroup) floorGroup.style.display = 'none';
                if (aptSelect) { aptSelect.required = false; aptSelect.value = ''; }
                if (floorSelect) { floorSelect.required = false; floorSelect.value = ''; }
            }
        }
        noticeTargetType.addEventListener('change', updateNoticeFields);
        updateNoticeFields();
    }

    // Bill Total Auto Calculation
    const rentInput = document.getElementById('rent_amount');
    const utilityInput = document.getElementById('utility_charge');
    const maintInput = document.getElementById('maintenance_service_charge');
    const otherInput = document.getElementById('other_charge');
    const totalInput = document.getElementById('total_amount');

    if (rentInput && totalInput) {
        function calculateBillTotal() {
            const rent = parseFloat(rentInput.value) || 0;
            const utility = parseFloat(utilityInput ? utilityInput.value : 0) || 0;
            const maint = parseFloat(maintInput ? maintInput.value : 0) || 0;
            const other = parseFloat(otherInput ? otherInput.value : 0) || 0;
            totalInput.value = (rent + utility + maint + other).toFixed(2);
        }
        [rentInput, utilityInput, maintInput, otherInput].forEach(function(el) {
            if (el) el.addEventListener('input', calculateBillTotal);
        });
    }

    // Payment method reference field toggle
    const payMethod = document.getElementById('payment_method');
    const refInput = document.getElementById('transaction_reference_id');
    const refStar = document.getElementById('ref_required_star');
    if (payMethod && refInput) {
        function updatePayMethod() {
            if (payMethod.value === 'Cash') {
                if (refStar) refStar.style.display = 'none';
                refInput.required = false;
                refInput.placeholder = 'Optional for Cash';
            } else {
                if (refStar) refStar.style.display = 'inline';
                refInput.required = true;
                refInput.placeholder = 'Required for ' + payMethod.value;
            }
        }
        payMethod.addEventListener('change', updatePayMethod);
        updatePayMethod();
    }
});
