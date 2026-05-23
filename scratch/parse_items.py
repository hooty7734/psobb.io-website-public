import json

lookup = {}

# Parse weapons
try:
    with open("c:/Users/liqui/git/psobb.io-website/scratch/raw_pso_weapons.tsv", "r", encoding="utf-8") as f:
        for line in f:
            parts = line.strip().split('\t')
            if len(parts) >= 3 and parts[0] != "Name":
                name = parts[0].strip().lower()
                subtype = parts[2].strip()
                if "Rifle (Needle)" in subtype: subtype = "Rifle"
                lookup[name] = subtype
except Exception as e:
    print("Error weapons:", e)

# Parse other
try:
    with open("c:/Users/liqui/git/psobb.io-website/scratch/raw_pso_other.tsv", "r", encoding="utf-8") as f:
        for line in f:
            parts = line.strip().split('\t')
            if len(parts) >= 3 and parts[0] != "Name":
                name = parts[0].strip().lower()
                raw_type = parts[2].strip().lower()
                
                subtype = "Other"
                if "armor" in raw_type:
                    if "frame" in name: subtype = "Frame"
                    elif "plate" in name: subtype = "Plate"
                    elif "cloak" in name or "mantle" in name or "coat" in name: subtype = "Cloak"
                    elif "garment" in name or "wear" in name or "suit" in name: subtype = "Garment"
                    elif "field" in name or "circle" in name: subtype = "Field"
                    elif "uniform" in name or "jacket" in name or "dress" in name or "cuirass" in name: subtype = "Clothes"
                    elif "gear" in name: subtype = "Gear"
                    else: subtype = "Armor"
                elif "shield" in raw_type:
                    if "barrier" in name: subtype = "Barrier"
                    elif "merge" in name: subtype = "Merge"
                    elif "ring" in name: subtype = "Ring"
                    elif "gear" in name: subtype = "Gear"
                    elif "wall" in name: subtype = "Wall"
                    else: subtype = "Shield"
                elif raw_type in ["support", "stat bonus", "status cure", "stat support", "technique enhancer"]:
                    subtype = raw_type.title()
                    if subtype == "Stat Support": subtype = "Stat Bonus"
                    if subtype == "Technique Enhancer": subtype = "Support"
                elif "enemy" in raw_type:
                    subtype = "Enemy Part"
                elif "mag cell" in raw_type:
                    subtype = "Mag Cell"
                elif "unique" in raw_type:
                    if "amplifier" in name: subtype = "Amplifier"
                    elif "badge" in name: subtype = "Weapon Badge"
                    elif "disk" in name: subtype = "Music Disk"
                    else: subtype = "Tool/Unique"
                else:
                    subtype = raw_type.title()
                
                lookup[name] = subtype
except Exception as e:
    print("Error other:", e)

# Write output
with open("c:/Users/liqui/git/psobb.io-website/api/item_subtypes.json", "w", encoding="utf-8") as f:
    json.dump(lookup, f, indent=4)
print("Done writing item_subtypes.json. Total items:", len(lookup))
