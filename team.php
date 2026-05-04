<?php
$page_title = 'Team Management - PSOBB Private Server';
$current_page = 'team';
include 'includes/header.php';
?>

    <div class="pso-spinner-svg">
        <canvas id="star-canvas-team"></canvas>
        <svg class="hex2"><!-- hex SVG --></svg>
    </div>

    <main class="container">
        <div class="main-header" style="margin-bottom: 2rem;">
            <h1>Team Management</h1>
        </div>

        <div class="layout-grid">
            <section class="main-content">
                <div id="auth-warning" style="display: none;" class="server-status-widget">
                    <h3 style="margin-top: 0; color: #ffca28;"><i class="fas fa-exclamation-triangle"></i> Not Logged In</h3>
                    <p style="color: #ccc;">You must be logged in to view your team information.</p>
                    <a href="/login.php" class="dl-btn" style="margin-top: 10px;">Login Here</a>
                </div>

                <div id="no-team-message" style="display: none;" class="server-status-widget">
                    <h3 style="margin-top: 0; color: var(--pso-blue);"><i class="fas fa-users-slash"></i> No Team Found</h3>
                    <p style="color: #ccc;">You are not currently a member of a team.</p>
                    <p style="font-size: 0.9em; color: rgba(255,255,255,0.5);">Join a team in-game at the Hunter's Guild counter.</p>
                </div>

                <div id="team-loading" class="server-status-widget" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-circle-notch fa-spin fa-3x" style="color: var(--pso-blue); margin-bottom: 1rem;"></i>
                    <p>Fetching team data from Pioneer 2...</p>
                </div>

                <div id="team-dashboard" style="display: none;">
                    <div class="server-status-widget" style="margin-bottom: 2rem; position: relative; overflow: hidden;">
                        <div style="position: absolute; top: -50px; right: -50px; font-size: 200px; opacity: 0.05; color: var(--pso-blue); pointer-events: none;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 id="display-team-name" style="font-size: 1.8rem; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(0,255,255,0.2); padding-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-users" style="color: var(--pso-blue); font-size: 1.4rem;"></i> <span>Team Name</span>
                        </h3>
                        <div class="status-row">
                            <span><i class="fas fa-user-tag" style="width: 20px; color: var(--pso-purple);"></i> Your Role:</span>
                            <span id="display-role" style="font-weight: bold; color: #fff; background: rgba(157, 78, 221, 0.2); padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(157, 78, 221, 0.5);">--</span>
                        </div>
                        <div class="status-row">
                            <span><i class="fas fa-hashtag" style="width: 20px; color: #aaa;"></i> Team ID:</span>
                            <span id="display-team-id" style="font-family: monospace; color: #aaa;">--</span>
                        </div>
                        <div class="status-row">
                            <span><i class="fas fa-user-friends" style="width: 20px; color: #00C851;"></i> Members:</span>
                            <span id="display-members" style="font-weight: bold; color: #00C851;">--</span>
                        </div>
                        <div class="status-row">
                            <span><i class="fas fa-star" style="width: 20px; color: #ffca28;"></i> Your Contributed Points:</span>
                            <span id="display-my-points" style="color: #ffca28; font-weight: bold;">--</span>
                        </div>
                        <div class="status-row" style="border-bottom: none; padding-bottom: 0;">
                            <span><i class="fas fa-coins" style="width: 20px; color: var(--pso-blue);"></i> Total Unspent Points:</span>
                            <span id="display-unspent-points" style="color: var(--pso-blue); font-weight: bold; font-size: 1.1em; text-shadow: 0 0 5px rgba(0,255,255,0.5);">--</span>
                        </div>
                    </div>

                    <div id="team-rewards-section" class="server-status-widget" style="margin-bottom: 2rem;">
                        <h3 style="display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem;">
                            <i class="fas fa-gift" style="color: var(--pso-purple);"></i> Available Rewards
                        </h3>
                        <div id="next-unlocks-list">
                            <p>Loading rewards...</p>
                        </div>
                    </div>

                    <div id="master-view-section" style="display: none;">
                        <h2 style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-clipboard-list" style="color: var(--pso-blue);"></i> Team Roster <span style="font-size: 0.5em; vertical-align: middle; background: rgba(0,255,255,0.1); color: var(--pso-blue); padding: 2px 6px; border-radius: 3px; border: 1px solid rgba(0,255,255,0.3); font-weight: normal; margin-left: 10px;">Master / Leader View</span>
                        </h2>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Account ID</th>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Points</th>
                                    </tr>
                                </thead>
                                <tbody id="roster-list">
                                    <tr>
                                        <td colspan="4">Loading roster...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="sidebar">
                <div class="sidebar-widget animate-fade-in">
                    <h3>Team FAQ</h3>
                    <p style="font-size: 0.9em; line-height: 1.4; color: #ccc;">
                        <strong>Q: How do I earn Team Points?</strong><br>
                        A: Complete Team Quests at the Hunter's Guild or trade rare items to the Team Point attendant in the episode 4 lobby.<br><br>
                        <strong>Q: Can I manage my team here?</strong><br>
                        A: Currently, this page provides a read-only overview of your team's status and points. Use the game client to promote members or buy rewards.
                    </p>
                    <div class="widget-divider"></div>
                    <ul class="sidebar-links">
                        <li><a href="/login.php">Dashboard</a></li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>

<script>
const TEAM_REWARDS = [
    { Key: "TeamFlag", Name: "Team flag", Description: "Show a custom banner\nabove your team's\nplayers in the lobby", Points: 100, Icon: "🏁" },
    { Key: "DressingRoom", Name: "Dressing room", Description: "Unlock the ability to\nchange your character's\nappearance", Points: 500, Icon: "👗" },
    { Key: "Members20Leaders3", Name: "20 team members", Description: "Increase your team's\nsize limit to 30 members\nand 3 leaders", Points: 1000, Icon: "👥" },
    { Key: "Members40Leaders5", Name: "40 team members", Description: "Increase your team's\nsize limit to 40 members\nand 5 leaders", Points: 2000, PrerequisiteKeys: ["Members20Leaders3"], Icon: "👥" },
    { Key: "Members70Leaders8", Name: "70 team members", Description: "Increase your team's\nsize limit to 70 members\nand 8 leaders", Points: 5000, PrerequisiteKeys: ["Members40Leaders5"], Icon: "👥" },
    { Key: "Members100Leaders10", Name: "100 team members", Description: "Increase your team's\nsize limit to 100 members\nand 10 leaders", Points: 10000, PrerequisiteKeys: ["Members70Leaders8"], Icon: "👥" },
    { Key: "PointOfDisasterQuest", Name: "Quest: Point of Disaster", Description: "Unlock the quest\nPoint of Disaster\nfor your team", Points: 1000, Icon: "📜" },
    { Key: "TheRobotsReckoningQuest", Name: "Quest: The Robots' Reckoning", Description: "Unlock the quest\nThe Robots' Reckoning\nfor your team", Points: 1000, Icon: "📜" },
    { Key: "CommanderBlade", Name: "Commander Blade", Description: "Create a Commander\nBlade weapon", IsUnique: false, Points: 8000, Icon: "🗡️" },
    { Key: "UnionField", Name: "Union Field", Description: "Create a Union Field\narmor", IsUnique: false, Points: 100, Icon: "🛡️" },
    { Key: "UnionGuard", Name: "Union Guard", Description: "Create a Union Guard\nshield", IsUnique: false, Points: 100, Icon: "🔰" },
    { Key: "Ticket500", Name: "Team Points Ticket 500", Description: "Create a 500-point ticket", IsUnique: false, Points: 500, Icon: "🎟️" },
    { Key: "Ticket1000", Name: "Team Points Ticket 1000", Description: "Create a 1000-point ticket", IsUnique: false, Points: 1000, Icon: "🎟️" },
    { Key: "Ticket5000", Name: "Team Points Ticket 5000", Description: "Create a 5000-point ticket", IsUnique: false, Points: 5000, Icon: "🎟️" },
    { Key: "Ticket10000", Name: "Team Points Ticket 10000", Description: "Create a 10000-point ticket", IsUnique: false, Points: 10000, Icon: "🎟️" }
];

document.addEventListener('DOMContentLoaded', () => {
    fetchTeamInfo();
});

async function fetchTeamInfo() {
    try {
        const response = await fetch('/api/team_info.php');
        const data = await response.json();

        document.getElementById('team-loading').style.display = 'none';

        if (response.status === 401) {
            document.getElementById('auth-warning').style.display = 'block';
            return;
        }

        if (data.success && !data.hasTeam) {
            document.getElementById('no-team-message').style.display = 'block';
            return;
        }

        if (data.success && data.hasTeam) {
            document.getElementById('team-dashboard').style.display = 'block';

            document.getElementById('display-team-name').textContent = data.TeamName || 'Unknown Team';
            document.getElementById('display-role').textContent = data.MyRole || 'Member';
            document.getElementById('display-team-id').textContent = data.TeamID || '-';
            document.getElementById('display-members').textContent = data.MemberCount + ' / ' + data.MaxMembers;
            document.getElementById('display-unspent-points').textContent = data.UnspentPoints || 0;
            document.getElementById('display-my-points').textContent = data.MyPoints || 0;

            renderNextUnlocks(data.UnspentPoints || 0, data.RewardKeys || []);

            if (data.Roster && (data.MyRole === 'Master' || data.MyRole === 'Leader')) {
                document.getElementById('master-view-section').style.display = 'block';
                renderRoster(data.Roster);
            }
        } else {
            document.getElementById('team-loading').style.display = 'block';
            document.getElementById('team-loading').innerHTML = '<p style="color:#ff4444;">Error loading team info: ' + (data.error || 'Unknown error') + '</p>';
        }

    } catch (e) {
        document.getElementById('team-loading').style.display = 'block';
        document.getElementById('team-loading').innerHTML = '<p style="color:#ff4444;">Connection error: ' + e.message + '</p>';
    }
}

function renderNextUnlocks(unspentPoints, unlockedKeys) {
    const list = document.getElementById('next-unlocks-list');
    list.innerHTML = '';

    const goals = [];

    TEAM_REWARDS.forEach(reward => {
        // Skip if unique and already unlocked
        if (reward.IsUnique !== false && unlockedKeys.includes(reward.Key)) return;

        // Skip if prerequisites aren't met
        if (reward.PrerequisiteKeys) {
            for (let prereq of reward.PrerequisiteKeys) {
                if (!unlockedKeys.includes(prereq)) return;
            }
        }

        goals.push(reward);
    });

    if (goals.length === 0) {
        list.innerHTML = '<p style="color:#00C851;">All possible team rewards have been unlocked!</p>';
        return;
    }

    let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">';
    goals.forEach(g => {
        const percent = Math.min(100, Math.round((unspentPoints / g.Points) * 100));
        let pgColor = percent >= 100 ? '#00C851' : '#00ffff';
        const description = (g.Description || '').replace(/\n/g, '<br>');

        html += `
            <div style="background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.2); padding: 15px; border-radius: 8px; display: flex; flex-direction: column; gap: 10px; transition: all 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.3);" onmouseover="this.style.borderColor='#00ffff'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='rgba(0,255,255,0.2)'; this.style.transform='translateY(0)';">
                <div style="display: flex; gap: 10px; align-items: flex-start;">
                    <span style="font-size: 2rem; width: 40px; text-align: center;">${g.Icon}</span>
                    <div style="flex-grow: 1;">
                        <h4 style="margin: 0 0 8px 0; color: var(--pso-text); font-size: 1.1rem; border-bottom: none; padding-bottom: 0;">${g.Name}</h4>
                        <p style="margin: 0; font-size: 0.85em; color: #aaa; line-height: 1.4;">${description}</p>
                    </div>
                    <div style="text-align: right; white-space: nowrap; margin-left: 5px;">
                        <span style="color: var(--pso-blue); font-weight: bold; font-size: 1.2em;">${g.Points}</span>
                        <span style="color: rgba(255,255,255,0.5); font-size: 0.8em; display: block;">PTS</span>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 10px; margin-top: auto; padding-top: 10px;">
                    <div style="background: rgba(255,255,255,0.1); flex-grow: 1; height: 8px; border-radius: 4px; overflow: hidden;">
                        <div style="background: ${pgColor}; width: ${percent}%; height: 100%; box-shadow: 0 0 5px ${pgColor}; transition: width 0.5s;"></div>
                    </div>
                    <span style="font-size: 0.85em; color: ${pgColor}; font-weight: bold;">${percent}%</span>
                </div>
            </div>
        `;
    });
    html += '</div>';
    list.innerHTML = html;
}

function renderRoster(rosterMap) {
    const list = document.getElementById('roster-list');
    list.innerHTML = '';

    const roster = Object.values(rosterMap);
    roster.sort((a, b) => b.Points - a.Points);

    roster.forEach(m => {
        const tr = document.createElement('tr');
        const flags = m.Flags || 0;
        let role = 'Member';
        let roleStyle = 'background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2);';
        let roleIcon = '<i class="fas fa-user"></i>';

        if (flags & 0x01) {
            role = 'Master';
            roleStyle = 'background: rgba(255, 170, 0, 0.2); color: #ffca28; border: 1px solid rgba(255, 170, 0, 0.5); font-weight: bold;';
            roleIcon = '<i class="fas fa-crown" style="color: #ffca28;"></i>';
        }
        else if (flags & 0x02) {
            role = 'Leader';
            roleStyle = 'background: rgba(0, 200, 81, 0.2); color: #00C851; border: 1px solid rgba(0, 200, 81, 0.5); font-weight: bold;';
            roleIcon = '<i class="fas fa-star" style="color: #00C851;"></i>';
        }
        
        // Escape HTML for safety
        const safeName = m.Name.replace(/</g, '&lt;').replace(/>/g, '&gt;');

        tr.innerHTML = `
            <td style="font-family: monospace; color: #aaa;">${m.AccountID}</td>
            <td style="font-size: 1.1em; color: var(--pso-text);">${safeName}</td>
            <td><span style="padding:4px 8px; border-radius:4px; display: inline-flex; align-items: center; gap: 6px; ${roleStyle}">${roleIcon} ${role}</span></td>
            <td style="color: var(--pso-blue); font-weight: bold;">${m.Points} <span style="font-size: 0.8em; color: #aaa; font-weight: normal;">pts</span></td>
        `;
        list.appendChild(tr);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
