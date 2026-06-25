<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';

require_admin();

$matches = $pdo->query("SELECT i.*, 
                        s.full_name as sender_name, s.email as sender_email,
                        r.full_name as receiver_name, r.email as receiver_email
                        FROM interests i
                        JOIN users s ON i.sender_id = s.id
                        JOIN users r ON i.receiver_id = r.id
                        WHERE i.status = 'Accepted'
                        ORDER BY i.created_at DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h1 class="h3 fw-bold text-dark mb-0">Successful Matches</h1>
        <p class="text-muted small">Viewing all users who have accepted interests and formed a match.</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Sender</th>
                        <th>Receiver</th>
                        <th>Match Date</th>
                        <th class="pe-4 text-end">Contact Info</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($matches)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No matches formed yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($matches as $m): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= htmlspecialchars($m['sender_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($m['sender_email']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($m['receiver_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($m['receiver_email']) ?></small>
                                </td>
                                <td><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                                <td class="pe-4 text-end">
                                    <a href="user_details.php?id=<?= $m['sender_id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/header.php'; ?>
