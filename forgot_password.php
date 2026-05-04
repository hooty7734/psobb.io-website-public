<?php
$page_title = 'Forgot Password - PSOBB Private Server';
include 'includes/header.php';
?>

<main class="container">
    <div class="login-container" style="max-width: 500px; margin: 0 auto; margin-top: 5rem;">
        <h1><?= __('Recover Account') ?></h1>
        
        <div class="login-container-form">
            <p><?= __('Enter the email address associated with your account. We will send you a link to reset your password.') ?></p>
            
            <div id="fp-message" style="display:none; padding:10px; margin-bottom:1rem; border-radius:4px;"></div>

            <form id="forgot-form">
                <div class="form-group">
                    <label for="email"><?= __('Email Address') ?></label>
                    <input type="email" id="email" name="email" placeholder="hunter@example.com" required style="width:100%; padding:10px; background:rgba(0,0,0,0.5); border:1px solid #444; color:white;">
                </div>
                
                <button type="submit" class="dl-btn" style="width:100%; margin-top:1rem;"><?= __('Send Reset Link') ?></button>
            </form>
            
            <div style="margin-top:2rem; text-align:center;">
                <a href="login.php" style="color:#aaa;"><?= __('Back to Login') ?></a>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('forgot-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const btn = e.target.querySelector('button');
    const msg = document.getElementById('fp-message');
    
    btn.disabled = true;
    btn.textContent = "<?= __('Sending...') ?>";
    msg.style.display = 'none';

    try {
        const res = await fetch('api/forgot_password.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({email})
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
            msg.textContent = data.message;
            msg.style.background = 'rgba(0, 200, 81, 0.2)';
            msg.style.color = '#00C851';
            msg.style.display = 'block';
            e.target.reset();
        } else {
            msg.textContent = data.error || "<?= __('Failed to send email.') ?>";
            msg.style.background = 'rgba(255, 68, 68, 0.2)';
            msg.style.color = '#ff4444';
            msg.style.display = 'block';
        }
    } catch(err) {
        msg.textContent = err.message;
        msg.style.background = 'rgba(255, 68, 68, 0.2)';
        msg.style.color = '#ff4444';
        msg.style.display = 'block';
    }
    btn.disabled = false;
    btn.textContent = "<?= __('Send Reset Link') ?>";
});
</script>

<?php include 'includes/footer.php'; ?>
