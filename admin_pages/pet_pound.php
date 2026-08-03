<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_pet_pound');

// Lazy grace-period expiry: any pet still Pending once its 14-day claim_deadline has
// passed flips to Expired, which is what makes it eligible to be posted for adoption
// (see post_pet_for_adoption.php). There is no cron in this stack, so this check runs
// on every page load instead.
$conn->query("UPDATE pet_pound SET status='Expired' WHERE status='Pending' AND claim_deadline < NOW()");

$result = $conn->query("
SELECT *
FROM pet_pound
WHERE status <> 'Deceased'
ORDER BY impound_date DESC
");

$deceasedResult = $conn->query("
SELECT *
FROM pet_pound
WHERE status = 'Deceased'
ORDER BY death_date DESC
");

function poundBadgeClass($status){
    switch($status){
        case "Pending":  return "badge-pending";
        case "Claimed":
        case "Paid":     return "badge-approved";
        case "Posted":   return "badge-info";
        case "Expired":
        case "Deceased": return "badge-danger";
        default:         return "badge";
    }
}
?>

<div class="card">

    <div class="card-header">

        <div>

            <div class="card-title">
                Pet Pound
            </div>

            <div class="card-sub">
                Manage impounded pets awaiting owner claim.
            </div>

        </div>

        <button
            class="btn btn-primary"
            onclick="openModal('addPetModal')">

            + Add Impounded Pet

        </button>

    </div>

    <div class="tabs" data-tg="pound">
        <button type="button" class="tab-btn active" data-tab="active" onclick="switchTab('pound','active')">Impounded Pets</button>
        <button type="button" class="tab-btn" data-tab="deceased" onclick="switchTab('pound','deceased')">Deceased Records</button>
    </div>

    <div class="tab-pane active" data-tg="pound" data-tab="active">

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Pet</th>
                    <th>Owner</th>
                    <th>Reason</th>
                    <th>Penalty</th>
                    <th>Impounded</th>
                    <th>Grace Period Ends</th>
                    <th>Status</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

            <?php if($result && $result->num_rows): ?>

                <?php while($row=$result->fetch_assoc()): ?>

                <?php $badge = poundBadgeClass($row['status']); ?>

                <tr>

                    <td><?= $row['id'] ?></td>

                    <td>

                        <div class="pet-cell" style="cursor:pointer;" onclick="openPet(<?= $row['id'] ?>)">

                            <?php if(!empty($row['pet_photo'])): ?>

                                <img
                                    src="images/<?= htmlspecialchars($row['pet_photo']) ?>"
                                    class="pet-thumb">

                            <?php endif; ?>

                            <span class="pet-name">

                                <?= htmlspecialchars($row['pet_name']) ?>

                            </span>

                        </div>

                    </td>

                    <td>

                        <?= htmlspecialchars($row['owner_name']) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($row['reason']) ?>

                    </td>

                    <td>

                        ₱<?= number_format($row['penalty_amount'],2) ?>

                    </td>

                    <td>

                        <?= date("M d, Y g:i A",strtotime($row['impound_date'])) ?>

                    </td>

                    <td>

                        <?= date("M d, Y g:i A",strtotime($row['claim_deadline'])) ?>

                    </td>

                    <td>

                        <span class="badge <?= $badge ?>">

                            <?= htmlspecialchars($row['status']) ?>

                        </span>

                    </td>

                    <td>

                        <button
                            class="btn btn-primary btn-sm"
                            onclick="openPet(<?= $row['id'] ?>)">
                            Manage
                        </button>

                    </td>

                </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="9">

                        <div class="empty-state">

                            <div class="empty-icon">

                                🐾

                            </div>

                            No impounded pets found.

                        </div>

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    </div>

    <div class="tab-pane" data-tg="pound" data-tab="deceased">

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Pet</th>
                    <th>Owner</th>
                    <th>Cause of Death</th>
                    <th>Remarks</th>
                    <th>Date</th>

                </tr>

            </thead>

            <tbody>

            <?php if($deceasedResult && $deceasedResult->num_rows): ?>

                <?php while($row=$deceasedResult->fetch_assoc()): ?>

                <tr>

                    <td><?= $row['id'] ?></td>

                    <td>

                        <div class="pet-cell">

                            <?php if(!empty($row['pet_photo'])): ?>

                                <img
                                    src="images/<?= htmlspecialchars($row['pet_photo']) ?>"
                                    class="pet-thumb">

                            <?php endif; ?>

                            <span class="pet-name">

                                <?= htmlspecialchars($row['pet_name']) ?>

                            </span>

                        </div>

                    </td>

                    <td><?= htmlspecialchars($row['owner_name']) ?></td>

                    <td><?= htmlspecialchars($row['cause_of_death'] ?? '') ?></td>

                    <td><?= nl2br(htmlspecialchars($row['death_remarks'] ?? '')) ?></td>

                    <td><?= $row['death_date'] ? date("M d, Y g:i A",strtotime($row['death_date'])) : '—' ?></td>

                </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="6">

                        <div class="empty-state">

                            <div class="empty-icon">

                                ☠

                            </div>

                            No deceased records found.

                        </div>

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    </div>

</div>

<!-- ===========================
ADD IMPOUNDED PET MODAL
=========================== -->

<div id="addPetModal" class="modal-backdrop">

    <div class="modal">

        <div class="modal-header">

            <h2 class="modal-title">

                Add Impounded Pet

            </h2>

            <button
                class="modal-close"
                onclick="closeModal('addPetModal')">

                &times;

            </button>

        </div>

        <div class="modal-body">

            <form
                id="addPetForm"
                enctype="multipart/form-data">

                <div class="form-group">
                    <label class="form-label">Pet Name</label>
                    <input type="text" name="pet_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Pet Photo</label>
                    <input type="file" name="pet_photo" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label">Owner Name</label>
                    <input type="text" name="owner_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Penalty Amount</label>
                    <input type="number" step="0.01" name="penalty_amount" class="form-control" required>
                </div>

                <div class="form-group">
                    <p class="note" style="margin:0;color:var(--muted);font-size:.85rem;">
                        The owner gets a fixed <strong>48-hour grace period</strong> from now to claim this pet before it becomes eligible for adoption posting.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">Species</label>
                    <input type="text" name="species" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Breed</label>
                    <input type="text" name="breed" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input type="text" name="age" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Health Status</label>
                    <input
                        type="text"
                        name="health_status"
                        class="form-control"
                        placeholder="Healthy, Injured, Sick..."
                        required>
                </div>

            </form>

        </div>

        <div class="modal-footer">

            <button class="btn btn-ghost" onclick="closeModal('addPetModal')">
                Cancel
            </button>

            <button class="btn btn-primary" onclick="saveImpoundedPet()">
                Save Pet
            </button>

        </div>

    </div>

</div>

<!-- ===========================
PET DETAILS / MANAGE MODAL
(content loaded dynamically via view_pet.php)
=========================== -->

<div id="petModal" class="modal-backdrop">

    <div class="modal modal-lg">

        <div class="modal-header">
            <div class="modal-title">Pet Details</div>

            <button class="modal-close" onclick="closeModal('petModal')">
                &times;
            </button>
        </div>

        <div class="modal-body" id="petModalBody">
        </div>

    </div>

</div>

<!-- ===========================
PAYMENT MODAL
=========================== -->

<div id="paymentModal" class="modal-backdrop">

    <div class="modal">

        <div class="modal-header">
            <h2 class="modal-title">Penalty Payment</h2>
            <button class="modal-close" onclick="closeModal('paymentModal')">&times;</button>
        </div>

        <div class="modal-body" id="paymentContent">
            Loading...
        </div>

    </div>

</div>

<!-- ===========================
MARK AS DECEASED MODAL
=========================== -->

<div class="modal-backdrop" id="deceasedModal">

    <div class="modal">

        <div class="modal-header">
            <div class="modal-title">Mark as Deceased</div>
            <button class="modal-close" onclick="closeModal('deceasedModal')">&times;</button>
        </div>

        <div class="modal-body">

            <div class="form-group">
                <label>Cause of Death</label>
                <select id="recordType" class="form-control">
                    <option value="Illness">Illness / Disease</option>
                    <option value="Euthanasia">Euthanasia</option>
                </select>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea
                    id="recordRemarks"
                    class="form-control"
                    rows="4"
                    placeholder="Enter remarks..."></textarea>
            </div>

        </div>

        <div class="modal-footer">

            <button class="btn btn-secondary" onclick="closeModal('deceasedModal')">
                Cancel
            </button>

            <button class="btn btn-danger" onclick="confirmDeceased()">
                Confirm
            </button>

        </div>

    </div>

</div>

<script>

/*=========================
MODALS
=========================*/
let penaltyPaymentPollTimer = null;
let activePenaltyPaymentIntentId = "";

async function generatePenaltyQrPayment() {
    const petPoundId = Number(
        document.getElementById("penaltyPetPoundId")?.value || 0
    );

    const button = document.getElementById("generatePenaltyQrButton");
    const placeholder = document.getElementById("penaltyQrPlaceholder");
    const loading = document.getElementById("penaltyQrLoading");
    const result = document.getElementById("penaltyQrResult");

    if (!petPoundId) {
        alert("Invalid impoundment ID.");
        return;
    }

    if (!button || !placeholder || !loading || !result) {
        alert("The payment interface is incomplete.");
        return;
    }

    button.disabled = true;
    button.textContent = "Generating...";

    placeholder.style.display = "none";
    loading.style.display = "block";
    result.style.display = "none";

    try {
        const response = await fetch(
            "create_penalty_qrph_payment.php",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    pet_pound_id: petPoundId
                })
            }
        );

        const rawResponse = await response.text();

        let data;

        try {
            data = JSON.parse(rawResponse);
        } catch (error) {
            console.error("Invalid server response:", rawResponse);

            throw new Error(
                "The server returned an invalid response. Check the PHP error logs."
            );
        }

        if (!response.ok || !data.success) {
            console.error("Payment creation error:", data);

            throw new Error(
                data.message ||
                "Unable to generate the QR Ph payment."
            );
        }

        if (
            !data.payment_intent_id ||
            !data.qr_image_url
        ) {
            throw new Error(
                "PayMongo did not return complete QR Ph information."
            );
        }

        activePenaltyPaymentIntentId =
            data.payment_intent_id;

        const qrImage =
            document.getElementById("penaltyQrImage");

        qrImage.onload = function () {
            loading.style.display = "none";
            result.style.display = "block";
        };

        qrImage.onerror = function () {
            loading.style.display = "none";
            placeholder.style.display = "block";

            button.disabled = false;
            button.textContent =
                "Generate QR Ph Payment";

            alert("The generated QR image could not be displayed.");
        };

        qrImage.src = data.qr_image_url;

        button.style.display = "none";

        startPenaltyPaymentPolling(
            activePenaltyPaymentIntentId
        );

    } catch (error) {
        console.error("QR Ph generation failed:", error);

        loading.style.display = "none";
        placeholder.style.display = "block";

        button.disabled = false;
        button.textContent =
            "Generate QR Ph Payment";

        alert(
            error.message ||
            "Unable to generate the QR Ph payment."
        );
    }
}

function startPenaltyPaymentPolling(paymentIntentId) {
    stopPenaltyPaymentPolling();

    checkPenaltyPaymentStatus(paymentIntentId);

    penaltyPaymentPollTimer = setInterval(
        function () {
            checkPenaltyPaymentStatus(paymentIntentId);
        },
        3000
    );
}

function stopPenaltyPaymentPolling() {
    if (penaltyPaymentPollTimer !== null) {
        clearInterval(penaltyPaymentPollTimer);
        penaltyPaymentPollTimer = null;
    }
}

async function checkPenaltyPaymentStatus(paymentIntentId) {
    if (!paymentIntentId) {
        return;
    }

    try {
        const response = await fetch(
            "check_penalty_qrph_payment.php",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    payment_intent_id: paymentIntentId
                })
            }
        );

        const rawResponse = await response.text();

        let data;

        try {
            data = JSON.parse(rawResponse);
        } catch (error) {
            console.error(
                "Invalid payment-status response:",
                rawResponse
            );
            return;
        }

        if (!response.ok || !data.success) {
            console.error(
                "Payment status error:",
                data.message || data
            );
            return;
        }

        const pollingStatus =
            document.getElementById(
                "penaltyPollingStatus"
            );

        if (!pollingStatus) {
            stopPenaltyPaymentPolling();
            return;
        }

        if (data.paid === true) {
            stopPenaltyPaymentPolling();

            pollingStatus.textContent =
                "Payment completed successfully.";

            pollingStatus.style.color =
                "var(--success)";

            const statusBadge =
                document.getElementById(
                    "penaltyPaymentStatusText"
                );

            if (statusBadge) {
                statusBadge.textContent = "Paid";
                statusBadge.className =
                    "badge badge-approved";
            }

            const referenceBox =
                document.getElementById(
                    "penaltyPaymentReferenceBox"
                );

            if (referenceBox) {
                referenceBox.style.display = "block";
            }

            document.getElementById(
                "penaltyPaidReference"
            ).textContent =
                data.reference_number || "—";

            document.getElementById(
                "penaltyPaidDate"
            ).textContent =
                formatPenaltyPaymentDate(
                    data.payment_date
                );

            setTimeout(function () {
                closeModal("paymentModal");
                window.location.reload();
            }, 3000);

            return;
        }

        pollingStatus.textContent =
            getPenaltyPaymentStatusText(
                data.status
            );

    } catch (error) {
        console.error(
            "Penalty status polling failed:",
            error
        );
    }
}

function getPenaltyPaymentStatusText(status) {
    switch (
        String(status || "").toLowerCase()
    ) {
        case "awaiting_next_action":
            return "Waiting for the customer to scan and pay...";

        case "processing":
            return "Payment is being processed...";

        case "succeeded":
            return "Payment completed successfully.";

        case "failed":
            return "Payment failed.";

        case "cancelled":
            return "Payment was cancelled.";

        default:
            return "Checking payment status...";
    }
}

function formatPenaltyPaymentDate(value) {
    if (!value) {
        return "—";
    }

    const parsedDate =
        new Date(value.replace(" ", "T"));

    if (Number.isNaN(parsedDate.getTime())) {
        return value;
    }

    return parsedDate.toLocaleString();
}
</script>