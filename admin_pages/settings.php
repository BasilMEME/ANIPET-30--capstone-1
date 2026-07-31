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

    // Donation information
    'donation_gcash_name' => get_system_setting(
        $conn,
        'donation_gcash_name',
        ''
    ),
    'donation_gcash_number' => get_system_setting(
        $conn,
        'donation_gcash_number',
        ''
    ),
    'donation_notes' => get_system_setting(
        $conn,
        'donation_notes',
        ''
    ),
    'donation_qr_filename' => get_system_setting(
        $conn,
        'donation_qr_filename',
        ''
    )
];

$qrFilename = trim(
    $settings['donation_qr_filename']
);

$qrExists = $qrFilename !== ''
    && is_file(
        __DIR__ . '/../images/' . $qrFilename
    );

$isSuperAdmin =
    current_user_role() === 'super_admin';

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
<!-- DONATION SETTINGS -->
<!-- ========================================================= -->

<div class="card">

    <div class="card-header">

        <div>

            <div class="card-title">
                Donations (GCash)
            </div>

            <div class="card-sub">
                Configure the GCash account and QR code shown
                to users who want to donate
            </div>

        </div>

    </div>

    <?php if (!$isSuperAdmin): ?>

        <p
            style="
                font-size: .82rem;
                color: var(--warning);
                margin-bottom: 14px;
            "
        >
            🔒 Only a Super Admin can edit donation settings.
            The information below is read-only.
        </p>

    <?php endif; ?>

    <form
        id="donationForm"
        enctype="multipart/form-data"
    >

        <div class="form-row cols-2">

            <div>

                <div class="form-group">

                    <label class="form-label">
                        GCash Account Name
                    </label>

                    <input
                        type="text"
                        name="donation_gcash_name"
                        class="form-control"
                        value="<?php echo htmlspecialchars(
                            $settings['donation_gcash_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        <?php echo $isSuperAdmin ? '' : 'disabled'; ?>
                    >

                </div>

                <div class="form-group">

                    <label class="form-label">
                        GCash Number
                    </label>

                    <input
                        type="text"
                        name="donation_gcash_number"
                        class="form-control"
                        placeholder="e.g. 09123456789"
                        value="<?php echo htmlspecialchars(
                            $settings['donation_gcash_number'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        <?php echo $isSuperAdmin ? '' : 'disabled'; ?>
                    >

                </div>

                <div class="form-group">

                    <label class="form-label">
                        Notes
                    </label>

                    <textarea
                        name="donation_notes"
                        class="form-control"
                        placeholder="Optional message shown alongside the QR code"
                        <?php echo $isSuperAdmin ? '' : 'disabled'; ?>
                    ><?php echo htmlspecialchars(
                        $settings['donation_notes'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></textarea>

                </div>

            </div>

            <div>

                <div class="form-group">

                    <label class="form-label">
                        Donation QR Code
                    </label>

                    <?php if ($qrExists): ?>

                        <div style="margin-bottom: 10px;">

                            <img
                                src="images/<?php echo rawurlencode(
                                    $qrFilename
                                ); ?>"
                                alt="Donation QR Code"
                                style="
                                    width: 160px;
                                    height: 160px;
                                    object-fit: contain;
                                    border: 1px solid var(--border);
                                    border-radius: 8px;
                                    background: #ffffff;
                                "
                            >

                        </div>

                    <?php else: ?>

                        <div
                            class="empty-state"
                            style="padding: 16px;"
                        >
                            <p style="font-size: .8rem;">
                                No donation QR code uploaded yet
                            </p>
                        </div>

                    <?php endif; ?>

                    <input
                        type="file"
                        name="donation_qr"
                        accept=".jpg,.jpeg,.png,.gif,.webp"
                        class="form-control"
                        <?php echo $isSuperAdmin ? '' : 'disabled'; ?>
                    >

                </div>

            </div>

        </div>

        <?php if ($isSuperAdmin): ?>

            <div
                class="action-row"
                style="margin-top: 6px;"
            >
                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Save Donation Settings
                </button>
            </div>

        <?php endif; ?>

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


const donationForm =
    document.getElementById('donationForm');

if (donationForm) {
    donationForm.addEventListener(
        'submit',
        async function (event) {
            event.preventDefault();

            const saved = await submitSettingsForm(
                event.currentTarget,
                'update_donation_settings'
            );

            if (saved) {
                setTimeout(
                    function () {
                        window.location.reload();
                    },
                    600
                );
            }
        }
    );
}

</script>