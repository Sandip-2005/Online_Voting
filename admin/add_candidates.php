<?php
?>
<div class="modal-dialog">
    <form method="POST" class="modal-content" enctype="multipart/form-data" action="admin_dashboard.php">

        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Polling Station</label>
                <select name="station_id" class="form-select" required>
                    <option value="">Select Polling Station</option>
                    <?php foreach ($stations as $station) { ?>
                        <option value="<?php echo $station['id']; ?>">
                            <?php echo htmlspecialchars($station['name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Candidate Name</label>
                <input type="text" name="candidate_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Candidate Email</label>
                <input type="email" name="candidate_email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Candidate Phone</label>
                <input type="text" name="candidate_phone" class="form-control" required pattern="\d{10}">
            </div>
            <div class="mb-3">
                <label class="form-label">Candidate Picture</label>
                <input type="file" name="candidate_picture" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Party</label>
                <select name="party" class="form-select" required>
                    <option value="">Select Party</option>
                    <option value="pjb">&#x1F7E1; PJB</option>
                    <option value="cmt">&#x1F7E3; CMT</option>
                    <option value="mpc">&#x1F7E2; MPC</option>
                </select>
            </div>
            <input type="hidden" name="add_candidate" value="1">
        </div>
        <div class="modal-footer d-flex flex-column gap-2">
            <button type="submit" class="btn btn-success w-100">Add Candidate</button>
            <a href="admin_dashboard.php" class="btn btn-secondary w-100">Cancel</a>
        </div>
    </form>
</div>