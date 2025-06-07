<div class="complaint-card <?php echo ($complaint['status'] === 'Pending' && !isset($complaint['is_read'])) ? 'unread' : ''; ?>" id="complaint-<?php echo $complaint['id']; ?>">
    <div class="complaint-header">
        <div class="student-info">
            <p><strong>Student Name:</strong> <?php echo htmlspecialchars($complaint['student_name']); ?></p>
            <p><strong>Roll No:</strong> <?php echo htmlspecialchars($complaint['student_roll_no']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($complaint['student_email']); ?></p>
        </div>
        <span class="timestamp">
            <?php echo date('M d, Y h:i A', strtotime($complaint['created_at'])); ?>
        </span>
    </div>
    
    <div class="complaint-content">
        <p><?php echo htmlspecialchars($complaint['complaint']); ?></p>
    </div>
    
    <?php if (!empty($complaint['proof_files'])): ?>
    <div class="proof-files">
        <strong>Attachments:</strong>
        <?php 
        $files = explode(',', $complaint['proof_files']);
        foreach ($files as $file): ?>
            <a href="<?php echo htmlspecialchars($file); ?>" target="_blank">View File</a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <span class="status-badge status-<?php echo strtolower($complaint['status']); ?>">
        <?php echo htmlspecialchars($complaint['status']); ?>
    </span>

    <?php if ($complaint['status'] === 'Pending'): ?>
    <div class="complaint-actions">
        <button class="action-btn accept-btn" data-id="<?php echo $complaint['id']; ?>" data-action="accept">
            Accept
        </button>
        <button class="action-btn reject-btn" data-id="<?php echo $complaint['id']; ?>" data-action="reject">
            Reject
        </button>
    </div>
    <?php else: ?>
    <div class="complaint-actions">
        <span>Action Taken</span>
    </div>
    <?php endif; ?>
</div>