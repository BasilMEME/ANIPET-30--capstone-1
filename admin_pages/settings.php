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
    'pet_pound_claim_policy' => get_system_setting(
        $conn,
        'pet_pound_claim_policy',
        'Impounded pets may be claimed by their owner within 14 days from the date of impoundment. After the claim period expires, the pet may become eligible for adoption subject to the Pet Pound\'s assessment.'
    )
];

?>

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

        <div
            class="action-row"
            style="margin-top: 6px;"
        >
            <button
                class="btn btn-primary"
                type="submit"
            >
                Save Pet Pound Information
            </button>
        </div>

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
                Set the Pet Pound schedule and claim policy
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
            <label class="form-label">Claim / Impound Policy</label>
            <textarea
                name="pet_pound_claim_policy"
                class="form-control"
                style="min-height: 120px;"
                placeholder="Enter the policy shown to staff or users..."
            ><?php echo htmlspecialchars(
                $settings['pet_pound_claim_policy'],
                ENT_QUOTES,
                'UTF-8'
            ); ?></textarea>
            <div style="font-size:.8rem;color:var(--muted);margin-top:6px;">
                Suggested policy keeps the current 14-day claim period consistent with the Pet Pound workflow.
            </div>
        </div>



        <div class="action-row" style="margin-top: 6px;">
            <button class="btn btn-primary" type="submit">
                Save Policies & Operating Hours
            </button>
        </div>

    </form>

</div>


<script>

async function submitSettingsForm(formElement, action) {
    const button = formElement.querySelector(
        'button[type="submit"]'
    );

    const originalText = button
        ? button.textContent
        : '';

    if (button) {
        button.disabled = true;
        button.textContent = 'Saving...';
    }

    try {
        const body = new FormData(formElement);

        body.append(
            'action',
            action
        );

        const response = await fetch(
            'admin_api.php',
            {
                method: 'POST',
                body: body
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message ||
                'Unable to save settings'
            );
        }

        showToast(
            data.message ||
            'Settings saved successfully'
        );

        return true;

    } catch (error) {
        showToast(
            error.message ||
            'Something went wrong',
            'error'
        );

        return false;

    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalText;
        }
    }
}


const petPoundForm =
    document.getElementById('petPoundForm');

if (petPoundForm) {
    petPoundForm.addEventListener(
        'submit',
        async function (event) {
            event.preventDefault();

            await submitSettingsForm(
                event.currentTarget,
                'update_pet_pound_settings'
            );
        }
    );
}


const petPoundPolicyForm =
    document.getElementById('petPoundPolicyForm');

if (petPoundPolicyForm) {
    petPoundPolicyForm.addEventListener(
        'submit',
        async function (event) {
            event.preventDefault();

            await submitSettingsForm(
                event.currentTarget,
                'update_pet_pound_policy_settings'
            );
        }
    );
}

</script>