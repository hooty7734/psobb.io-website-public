import os
import json
import re

log_path = r"C:\Users\liqui\.gemini\antigravity\brain\e0532915-bdc0-47b9-8ac3-54090a372403\.system_generated\logs\overview.txt"

item_equip_map = {}

classes = {
    "HUmar", "HUnewearl", "HUcast", "HUcaseal",
    "RAmar", "RAmarl", "RAcast", "RAcaseal",
    "FOmar", "FOmarl", "FOnewm", "FOnewearl"
}

try:
    with open(log_path, 'r', encoding='utf-8', errors='ignore') as f:
        log_lines = f.readlines()
        
    for log_line in log_lines:
        try:
            data = json.loads(log_line)
            if 'content' not in data: continue
            content = data['content']
            
            # Split content into lines
            lines = content.split('\n')
            
            current_class = None
            parsing_items = False
            
            for i, line in enumerate(lines):
                line = line.strip()
                
                # Detect class context
                if "Class:" in line:
                    for j in range(1, 5):
                        if i + j < len(lines):
                            pot_class = lines[i+j].strip()
                            if pot_class in classes:
                                current_class = pot_class
                                break
                    continue
                    
                if line.startswith("Name\tImages\tType\tPlatforms") or line.startswith("Name\tImages"):
                    parsing_items = True
                    continue
                    
                if line == "</USER_REQUEST>" or line.startswith("Rare Items") or line.startswith("Normal Items") or line.startswith("Display Items"):
                    parsing_items = False
                    continue
                    
                if parsing_items and line:
                    parts = line.split('\t')
                    if len(parts) >= 2:
                        item_name = parts[0].strip()
                        # sanity check
                        if item_name and current_class and "Displays" not in item_name and "Name" not in item_name:
                            if item_name not in item_equip_map:
                                item_equip_map[item_name] = []
                            if current_class not in item_equip_map[item_name]:
                                item_equip_map[item_name].append(current_class)
        except json.JSONDecodeError:
            pass

    print(f"Parsed {len(item_equip_map)} unique items.")
    
    # Save to file
    out_path = r"C:\Users\liqui\git\psobb.io-website\api\item_equip_map.json"
    with open(out_path, 'w', encoding='utf-8') as f:
        json.dump(item_equip_map, f, indent=4)
        
    print(f"Saved to {out_path}")
    
except Exception as e:
    print(f"Error: {e}")
