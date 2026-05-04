<?php
/**
 * PSOBB Website: Game Mods Directory
 * 
 * Displays approved client modifications (HUDs, Skins, Texture Packs) submitted 
 * by the community. Allows users to download, rate, and submit their own mods.
 */
$page_title = 'Mods - PSOBB Private Server';
$current_page = 'mods';
include 'includes/header.php';
?>

<div class="pso-spinner-svg">
    <canvas id="star-canvas-dl"></canvas>
</div>

<main class="container">
    <div class="main-header">
        <h1>Mods & Customizations</h1>
        <p>Browse, download, and submit mods for the PSOBB client. Mods are installed automatically via the Launcher.</p>
    </div>

    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h2 style="margin:0;">Approved Mods</h2>
        
        <div style="flex-grow: 1; max-width: 400px; position: relative;">
            <input type="text" id="mod-search" placeholder="Search mods by name, author, or category..." style="width: 100%; padding: 10px 15px 10px 35px; border-radius: 20px; border: 1px solid var(--pso-blue); background: rgba(0,0,0,0.5); color: #fff; box-sizing: border-box; outline: none;" oninput="filterMods()">
            <i class="fas fa-search" style="position: absolute; left: 12px; top: 12px; color: var(--pso-blue);"></i>
        </div>

        <?php if (isset($_SESSION['user'])): ?>
            <button onclick="document.getElementById('submit-mod-modal').style.display='flex'" class="dl-btn" style="background: var(--pso-blue); color: #000;"><i class="fas fa-plus"></i> Submit Mod</button>
        <?php else: ?>
            <a href="/login.php" class="dl-btn" style="background: rgba(0, 255, 255, 0.1);"><i class="fas fa-sign-in-alt"></i> Login to Submit Mod</a>
        <?php endif; ?>
    </div>

    <div id="mods-list" class="download-grid">
        <p>Loading mods...</p>
    </div>

    <!-- Submit Mod Modal -->
    <div id="submit-mod-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center; overflow-y:auto; padding: 20px;">
        <div style="background: #1a1a1a; padding: 2rem; border-radius: 8px; border: 1px solid var(--pso-blue); max-width: 600px; width: 100%; box-shadow: 0 0 20px rgba(0, 255, 255, 0.2); margin: auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem;">
                <h3 style="color: #fff; margin:0;">Submit a Mod</h3>
                <button onclick="document.getElementById('submit-mod-modal').style.display='none'" style="background:transparent; border:none; color:#aaa; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            
            <form id="submit-mod-form" enctype="multipart/form-data">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="color:#ccc; font-size:0.9em; display:block; margin-bottom:5px;">Mod Name</label>
                        <input type="text" name="name" required style="width:100%; padding:8px; background:#000; border:1px solid #444; color:#fff; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="color:#ccc; font-size:0.9em; display:block; margin-bottom:5px;">Author</label>
                        <input type="text" name="author" value="<?php echo htmlspecialchars($_SESSION['user']['username'] ?? ''); ?>" required style="width:100%; padding:8px; background:#000; border:1px solid #444; color:#fff; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="color:#ccc; font-size:0.9em; display:block; margin-bottom:5px;">Version (e.g. 1.0.0)</label>
                        <input type="text" name="version" required style="width:100%; padding:8px; background:#000; border:1px solid #444; color:#fff; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="color:#ccc; font-size:0.9em; display:block; margin-bottom:5px;">Category</label>
                        <select name="category" required style="width:100%; padding:8px; background:#000; border:1px solid #444; color:#fff; box-sizing:border-box;">
                            <option value="UI">UI</option>
                            <option value="Textures">Textures</option>
                            <option value="Audio">Audio</option>
                            <option value="Plugin">Plugin</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="color:#ccc; font-size:0.9em; display:block; margin-bottom:5px;">Short Description</label>
                        <textarea name="description" required style="width:100%; padding:8px; background:#000; border:1px solid #444; color:#fff; box-sizing:border-box; resize:vertical; min-height:60px;"></textarea>
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="color:#ccc; font-size:0.9em; display:block; margin-bottom:5px;">Detailed Purpose (Why are you submitting this?)</label>
                        <textarea name="purpose" required style="width:100%; padding:8px; background:#000; border:1px solid #444; color:#fff; box-sizing:border-box; resize:vertical; min-height:60px;"></textarea>
                    </div>
                    <div style="grid-column: span 2; background: rgba(0, 255, 255, 0.05); border-left: 3px solid var(--pso-blue); padding: 10px 15px; margin-bottom: 5px;">
                        <h4 style="margin: 0 0 5px 0; color: var(--pso-blue);">Archive Structure Rules</h4>
                        <p style="margin: 0; font-size: 0.85em; color: #ccc; line-height: 1.4;">
                            The Launcher will extract your mod <strong>directly</strong> into the game's root directory. 
                            Your `.zip` file must maintain the correct folder structure and should NOT contain an extra top-level folder.<br>
                            <span style="color:#00ff00;">Correct:</span> <code>data/Texture/custom_title.xvm</code><br>
                            <span style="color:#ff4444;">Incorrect:</span> <code>MyModFolder/data/Texture/custom_title.xvm</code>
                        </p>
                    </div>
                    <div>
                        <label style="color:#ccc; font-size:0.9em; display:block; margin-bottom:5px;">Mod Archive (.zip)</label>
                        <input type="file" name="mod_file" accept=".zip" required style="color:#ccc;">
                    </div>
                    <div>
                        <label style="color:#ccc; font-size:0.9em; display:block; margin-bottom:5px;">Media (Max 6: .jpg, .png, .mp4, .webm)</label>
                        <input type="file" name="mod_images[]" accept="image/png, image/jpeg, video/mp4, video/webm" multiple style="color:#ccc;">
                    </div>
                </div>
                
                <div id="submit-error" style="color: #ff4444; display: none; margin-top: 1rem; padding: 10px; background: rgba(255,0,0,0.1); border: 1px solid #ff4444;"></div>
                <div id="submit-success" style="color: #00C851; display: none; margin-top: 1rem; padding: 10px; background: rgba(0,200,81,0.1); border: 1px solid #00C851;"></div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" id="btn-submit-mod" class="dl-btn">Submit for Approval</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
const IS_LOGGED_IN = <?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>;

document.addEventListener('DOMContentLoaded', () => {
    fetchMods();

    document.getElementById('submit-mod-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-submit-mod');
        const err = document.getElementById('submit-error');
        const suc = document.getElementById('submit-success');
        
        btn.disabled = true;
        btn.textContent = 'Uploading...';
        err.style.display = 'none';
        suc.style.display = 'none';

        try {
            const formData = new FormData(e.target);
            const res = await fetch('api/submit_mod.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.getCSRFToken() },
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                suc.textContent = 'Mod submitted successfully! It is pending admin approval.';
                suc.style.display = 'block';
                e.target.reset();
                setTimeout(() => {
                    document.getElementById('submit-mod-modal').style.display='none';
                    suc.style.display = 'none';
                }, 3000);
            } else {
                err.textContent = data.error || 'Failed to submit mod.';
                err.style.display = 'block';
            }
        } catch(ex) {
            err.textContent = 'Connection error: ' + ex.message;
            err.style.display = 'block';
        }

        btn.disabled = false;
        btn.textContent = 'Submit for Approval';
    });
});

async function fetchMods() {
    try {
        const res = await fetch('api/mods.php');
        const data = await res.json();
        
        const list = document.getElementById('mods-list');
        list.innerHTML = '';
        
        if (data.length === 0) {
            list.innerHTML = '<p>No mods approved yet.</p>';
            return;
        }

        data.forEach(m => {
            let mediaHtml = `<div style="width:100%; height:150px; background:#333; border-radius:5px; margin-bottom:10px; display:flex; align-items:center; justify-content:center; color:#555;"><i class="fas fa-box-open fa-3x"></i></div>`;
            
            if (m.imageUrls && m.imageUrls.length > 0) {
                if (m.imageUrls.length === 1) {
                    const url = m.imageUrls[0];
                    const ext = url.split('.').pop().toLowerCase();
                    if (ext === 'mp4' || ext === 'webm') {
                        mediaHtml = `<video src="${url}" autoplay loop muted style="width:100%; height:150px; object-fit:cover; border-radius:5px; margin-bottom:10px;"></video>`;
                    } else {
                        mediaHtml = `<img src="${url}" style="width:100%; height:150px; object-fit:cover; border-radius:5px; margin-bottom:10px;">`;
                    }
                } else {
                    // Build Carousel
                    let slides = '';
                    let dots = '';
                    m.imageUrls.forEach((url, idx) => {
                        const ext = url.split('.').pop().toLowerCase();
                        let inner = '';
                        if (ext === 'mp4' || ext === 'webm') {
                            inner = `<video src="${url}" autoplay loop muted style="width:100%; height:100%; object-fit:cover;"></video>`;
                        } else {
                            inner = `<img src="${url}" style="width:100%; height:100%; object-fit:cover;">`;
                        }
                        slides += `<div class="mod-carousel-slide ${idx === 0 ? 'active' : ''}">${inner}</div>`;
                        dots += `<span class="mod-carousel-dot ${idx === 0 ? 'active' : ''}" onclick="setCarouselSlide('${m.id}', ${idx})"></span>`;
                    });
                    
                    mediaHtml = `
                        <div class="mod-carousel" id="carousel-${m.id}">
                            <div class="mod-carousel-inner">
                                ${slides}
                            </div>
                            <button class="mod-carousel-prev" onclick="moveCarousel('${m.id}', -1)">&#10094;</button>
                            <button class="mod-carousel-next" onclick="moveCarousel('${m.id}', 1)">&#10095;</button>
                            <div class="mod-carousel-dots">${dots}</div>
                        </div>
                    `;
                }
            }
            
            let starsHtml = '';
            const avg = m.averageRating ? parseFloat(m.averageRating) : 0;
            const count = m.ratingCount ? parseInt(m.ratingCount) : 0;
            
            if (IS_LOGGED_IN) {
                starsHtml = `
                    <div class="star-rating" data-mod-id="${m.id}" style="margin-bottom: 10px;">
                        <i class="fas fa-star" data-rating="5"></i>
                        <i class="fas fa-star" data-rating="4"></i>
                        <i class="fas fa-star" data-rating="3"></i>
                        <i class="fas fa-star" data-rating="2"></i>
                        <i class="fas fa-star" data-rating="1"></i>
                        <span class="rating-text" style="font-size:0.8em; color:#888; direction:ltr; margin-left:8px;">${avg.toFixed(1)} (${count})</span>
                    </div>
                `;
            } else {
                let staticStars = '';
                for(let i=1; i<=5; i++) {
                    staticStars += `<i class="fas fa-star ${i <= Math.round(avg) ? 'filled' : ''}"></i>`;
                }
                starsHtml = `
                    <div class="star-rating-static" style="margin-bottom: 10px;">
                        ${staticStars}
                        <span style="font-size:0.8em; color:#888; margin-left:8px;">${avg.toFixed(1)} (${count})</span>
                    </div>
                `;
            }

            list.innerHTML += `
                <div class="dl-card" style="padding:15px;">
                    ${mediaHtml}
                    <h3 style="margin:0 0 5px 0;">${m.name}</h3>
                    <div style="font-size:0.8em; color:#aaa; margin-bottom:5px;">By ${m.author} | v${m.version} | ${m.category}</div>
                    ${starsHtml}
                    <p style="font-size:0.9em; margin-bottom:15px; flex-grow:1;">${m.description}</p>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.8em; color:#888;">${(m.fileSize / 1024 / 1024).toFixed(2)} MB</span>
                        <a href="${m.downloadUrl}" class="dl-btn" download style="padding: 5px 10px; font-size:0.9em;"><i class="fas fa-download"></i> Zip</a>
                    </div>
                </div>
            `;
        });
        
        // Bind rating events
        if (IS_LOGGED_IN) {
            document.querySelectorAll('.star-rating i').forEach(star => {
                star.addEventListener('click', async (e) => {
                    const rating = e.target.getAttribute('data-rating');
                    const container = e.target.closest('.star-rating');
                    const modId = container.getAttribute('data-mod-id');
                    
                    const formData = new URLSearchParams();
                    formData.append('mod_id', modId);
                    formData.append('rating', rating);
                    
                    try {
                        const res = await fetch('api/rate_mod.php', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-CSRF-Token': window.getCSRFToken()
                            },
                            body: formData.toString()
                        });
                        const data = await res.json();
                        if (data.success) {
                            // Update UI stars to reflect the user's vote visually immediately
                            Array.from(container.children).forEach(c => {
                                if(c.tagName === 'I') c.classList.remove('active');
                            });
                            e.target.classList.add('active');
                            
                            // Update the average text
                            container.querySelector('.rating-text').textContent = `${data.average.toFixed(1)} (${data.count})`;
                        } else {
                            alert(data.error || 'Failed to rate mod');
                        }
                    } catch(ex) {
                        alert('Connection error while rating mod');
                    }
                });
            });
        }
    } catch (e) {
        document.getElementById('mods-list').innerHTML = '<p style="color:#ff4444;">Failed to load mods.</p>';
    }
}

function moveCarousel(modId, direction) {
    const carousel = document.getElementById('carousel-' + modId);
    if(!carousel) return;
    const slides = carousel.querySelectorAll('.mod-carousel-slide');
    const dots = carousel.querySelectorAll('.mod-carousel-dot');
    let activeIdx = 0;
    slides.forEach((s, idx) => { if(s.classList.contains('active')) activeIdx = idx; });
    
    let newIdx = activeIdx + direction;
    if(newIdx < 0) newIdx = slides.length - 1;
    if(newIdx >= slides.length) newIdx = 0;
    
    setCarouselSlide(modId, newIdx);
}

function setCarouselSlide(modId, idx) {
    const carousel = document.getElementById('carousel-' + modId);
    if(!carousel) return;
    const slides = carousel.querySelectorAll('.mod-carousel-slide');
    const dots = carousel.querySelectorAll('.mod-carousel-dot');
    
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    
    if(slides[idx]) slides[idx].classList.add('active');
    if(dots[idx]) dots[idx].classList.add('active');
}

function filterMods() {
    const query = document.getElementById('mod-search').value.toLowerCase();
    const cards = document.querySelectorAll('#mods-list > div.download-card');
    let hasVisible = false;
    
    cards.forEach(card => {
        // Extract searchable text from the card
        const title = card.querySelector('h3') ? card.querySelector('h3').textContent.toLowerCase() : '';
        const authorMatch = card.textContent.match(/By:\s*([^\n<]+)/i);
        const author = authorMatch ? authorMatch[1].toLowerCase() : '';
        const categoryMatch = card.textContent.match(/Category:\s*([^\n<]+)/i);
        const category = categoryMatch ? categoryMatch[1].toLowerCase() : '';
        
        // Check if any field matches
        if (title.includes(query) || author.includes(query) || category.includes(query)) {
            card.style.display = 'flex'; // Restore grid/flex layout
            hasVisible = true;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Handle empty state if search yields no results
    let noResultsMsg = document.getElementById('no-search-results');
    if (!hasVisible && cards.length > 0) {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('p');
            noResultsMsg.id = 'no-search-results';
            noResultsMsg.textContent = 'No mods matched your search.';
            noResultsMsg.style.width = '100%';
            noResultsMsg.style.textAlign = 'center';
            document.getElementById('mods-list').appendChild(noResultsMsg);
        }
        noResultsMsg.style.display = 'block';
    } else if (noResultsMsg) {
        noResultsMsg.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
