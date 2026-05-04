    <footer>
        <?php if (isset($current_page) && $current_page == 'home'): ?>
        <p>PSOBB Server Name: <span id="server-name">Loading...</span></p>
        <?php elseif (isset($current_page) && $current_page == 'login'): ?>
        <p>PSOBB Server Name: <span id="server-name">Loading...</span> | Uptime: <span id="uptime">Loading...</span></p>
        <?php else: ?>
        <p>Stats update every 30 seconds.</p>
        <?php endif; ?>
        <p>&copy; 2026 psobb.io private server<br>
        <span style="font-size: 0.8em; opacity: 0.7;">
            Server <a href="https://github.com/fuzziqersoftware/newserv" target="_blank" style="color: inherit; text-decoration: underline;">newserv</a> created by <a href="http://fuzziqersoftware.com" target="_blank" style="color: inherit; text-decoration: underline;">fuzziqersoftware</a>
        </span>
        </p>
    </footer>
</body>

</html>
