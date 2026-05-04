<?php
require_once __DIR__ . '/../api/config.php';
start_secure_session();
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../api/db.php';
$db = get_db();

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['mod_id'])) {
    $mod_id = $_POST['mod_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $db->prepare("UPDATE mods SET status = 'approved' WHERE mod_id = :id");
        $stmt->bindValue(':id', $mod_id);
        $stmt->execute();
        $message = "Mod approved successfully.";
    } elseif ($action === 'reject' || $action === 'delete') {
        // Fetch to delete files
        $stmt = $db->prepare("SELECT file_path, image_path FROM mods WHERE mod_id = :id");
        $stmt->bindValue(':id', $mod_id);
        $res = $stmt->execute();
        if ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            if (file_exists(__DIR__ . '/../' . $row['file_path'])) unlink(__DIR__ . '/../' . $row['file_path']);
            if (!empty($row['image_path']) && file_exists(__DIR__ . '/../' . $row['image_path'])) unlink(__DIR__ . '/../' . $row['image_path']);
        }
        $stmt = $db->prepare("DELETE FROM mods WHERE mod_id = :id");
        $stmt->bindValue(':id', $mod_id);
        $stmt->execute();
        $message = "Mod rejected and deleted.";
    }
}

// Fetch Mods
$mods = [];
$res = $db->query("SELECT * FROM mods ORDER BY published_at DESC");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $mods[] = $row;
}

$page_title = "Manage Mods - Admin";
include '../includes/header.php'; 
?>

<main class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
        <div>
            <h1>Manage Mods</h1>
            <a href="dashboard.php" style="color: var(--pso-blue);"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
    
    <?php if (isset($message)): ?>
        <div style="background: rgba(0,200,81,0.1); border: 1px solid #00C851; color: #00C851; padding: 10px; margin-bottom: 1rem;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div style="overflow-x: auto;">
        <table style="width:100%; border-collapse: collapse; background: rgba(0,0,0,0.6); border: 1px solid #333;">
            <thead>
                <tr style="text-align:left; border-bottom:1px solid #333; background: #111;">
                    <th style="padding: 10px;">Thumbnail</th>
                    <th style="padding: 10px;">Details</th>
                    <th style="padding: 10px;">Submitter & Purpose</th>
                    <th style="padding: 10px;">Status</th>
                    <th style="padding: 10px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mods)): ?>
                    <tr><td colspan="5" style="padding: 20px; text-align: center;">No mods found.</td></tr>
                <?php endif; ?>
                <?php foreach ($mods as $m): ?>
                    <tr style="border-bottom: 1px solid #222;">
                        <td style="padding: 10px;">
                            <?php if (!empty($m['image_path'])): ?>
                                <img src="/<?php echo htmlspecialchars($m['image_path']); ?>" style="width: 100px; height: auto; border-radius: 4px;">
                            <?php else: ?>
                                <div style="width: 100px; height: 60px; background: #333; display: flex; align-items: center; justify-content: center; color: #666;"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px;">
                            <strong><?php echo htmlspecialchars($m['name']); ?></strong> v<?php echo htmlspecialchars($m['version']); ?><br>
                            <small style="color: #aaa;">Author: <?php echo htmlspecialchars($m['author']); ?> | <?php echo htmlspecialchars($m['category']); ?></small><br>
                            <a href="/<?php echo htmlspecialchars($m['file_path']); ?>" style="color: var(--pso-blue); font-size: 0.9em;" target="_blank"><i class="fas fa-download"></i> Download Zip</a>
                        </td>
                        <td style="padding: 10px;">
                            <div style="font-size: 0.9em;">
                                <strong style="color: var(--pso-purple);">By:</strong> <?php echo htmlspecialchars($m['submitted_by']); ?><br>
                                <strong style="color: var(--pso-purple);">Purpose:</strong><br>
                                <div style="background: rgba(255,255,255,0.05); padding: 5px; margin-top: 5px; font-size: 0.85em; max-height: 80px; overflow-y: auto;">
                                    <?php echo nl2br(htmlspecialchars($m['purpose'])); ?>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 10px;">
                            <?php if ($m['status'] === 'approved'): ?>
                                <span style="background: rgba(0, 200, 81, 0.2); color: #00C851; padding: 3px 8px; border-radius: 3px;">Approved</span>
                            <?php else: ?>
                                <span style="background: rgba(255, 170, 0, 0.2); color: #ffca28; padding: 3px 8px; border-radius: 3px;">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="mod_id" value="<?php echo htmlspecialchars($m['mod_id']); ?>">
                                <?php if ($m['status'] !== 'approved'): ?>
                                    <button type="submit" name="action" value="approve" class="dl-btn" style="background: rgba(0,200,81,0.2); border-color: #00C851; color: #00C851; padding: 5px 10px; font-size: 0.8rem; margin-right: 5px;"><i class="fas fa-check"></i> Approve</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="reject" class="dl-btn" style="background: rgba(255,68,68,0.2); border-color: #ff4444; color: #ff4444; padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Are you sure you want to delete this mod?');"><i class="fas fa-times"></i> Reject/Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
