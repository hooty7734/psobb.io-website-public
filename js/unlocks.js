document.addEventListener('DOMContentLoaded', () => {
    const milestonesContainer = document.getElementById('milestones-container');
    const statusBox = document.getElementById('unlocks-status');
    const charInfo = document.getElementById('character-info');

    // Modal elements
    const modal = document.getElementById('claim-modal');
    const closeModal = document.querySelector('.close-modal');
    const modalLevel = document.getElementById('modal-level');
    const modalError = document.getElementById('modal-error');
    const claimBtns = document.querySelectorAll('.claim-category-btn');

    let currentClaimLevel = 0;

    // Fetch unlocks data
    function loadUnlocks() {
        fetch('api/get_unlocks.php', { credentials: 'same-origin' })
            .then(res => {
                if (res.status === 401) {
                    sessionStorage.removeItem('psobb_user');
                    window.location.reload(); // Force UI refresh so they see the Login button again
                    throw new Error("You must be logged into the website to view unlocks. <a href='login.php'>Login here</a>.");
                }
                return res.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);

                if (!data.is_online) {
                    showStatus(data.message || "You must be online in a game to view and claim rewards on your character.", false);
                    milestonesContainer.innerHTML = '';
                    return;
                }

                // Show char info
                charInfo.style.display = 'block';
                document.getElementById('char-name').textContent = data.character.name;
                document.getElementById('char-class').textContent = data.character.class;
                document.getElementById('char-level').textContent = data.character.level;

                if (!data.in_game) {
                    showStatus("⚠️ Character found in Lobby. <b>You must join or create a Game to claim rewards!</b>", false);
                }

                renderMilestones(data.milestones, data.in_game);
            })
            .catch(err => {
                showStatus(err.message, false);
                milestonesContainer.innerHTML = '';
            });
    }

    function renderMilestones(milestones, inGame) {
        if (!milestones || milestones.length === 0) {
            milestonesContainer.innerHTML = '<p style="color: var(--text-muted);">You have not reached Level 5 yet. Keep hunting!</p>';
            return;
        }

        milestonesContainer.innerHTML = '';

        milestones.forEach(m => {
            const card = document.createElement('div');
            card.className = `milestone-card ${m.claimed ? 'claimed' : ''}`;

            let btnHtml = '';
            if (m.claimed) {
                btnHtml = `<p style="color: var(--pso-purple); margin-top: 1.5rem; font-family: 'Share Tech Mono', 'Segoe UI', monospace; text-shadow: 0 0 5px rgba(255, 255, 255, 0.2);">CLAIMED:<br>${m.claimed_category}</p>`;
            } else {
                const disabledStr = !inGame ? 'disabled' : '';
                btnHtml = `<button class="open-claim-btn" data-level="${m.level}" ${disabledStr}>Claim Reward</button>`;
            }

            card.innerHTML = `
                <div class="milestone-level">Level ${m.level}</div>
                ${btnHtml}
            `;
            milestonesContainer.appendChild(card);
        });

        // Attach listeners to new buttons
        document.querySelectorAll('.open-claim-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (!inGame) return;
                currentClaimLevel = e.target.getAttribute('data-level');
                modalLevel.textContent = currentClaimLevel;
                modalError.style.display = 'none';
                modal.style.display = 'flex';
            });
        });
    }

    function showStatus(msg, isSuccess = false) {
        statusBox.style.display = 'block';
        statusBox.innerHTML = msg;
        statusBox.className = isSuccess ? 'alert-box success' : 'alert-box';
    }

    // Modal behavior
    closeModal.addEventListener('click', () => modal.style.display = 'none');
    window.addEventListener('click', (e) => {
        if (e.target == modal) modal.style.display = 'none';
    });

    // Handle claim
    claimBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const category = e.target.getAttribute('data-category');

            // Disable buttons temporarily
            claimBtns.forEach(b => b.disabled = true);
            e.target.textContent = 'Preparing...';

            modal.style.display = 'none';

            const overlay = document.getElementById('drop-animation-overlay');
            const box = document.getElementById('drop-item-box');
            const countdownEl = document.getElementById('countdown-text');
            const thankYouText = document.getElementById('thank-you-text');

            // Assign correct box color based on item category
            if (category === 'Random') {
                box.className = 'drop-item-box green-box';
            } else if (category === 'Armor' || category === 'Shield') {
                box.className = 'drop-item-box blue-box';
            } else if (category === 'Mag') {
                box.className = 'drop-item-box teal-box';
            } else if (category === 'Weapon') {
                if (currentClaimLevel % 25 === 0) {
                    box.className = 'drop-item-box'; // red box for rare weapon
                } else {
                    box.className = 'drop-item-box orange-box'; // orange box for common weapon
                }
            } else {
                box.className = 'drop-item-box';
            }

            // Reset thank you text animation
            thankYouText.style.animation = 'none';
            thankYouText.style.opacity = '0';

            // Restart animations by cloning
            const newBox = box.cloneNode(true);
            box.parentNode.replaceChild(newBox, box);

            overlay.style.display = 'flex';

            let count = 3;
            countdownEl.style.display = 'block';
            countdownEl.textContent = count;

            const countdownInterval = setInterval(() => {
                count--;
                if (count > 0) {
                    countdownEl.textContent = count;
                    countdownEl.style.transform = 'scale(1.5)';
                    setTimeout(() => countdownEl.style.transform = 'scale(1)', 100);
                } else if (count === 0) {
                    countdownEl.style.transform = 'scale(1.5)';
                    countdownEl.textContent = "DROPPING!";

                    // Fire the fetch API
                    fetch('api/claim_unlock.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ level: currentClaimLevel, category: category })
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.error) {
                                clearInterval(countdownInterval);
                                overlay.style.display = 'none';
                                modalError.textContent = data.error;
                                modalError.style.display = 'block';
                                modal.style.display = 'flex';
                                claimBtns.forEach(b => b.disabled = false);
                                e.target.textContent = e.target.getAttribute('data-category');
                            } else {
                                // Success!
                                countdownEl.style.display = 'none';
                                thankYouText.style.animation = 'textDrop 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards';

                                createFireworks();

                                // Wait for drop + glow burst, then show success msg
                                setTimeout(() => {
                                    overlay.style.display = 'none';
                                    showStatus(`🎉 <strong>Success!</strong> ${category} reward dropped in-game. Enjoy!`, true);
                                    loadUnlocks(); // Reload the board
                                }, 3500);
                            }
                        })
                        .catch(err => {
                            clearInterval(countdownInterval);
                            overlay.style.display = 'none';
                            modalError.textContent = "Network error occurred.";
                            modalError.style.display = 'block';
                            modal.style.display = 'flex';
                            claimBtns.forEach(b => b.disabled = false);
                            e.target.textContent = e.target.getAttribute('data-category');
                        });

                    clearInterval(countdownInterval);
                }
            }, 1000);
        });
    });

    function createFireworks() {
        const overlay = document.getElementById('drop-animation-overlay');
        const colors = ['#ff4444', '#33b5e5', '#00C851', '#ffaa00', '#aa66cc', '#ffffff'];

        for (let b = 0; b < 6; b++) {
            setTimeout(() => {
                const centerX = window.innerWidth / 2 + (Math.random() - 0.5) * 600;
                const centerY = window.innerHeight / 2 + (Math.random() - 0.5) * 500 - 150;

                for (let i = 0; i < 80; i++) {
                    const particle = document.createElement('div');
                    particle.style.position = 'absolute';
                    particle.style.left = centerX + 'px';
                    particle.style.top = centerY + 'px';
                    particle.style.width = (Math.random() * 8 + 4) + 'px';
                    particle.style.height = particle.style.width;
                    particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    particle.style.borderRadius = '50%';
                    particle.style.pointerEvents = 'none';
                    particle.style.zIndex = '9998';
                    particle.style.boxShadow = `0 0 15px ${particle.style.backgroundColor}, 0 0 30px ${particle.style.backgroundColor}`;

                    overlay.appendChild(particle);

                    const angle = Math.random() * Math.PI * 2;
                    const velocity = 100 + Math.random() * 300;
                    const tx = Math.cos(angle) * velocity;
                    const ty = Math.sin(angle) * velocity;

                    particle.animate([
                        { transform: 'translate(0,0) scale(1)', opacity: 1 },
                        { transform: `translate(${tx}px, ${ty}px) scale(0)`, opacity: 0 }
                    ], {
                        duration: 1000 + Math.random() * 800,
                        easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
                        fill: 'forwards'
                    });

                    setTimeout(() => { if (particle.parentNode) particle.remove(); }, 2000);
                }
            }, b * 300);
        }
    }

    // =============================================
    // DAILY STREAK & DAILY REWARD SYSTEM
    // =============================================
    function loadStreak() {
        fetch('api/get_streak.php', { credentials: 'same-origin' })
            .then(res => {
                if (res.status === 401) {
                    sessionStorage.removeItem('psobb_user');
                    throw new Error("Unauthorized");
                }
                return res.json();
            })
            .then(data => {
                if (data.error) return;

                const streakSection = document.getElementById('streak-section');
                const dailySection = document.getElementById('daily-reward-section');
                streakSection.style.display = 'block';
                dailySection.style.display = 'block';

                // Update streak count
                document.getElementById('streak-count').textContent = data.streak;

                // Update progress bar (max at 30 days)
                const fillPct = Math.min((data.streak / 30) * 100, 100);
                document.getElementById('streak-fill').style.width = fillPct + '%';

                // Update nodes
                const nodes = document.querySelectorAll('.streak-node');
                nodes.forEach(node => {
                    const day = parseInt(node.dataset.day);
                    node.classList.remove('reached', 'claimable', 'claimed');

                    if (data.claimed.includes(day)) {
                        node.classList.add('claimed');
                    } else if (data.claimable.includes(day)) {
                        node.classList.add('claimable');
                    } else if (data.streak >= day) {
                        node.classList.add('reached');
                    }
                });

                // Render streak calendar grid
                const claimsDiv = document.getElementById('streak-claims');
                claimsDiv.innerHTML = '';

                const daysArray = Array.from({ length: 30 }, (_, i) => i + 1);
                daysArray.forEach(m => {
                    let rewardName = 'Monogrinder';
                    let tierClass = 'tier-mono';
                    if (m === 30) { rewardName = 'Trigrinder'; tierClass = 'tier-tri'; }
                    else if (m % 2 === 0) { rewardName = 'Stat Material'; tierClass = 'tier-stat'; }
                    else if (m % 3 === 0) { rewardName = 'Digrinder'; tierClass = 'tier-dig'; }

                    const day = document.createElement('div');
                    day.className = `streak-day ${tierClass}`;

                    let stateHtml = '';
                    if (data.claimed.includes(m)) {
                        day.classList.add('day-claimed');
                        stateHtml = '<span class="day-check">✓</span>';
                    } else if (data.claimable.includes(m)) {
                        day.classList.add('day-claimable');
                        stateHtml = '<span class="claim-label">Claim</span>';
                        day.addEventListener('click', () => claimStreak(m));
                    } else if (data.streak >= m) {
                        day.classList.add('day-reached');
                    }

                    day.innerHTML = `
                        ${stateHtml}
                        <div class="day-num">Day ${m}</div>
                        <div class="day-reward">${rewardName}</div>
                    `;
                    claimsDiv.appendChild(day);
                });

                // Daily reward button
                const dailyBtn = document.getElementById('daily-claim-btn');
                const dailyResult = document.getElementById('daily-result');

                if (data.daily_claimed) {
                    startDailyCountdown(dailyBtn, data.next_daily_reset, data.server_time);
                } else if (!data.is_online) {
                    dailyBtn.textContent = 'Log into the game first';
                    dailyBtn.disabled = true;
                } else {
                    dailyBtn.disabled = false;
                    dailyBtn.onclick = () => claimDaily();
                }
            })
            .catch(err => console.error('Streak fetch error:', err));
    }

    function claimStreak(milestone) {
        const overlay = document.getElementById('drop-animation-overlay');
        const box = document.getElementById('drop-item-box');
        const thankYouText = document.getElementById('thank-you-text');
        const countdown = document.getElementById('countdown-text');

        // Green box for streak rewards
        box.className = 'drop-item-box green-box';

        thankYouText.style.animation = 'none';
        thankYouText.style.opacity = '0';

        // Restart animations by cloning
        const newBox = box.cloneNode(true);
        box.parentNode.replaceChild(newBox, box);

        overlay.style.display = 'flex';

        // Countdown
        let count = 3;
        countdown.textContent = count;
        countdown.style.display = 'block';
        const countInterval = setInterval(() => {
            count--;
            if (count > 0) {
                countdown.textContent = count;
                countdown.style.transform = 'scale(1.5)';
                setTimeout(() => countdown.style.transform = 'scale(1)', 100);
            } else {
                clearInterval(countInterval);
                countdown.style.transform = 'scale(1.5)';
                countdown.textContent = 'DROPPING!';
                setTimeout(() => { countdown.style.display = 'none'; }, 600);

                // Send streak claim
                fetch('api/claim_streak.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ milestone })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            overlay.style.display = 'none';
                            alert(data.error);
                        } else {
                            // Show text + fireworks
                            thankYouText.style.animation = 'textDrop 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards';
                            createFireworks();
                            setTimeout(() => {
                                overlay.style.display = 'none';
                                loadStreak();
                            }, 4000);
                        }
                    })
                    .catch(() => {
                        overlay.style.display = 'none';
                        alert('Connection error. Please try again.');
                    });
            }
        }, 1000);
    }

    function claimDaily() {
        const dailyBtn = document.getElementById('daily-claim-btn');
        const dailyResult = document.getElementById('daily-result');
        dailyBtn.disabled = true;
        dailyBtn.textContent = 'Preparing...';

        const overlay = document.getElementById('drop-animation-overlay');
        const box = document.getElementById('drop-item-box');
        const thankYouText = document.getElementById('thank-you-text');
        const countdown = document.getElementById('countdown-text');

        // Teal box for daily rewards
        box.className = 'drop-item-box teal-box';

        thankYouText.style.animation = 'none';
        thankYouText.style.opacity = '0';

        const newBox = box.cloneNode(true);
        box.parentNode.replaceChild(newBox, box);

        overlay.style.display = 'flex';

        let count = 3;
        countdown.textContent = count;
        countdown.style.display = 'block';

        const countInterval = setInterval(() => {
            count--;
            if (count > 0) {
                countdown.textContent = count;
                countdown.style.transform = 'scale(1.5)';
                setTimeout(() => countdown.style.transform = 'scale(1)', 100);
            } else {
                clearInterval(countInterval);
                countdown.style.transform = 'scale(1.5)';
                countdown.textContent = 'DROPPING!';
                setTimeout(() => { countdown.style.display = 'none'; }, 600);

                fetch('api/claim_daily.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({})
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            overlay.style.display = 'none';
                            dailyBtn.disabled = false;
                            dailyBtn.textContent = '🎲 Claim Daily Reward';
                            dailyResult.style.display = 'block';
                            dailyResult.style.color = '#ff4444';
                            dailyResult.textContent = data.error;
                        } else {
                            thankYouText.style.animation = 'textDrop 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards';
                            createFireworks();

                            setTimeout(() => {
                                overlay.style.display = 'none';
                                dailyResult.style.display = 'block';
                                dailyResult.style.color = '#00ff88';
                                dailyResult.textContent = '🎉 ' + data.item + ' dropped in-game!';

                                const nowUnix = Math.floor(Date.now() / 1000);
                                const midnightEstimate = nowUnix + (86400 - (nowUnix % 86400));
                                startDailyCountdown(dailyBtn, midnightEstimate, nowUnix);
                            }, 4000);
                        }
                    })
                    .catch(() => {
                        overlay.style.display = 'none';
                        dailyBtn.disabled = false;
                        dailyBtn.textContent = '🎲 Claim Daily Reward';
                        dailyResult.style.display = 'block';
                        dailyResult.style.color = '#ff4444';
                        dailyResult.textContent = 'Connection error.';
                    });
            }
        }, 1000);
    }

    let dailyCountdownInterval = null;
    function startDailyCountdown(btn, resetTimestamp, serverTime) {
        btn.disabled = true;
        btn.style.borderColor = 'rgba(255,255,255,0.15)';

        // Calculate offset between server time and local time
        const offset = serverTime - Math.floor(Date.now() / 1000);

        function updateCountdown() {
            const nowServer = Math.floor(Date.now() / 1000) + offset;
            const remaining = resetTimestamp - nowServer;

            if (remaining <= 0) {
                btn.textContent = '🎲 Claim Daily Reward';
                btn.disabled = false;
                btn.style.borderColor = '#00ff88';
                if (dailyCountdownInterval) clearInterval(dailyCountdownInterval);
                loadStreak(); // Refresh
                return;
            }

            const hours = Math.floor(remaining / 3600);
            const mins = Math.floor((remaining % 3600) / 60);
            const secs = remaining % 60;
            btn.textContent = `✓ Claimed — Next in ${hours}h ${mins}m ${secs}s`;
        }

        updateCountdown();
        if (dailyCountdownInterval) clearInterval(dailyCountdownInterval);
        dailyCountdownInterval = setInterval(updateCountdown, 1000);
    }

    // Initialize
    loadUnlocks();
    loadStreak();
});
