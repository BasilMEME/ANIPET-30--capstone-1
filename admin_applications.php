<?php
require_once __DIR__ . '/auth_helper.php';
require_permission($conn, 'manage_applications');

$sql = "SELECT id, pet_id, user_id, applicant_name, message, status, created_at
        FROM adoption_applications
        ORDER BY id DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Applications</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        h2 {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #eee;
        }
        .btn {
            padding: 8px 12px;
            text-decoration: none;
            color: white;
            border-radius: 4px;
            margin-right: 5px;
            display: inline-block;
        }
        .approve {
            background: green;
        }
        .reject {
            background: red;
        }
        .pending {
            color: orange;
            font-weight: bold;
        }
        .approved {
            color: green;
            font-weight: bold;
        }
        .rejected {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>

<h2>Admin - Adoption Applications</h2>

<table>
    <tr>
        <th>ID</th>
        <th>Pet ID</th>
        <th>User ID</th>
        <th>Applicant Name</th>
        <th>Message</th>
        <th>Status</th>
        <th>Date</th>
        <th>Action</th>
    </tr>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo $row['pet_id']; ?></td>
                <td><?php echo $row['user_id']; ?></td>
                <td><?php echo htmlspecialchars($row['applicant_name']); ?></td>
                <td><?php echo htmlspecialchars($row['message']); ?></td>
                <td class="<?php echo strtolower($row['status']); ?>">
                    <?php echo ucfirst($row['status']); ?>
                </td>
                <td><?php echo $row['created_at']; ?></td>
                <td>
                    <?php if ($row['status'] === 'pending'): ?>
                        <a class="btn approve" href="approve_application.php?id=<?php echo $row['id']; ?>">Approve</a>
                        <a class="btn reject" href="reject_application.php?id=<?php echo $row['id']; ?>">Reject</a>
                    <?php else: ?>
                        No actions
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="8">No applications found.</td>
        </tr>
    <?php endif; ?>
</table>

</body>
</html>