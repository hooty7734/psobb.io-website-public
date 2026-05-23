import urllib.request
import urllib.parse
import re
import json

class_map = {
    '1': 'HUmar', '11': 'HUnewearl', '6': 'HUcast', '8': 'HUcaseal',
    '2': 'RAmar', '4': 'RAmarl', '7': 'RAcast', '9': 'RAcaseal',
    '3': 'FOmar', '5': 'FOmarl', '10': 'FOnewm', '12': 'FOnewearl'
}

secids = ['8', '9', '11', '12']
url = 'https://www.pso-world.com/items.php'

item_equip_map = {}

for secid in secids:
    for classid, classname in class_map.items():
        data = urllib.parse.urlencode({
            'op': 'listarticles',
            'secid': secid,
            'filter': '1',
            'classid': classid,
            'version': 'v3',
            'sortby': 'name'
        }).encode('utf-8')
        
        req = urllib.request.Request(url, data=data, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
        
        try:
            response = urllib.request.urlopen(req)
            html = response.read().decode('latin-1')
            
            items = re.findall(r'<td[^>]*>\s*<a\s+href="/items\.php\?op=viewarticle&artid=\d+(?:&[^"]*)?">([^<]+)</a>\s*</td>', html, re.IGNORECASE)
            
            # Add to map
            for item in items:
                item = item.strip()
                if item not in item_equip_map:
                    item_equip_map[item] = []
                if classname not in item_equip_map[item]:
                    item_equip_map[item].append(classname)
                    
            print(f"Sec {secid}, Class {classname}: found {len(items)} items")
            
        except Exception as e:
            print(f"Error for sec {secid}, class {classname}: {e}")

out_path = r"C:\Users\liqui\git\psobb.io-website\api\item_equip_map.json"
with open(out_path, 'w', encoding='utf-8') as f:
    json.dump(item_equip_map, f, indent=4)
print(f"Saved {len(item_equip_map)} unique items to {out_path}")
