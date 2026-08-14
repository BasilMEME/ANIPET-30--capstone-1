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
                        The owner gets a fixed <strong>14-day</strong> grace period from now to claim this pet before it becomes eligible for adoption posting.
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

<style>
/* Keep the Post Pet for Adoption footer visible on smaller screens */
#adoptionPostModal {
    padding: 24px 12px;
    box-sizing: border-box;
}

#adoptionPostModal > .modal {
    max-height: calc(100vh - 48px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#adoptionPostModal #adoptionPostForm {
    min-height: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#adoptionPostModal .modal-body {
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
}

#adoptionPostModal .modal-footer {
    flex-shrink: 0;
    background: #fff;
    border-top: 1px solid #e5e7eb;
    padding: 16px 28px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    position: relative;
    z-index: 2;
}
/* Pet details: show the full uploaded photo instead of cropping it */
#petModalBody img {
    max-width: 460px;
    max-height: 420px;
    width: 100%;
    height: auto;
    object-fit: contain !important;
    object-position: center;
}

</style>

<!-- ===========================
POST PET FOR ADOPTION MODAL
=========================== -->

<div id="adoptionPostModal" class="modal-backdrop">

    <div class="modal modal-lg">

        <div class="modal-header">

            <div class="modal-title">
                Post Pet for Adoption
            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeAdoptionPostModal()">
                &times;
            </button>

        </div>

        <form id="adoptionPostForm">

            <div class="modal-body">

                <input
                    type="hidden"
                    id="adoptionPoundId"
                    name="id">

                <div class="form-group">
                    <label class="form-label">Pet Name</label>
                    <input
                        type="text"
                        id="adoptionName"
                        name="name"
                        class="form-control"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Species</label>
                    <input
                        type="text"
                        id="adoptionSpecies"
                        name="species"
                        class="form-control"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Breed</label>
                    <input
                        type="text"
                        id="adoptionBreed"
                        name="breed"
                        class="form-control"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input
                        type="text"
                        id="adoptionAge"
                        name="age"
                        class="form-control"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Gender</label>

                    <select
                        id="adoptionGender"
                        name="gender"
                        class="form-control"
                        required>

                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>

                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Health Status</label>

                    <input
                        type="text"
                        id="adoptionHealthStatus"
                        name="health_status"
                        class="form-control"
                        required>
                </div>

                <div class="form-group">

                    <label class="form-label">
                        Description
                    </label>

                    <textarea
                        id="adoptionDescription"
                        name="description"
                        class="form-control"
                        rows="4"></textarea>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-ghost"
                    onclick="closeAdoptionPostModal()">
                    Cancel
                </button>

                <button
                    type="submit"
                    id="publishAdoptionButton"
                    class="btn btn-info">
                    Finish and Publish
                </button>

            </div>

        </form>

    </div>

</div>

<script>
/* =========================================================
   SHARED MODAL HELPERS
   ========================================================= */

let currentPetId = 0;
let penaltyPaymentPollTimer = null;
let activePenaltyPaymentIntentId = "";

function openModal(id) {
    const modal = document.getElementById(id);

    if (!modal) {
        console.error("Modal not found:", id);
        return;
    }

    modal.style.display = "flex";
}

function closeModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.style.display = "none";
    }

    if (id === "paymentModal") {
        stopPenaltyPaymentPolling();
        activePenaltyPaymentIntentId = "";
    }
}

window.addEventListener("click", function (event) {
    document.querySelectorAll(".modal-backdrop").forEach(function (modal) {
        if (event.target === modal) {
            closeModal(modal.id);
        }
    });

    const adoptionModal = document.getElementById("adoptionPostModal");

    if (adoptionModal && event.target === adoptionModal) {
        closeAdoptionPostModal();
    }
});


/* =========================================================
   OPEN PET DETAILS / MANAGE MODAL
   ========================================================= */

async function openPet(id) {
    currentPetId = Number(id) || 0;

    if (!currentPetId) {
        alert("Invalid impoundment ID.");
        return;
    }

    const modalBody = document.getElementById("petModalBody");

    if (!modalBody) {
        alert("Pet details modal was not found.");
        return;
    }

    modalBody.innerHTML = "<p>Loading pet details...</p>";
    openModal("petModal");

    try {
        const response = await fetch(
            "admin_pages/view_pet.php?id=" + encodeURIComponent(currentPetId),
            {
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            }
        );

        const html = await response.text();

        if (!response.ok) {
            console.error("view_pet.php response:", response.status, html);
            throw new Error(
                "Unable to load pet details. Server returned HTTP " +
                response.status +
                "."
            );
        }

        modalBody.innerHTML = html;

        // Keep the grace-period/adoption notice hidden until the full 14 days
        // have actually passed. view_pet.php may render the notice early, so
        // enforce the deadline again here using the dates shown in the modal.
        normalizePetGracePeriodDisplay(modalBody);
    } catch (error) {
        console.error("Unable to open pet details:", error);

        modalBody.innerHTML =
            '<div class="empty-state">' +
            '<div class="empty-icon">⚠️</div>' +
            '<p>' + escapeHtml(error.message || "Unable to load pet details.") + '</p>' +
            '</div>';
    }
}


function normalizePetGracePeriodDisplay(modalBody) {
    if (!modalBody) return;

    const text = modalBody.innerText || "";
    const deadlineMatch = text.match(/GRACE PERIOD ENDS\s*\n?\s*([^\n]+)/i);

    if (deadlineMatch) {
        const deadline = new Date(deadlineMatch[1].trim());
        const now = new Date();
        const hasExpired = !Number.isNaN(deadline.getTime()) && now >= deadline;

        // Find any element containing the adoption-eligibility warning.
        modalBody.querySelectorAll("p, div, span").forEach(function (element) {
            const message = (element.textContent || "").trim();
            if (/14-day grace period has expired|eligible to be posted for adoption/i.test(message)) {
                element.style.display = hasExpired ? "" : "none";
            }
        });
    }

    // Show the whole uploaded pet photo without cropping it.
    modalBody.querySelectorAll("img").forEach(function (image) {
        if (image.closest(".pet-details-photo, .pet-photo, .photo-wrap") || image.width > 120 || image.height > 120) {
            image.style.width = "100%";
            image.style.maxWidth = "460px";
            image.style.height = "auto";
            image.style.maxHeight = "420px";
            image.style.objectFit = "contain";
            image.style.objectPosition = "center";
            image.style.display = "block";
            image.style.margin = "0 auto";
        }
    });
}


/* =========================================================
   ADD IMPOUNDED PET
   ========================================================= */

async function saveImpoundedPet() {
    const form = document.getElementById("addPetForm");

    if (!form) {
        alert("Add pet form was not found.");
        return;
    }

    const data = new FormData(form);

    try {
        const response = await fetch(
            "admin_pages/save_impounded_pet.php",
            {
                method: "POST",
                body: data
            }
        );

        const result = (await response.text()).trim();

        if (!response.ok) {
            throw new Error(result || "Unable to save the impounded pet.");
        }

        if (result === "success") {
            alert("Pet added successfully.");
            closeModal("addPetModal");
            window.location.reload();
            return;
        }

        alert(result || "Unable to save the impounded pet.");
    } catch (error) {
        console.error("Save impounded pet failed:", error);
        alert(error.message || "Unable to save the impounded pet.");
    }
}


/* =========================================================
   OPEN PENALTY PAYMENT MODAL
   ========================================================= */

async function paymentPet() {
    if (!currentPetId) {
        alert("No impounded pet is selected.");
        return;
    }

    closeModal("petModal");

    const paymentContent = document.getElementById("paymentContent");

    if (!paymentContent) {
        alert("Payment modal was not found.");
        return;
    }

    paymentContent.innerHTML = "<p>Loading payment details...</p>";
    openModal("paymentModal");

    try {
        const response = await fetch(
            "admin_pages/payment_pet.php?id=" +
            encodeURIComponent(currentPetId),
            {
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            }
        );

        const html = await response.text();

        if (!response.ok) {
            console.error("payment_pet.php response:", response.status, html);
            throw new Error(
                "Unable to load penalty payment. Server returned HTTP " +
                response.status +
                "."
            );
        }

        paymentContent.innerHTML = html;
    } catch (error) {
        console.error("Unable to open penalty payment:", error);

        paymentContent.innerHTML =
            '<div class="empty-state">' +
            '<div class="empty-icon">⚠️</div>' +
            '<p>' + escapeHtml(error.message || "Unable to load payment details.") + '</p>' +
            '</div>';
    }
}

function viewPaymentHistory() {
    window.location.href = "?page=penalty_payments";
}


/* =========================================================
   GENERATE PAYMONGO QR PH PENALTY PAYMENT
   ========================================================= */

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
        } catch (parseError) {
            console.error("Invalid QR creation response:", rawResponse);
            throw new Error(
                "The payment server returned an invalid response."
            );
        }

        if (!response.ok || !data.success) {
            console.error("Penalty QR creation error:", data);

            throw new Error(
                data.message || "Unable to generate the QR Ph payment."
            );
        }

        if (!data.payment_intent_id || !data.qr_image_url) {
            throw new Error(
                "PayMongo did not return complete QR Ph information."
            );
        }

        activePenaltyPaymentIntentId = data.payment_intent_id;

        const qrImage = document.getElementById("penaltyQrImage");

        if (!qrImage) {
            throw new Error("QR image area was not found.");
        }

        qrImage.onload = function () {
            loading.style.display = "none";
            result.style.display = "block";
        };

        qrImage.onerror = function () {
            loading.style.display = "none";
            placeholder.style.display = "block";

            button.disabled = false;
            button.style.display = "";
            button.textContent = "Generate QR Ph Payment";

            alert("The generated QR image could not be displayed.");
        };

        qrImage.src = data.qr_image_url;
        button.style.display = "none";

        startPenaltyPaymentPolling(activePenaltyPaymentIntentId);
    } catch (error) {
        console.error("QR Ph generation failed:", error);

        loading.style.display = "none";
        placeholder.style.display = "block";

        button.disabled = false;
        button.style.display = "";
        button.textContent = "Generate QR Ph Payment";

        alert(
            error.message || "Unable to generate the QR Ph payment."
        );
    }
}

function startPenaltyPaymentPolling(paymentIntentId) {
    stopPenaltyPaymentPolling();

    checkPenaltyPaymentStatus(paymentIntentId);

    penaltyPaymentPollTimer = window.setInterval(function () {
        checkPenaltyPaymentStatus(paymentIntentId);
    }, 3000);
}

function stopPenaltyPaymentPolling() {
    if (penaltyPaymentPollTimer !== null) {
        window.clearInterval(penaltyPaymentPollTimer);
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
        } catch (parseError) {
            console.error("Invalid payment status response:", rawResponse);
            return;
        }

        if (!response.ok || !data.success) {
            console.error(
                "Payment status error:",
                data.message || data
            );
            return;
        }

        const pollingStatus = document.getElementById(
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
            pollingStatus.style.color = "var(--success)";

            const statusBadge = document.getElementById(
                "penaltyPaymentStatusText"
            );

            if (statusBadge) {
                statusBadge.textContent = "Paid";
                statusBadge.className = "badge badge-approved";
            }

            const referenceBox = document.getElementById(
                "penaltyPaymentReferenceBox"
            );

            if (referenceBox) {
                referenceBox.style.display = "block";
            }

            const referenceText = document.getElementById(
                "penaltyPaidReference"
            );

            if (referenceText) {
                referenceText.textContent =
                    data.reference_number || "—";
            }

            const paidDateText = document.getElementById(
                "penaltyPaidDate"
            );

            if (paidDateText) {
                paidDateText.textContent =
                    formatPenaltyPaymentDate(data.payment_date);
            }

            window.setTimeout(function () {
                closeModal("paymentModal");
                window.location.reload();
            }, 3000);

            return;
        }

        pollingStatus.textContent =
            getPenaltyPaymentStatusText(data.status);
    } catch (error) {
        console.error("Penalty status polling failed:", error);
    }
}

function getPenaltyPaymentStatusText(status) {
    switch (String(status || "").toLowerCase()) {
        case "awaiting_next_action":
            return "Waiting for the customer to scan and pay...";

        case "processing":
            return "Payment is being processed...";

        case "succeeded":
            return "Payment completed successfully.";

        case "failed":
            return "Payment failed. Please generate a new QR.";

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

    const parsedDate = new Date(String(value).replace(" ", "T"));

    if (Number.isNaN(parsedDate.getTime())) {
        return value;
    }

    return parsedDate.toLocaleString();
}


/* =========================================================
   UPDATE PET-POUND STATUS
   ========================================================= */

async function updatePetStatus() {
    const statusSelect = document.getElementById("statusSelect");

    if (!statusSelect) {
        alert("Status field was not found.");
        return;
    }

    try {
        const response = await fetch(
            "admin_pages/update_pet_status.php",
            {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded;charset=UTF-8",
                    "Accept": "application/json"
                },
                body:
                    "id=" +
                    encodeURIComponent(currentPetId) +
                    "&status=" +
                    encodeURIComponent(statusSelect.value)
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to update the status.");
        }

        alert("Status updated to " + data.new_status + ".");
        closeModal("petModal");
        window.location.reload();
    } catch (error) {
        console.error("Update status failed:", error);
        alert(error.message || "Unable to update the status.");
    }
}


/* =========================================================
   CLAIM PET
   ========================================================= */

async function claimPet() {
    if (!window.confirm("Return this pet to the owner?")) {
        return;
    }

    try {
        const response = await fetch(
            "admin_pages/claim_pet.php",
            {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded;charset=UTF-8",
                    "Accept": "application/json"
                },
                body:
                    "id=" + encodeURIComponent(currentPetId)
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Unable to claim the pet.");
        }

        alert("Pet successfully claimed.");
        closeModal("petModal");
        window.location.reload();
    } catch (error) {
        console.error("Claim pet failed:", error);
        alert(error.message || "Unable to claim the pet.");
    }
}


/* =========================================================
   POST PET FOR ADOPTION
   ========================================================= */

function ensureAdoptionPostModal() {
    let modal = document.getElementById("adoptionPostModal");

    if (modal) {
        return modal;
    }

    const wrapper = document.createElement("div");
    wrapper.innerHTML = `
        <div id="adoptionPostModal" class="modal-backdrop">
            <div class="modal modal-lg">
                <div class="modal-header">
                    <div class="modal-title">Post Pet for Adoption</div>
                    <button type="button" class="modal-close" onclick="closeAdoptionPostModal()">&times;</button>
                </div>

                <form id="adoptionPostForm">
                    <div class="modal-body">
                        <input type="hidden" id="adoptionPoundId" name="pet_pound_id">

                        <div class="form-group">
                            <label class="form-label">Pet Name</label>
                            <input type="text" id="adoptionName" name="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Species</label>
                            <input type="text" id="adoptionSpecies" name="species" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Breed</label>
                            <input type="text" id="adoptionBreed" name="breed" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Age</label>
                            <input type="text" id="adoptionAge" name="age" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select id="adoptionGender" name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Health Status</label>
                            <input type="text" id="adoptionHealthStatus" name="health_status" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea id="adoptionDescription" name="description" class="form-control" rows="4"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" onclick="closeAdoptionPostModal()">Cancel</button>
                        <button type="submit" id="publishAdoptionButton" class="btn btn-info">Finish and Publish</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    modal = wrapper.firstElementChild;
    document.body.appendChild(modal);
    bindAdoptionPostForm();

    return modal;
}

function openAdoptionPostModal(button) {
    const modal = ensureAdoptionPostModal();

    const setValue = function (id, value) {
        const field = document.getElementById(id);

        if (field) {
            field.value = value || "";
        }
    };

    setValue("adoptionPoundId", currentPetId);
    setValue("adoptionName", button?.dataset?.name);
    setValue("adoptionSpecies", button?.dataset?.species);
    setValue("adoptionBreed", button?.dataset?.breed);
    setValue("adoptionAge", button?.dataset?.age);
    setValue("adoptionGender", button?.dataset?.gender);
    setValue("adoptionHealthStatus", button?.dataset?.health);
    setValue("adoptionDescription", button?.dataset?.description);

    modal.style.display = "flex";
}

function closeAdoptionPostModal() {
    const modal = document.getElementById("adoptionPostModal");

    if (modal) {
        modal.style.display = "none";
    }
}

function bindAdoptionPostForm() {
    const adoptionPostForm = document.getElementById("adoptionPostForm");

    if (!adoptionPostForm || adoptionPostForm.dataset.bound === "1") {
        return;
    }

    adoptionPostForm.dataset.bound = "1";

    adoptionPostForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        const publishButton = document.getElementById("publishAdoptionButton");

        if (!window.confirm("Finish and publish this pet for adoption?")) {
            return;
        }

        if (publishButton) {
            publishButton.disabled = true;
            publishButton.textContent = "Publishing...";
        }

        try {
            const response = await fetch(
                "admin_pages/post_pet_for_adoption.php",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                        "Accept": "application/json"
                    },
                    body: new URLSearchParams(new FormData(this)).toString()
                }
            );

            const rawResponse = await response.text();
            let data;

            try {
                data = JSON.parse(rawResponse);
            } catch (_) {
                console.error("Invalid adoption posting response:", rawResponse);
                throw new Error("The server returned an invalid adoption response.");
            }

            if (!response.ok || !data.success) {
                throw new Error(data.message || "Posting failed.");
            }

            alert(data.message || "Pet posted for adoption successfully.");
            closeAdoptionPostModal();
            closeModal("petModal");
            window.location.reload();
        } catch (error) {
            console.error("Posting failed:", error);
            alert(error.message || "Posting failed.");
        } finally {
            if (publishButton) {
                publishButton.disabled = false;
                publishButton.textContent = "Finish and Publish";
            }
        }
    });
}

// Bind immediately when the modal already exists in the page.
bindAdoptionPostForm();

/* =========================================================
   MARK PET AS DECEASED
   ========================================================= */

function openDeceasedModal() {
    const type = document.getElementById("recordType");
    const remarks = document.getElementById("recordRemarks");

    if (type) {
        type.value = "Illness";
    }

    if (remarks) {
        remarks.value = "";
    }

    openModal("deceasedModal");
}

async function confirmDeceased() {
    const type = document.getElementById("recordType")?.value || "";
    const remarks =
        document.getElementById("recordRemarks")?.value || "";

    try {
        const response = await fetch(
            "admin_pages/mark_pet_deceased.php",
            {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded;charset=UTF-8",
                    "Accept": "application/json"
                },
                body:
                    "id=" +
                    encodeURIComponent(currentPetId) +
                    "&record_type=" +
                    encodeURIComponent(type) +
                    "&remarks=" +
                    encodeURIComponent(remarks)
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(
                data.message || "Unable to mark the pet as deceased."
            );
        }

        alert("Pet marked as deceased.");
        closeModal("deceasedModal");
        closeModal("petModal");
        window.location.reload();
    } catch (error) {
        console.error("Mark deceased failed:", error);
        alert(
            error.message ||
            "Unable to mark the pet as deceased."
        );
    }
}

/* =========================================================
   SAFE TEXT FOR ERROR OUTPUT
   ========================================================= */

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}
</script>