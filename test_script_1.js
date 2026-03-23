
        // Tab Navigation & Search Logic
        document.addEventListener('DOMContentLoaded', () => {
            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            const sections = document.querySelectorAll('.dashboard-section');
            
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    const targetId = link.getAttribute('data-target');
                    if (targetId) {
                        e.preventDefault();
                        sections.forEach(sec => sec.classList.add('d-none'));
                        const target = document.getElementById(targetId);
                        if (target) target.classList.remove('d-none');
                        links.forEach(l => l.classList.remove('active'));
                        link.classList.add('active');
                    }
                });
            });

            // Search Logic
            const searchInput = document.getElementById('patientSearchInput');
            const searchResults = document.getElementById('searchResults');
            let searchTimeout;

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    const query = e.target.value;
                    if (query.length < 2) {
                        searchResults.innerHTML = '<p class="text-center text-muted py-3 small">Enter at least 2 characters...</p>';
                        return;
                    }

                    searchTimeout = setTimeout(async () => {
                        searchResults.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
                        try {
                            const res = await fetch(`/api/search_patients?q=${encodeURIComponent(query)}`);
                            const data = await res.json();
                            
                            if (data.length === 0) {
                                searchResults.innerHTML = '<p class="text-center text-muted py-3 small">No patients found matching your search.</p>';
                                return;
                            }

                            searchResults.innerHTML = data.map(p => `
                                <a href="/emr.php?patient_id=${p.id}" class="list-group-item list-group-item-action border-0 rounded-4 mb-2 p-3 d-flex align-items-center bg-light">
                                    <div class="bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; min-width: 40px;">
                                        ${p.name.charAt(0)}
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-0">${p.name}</h6>
                                        <small class="text-muted d-block" style="font-size: 0.75rem;">${p.email || 'No email'}</small>
                                        <small class="text-primary extra-small">ID: ${p.id.substring(0, 13)}...</small>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted ms-auto"></i>
                                </a>
                            `).join('');
                        } catch (err) {
                            searchResults.innerHTML = '<p class="text-center text-danger py-3 small">Error searching. Please try again.</p>';
                        }
                    }, 300);
                });
            }
        });

        function editStaff(staffBase64) {
            try {
                const staff = JSON.parse(atob(staffBase64));
                document.getElementById('edit_user_id').value = staff.id;
                document.getElementById('edit_name').value = staff.name;
                document.getElementById('edit_email').value = staff.email;
                document.getElementById('edit_role').value = staff.role;
                document.getElementById('edit_department').value = staff.department;
                
                const editModal = new bootstrap.Modal(document.getElementById('editStaffModal'));
                editModal.show();
            } catch (e) {
                console.error("Error parsing staff data:", e);
                alert("Could not load staff details. Please try again.");
            }
        }

        function openLinkModal(patientId, patientName) {
            document.getElementById('link_patient_id').value = patientId;
            document.getElementById('link_patient_name').value = patientName;
            const linkModal = new bootstrap.Modal(document.getElementById('linkGuardianModal'));
            linkModal.show();
        }

        function openLinkModalDirect(guardianId, guardianName) {
            // Re-use linkGuardianModal but swap fields? Better to have a dedicated one or generic
            // For now, let's just alert that it's coming soon or use the same one logic
            alert("To link " + guardianName + ", please go to the Patient Directory and click '+ Link' next to the patient.");
        }

        async function approveLink(linkId) {
            if (!confirm("Approve this guardian-patient link?")) return;
            const fd = new FormData();
            fd.append('link_id', linkId);
            fd.append('action', 'approve');

            const res = await fetch('/api/admin/approve_guardian.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Link approved successfully!");
                silentRefresh();
            } else {
                alert("Error: " + data.error);
            }
        }

        async function removeGuardianLink(linkId) {
            if (!confirm("Are you sure you want to remove this guardian-patient link? This cannot be undone.")) return;
            const res = await fetch('/api/admin/remove_guardian_link.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ link_id: linkId })
            });
            const data = await res.json();
            if (data.success) {
                alert("Link removed successfully.");
                silentRefresh();
            } else {
                alert("Error removing link: " + (data.message || 'Unknown error'));
            }
        }

        function openAssignModal(apptId, department) {
            document.getElementById('assign_appt_id').value = apptId;
            document.getElementById('assign_dept_display').value = department;
            
            // Filter dropdown to show relevant department first (optional UX improvement)
            const select = document.getElementById('assign_staff_select');
            const options = select.options;
            for (let i = 1; i < options.length; i++) {
                const optDept = options[i].getAttribute('data-dept');
                if (optDept === department) {
                    options[i].style.fontWeight = 'bold';
                    options[i].text = "⭐ " + options[i].text.replace("⭐ ", "");
                }
            }

            const assignModal = new bootstrap.Modal(document.getElementById('assignStaffModal'));
            assignModal.show();
        }

        async function viewInvoiceDetails(id, name) {
            document.getElementById('detail_patient_name').innerText = name;
            const container = document.getElementById('invoice_items_container');
            container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
            
            const modal = new bootstrap.Modal(document.getElementById('invoiceDetailsModal'));
            modal.show();

            try {
                const res = await fetch(`/api/billing/get_invoice_details.php?id=${id}`);
                const data = await res.json();
                
                if (data.items && data.items.length > 0) {
                    let html = '<div class="list-group list-group-flush">';
                    data.items.forEach(item => {
                        html += `
                            <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                <div>
                                    <h6 class="mb-0 small fw-bold">${item.description}</h6>
                                    <small class="text-muted extra-small">${new Date(item.created_at).toLocaleDateString()}</small>
                                </div>
                                <span class="fw-bold text-primary">₵ ${parseFloat(item.amount).toFixed(2)}</span>
                            </div>
                        `;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p class="text-center text-muted py-4">No items found for this invoice.</p>';
                }
            } catch (e) {
                container.innerHTML = '<p class="text-center text-danger py-4">Error loading details.</p>';
            }
        }

        function openInventoryModal(action, drug = null) {
            const modalTitle = document.getElementById('invModalTitle');
            const actionInput = document.getElementById('inv_action');
            const idInput = document.getElementById('inv_id');
            const delBtn = document.getElementById('inv_delete_btn');

            if (action === 'add') {
                modalTitle.innerText = 'Add New Drug Stock';
                actionInput.value = 'add';
                idInput.value = '';
                document.getElementById('inv_name').value = '';
                document.getElementById('inv_stock').value = '';
                document.getElementById('inv_price').value = '';
                document.getElementById('inv_category').value = 'General';
                delBtn.classList.add('d-none');
            } else {
                modalTitle.innerText = 'Update Drug: ' + drug.drug_name;
                actionInput.value = 'update';
                idInput.value = drug.id;
                document.getElementById('inv_name').value = drug.drug_name;
                document.getElementById('inv_stock').value = drug.stock_count;
                document.getElementById('inv_price').value = drug.unit_price;
                document.getElementById('inv_category').value = drug.category || 'General';
                delBtn.classList.remove('d-none');
            }

            const modal = new bootstrap.Modal(document.getElementById('inventoryModal'));
            modal.show();
        }

        async function syncStaffData() {
            if (!confirm("This will scan for missing staff profiles and restore them. Continue?")) return;
            
            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Syncing...';

            try {
                const response = await fetch('/api/admin/reconcile_staff.php');
                const result = await response.json();
                
                if (result.success) {
                    let logSummary = result.logs ? "\n\nDetails:\n" + result.logs.slice(0, 10).join("\n") : "";
                    if (result.logs && result.logs.length > 10) logSummary += "\n...and more.";
                    
                    alert(`Sync Complete!\nReconciled: ${result.reconciled_count}\nSkipped: ${result.skipped_count}\nTotal Checked: ${result.total_checked}${logSummary}`);
                    silentRefresh();
                } else {
                    alert("Sync failed: " + (result.error || "Unknown error"));
                }
            } catch (e) {
                console.error(e);
                alert("An error occurred during synchronization.");
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        function navigateTo(targetId) {
            document.querySelectorAll('.dashboard-section').forEach(s => s.classList.add('d-none'));
            document.getElementById(targetId).classList.remove('d-none');
            document.querySelectorAll('.nav-link-custom').forEach(l => {
                if(l.getAttribute('data-target') === targetId) l.classList.add('active');
                else l.classList.remove('active');
            });
        }

        function respondToEmergency(id) {
            // Simulate clicking the sidebar link for emergencies
            const emergencyLink = document.querySelector('#sidebarMenu .nav-link-custom[data-target="section-emergencies"]');
            if (emergencyLink) {
                emergencyLink.click(); // This will trigger the section navigation
            }
            
            // Close sidebar if open (for mobile)
            toggleSidebar();

            setTimeout(() => {
                const row = document.getElementById('emerg-row-' + id);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('bg-primary-soft', 'pulse-highlight');
                    setTimeout(() => row.classList.remove('bg-primary-soft', 'pulse-highlight'), 3000);
                }
            }, 300);
        }

        async function markNotificationRead(el, id) {
            if (el.classList.contains('bg-light')) {
                await fetch('/api/notifications/read.php?id=' + id, {method: 'POST'});
                el.classList.remove('bg-light');
                const p = el.querySelector('p');
                if (p) {
                    p.classList.remove('fw-bold', 'text-dark');
                    p.classList.add('text-muted');
                }
                el.style.cursor = 'default';
                el.onclick = null;
                
                // Update badge count
                document.querySelectorAll('.top-notif-badge').forEach(badge => {
                    let count = (parseInt(badge.innerText) || 0) - 1;
                    if (count <= 0) badge.remove();
                    else badge.innerText = count;
                });
            }
        }

        async function clearEmergencyTask(notificationId, btn) {
            btn.disabled = true;
            btn.innerHTML = '...';
            try {
                const fd = new FormData();
                fd.append('notification_id', notificationId);
                const res = await fetch('/api/emergency/clear_task.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    const item = btn.closest('.p-2');
                    if (item) {
                        item.style.transition = 'opacity 0.4s';
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 400);
                    }
                    // Update badge count if it was unread
                    const isUnread = btn.closest('.bg-light');
                    if (isUnread) {
                        document.querySelectorAll('.top-notif-badge').forEach(badge => {
                            let count = (parseInt(badge.innerText) || 0) - 1;
                            if (count <= 0) badge.remove();
                            else badge.innerText = count;
                        });
                    }
                } else {
                    btn.disabled = false;
                    btn.innerHTML = 'Clear';
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = 'Clear';
            }
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.sidebar-overlay').classList.toggle('show');
        }

        // Auto-close sidebar on mobile link click
        document.querySelectorAll('.nav-link-custom').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    document.querySelector('.sidebar').classList.remove('show');
                    document.querySelector('.sidebar-overlay').classList.remove('show');
                }
            });
        });
        async function dischargePatient(admId, wardId) {
            if (!confirm("Are you sure you want to discharge this patient? This will free up the bed.")) return;
            const fd = new FormData();
            fd.append('admission_id', admId);
            fd.append('ward_id', wardId);
            
            const res = await fetch('/api/admission/discharge.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Patient discharged and bed freed.");
                silentRefresh();
            } else {
                alert("Error: " + data.error);
            }
        }

        function openAssignBedModal(ptId, ptName) {
            document.getElementById('assign_bed_patient_id').value = ptId;
            document.getElementById('assign_bed_patient_name').innerText = ptName;
            new bootstrap.Modal(document.getElementById('assignBedModal')).show();
        }

        async function submitBedAssignment() {
            const form = document.getElementById('assignBedForm');
            const ptId = document.getElementById('assign_bed_patient_id').value;
            const wardId = document.getElementById('assign_bed_ward_select').value;
            const bedNum = document.getElementById('assign_bed_number').value;

            if (!wardId || !bedNum) { alert("Please select ward and bed number"); return; }

            const fd = new FormData();
            fd.append('patient_id', ptId);
            fd.append('ward_id', wardId);
            fd.append('bed_number', bedNum);
            
            const res = await fetch('/api/admission/finalize_assignment.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Bed assigned successfully.");
                silentRefresh();
            } else {
                alert("Error: " + data.error);
                if (data.debug) console.error("API Debug Info:", data.debug);
            }
        }

        function openEditAdmissionModal(adm) {
            document.getElementById('edit_adm_id').value = adm.id;
            document.getElementById('edit_adm_old_ward').value = adm.ward_id;
            document.getElementById('edit_adm_patient_name').innerText = adm.patient ? adm.patient.name : 'Patient';
            document.getElementById('edit_adm_ward_select').value = adm.ward_id;
            document.getElementById('edit_adm_bed_number').value = adm.bed_number;
            new bootstrap.Modal(document.getElementById('editAdmissionModal')).show();
        }

        async function submitBedUpdate() {
            const fd = new FormData(document.getElementById('editAdmissionForm'));
            const res = await fetch('/api/admission/update_assignment.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Admission details updated.");
                silentRefresh();
            } else {
                alert("Error: " + data.error);
            }
        }

        // EMERGENCY MANAGEMENT JS
        function openAssignEmergencyModal(e) {
            document.getElementById('assign_emerg_id').value = e.id;
            new bootstrap.Modal(document.getElementById('assignEmergencyModal')).show();
        }

        async function submitEmergencyAssignment() {
            const fd = new FormData(document.getElementById('assignEmergencyForm'));
            const res = await fetch('/api/emergency/assign.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Staff assigned to emergency.");
                silentRefresh();
            } else {
                alert("Error: " + data.error);
            }
        }

        function openDispatchEmergencyModal(e) {
            document.getElementById('dispatch_emerg_id').value = e.id;
            const dispatchModalEl = document.getElementById('dispatchEmergencyModal');
            const typeSelect = dispatchModalEl.querySelector('select[name="dispatch_type"]');
            const medSection = document.getElementById('riderMedicationSection');
            
            typeSelect.onchange = () => {
                if(typeSelect.value === 'rider') {
                    medSection.classList.remove('d-none');
                } else {
                    medSection.classList.add('d-none');
                }
            };
            // Trigger once for initial state
            if(typeSelect.value === 'rider') medSection.classList.remove('d-none');
            else medSection.classList.add('d-none');

            new bootstrap.Modal(dispatchModalEl).show();
        }

        let emergMedCount = 1;
        function addEmergencyMedication() {
            const container = document.getElementById('emergency-med-list');
            const firstItem = container.querySelector('.med-item');
            const newItem = firstItem.cloneNode(true);
            
            newItem.querySelector('.remove-med-btn').classList.remove('d-none');
            newItem.querySelector('.small.fw-bold.text-muted').innerText = 'Medication #' + (container.querySelectorAll('.med-item').length + 1);
            
            newItem.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
            newItem.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
            newItem.querySelectorAll('input[type="number"]').forEach(input => input.value = '1');
            
            newItem.querySelectorAll('[name^="meds["]').forEach(el => {
                const newName = el.getAttribute('name').replace(/meds\[\d+\]/, `meds[${emergMedCount}]`);
                el.setAttribute('name', newName);
            });
            
            container.appendChild(newItem);
            emergMedCount++;
        }

        async function submitEmergencyDispatch() {
            const emergId = document.getElementById('dispatch_emerg_id').value;
            const fd = new FormData(document.getElementById('dispatchEmergencyForm'));
            const res = await fetch('/api/emergency/dispatch.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('dispatchEmergencyModal')).hide();
                const row = document.getElementById('emerg-row-' + emergId);
                if (row) {
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                }
            } else {
                alert("Error: " + data.error);
            }
        }

        async function resolveEmergency(id) {
            if(!confirm("Mark this emergency as resolved/completed?")) return;
            const fd = new FormData();
            fd.append('emergency_id', id);
            fd.append('resolution_notes', 'Resolved by admin.');
            const res = await fetch('/api/emergency/resolve.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                const row = document.getElementById('emerg-row-' + id);
                if (row) {
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                }
            } else {
                alert("Error: " + data.error);
            }
        }
        async function submitAddStaff(e) {
            e.preventDefault();
            const form = document.getElementById('addStaffForm');
            const btn = form.querySelector('button[type="submit"]');
            const oriText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
            btn.disabled = true;

            try {
                const fd = new FormData(form);
                const res = await fetch('/api/admin/add_staff.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    const modalEl = document.getElementById('addStaffModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    form.reset();
                    if (typeof silentRefresh === 'function') silentRefresh();
                    else location.reload();
                } else {
                    alert("Error: " + (data.error || "Failed to create account."));
                }
            } catch (err) {
                alert("A server error occurred.");
            } finally {
                btn.innerHTML = oriText;
                btn.disabled = false;
            }
        }
    