import urllib.request
import json
import ssl

NEWSERV_API_URL = "http://localhost:12000"
accountId = 100

items = [
    "? Charge Calibur +5 20/0/0/50",
    "Celestial Armor +4",
    "Divinity Barrier +10def +5evp"
]

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

for item in items:
    cmd = f"on {accountId} cc $item {item}"
    req = urllib.request.Request(f"{NEWSERV_API_URL}/y/shell-exec", data=json.dumps({"command": cmd}).encode('utf-8'), headers={'Content-Type': 'application/json'})
    try:
        with urllib.request.urlopen(req, context=ctx) as response:
            print(f"Item: {item}\nResult: {response.read().decode('utf-8')}\n")
    except Exception as e:
        print(f"Item: {item}\nError: {e}\n")
