<?php
$page_title = 'Reset Password - PSOBB Private Server';
include 'includes/header.php';

$token = $_GET['token'] ?? '';
?>

<main class="container">
    <div class="login-container" style="max-width: 500px; margin: 0 auto; margin-top: 5rem;">
        <h1><?= __('Set New Password') ?></h1>
        
        <div class="login-container-form">
            <?php if (!$token): ?>
                <div style="color: #ff4444;"><?= __('Invalid request. No token provided.') ?></div>
            <?php else: ?>
                <div id="rp-message" style="display:none; padding:10px; margin-bottom:1rem; border-radius:4px;"></div>

                <form id="reset-form">
                    <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="new_password"><?= __('New Password') ?></label>
                        <input type="password" id="new_password" required placeholder="<?= __('New Password') ?>" style="width:100%; padding:10px; background:rgba(0,0,0,0.5); border:1px solid #444; color:white;">
                    </div>

                    <button type="submit" class="dl-btn" style="width:100%; margin-top:1rem;"><?= __('Reset Password') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
const form = document.getElementById('reset-form');
if (form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const token = document.getElementById('token').value;
        const password = document.getElementById('new_password').value;
        const msg = document.getElementById('rp-message');
        const btn = e.target.querySelector('button');

        btn.disabled = true;
        btn.textContent = "<?= __('Processing...') ?>";
        msg.style.display = 'none';

        try {
            const res = await fetch('api/reset_password.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({token, password})
            });
            
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Invalid JSON:", text);
                throw new Error("Server returned invalid response: " + text.substring(0, 50) + "...");
            }
            
            if (data.success) {
                msg.textContent = "<?= __('Password updated successfully!') ?>";
                msg.style.background = 'rgba(0, 200, 81, 0.2)';
                msg.style.color = '#00C851';
                msg.style.display = 'block';
                setTimeout(() => window.location.href = 'login.php', 2000);
            } else {
                msg.textContent = data.error || "<?= __('Reset failed.') ?>";
                msg.style.background = 'rgba(255, 68, 68, 0.2)';
                msg.style.color = '#ff4444';
                msg.style.display = 'block';
                btn.disabled = false;
                btn.textContent = "<?= __('Reset Password') ?>";
            }
        } catch(err) {
            msg.textContent = err.message;
            msg.style.background = 'rgba(255, 68, 68, 0.2)';
            msg.style.color = '#ff4444';
            msg.style.display = 'block';
            btn.disabled = false;
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
