<?php
$page_title = 'Downloads - PSOBB Private Server';
$current_page = 'downloads';
include 'includes/header.php';
?>

<div class="pso-spinner-svg">
    <canvas id="star-canvas-dl"></canvas>
    <!-- Hex and sigils same as index, but lighter or omit for page focus -->
    <svg class="hex2" viewBox="0 0 8465 8477" width="140vw"><!-- abbreviated for brevity --></svg>
</div>

<main class="container">
    <div class="main-header">
        <h1><?= __('Download Clients') ?></h1>
        <p><?= __('Download the patched PSO Blue Burst client for your platform. Follow the instructions after download.') ?></p>
    </div>

    <div class="download-grid">
        <div class="dl-card dl-windows">
            <div class="dl-icon"><i class="fab fa-windows"></i></div>
            <h3><?= __('Windows (Beta)') ?></h3>
            <p><?= __('Native PSO BB PC client with Widescreen support and Frame generation. <strong>Supports both JP and EN players.</strong>') ?></p>
            <div class="dl-meta">
                <span><i class="fas fa-hdd"></i> 851 MB</span>
                <span><i class="fas fa-code-branch"></i> v1.25.13b</span>
            </div>
            <a href="/downloads/PSOBBIO-Setup_1.25.13b.exe" class="dl-btn" download><i class="fas fa-download"></i>
                <?= __('Download') ?></a>
            <small><?= __('Requires Windows 10 or later') ?></small>
            <div style="font-size: 0.8em; margin-top: 10px; opacity: 0.8;">
                <?= __('Based on the amazing work by') ?> <a href="https://github.com/anzz1/psobb_patches" target="_blank"
                    style="color: inherit; text-decoration: underline;">anzz1</a>
            </div>
        </div>

        <div class="dl-card dl-mac">
            <div class="dl-icon"><i class="fab fa-apple"></i></div>
            <h3><?= __('Mac (Alpha)') ?></h3>
            <p><?= __('PSO BB Mac client.') ?></p>
            <div class="dl-meta">
                <span><i class="fas fa-hdd"></i> 1.89 GB</span>
                <span><i class="fas fa-code-branch"></i> v1.25.13</span>
            </div>
            <a href="/downloads/PSOBBIO_125.13.dmg" class="dl-btn" download><i class="fas fa-download"></i> <?= __('Download') ?></a>
            <small><?= __('Requires Macbook Pro or Macbook Air M2 or higher') ?></small>
        </div>

        <div class="dl-card dl-linux">
            <div class="dl-icon"><i class="fab fa-linux"></i></div>
            <h3><?= __('Linux & Steam Deck') ?></h3>
            <p><?= __('Native support via Wine/Proton.') ?></p>
            <div class="dl-meta">
                <span><i class="fas fa-hdd"></i> 1.08 GB</span>
                <span><i class="fas fa-code-branch"></i> v1.25.13</span>
            </div>
            <div class="deck-install"
                style="margin: 15px 0; text-align: left; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px;">
                <strong style="display:block; margin-bottom:5px; color:#4fc3f7;"><?= __('Steam Deck (Desktop Mode):') ?></strong>
                <code
                    style="display:block; background:#222; padding:5px; font-size:0.85em; user-select:all; cursor:pointer;"
                    onclick="navigator.clipboard.writeText(this.innerText); alert('Copied to clipboard!');">curl -sL https://psobb.io/install-deck.sh | bash</code>
                <small style="display:block; margin-top:5px; color:#aaa;"><?= __('(Click command to copy)') ?></small>
            </div>
            <div style="margin-top: 10px;">
                <strong style="color:#aaa;"><?= __('Other Linux:') ?></strong>
                <a href="/downloads/PSOBBIO-Linux_1.25.13.zip" class="dl-btn" download style="margin-top: 5px;"><i
                        class="fas fa-download"></i> <?= __('Download Zip') ?></a>
            </div>
            <small><?= __('Ubuntu 20.04+ or SteamOS') ?></small>
        </div>
    </div>

    <div class="about-content" style="margin-top: 2rem;">
        <h2><?= __('Installation Instructions') ?></h2>
        <ol>
            <li><?= __('Download the client for your platform.') ?></li>
            <li><?= __('While waiting for the download, <a href="/register.php">create account</a> via website login.') ?></li>
            <li><?= __('Run the installer.') ?></li>
            <li><?= __('Launch PSOBB and select options, customize to your preferences.') ?></li>
            <li><?= __('Exempt psobb.exe from your antivirus.') ?></li>
            <li><?= __('Launch the game when you are ready!') ?></li>
            <li><?= __('Happy hunting on Ragol!') ?></li>
        </ol>
        <p><?= __('<strong>Note:</strong> Patches from newserv/system/patch-pc applied. Server address: <code>psobb.io</code>') ?></p>
    </div>
</main>

<?php include 'includes/footer.php'; ?>