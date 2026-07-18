<?php
require_once __DIR__ . '/db_connect.php';
$pet_id = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Adoption Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body { font-family: Arial, sans-serif; background:#f6f8fa; color:#111; padding:20px; }
        .container { max-width:900px; margin:0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.06); }
        h1 { margin-top:0 }
        label { display:block; margin-top:12px; font-weight:600 }
        input[type=text], input[type=email], input[type=date], input[type=time], textarea, select { width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; }
        .row { display:flex; gap:12px }
        .col { flex:1 }
        .small { width:140px }
        .muted { color:#666; font-size:0.9em }
        .actions { margin-top:18px }
        button { background:#2b6cb0; color:white; border:none; padding:10px 16px; border-radius:6px; cursor:pointer }
        .success { background:#e6ffed; border:1px solid #2ecc71; padding:10px; margin-top:12px }
        .error { background:#ffe6e6; border:1px solid #e74c3c; padding:10px; margin-top:12px }
        .files-list img { max-width:120px; margin-right:8px; margin-top:8px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Adoption Application</h1>
    <p class="muted">Fields marked * are required. This form is modeled after the PAWS adoption application.</p>

    <form id="appForm" enctype="multipart/form-data">
        <input type="hidden" name="pet_id" value="<?php echo htmlspecialchars($pet_id); ?>" />
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>" />

        <h3>Applicant's Info</h3>
        <label>Name *</label>
        <input name="applicant_name" type="text" required />

        <label>Address *</label>
        <input name="address" type="text" required />

        <div class="row">
            <div class="col">
                <label>Phone *</label>
                <input name="phone" type="text" required />
            </div>
            <div class="col">
                <label>Email *</label>
                <input name="email" type="email" required />
            </div>
        </div>

        <label>Preferred Interaction Method *</label>
        <select name="interaction_method" id="interactionMethod" required>
            <option value="Email">Email</option>
            <option value="Phone">Phone</option>
            <option value="Zoom">Zoom</option>
        </select>

        <div class="row">
            <div class="col">
                <label>Birth Date *</label>
                <input name="birth_date" type="date" required />
            </div>
            <div class="col">
                <label>Occupation</label>
                <input name="occupation" type="text" />
            </div>
        </div>

        <label>Company / Business Name *</label>
        <input name="company" type="text" placeholder="Type N/A if unemployed" required />

        <label>Social Media Profile</label>
        <input name="social_profile" type="text" placeholder="Type N/A if none" />

        <div class="row">
            <div class="col">
                <label>Status *</label>
                <select name="status" required>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col">
                <label>Pronouns *</label>
                <select name="pronouns" required>
                    <option>She/her</option>
                    <option>He/him</option>
                    <option>They/them</option>
                </select>
            </div>
        </div>

        <label>What prompted you to adopt from us? *</label>
        <select name="prompted_by" required>
            <option>Friends</option>
            <option>Website</option>
            <option>Social Media</option>
            <option>Other</option>
        </select>

        <label>Have you adopted from us before? *</label>
        <select name="adopted_before" required>
            <option value="No">No</option>
            <option value="Yes">Yes</option>
        </select>

        <h3>Alternate Contact</h3>
        <label>Name</label>
        <input name="alt_name" type="text" />
        <label>Relationship</label>
        <input name="alt_relation" type="text" />
        <label>Phone</label>
        <input name="alt_phone" type="text" />
        <label>Email</label>
        <input name="alt_email" type="email" />

        <h3>Questionnaire</h3>
        <label>What are you looking to adopt? *</label>
        <select name="looking_for" required>
            <option>Cat</option>
            <option>Dog</option>
            <option>Both</option>
            <option>Not decided</option>
        </select>

        <label>Are you applying to adopt a specific shelter animal? *</label>
        <select name="specific_animal" required>
            <option value="No">No</option>
            <option value="Yes">Yes</option>
        </select>

        <label>Describe your ideal pet *</label>
        <textarea name="ideal_pet" rows="4" required></textarea>

        <label>Type of building you live in *</label>
        <select name="building_type" required>
            <option>House</option>
            <option>Apartment</option>
            <option>Condo</option>
            <option>Other</option>
        </select>

        <label>Do you rent? *</label>
        <select name="do_rent" required>
            <option value="No">No</option>
            <option value="Yes">Yes</option>
        </select>

        <label>What happens to your pet if or when you move? *</label>
        <textarea name="move_plan" rows="3" required></textarea>

        <label>Who do you live with? *</label>
        <input name="household" type="text" required placeholder="e.g., Spouse, Children, Roommate(s)" />

        <label>Are any household members allergic to animals? *</label>
        <select name="allergic" required>
            <option value="No">No</option>
            <option value="Yes">Yes</option>
        </select>

        <label>Who will be responsible for daily care? *</label>
        <input name="daily_caregiver" type="text" required />

        <label>Who will be financially responsible? *</label>
        <input name="financial_responsible" type="text" required />

        <label>Who will look after your pet during vacations/emergencies? *</label>
        <input name="pet_sitter" type="text" required />

        <label>How many hours per day will your pet be left alone? *</label>
        <input name="hours_left" type="text" required />

        <label>Does everyone support the decision? *</label>
        <select name="family_support" required>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>

        <label>Please explain</label>
        <textarea name="family_explain" rows="2"></textarea>

        <label>Do you have other pets? *</label>
        <select name="other_pets" required>
            <option value="No">No</option>
            <option value="Yes">Yes</option>
        </select>

        <label>Have you had pets in the past? *</label>
        <select name="past_pets" required>
            <option value="No">No</option>
            <option value="Yes">Yes</option>
        </select>

        <label>Attach photos of your home (max 8MB each) *</label>
        <input type="file" name="house_photos[]" multiple accept="image/*" required />

        <label>Upload a valid ID (max 8MB) *</label>
        <input type="file" name="id_document" accept="image/*,.pdf" required />

        <h3>Interview & Visitation</h3>
        <div id="zoomFields">
            <label>Preferred date for Zoom interview *</label>
            <input name="preferred_date" id="preferredDate" type="date" />

            <label>Preferred time for Zoom interview *</label>
            <input name="preferred_time" id="preferredTime" type="time" />
        </div>
        <p class="muted" id="noZoomNote" style="display:none;">We will contact you using your preferred interaction method above; no Zoom scheduling is needed.</p>

        <label>Will you be able to visit the shelter for meet-and-greet? *</label>
        <select name="will_visit" required>
            <option value="Yes">Yes</option>
            <option value="No">No</option>
        </select>

        <label style="margin-top:16px; font-weight:600;">
            <input type="checkbox" name="terms_accepted" value="1" required />
            I agree to the terms and conditions and understand that my private information will only be used for the adoption application process.
        </label>

        <input type="hidden" name="privacy_consent" value="I agree to the terms and conditions and consent to the use of my private information for the adoption application process only." />

        <div class="actions">
            <button type="submit">Submit Application</button>
        </div>
    </form>

    <div id="response"></div>
    <script>
        const interactionSelect = document.getElementById('interactionMethod');
        const zoomFields = document.getElementById('zoomFields');
        const noZoomNote = document.getElementById('noZoomNote');
        const preferredDate = document.getElementById('preferredDate');
        const preferredTime = document.getElementById('preferredTime');
        function syncZoomFields() {
            const isZoom = interactionSelect.value === 'Zoom';
            zoomFields.style.display = isZoom ? '' : 'none';
            noZoomNote.style.display = isZoom ? 'none' : '';
            preferredDate.required = isZoom;
            preferredTime.required = isZoom;
        }
        interactionSelect.addEventListener('change', syncZoomFields);
        syncZoomFields();

        const form = document.getElementById('appForm');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);

            // Collect extra form fields into structured JSON
            const extras = {};
            new FormData(form).forEach((v,k) => {
                if (!['pet_id','user_id','applicant_name','message','id_document','house_photos[]'].includes(k)) {
                    extras[k] = v;
                }
            });

            fd.append('form_data', JSON.stringify(extras));

            const respEl = document.getElementById('response');
            respEl.innerHTML = 'Submitting...';

            try {
                const res = await fetch('apply_adoption.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.status === 'success') {
                    respEl.innerHTML = '<div class="success">Application submitted. ID: ' + json.application_id + '</div>';
                } else if (json.success === true) {
                    // fallback if different response shape
                    respEl.innerHTML = '<div class="success">Application updated. ' + (json.message || '') + '</div>';
                } else {
                    respEl.innerHTML = '<div class="error">Error: ' + (json.message || JSON.stringify(json)) + '</div>';
                }
            } catch (err) {
                respEl.innerHTML = '<div class="error">Network error: ' + err.message + '</div>';
            }
        });
    </script>
</div>
</body>
</html>
