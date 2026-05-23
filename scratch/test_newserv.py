import urllib.request
import json
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

try:
    req = urllib.request.urlopen("http://127.0.0.1:8443/", context=ctx, timeout=2)
    print("Root:", req.read().decode())
except Exception as e:
    print("Root error:", e)

endpoints = ["/y/drops", "/y/drop_chart", "/y/item_rates", "/y/rare_drops", "/y/", "/drops.json", "/y/drop_table"]
for ep in endpoints:
    url = f"http://127.0.0.1:8443{ep}"
    try:
        req = urllib.request.urlopen(url, context=ctx, timeout=2)
        print(f"Success for {ep}:", req.read().decode()[:200])
    except Exception as e:
        print(f"Error for {ep}:", e)
