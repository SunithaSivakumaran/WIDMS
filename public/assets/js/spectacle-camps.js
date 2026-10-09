(() => {
    document.querySelectorAll('[data-sc-existing-participant]').forEach(select => {
        select.addEventListener('change', () => {
            const fields = JSON.parse(select.selectedOptions[0]?.dataset.participant || '{}');
            for (const key of ['full_name','gender','nic','elder_card_number','address','phone','gn_division_id']) {
                select.form.elements[key].value = fields[key] ?? '';
                select.form.elements[key].dispatchEvent(new Event('input'));
            }
            select.form.querySelectorAll('[name="spectacle_category_id"]').forEach(radio => {
                radio.checked = radio.value === String(fields.spectacle_category_id || '')
            })
            select.form.elements.decision.value = '';
            select.form.elements.reason.value = '';
            select.form.elements.decision.dispatchEvent(new Event('change'));
        });
    });
    document.querySelectorAll('form').forEach(form => {
        if (form.querySelector('input[name="action"]')?.value !== 'save-participant') return;
        const nic = form.elements.nic, elder = form.elements.elder_card_number;
        const help=document.getElementById('camp-identification-help');
        const conflict=form.querySelector('[data-sc-division-conflict]');
        const submit=form.querySelector('button[type="submit"]');
        let divisionMessage='', checking=false;
        const identity=()=>`${nic.value.trim().toUpperCase()}|${elder.value.trim().toUpperCase()}`;
        const showConflict=message=>{
            divisionMessage=message;
            conflict.textContent=message;
            conflict.hidden=!message;
            nic.setCustomValidity(message || (nic.value.trim() || elder.value.trim() ? '' : help.textContent));
            if (submit) submit.disabled=Boolean(message || checking);
        };
        const checkDivision=async()=>{
            if (!nic.value.trim() && !elder.value.trim()) { showConflict(''); return true; }
            const checkIdentity=identity(); checking=true; if (submit) submit.disabled=true;
            try {
                const campId=form.elements.camp_id?.value || '';
                const query=new URLSearchParams({camp_id:campId,nic:nic.value.trim(),elder_card_number:elder.value.trim(),participant_id:form.elements.participant_id?.value || ''});
                const response=await fetch(`vision-camp-beneficiary-division-check.php?${query}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
                const payload=await response.json();
                if (identity()!==checkIdentity) return false;
                showConflict(payload.valid ? '' : (payload.message || 'This beneficiary belongs to another DS Division.'));
                return Boolean(payload.valid);
            } catch (_) {
                if (identity()===checkIdentity) showConflict('Unable to validate the beneficiary division. Please try again.');
                return false;
            } finally {
                checking=false;
                if (!divisionMessage && submit) submit.disabled=false;
            }
        };
        const sync=()=>{
            divisionMessage=''; conflict.hidden=true; conflict.textContent='';
            nic.setCustomValidity(nic.value.trim() || elder.value.trim() ? '' : help.textContent);
            if (submit) submit.disabled=false;
        };
        nic.addEventListener('input', sync); elder.addEventListener('input', sync);
        nic.addEventListener('blur', checkDivision); elder.addEventListener('blur', checkDivision);
        form.addEventListener('submit',async event=>{
            if (divisionMessage) { event.preventDefault(); nic.reportValidity(); return; }
            if (!nic.value.trim() && !elder.value.trim()) return;
            event.preventDefault();
            if (await checkDivision()) HTMLFormElement.prototype.submit.call(form);
        });
        sync();
    });
    document.querySelectorAll('[data-sc-district]').forEach(district => {
        const division = district.form.querySelector('[data-sc-division]');
        const sync = () => {
            for (const option of division.options) {
                if (!option.value) continue;
                option.hidden = option.disabled = option.dataset.district !== district.value;
                if (option.selected && option.disabled) division.value = '';
            }
        };
        district.addEventListener('change', sync); sync();
    });
    document.querySelectorAll('[data-sc-decision]').forEach(decision => {
        const sync = () => {
            const reason = decision.form.querySelector('[data-sc-rejection-reason]');
            if (reason) reason.required = decision.value === 'rejected';
            decision.form.querySelectorAll('[data-sc-approval-only]').forEach(field => {
                field.required = decision.value === 'approved';
                field.disabled = decision.value !== 'approved';
            });
            const approvedSection = decision.form.querySelector('[data-sc-approved-section]');
            const rejectedSection = decision.form.querySelector('[data-sc-rejected-section]');
            if (approvedSection && rejectedSection) {
                approvedSection.hidden = decision.value !== 'approved';
                rejectedSection.hidden = decision.value !== 'rejected';
                if (reason) reason.disabled = decision.value !== 'rejected';
            }
        };
        decision.addEventListener('change',sync); sync();
    });
    document.querySelectorAll('[data-sc-search]').forEach(search => {
        const section=search.closest('.sc-table-section');
        const rows=[...section.querySelectorAll('[data-sc-row]')];
        const statusFilter=section.querySelector('[data-sc-status-filter]');
        const campStatusFilter=section.querySelector('[data-sc-camp-status-filter]');
        const campDistrictFilter=section.querySelector('[data-sc-camp-district-filter]');
        const stockStatusFilter=section.querySelector('[data-sc-stock-status-filter]');
        const distributionStatusFilter=section.querySelector('[data-sc-distribution-status-filter]');
        const sync=()=>{
            const term=search.value.trim().toLocaleLowerCase(); let visible=0;
            rows.forEach(row=>{
                row.hidden=Boolean(!row.textContent.toLocaleLowerCase().includes(term)
                    || (statusFilter && statusFilter.value!=='' && row.dataset.scStatus!==statusFilter.value)
                    || (campStatusFilter && campStatusFilter.value!=='' && row.dataset.scCampStage!==campStatusFilter.value)
                    || (campDistrictFilter && campDistrictFilter.value!=='' && row.dataset.scCampDistrict!==campDistrictFilter.value)
                    || (stockStatusFilter && stockStatusFilter.value!=='' && !(row.dataset.scStockStatus || '').split(' ').includes(stockStatusFilter.value))
                    || (distributionStatusFilter && distributionStatusFilter.value!=='' && row.dataset.scDistributionStatus!==distributionStatusFilter.value));
                if(!row.hidden)visible++;
            });
            section.querySelector('[data-sc-empty]').hidden=visible>0;
        };
        search.addEventListener('input',sync);
        if(statusFilter)statusFilter.addEventListener('change',sync);
        if(campStatusFilter)campStatusFilter.addEventListener('change',sync);
        if(campDistrictFilter)campDistrictFilter.addEventListener('change',sync);
        if(stockStatusFilter)stockStatusFilter.addEventListener('change',sync);
        if(distributionStatusFilter)distributionStatusFilter.addEventListener('change',sync);
    });
    document.querySelectorAll('[data-sc-approval-search]').forEach(search => {
        const section = search.closest('.sc-approval-list');
        const cards = [...section.querySelectorAll('[data-sc-approval-card]')];
        const empty = section.querySelector('[data-sc-approval-empty]');
        const sync = () => {
            const term = search.value.trim().toLocaleLowerCase();
            let visible = 0;
            cards.forEach(card => {
                card.hidden = !card.textContent.toLocaleLowerCase().includes(term);
                if (!card.hidden) visible++;
            });
            if (empty) empty.hidden = visible > 0;
        };
        search.addEventListener('input', sync);
    });
    const completionForms=[...document.querySelectorAll('[data-sc-complete-form]')];
    if (completionForms.length) {
        const dialog=document.getElementById('sc-complete-dialog');
        let pendingForm=null;
        const close=()=>{
            pendingForm=null;
            if (dialog.open) dialog.close();
        };
        const confirm=()=>{
            const form=pendingForm;
            if (!form) return;
            close();
            // This action contains only server-validated hidden fields. A second
            // submit event can be stopped by the shared double-submit guard, so
            // send the already-confirmed form directly.
            HTMLFormElement.prototype.submit.call(form);
        };
        completionForms.forEach(form=>form.addEventListener('submit',event=>{
            event.preventDefault();
            pendingForm=form;
            dialog.querySelector('[data-sc-complete-camp]').textContent=form.dataset.campReference || 'this camp';
            if (typeof dialog.showModal==='function') dialog.showModal();
            else if (window.confirm(dialog.querySelector('#sc-complete-description').textContent.trim())) confirm();
            else pendingForm=null;
        }));
        dialog.querySelector('[data-sc-complete-cancel]').addEventListener('click',close);
        dialog.querySelector('[data-sc-complete-confirm]').addEventListener('click',confirm);
        dialog.addEventListener('cancel',()=>{ pendingForm=null; });
        dialog.addEventListener('click',event=>{ if (event.target===dialog) close(); });
    }
    const handoverForms=[...document.querySelectorAll('[data-sc-handover-form]')];
    if (handoverForms.length) {
        const dialog=document.getElementById('sc-handover-dialog');
        let pendingForm=null;
        const close=()=>{
            pendingForm=null;
            if (dialog.open) dialog.close();
        };
        const confirm=()=>{
            const form=pendingForm;
            if (!form) return;
            close();
            HTMLFormElement.prototype.submit.call(form);
        };
        handoverForms.forEach(form=>form.addEventListener('submit',event=>{
            event.preventDefault();
            pendingForm=form;
            dialog.querySelector('[data-sc-handover-camp]').textContent=form.dataset.campReference || 'this camp';
            if (typeof dialog.showModal==='function') dialog.showModal();
            else if (window.confirm(dialog.querySelector('#sc-handover-description').textContent.trim())) confirm();
            else pendingForm=null;
        }));
        dialog.querySelector('[data-sc-handover-cancel]').addEventListener('click',close);
        dialog.querySelector('[data-sc-handover-confirm]').addEventListener('click',confirm);
        dialog.addEventListener('cancel',()=>{ pendingForm=null; });
        dialog.addEventListener('click',event=>{ if (event.target===dialog) close(); });
    }
})();
