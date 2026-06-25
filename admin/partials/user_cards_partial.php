<?php
// admin/partials/user_cards_partial.php
// Used both on initial page load and for AJAX requests
// Requires $users, $total_pages, $page, $q, $status_filter, $gender_filter, $plan_filter, $date_filter, $sort
?>
<?php if (empty($users)): ?>
<div class="col-12">
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">
        <i class="bi bi-person-x display-1 opacity-25 mb-3"></i>
        <h5 class="fw-bold">No users found</h5>
        <p class="small">Try adjusting your filters or search term.</p>
    </div>
</div>
<?php else: ?>

<!-- List view: table -->
<div id="listViewContainer">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" width="40"><input type="checkbox" id="selectAllInner" class="form-check-input" onchange="document.querySelectorAll('.user-checkbox').forEach(cb=>cb.checked=this.checked)"></th>
                        <th>Member</th>
                        <th>Status</th>
                        <th>Plan</th>
                        <th>City</th>
                        <th>Joined</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr class="<?= $u['ip_matches'] > 0 ? 'bg-danger bg-opacity-5' : '' ?>">
                        <td class="ps-4">
                            <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" class="user-checkbox form-check-input">
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="position-relative">
                                    <img src="/online-rishta-system/assets/images/uploads/<?= $u['profile_pic'] ?: 'default.jpg' ?>" class="rounded-circle border shadow-sm" width="44" height="44" style="object-fit:cover;">
                                    <?php if($u['status'] == 'Active'): ?>
                                    <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white rounded-circle" style="width:12px;height:12px;display:block;"></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small"><?= htmlspecialchars($u['full_name']) ?></div>
                                    <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($u['email']) ?></div>
                                    <?php if($u['ip_matches'] > 0): ?>
                                    <span class="badge bg-danger bg-opacity-15 text-danger" style="font-size:9px;"><i class="bi bi-exclamation-triangle me-1"></i>Duplicate IP</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $u['status']=='Active'?'success':($u['status']=='Suspended'?'warning text-dark':'danger') ?>" style="font-size:10px;"><?= $u['status'] ?></span>
                            <div class="mt-1"><span class="badge bg-<?= $u['is_verified']?'primary':'secondary' ?> bg-opacity-15 text-<?= $u['is_verified']?'primary':'secondary' ?>" style="font-size:9px;"><?= $u['is_verified']?'Verified':'Unverified' ?></span></div>
                        </td>
                        <td><span class="fw-bold small"><?= $u['plan_name'] ?? 'Free' ?></span></td>
                        <td class="text-muted small"><?= htmlspecialchars($u['city'] ?? '—') ?></td>
                        <td class="text-muted small"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <a href="user_form.php?id=<?= $u['id'] ?>" class="btn btn-white border shadow-sm" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                <a href="user_details.php?id=<?= $u['id'] ?>" class="btn btn-white border shadow-sm" title="View"><i class="bi bi-eye"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Grid view (initially hidden) -->
<div id="gridViewContainer" class="row g-3 d-none">
    <?php foreach($users as $u): ?>
    <div class="col-md-6 col-lg-4 col-xl-3 user-col">
        <div class="card user-grid-card bg-white p-3 h-100">
            <div class="text-center mb-3">
                <div class="position-relative d-inline-block">
                    <img src="/online-rishta-system/assets/images/uploads/<?= $u['profile_pic'] ?: 'default.jpg' ?>" class="user-avatar-lg mb-0">
                    <?php if($u['status'] == 'Active'): ?>
                    <span class="position-absolute bottom-0 end-0 border border-white rounded-circle bg-success" style="width:14px;height:14px;display:block;"></span>
                    <?php endif; ?>
                </div>
                <div class="mt-2">
                    <div class="fw-bold"><?= htmlspecialchars($u['full_name']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($u['city'] ?? 'Unknown') ?></div>
                </div>
            </div>
            <div class="d-flex justify-content-center gap-1 mb-3 flex-wrap">
                <span class="badge bg-<?= $u['status']=='Active'?'success':($u['status']=='Suspended'?'warning text-dark':'danger') ?>" style="font-size:10px;"><?= $u['status'] ?></span>
                <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:10px;"><?= $u['plan_name'] ?? 'Free' ?></span>
                <?php if($u['is_verified']): ?><span class="badge bg-info bg-opacity-15 text-info" style="font-size:10px;"><i class="bi bi-shield-check"></i></span><?php endif; ?>
            </div>
            <div class="text-muted small text-center mb-3"><?= date('M d, Y', strtotime($u['created_at'])) ?></div>
            <div class="d-flex gap-2 mt-auto">
                <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" class="user-checkbox form-check-input me-1">
                <a href="user_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary w-50 rounded-pill">Edit</a>
                <a href="user_details.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary w-50 rounded-pill">View</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<div class="d-flex justify-content-center mt-4">
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php if($page > 1): ?>
            <li class="page-item"><a class="page-link rounded-pill me-1" href="?page=<?= $page-1 ?>&q=<?= urlencode($q) ?>&status=<?= $status_filter ?>&gender=<?= $gender_filter ?>&plan=<?= $plan_filter ?>&date_filter=<?= $date_filter ?>&sort=<?= $sort ?>">← Prev</a></li>
            <?php endif; ?>
            <?php for($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <li class="page-item <?= $i==$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= $status_filter ?>&gender=<?= $gender_filter ?>&plan=<?= $plan_filter ?>&date_filter=<?= $date_filter ?>&sort=<?= $sort ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <?php if($page < $total_pages): ?>
            <li class="page-item"><a class="page-link rounded-pill ms-1" href="?page=<?= $page+1 ?>&q=<?= urlencode($q) ?>&status=<?= $status_filter ?>&gender=<?= $gender_filter ?>&plan=<?= $plan_filter ?>&date_filter=<?= $date_filter ?>&sort=<?= $sort ?>">Next →</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>

<script>
// Wire up view toggle buttons to new containers
document.getElementById('listViewBtn')?.addEventListener('click', () => {
    document.getElementById('listViewContainer')?.classList.remove('d-none');
    document.getElementById('gridViewContainer')?.classList.add('d-none');
});
document.getElementById('gridViewBtn')?.addEventListener('click', () => {
    document.getElementById('gridViewContainer')?.classList.remove('d-none');
    document.getElementById('listViewContainer')?.classList.add('d-none');
});
</script>
