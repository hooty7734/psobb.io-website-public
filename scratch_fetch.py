import urllib.request
import re

for secid in range(1, 15):
    try:
        url = f'https://www.pso-world.com/items.php?op=listarticles&secid={secid}'
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
        response = urllib.request.urlopen(req)
        html = response.read().decode('latin-1')
        title_match = re.search(r'<title>([^<]+)</title>', html, re.IGNORECASE)
        title = title_match.group(1) if title_match else "No Title"
        print(f"Sec {secid}: {title}")
    except Exception as e:
        print(f"Sec {secid} Error:", e)
