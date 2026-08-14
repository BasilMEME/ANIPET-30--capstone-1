<?php

require_permission($conn, 'manage_settings');
require_once __DIR__ . '/../system_settings_helper.php';

$settings = [
    // Pet Pound information
    'pet_pound_name' => get_system_setting(
        $conn,
        'pet_pound_name',
        ''
    ),
    'pet_pound_contact' => get_system_setting(
        $conn,
        'pet_pound_contact',
        ''
    ),
    'pet_pound_address' => get_system_setting(
        $conn,
        'pet_pound_address',
        ''
    ),
    'pet_pound_notes' => get_system_setting(
        $conn,
        'pet_pound_notes',
        ''
    ),

    // Pet Pound policies and operating hours
    'pet_pound_operating_days' => get_system_setting(
        $conn,
        'pet_pound_operating_days',
        'Monday to Friday'
    ),
    'pet_pound_opening_time' => get_system_setting(
        $conn,
        'pet_pound_opening_time',
        '08:00'
    ),
    'pet_pound_closing_time' => get_system_setting(
        $conn,
        'pet_pound_closing_time',
        '17:00'
    ),
    'claim_grace_period_days' => get_system_setting(
        $conn,
        'claim_grace_period_days',
        '14'
    )
];

?>

<div class="card" style="margin-bottom:18px;border-left:4px solid var(--accent,#986038);">
    <div class="card-header">
        <div>
            <div class="card-title">Read-only Settings</div>
            <div class="card-sub">
                These Pet Pound settings are managed by the Super Admin. Changes made by the Super Admin will automatically appear here.
            </div>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- PET POUND INFORMATION -->
<!-- ========================================================= -->

<div class="card">

    <div class="card-header">
        <div>
            <div class="card-title">
                Pet Pound Information
            </div>

            <div class="card-sub">
                Configure the contact and location information
                for the Pet Pound
            </div>
        </div>
    </div>

    <form id="petPoundForm">

        <div class="form-row cols-2">

            <div class="form-group">

                <label class="form-label">
                    Pet Pound Name
                </label>

                <input
                    type="text"
                    name="pet_pound_name"
                    class="form-control"
                    placeholder="e.g. City Animal Pound"
                    value="<?php echo htmlspecialchars(
                        $settings['pet_pound_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label class="form-label">
                    Contact Number
                </label>

                <input
                    type="text"
                    name="pet_pound_contact"
                    class="form-control"
                    placeholder="e.g. 09123456789"
                    value="<?php echo htmlspecialchars(
                        $settings['pet_pound_contact'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >

            </div>

        </div>

        <div class="form-group">

            <label class="form-label">
                Address
            </label>

            <input
                type="text"
                name="pet_pound_address"
                class="form-control"
                placeholder="Enter the complete Pet Pound address"
                value="<?php echo htmlspecialchars(
                    $settings['pet_pound_address'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >

        </div>

        <div class="form-group">

            <label class="form-label">
                Notes
            </label>

            <textarea
                name="pet_pound_notes"
                class="form-control"
                placeholder="Optional information about the Pet Pound"
            ><?php echo htmlspecialchars(
                $settings['pet_pound_notes'],
                ENT_QUOTES,
                'UTF-8'
            ); ?></textarea>

        </div>

        <div style="font-size:.82rem;color:var(--muted);margin-top:8px;">Only the Super Admin can change these values.</div>

    </form>

</div>


<!-- ========================================================= -->
<!-- PET POUND POLICIES & OPERATING HOURS -->
<!-- ========================================================= -->

<div class="card">

    <div class="card-header">
        <div>
            <div class="card-title">
                Pet Pound Policies & Operating Hours
            </div>

            <div class="card-sub">
                Set the Pet Pound schedule and view the system claim policy
            </div>
        </div>
    </div>

    <form id="petPoundPolicyForm">

        <div class="form-group">
            <label class="form-label">Operating Days</label>
            <input
                type="text"
                name="pet_pound_operating_days"
                class="form-control"
                placeholder="e.g. Monday to Friday"
                value="<?php echo htmlspecialchars(
                    $settings['pet_pound_operating_days'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div class="form-row cols-2">
            <div class="form-group">
                <label class="form-label">Opening Time</label>
                <input
                    type="time"
                    name="pet_pound_opening_time"
                    class="form-control"
                    value="<?php echo htmlspecialchars(
                        $settings['pet_pound_opening_time'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >
            </div>

            <div class="form-group">
                <label class="form-label">Closing Time</label>
                <input
                    type="time"
                    name="pet_pound_closing_time"
                    class="form-control"
                    value="<?php echo htmlspecialchars(
                        $settings['pet_pound_closing_time'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Pet Claim Grace Period</label>
            <div style="display:flex;align-items:center;gap:10px;max-width:280px;">
                <input
                    type="number"
                    name="claim_grace_period_days"
                    id="claimGracePeriodDays"
                    class="form-control"
                    min="1"
                    max="365"
                    step="1"
                    value="<?php echo (int)$settings['claim_grace_period_days']; ?>"
                    required
                >
                <span style="white-space:nowrap;color:var(--muted);">day(s)</span>
            </div>
            <div style="font-size:.8rem;color:var(--muted);margin-top:6px;">
                This value will be used for newly impounded pets. Existing claim deadlines will not be changed.
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Claim / Impound Policy</label>
            <textarea
                id="claimPolicyPreview"
                class="form-control"
                style="min-height:120px;background:var(--bg-light,#f8fafc);"
                readonly
                aria-readonly="true"
            ><?php $days = max(1, (int)$settings['claim_grace_period_days']); echo htmlspecialchars("Impounded pets may be claimed by their owner within {$days} day" . ($days === 1 ? '' : 's') . " from the date of impoundment. After the {$days}-day grace period has passed, an unclaimed pet becomes eligible for adoption.", ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div style="font-size:.8rem;color:var(--muted);margin-top:6px;">
                The policy text is generated automatically from the grace-period setting above.
            </div>
        </div>

        <div style="font-size:.82rem;color:var(--muted);margin-top:8px;">Only the Super Admin can change these values.</div>

    </form>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#petPoundForm input, #petPoundForm textarea, #petPoundPolicyForm input, #petPoundPolicyForm textarea')
        .forEach(function (field) {
            field.readOnly = true;
            field.setAttribute('aria-readonly', 'true');
        });

    document.querySelectorAll('#petPoundForm, #petPoundPolicyForm').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });
    });
});
</script>