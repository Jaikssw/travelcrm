<?php
session_start();
require_once 'config/config.php';
require_once BASE_PATH . '/includes/auth_validate.php';

if (empty($_SESSION['admin_type'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Asia/Kolkata');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$owners = [
    "super","afreen","shilpa","kirti","kajal","manoj","swati","santosh","anamika",
    "neelam","deepti","tripti","dilpreet","rishabhsaini","rahul","vanshikap",
    "vikram","adrija","atharva","bhumika","darshita","harsh","khushi","prajakta",
    "suhrid","vanshika"
];

include BASE_PATH . '/includes/header.php';
?>

<style>
.manual-lead-wrap {
    padding: 18px;
}

.querywhitebox {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
}

.manual-title {
    margin: 0;
    padding: 15px 22px;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
    font-size: 20px;
    font-weight: 700;
    text-transform: uppercase;
}

.manual-body {
    padding: 20px;
}

.manual-body label {
    width: 100%;
    margin-bottom: 3px;
    font-size: 12px;
    text-transform: uppercase;
    font-weight: 700;
    color: #334155;
}

.manual-body .form-group {
    margin-bottom: 14px;
}

.manual-body .form-control {
    border-radius: 8px;
    min-height: 38px;
}

.manual-body .input-group-text {
    min-width: 42px;
    justify-content: center;
    background: #f1f5f9;
    border-color: #dbeafe;
}

.redmtext {
    color: #dc2626;
}

.redborder {
    border-color: #fecaca;
}

.redborder:focus {
    border-color: #dc2626;
    box-shadow: 0 0 0 .2rem rgba(220, 38, 38, .12);
}

.addon-check {
    display: inline-block !important;
    width: auto !important;
    margin-right: 18px;
    margin-bottom: 8px;
    font-size: 13px !important;
    text-transform: none !important;
    font-weight: 500 !important;
}

.manual-footer {
    border-top: 1px solid #e5e7eb;
    padding: 12px 20px;
    background: #f8fafc;
    text-align: right;
}

.result-box {
    display: none;
    margin-top: 12px;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
}

.result-success {
    background: #dcfce7;
    color: #166534;
}

.result-error {
    background: #fee2e2;
    color: #991b1b;
}
</style>

<div id="page-wrapper" class="manual-lead-wrap">
    <div class="querywhitebox">
        <h4 class="manual-title">Create Query</h4>

        <div class="manual-body">
            <form id="manualLeadForm">

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group input-group">
                            <label>Mobile <span class="redmtext">*</span></label>
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-mobile"></i></span>
                            </div>
                            <input type="text" maxlength="10" id="mobile" name="mobile" class="form-control redborder" placeholder="Phone / Mobile" required>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group input-group">
                            <label>
                                Whatsapp
                                <span style="float:right;">
                                    <input name="same_as_mobile" id="same_as_mobile" type="checkbox" value="1">
                                    Same as Mobile
                                </span>
                            </label>
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-whatsapp"></i></span>
                            </div>
                            <input type="text" maxlength="10" id="wNumber" name="wNumber" class="form-control" placeholder="Whatsapp Number">
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group input-group">
                            <label>Email</label>
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                            </div>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Email">
                        </div>
                    </div>

                    <div class="col-lg-2">
                        <div class="form-group">
                            <label>Title</label>
                            <select name="submitName" id="submitName" class="form-control">
                                <option value="Mr.">Mr.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Ms.">Ms.</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Prof.">Prof.</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group input-group">
                            <label>Client Name <span class="redmtext">*</span></label>
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                            </div>
                            <input type="text" id="name" name="name" class="form-control redborder" placeholder="Name" required>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label>Destination <span class="redmtext">*</span></label>
                            <input type="text" id="destination" name="destination" class="form-control redborder" placeholder="Destination" required>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label>Lead Source <span class="redmtext">*</span></label>
                            <select id="leadSource" name="leadSource" class="form-control" required>
                                <option value="Facebook">Facebook</option>
                                <option value="interakt">Interakt</option>
                                <option value="Website">Website</option>
                                <option value="WhatsApp">WhatsApp</option>
                                <option value="Direct Call">Direct Call</option>
                                <option value="Referral">Referral</option>
                                <option value="Email">Email</option>
                                <option value="Instagram">Instagram</option>
                                <option value="Manual">Manual</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label>Campaign Name</label>
                            <input type="text" id="campaign" name="campaign" class="form-control" placeholder="Campaign Name">
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label>Campaign ID</label>
                            <input type="text" id="campaign_id" name="campaign_id" class="form-control" placeholder="Campaign ID">
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label>Assign To <span class="redmtext">*</span></label>
                            <select id="assigned_to" name="assigned_to" class="form-control" required>
                                <option value="">Select User</option>
                                <?php foreach ($owners as $owner): ?>
                                    <option value="<?= h($owner) ?>"><?= h(ucfirst($owner)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label>Number of Pax</label>
                            <input type="number" id="pax" name="pax" class="form-control" placeholder="Pax" min="0">
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="form-group">
                            <label>Remark</label>
                            <textarea name="details" id="details" rows="3" class="form-control" placeholder="Remark"></textarea>
                        </div>
                    </div>
                </div>

                <div id="manualLeadResult" class="result-box"></div>

                <div class="manual-footer">
                    <a href="query.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" id="saveLeadBtn" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
function makeRowKey() {
    const chars = 'abcdef0123456789';
    let out = '';
    for (let i = 0; i < 32; i++) {
        out += chars[Math.floor(Math.random() * chars.length)];
    }
    return out;
}

function onlyDigits(v) {
    return String(v || '').replace(/\D/g, '').slice(-10);
}

function showResult(ok, msg) {
    const box = document.getElementById('manualLeadResult');
    box.style.display = 'block';
    box.className = 'result-box ' + (ok ? 'result-success' : 'result-error');
    box.textContent = msg;
}

document.getElementById('same_as_mobile').addEventListener('change', function () {
    const mobile = document.getElementById('mobile');
    const wNumber = document.getElementById('wNumber');

    if (this.checked) {
        wNumber.value = mobile.value;
        wNumber.setAttribute('readonly', 'readonly');
    } else {
        wNumber.value = '';
        wNumber.removeAttribute('readonly');
    }
});

document.getElementById('mobile').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);

    if (document.getElementById('same_as_mobile').checked) {
        document.getElementById('wNumber').value = this.value;
    }
});

document.getElementById('wNumber').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

document.getElementById('manualLeadForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const btn = document.getElementById('saveLeadBtn');
    btn.disabled = true;
    btn.innerHTML = 'Saving...';

    const mobile = onlyDigits(document.getElementById('mobile').value);
    const wNumber = onlyDigits(document.getElementById('wNumber').value);
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const destination = document.getElementById('destination').value.trim();
    const leadSource = document.getElementById('leadSource').value.trim();
    const campaign = document.getElementById('campaign').value.trim();
    const campaignId = document.getElementById('campaign_id').value.trim();
    const assignedTo = document.getElementById('assigned_to').value.trim();
    const pax = document.getElementById('pax').value.trim();

    if (!mobile || mobile.length !== 10) {
        showResult(false, 'Please enter valid 10 digit mobile number.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-save"></i> Save';
        return;
    }

    if (!name || !destination || !assignedTo) {
        showResult(false, 'Name, destination and assign to are required.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-save"></i> Save';
        return;
    }

    const now = new Date();
    const createdIst = now.getFullYear() + '-' +
        String(now.getMonth() + 1).padStart(2, '0') + '-' +
        String(now.getDate()).padStart(2, '0') + ' ' +
        String(now.getHours()).padStart(2, '0') + ':' +
        String(now.getMinutes()).padStart(2, '0') + ':' +
        String(now.getSeconds()).padStart(2, '0');

    const payload = {
        row_key: makeRowKey(),
        phone10: mobile,
        phone_raw: mobile,
        wNumber: wNumber || mobile,
        created_ist: createdIst,
        destination: destination,
        name: name,
        email: email,
        campaign: campaign,
        campaign_id: campaignId,
        lead_source: leadSource,
        lead_source_label: leadSource,
        pax: pax,
        assigned_to: assignedTo
    };

    fetch('assign_lead.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            showResult(true, 'Lead added successfully.');
            document.getElementById('manualLeadForm').reset();

            setTimeout(function () {
                window.location.href = 'query.php';
            }, 1000);
        } else {
            showResult(false, data.error || 'Unable to add lead.');
        }
    })
    .catch(function () {
        showResult(false, 'Network error. Please try again.');
    })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-save"></i> Save';
    });
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>