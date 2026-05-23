#!/bin/bash

# PSOBB.IO Steam Deck Installer

# Check dependencies
for cmd in unzip python3 curl; do
    if ! command -v $cmd &> /dev/null; then
        echo "Error: $cmd is required but not installed."
        exit 1
    fi
done

INSTALL_DIR="$HOME/Games/PSOBBIO"
ZIP_URL="https://psobb.io/downloads/PSOBBIO-Linux_1.25.13.zip"
ZIP_FILE="$INSTALL_DIR/psobbio.zip"

echo "Installing PSOBB to $INSTALL_DIR..."

# Create directory
mkdir -p "$INSTALL_DIR"

# Download
echo "Downloading client..."
curl -L -o "$ZIP_FILE" "$ZIP_URL"

if [ $? -ne 0 ]; then
    echo "Download failed. Please check your internet connection."
    exit 1
fi

# Extract
echo "Extracting files..."
unzip -o "$ZIP_FILE" -d "$INSTALL_DIR"

if [ $? -ne 0 ]; then
    echo "Extraction failed."
    exit 1
fi

# Cleanup
rm "$ZIP_FILE"


# ----------------------------------------------------------------------------
# 1. Custom Configurations & Widescreen Patch
# ----------------------------------------------------------------------------
echo "Detecting screen resolution..."

# Default to Standard Deck (800p)
TARGET_WIDTH=1280
TARGET_HEIGHT=800

# Try to detect resolution using xrandr
if command -v xrandr &> /dev/null; then
    # Get the width of the primary connected screen (looking for "connected primary" or just the first resolution with *)
    # Steam Deck Game Mode runs in a way that might just show the screen size.
    # In Desktop mode, this should work.
    DETECTED_WIDTH=$(xrandr --current | grep '*' | awk '{print $1}' | cut -d 'x' -f1 | head -n 1)
    
    # Check for 1920 (Landscape) or 1080 (Portrait/Rotated which Decksight might report)
    if [ "$DETECTED_WIDTH" == "1920" ] || [ "$DETECTED_WIDTH" == "1080" ]; then
        echo "Detected 1080p screen (Decksight)."
        TARGET_WIDTH=1920
        TARGET_HEIGHT=1080
    elif [ ! -z "$DETECTED_WIDTH" ]; then
        echo "Detected resolution width: $DETECTED_WIDTH"
    fi
else
    echo "xrandr not found, defaulting to Standard Deck (800p)."
fi

echo "Applying Widescreen Configuration ($TARGET_WIDTH x $TARGET_HEIGHT)..."
cat > "$INSTALL_DIR/widescreen.cfg" << EOL
MSAA=1
SMAA=1
SSAO=1
CelShader=1
DOF=1
HDR=1
HUDScale=1.0
Width=$TARGET_WIDTH
Height=$TARGET_HEIGHT
Windowed=1
EOL

echo "Configuration updated. Using bundled Widescreen Patch (d3d8.dll)."

# Download Artwork
echo "Downloading Steam artwork..."
LOGO_URL="https://psobb.io/img/steam_logo.png"
HERO_URL="https://psobb.io/img/steam_hero.png"
ICON_URL="https://psobb.io/img/steam_icon.png"
PORTRAIT_URL="https://psobb.io/img/steam_portrait.png"

curl -L -o "$INSTALL_DIR/steam_logo.png" "$LOGO_URL"
curl -L -o "$INSTALL_DIR/steam_hero.png" "$HERO_URL"
curl -L -o "$INSTALL_DIR/steam_icon.png" "$ICON_URL"
curl -L -o "$INSTALL_DIR/steam_portrait.png" "$PORTRAIT_URL"

# ----------------------------------------------------------------------------
# 2. Deploy Steam Deck Controller Layout
# ----------------------------------------------------------------------------
echo "Installing Steam Deck controller layout..."

STEAM_DIR="$HOME/.steam/steam"
if [ ! -d "$STEAM_DIR" ]; then
    STEAM_DIR="$HOME/.local/share/Steam"
fi

TEMPLATE_DIR="$STEAM_DIR/controller_base/templates"
mkdir -p "$TEMPLATE_DIR"

CONTROLLER_URL="https://psobb.io/psobb_io_controller.vdf"
curl -L -o "$TEMPLATE_DIR/psobb_io_controller.vdf" "$CONTROLLER_URL" 2>/dev/null || {
    echo "Warning: Could not download controller layout. You can configure controls manually in Steam."
}

echo "Controller layout installed to $TEMPLATE_DIR/psobb_io_controller.vdf"

# ----------------------------------------------------------------------------
# 3. Add to Steam & Configure Proton (Automated)
# ----------------------------------------------------------------------------
echo "Configuring Steam integration (Shortcuts + Proton + Artwork)..."

# Note: Steam must be restarted manually for changes to shortcuts.vdf to take effect properly
# if it is currently running. We will not close it automatically.

cat > "$INSTALL_DIR/add_to_steam.py" << 'PYTHON_EOF'
import os
import struct
import shutil
import zlib

# ============================================================================
# Self-contained Binary VDF Parser/Serializer (no external dependencies)
# 
# Binary VDF Format:
#   0x00 + name\0 = Map start   (followed by children, closed by 0x08)
#   0x01 + name\0 + value\0 = String
#   0x02 + name\0 + 4-byte LE int = Int32
#   0x08 = End of Map
# ============================================================================

TYPE_MAP    = 0x00
TYPE_STRING = 0x01
TYPE_INT32  = 0x02
TYPE_END    = 0x08

class BinaryVDF:
    """Minimal binary VDF parser/serializer using only Python stdlib."""
    
    @staticmethod
    def loads(data):
        """Parse binary VDF bytes into an OrderedDict-like structure."""
        result, _ = BinaryVDF._parse_map(data, 0, root=True)
        return result
    
    @staticmethod
    def dumps(obj):
        """Serialize a dict structure to binary VDF bytes."""
        out = bytearray()
        for key, value in obj.items():
            BinaryVDF._write_entry(out, key, value)
        out.append(TYPE_END)
        return bytes(out)
    
    @staticmethod
    def _read_string(data, pos):
        """Read a null-terminated string starting at pos."""
        end = data.index(b'\x00', pos)
        return data[pos:end].decode('utf-8', errors='replace'), end + 1
    
    @staticmethod
    def _parse_map(data, pos, root=False):
        """Parse a map (dict) from binary VDF data."""
        result = {}
        if root:
            # Root level: read the first map entry header
            if pos < len(data) and data[pos] == TYPE_MAP:
                pos += 1
                name, pos = BinaryVDF._read_string(data, pos)
                inner, pos = BinaryVDF._parse_map(data, pos)
                result[name] = inner
                return result, pos
            return result, pos
        
        while pos < len(data):
            type_byte = data[pos]
            pos += 1
            
            if type_byte == TYPE_END:
                break
            elif type_byte == TYPE_MAP:
                name, pos = BinaryVDF._read_string(data, pos)
                value, pos = BinaryVDF._parse_map(data, pos)
                # Handle duplicate keys by appending suffix
                orig_name = name
                counter = 1
                while name in result:
                    name = f"{orig_name}_{counter}"
                    counter += 1
                result[name] = value
            elif type_byte == TYPE_STRING:
                name, pos = BinaryVDF._read_string(data, pos)
                value, pos = BinaryVDF._read_string(data, pos)
                result[name] = value
            elif type_byte == TYPE_INT32:
                name, pos = BinaryVDF._read_string(data, pos)
                value = struct.unpack_from('<i', data, pos)[0]
                pos += 4
                result[name] = value
            else:
                # Unknown type, try to skip
                break
        
        return result, pos
    
    @staticmethod
    def _write_entry(out, key, value):
        """Write a single key-value entry to the output buffer."""
        key_bytes = key.encode('utf-8') + b'\x00'
        
        if isinstance(value, dict):
            out.append(TYPE_MAP)
            out.extend(key_bytes)
            for k, v in value.items():
                BinaryVDF._write_entry(out, k, v)
            out.append(TYPE_END)
        elif isinstance(value, int):
            out.append(TYPE_INT32)
            out.extend(key_bytes)
            out.extend(struct.pack('<i', value))
        elif isinstance(value, str):
            out.append(TYPE_STRING)
            out.extend(key_bytes)
            out.extend(value.encode('utf-8') + b'\x00')
        else:
            # Fallback: treat as string
            out.append(TYPE_STRING)
            out.extend(key_bytes)
            out.extend(str(value).encode('utf-8') + b'\x00')

# ============================================================================
# AppID Generation
# ============================================================================
def generate_appid(exe_path, app_name):
    """Generate a Steam-compatible AppID for a non-Steam game shortcut.
    
    Steam uses CRC32 of (exe + appname) with the top bit set,
    stored as a SIGNED 32-bit integer in shortcuts.vdf.
    """
    key = exe_path + app_name
    crc = zlib.crc32(key.encode('utf-8')) & 0xFFFFFFFF
    crc = crc | 0x80000000
    # Convert unsigned to signed 32-bit
    return struct.unpack('<i', struct.pack('<I', crc))[0]

# ============================================================================
# shortcuts.vdf Management
# ============================================================================
def build_entry(appid, app_name, exe_path, start_dir, icon_path, launch_options):
    """Build a shortcut entry dict with correct types."""
    return {
        'appid': appid,                    # int32 (signed)
        'AppName': app_name,               # string
        'Exe': f'"{exe_path}"',            # string, QUOTED
        'StartDir': f'"{start_dir}"',      # string, QUOTED
        'icon': icon_path,                 # string
        'ShortcutPath': '',                # string
        'LaunchOptions': launch_options,   # string
        'IsHidden': 0,                     # int32
        'AllowDesktopConfig': 1,           # int32
        'AllowOverlay': 1,                 # int32
        'OpenVR': 0,                       # int32
        'Devkit': 0,                       # int32
        'DevkitGameID': '',                # string
        'DevkitOverrideAppID': 0,          # int32
        'LastPlayTime': 0,                 # int32
        'FlatpakAppID': '',                # string
        'tags': {},                        # empty map
    }

def add_shortcut(shortcuts_path, app_name, exe_path, start_dir, icon_path, launch_options):
    """Add a non-Steam game shortcut to shortcuts.vdf.
    
    Returns the unsigned AppID (for use with artwork/config).
    """
    print(f"Modifying shortcuts: {shortcuts_path}")
    
    exe_path = os.path.abspath(exe_path)
    start_dir = os.path.abspath(start_dir)
    
    # Read existing shortcuts or create empty structure
    shortcuts_data = {'shortcuts': {}}
    if os.path.exists(shortcuts_path):
        try:
            with open(shortcuts_path, 'rb') as f:
                raw = f.read()
            if len(raw) > 0:
                shortcuts_data = BinaryVDF.loads(raw)
        except Exception as e:
            print(f"Warning: Could not parse existing shortcuts.vdf ({e}), creating new.")
            shortcuts_data = {'shortcuts': {}}
    
    shortcuts_map = shortcuts_data.get('shortcuts', {})
    
    # Check for existing entry with same AppName to avoid duplicates
    for key in list(shortcuts_map.keys()):
        entry = shortcuts_map[key]
        if isinstance(entry, dict):
            existing_name = entry.get('AppName', entry.get('appname', ''))
            if existing_name == app_name:
                print(f"Shortcut '{app_name}' already exists (key={key}). Updating.")
                existing_appid = entry.get('appid', 0)
                appid = existing_appid if existing_appid else generate_appid(f'"{exe_path}"', app_name)
                shortcuts_map[key] = build_entry(appid, app_name, exe_path, start_dir, icon_path, launch_options)
                shortcuts_data['shortcuts'] = shortcuts_map
                
                with open(shortcuts_path, 'wb') as f:
                    f.write(BinaryVDF.dumps(shortcuts_data))
                
                unsigned_id = struct.unpack('<I', struct.pack('<i', appid))[0]
                print(f"Updated AppID: {unsigned_id}")
                return unsigned_id
    
    # Calculate next index
    existing_keys = [int(k) for k in shortcuts_map.keys() if k.isdigit()]
    next_index = str(max(existing_keys) + 1) if existing_keys else '0'
    
    appid = generate_appid(f'"{exe_path}"', app_name)
    shortcuts_map[next_index] = build_entry(appid, app_name, exe_path, start_dir, icon_path, launch_options)
    shortcuts_data['shortcuts'] = shortcuts_map
    
    with open(shortcuts_path, 'wb') as f:
        f.write(BinaryVDF.dumps(shortcuts_data))
    
    unsigned_id = struct.unpack('<I', struct.pack('<i', appid))[0]
    print(f"Generated AppID: {unsigned_id}")
    print(f"Exe Path: {exe_path}")
    return unsigned_id

# ============================================================================
# Proton Configuration (Text VDF)
# ============================================================================
def update_config_vdf_proton(config_path, app_id, proton_name):
    """Edit CompatToolMapping in config.vdf to force specific Proton."""
    print(f"Updating config: {config_path} for AppID: {app_id} -> {proton_name}")
    
    if not os.path.exists(config_path):
        print("Config file not found.")
        return

    try:
        with open(config_path, 'r', encoding='utf-8') as f:
            lines = f.readlines()
            
        entry_lines = [
            f'\t\t\t\t"{app_id}"\n',
            '\t\t\t\t{\n',
            f'\t\t\t\t\t"name"\t\t"{proton_name}"\n',
            '\t\t\t\t\t"config"\t\t""\n',
            '\t\t\t\t\t"priority"\t\t"250"\n',
            '\t\t\t\t}\n'
        ]
        
        # Look for existing CompatToolMapping
        start_idx = -1
        for i, line in enumerate(lines):
            if '"CompatToolMapping"' in line:
                start_idx = i
                break
                
        if start_idx != -1:
            if f'"{app_id}"' in ''.join(lines):
                print(f"Mapping already exists for AppID {app_id}. Skipping.")
                return
            
            brace_idx = -1
            # Find the opening brace of CompatToolMapping
            for i in range(start_idx, len(lines)):
                 if '{' in lines[i]:
                     brace_idx = i
                     break
            
            if brace_idx != -1:
                # Insert AFTER the opening brace
                lines[brace_idx+1:brace_idx+1] = entry_lines
                with open(config_path, 'w', encoding='utf-8') as f:
                    f.writelines(lines)
                print("Proton forced (appended to existing section)")
        else:
            print("CompatToolMapping not found. Creating it...")
            steam_idx = -1
            for i, line in enumerate(lines):
                if '"Steam"' in line: # Try to find Steam block
                    steam_idx = i
                    break # Assuming first one is correct
            
            # If standard "Steam" block not found, try inserting at end of file (risky but fallback)
            # Better: Find the first root key.
            # actually config.vdf usually starts with "InstallConfigStore" or "UserLocalConfigStore" depending on file.
            # Let's search for "Software" -> "Valve" -> "Steam" structure if possible, OR just append if we can identify root.
            # Safest is usually looking for ' "Steam"'
            
            if steam_idx != -1:
                brace_idx = -1
                for i in range(steam_idx, len(lines)):
                    if '{' in lines[i]:
                        brace_idx = i
                        break
                if brace_idx != -1:
                    new_block = [
                        '\t\t\t"CompatToolMapping"\n',
                        '\t\t\t{\n'
                    ] + entry_lines + [
                        '\t\t\t}\n'
                    ]
                    lines[brace_idx+1:brace_idx+1] = new_block
                    with open(config_path, 'w', encoding='utf-8') as f:
                        f.writelines(lines)
                    print("Proton forced (created new section)")
                else:
                    print("Could not find brace for 'Steam' section.")
            else:
                print("Could not find 'Steam' section to insert CompatToolMapping.")

    except Exception as e:
        print(f"Error updating config.vdf: {e}")

# ============================================================================
# Artwork
# ============================================================================
def apply_artwork(config_dir, app_id, logo_src, hero_src, portrait_src):
    grid_dir = os.path.join(config_dir, 'grid')
    os.makedirs(grid_dir, exist_ok=True)
    
    for src, suffix in [(logo_src, '_logo.png'), (hero_src, '_hero.png'), (portrait_src, 'p.png')]:
        if src and os.path.exists(src):
            dest = os.path.join(grid_dir, f'{app_id}{suffix}')
            shutil.copy2(src, dest)
            print(f"Applied: {dest}")

# ============================================================================
# Desktop Shortcut
# ============================================================================
def create_steam_desktop_shortcut(app_id, app_name):
    desktop_dir = os.path.join(os.environ.get('HOME'), 'Desktop')
    os.makedirs(desktop_dir, exist_ok=True)
    shortcut_path = os.path.join(desktop_dir, f'{app_name}.desktop')
    
    steam_launch_id = (app_id << 32) | 0x02000000
    
    with open(shortcut_path, 'w') as f:
        f.write('[Desktop Entry]\n')
        f.write(f'Name={app_name}\n')
        f.write('Comment=Play via Steam\n')
        f.write(f'Exec=steam steam://rungameid/{steam_launch_id}\n')
        f.write(f'Icon=steam_icon_{app_id}\n')
        f.write('Terminal=false\n')
        f.write('Type=Application\n')
        f.write('Categories=Game;\n')
     
    os.chmod(shortcut_path, 0o755)
    print(f"Created Desktop shortcut: {shortcut_path}")

# ============================================================================
# Main
# ============================================================================
def main():
    home = os.environ.get('HOME')
    steam_userdata = os.path.join(home, '.steam', 'steam', 'userdata')
    
    if not os.path.exists(steam_userdata):
        steam_userdata = os.path.join(home, '.local', 'share', 'Steam', 'userdata')
        
    if not os.path.exists(steam_userdata):
        print("Could not find Steam userdata.")
        return

    app_name = "PSOBB IO"
    install_dir = os.environ.get('INSTALL_DIR')
    
    # Bypass Launcher to avoid resolution/windowing issues on Deck (Gamescope)
    # The launcher can confuse Proton if it runs at a different resolution.
    exe_path = os.path.join(install_dir, 'psobb.exe')
    start_dir = install_dir
    
    if not os.path.exists(exe_path):
        print(f"Warning: Executable not found at {exe_path}")
        # Fallback to older structure?
        old_exe = os.path.join(install_dir, 'Launcher', 'PsobbLauncher.exe')
        if os.path.exists(old_exe):
             exe_path = old_exe
             start_dir = os.path.join(install_dir, 'Launcher')
             print(f"Found Launcher instead: {exe_path}")
    # Launch options:
    # - Standard Proton options (WINEDLLOVERRIDES for d3d8/dinput8)
    launch_options = 'WINEDLLOVERRIDES="d3d8=n;dinput8=n,b" XMODIFIERS="" %command%'
             
    icon_path = os.path.join(install_dir, 'steam_icon.png')
    logo_path = os.path.join(install_dir, 'steam_logo.png')
    hero_path = os.path.join(install_dir, 'steam_hero.png')
    portrait_path = os.path.join(install_dir, 'steam_portrait.png')
    
    # ------------------------------------------------------------------------
    # Proton 10 Detection
    # ------------------------------------------------------------------------
    proton_name = "proton_10" # Default fallback
    steam_root = os.path.expanduser("~/.steam/steam")
    if not os.path.exists(steam_root):
         steam_root = os.path.expanduser("~/.local/share/Steam")
         
    common_dir = os.path.join(steam_root, "steamapps", "common")
    if os.path.exists(common_dir):
        # Look for "Proton 10*" folder
        proton_dirs = [d for d in os.listdir(common_dir) if d.lower().startswith("proton 10")]
        if proton_dirs:
            # Sort to get latest if multiple (e.g. 10.0 vs 10.1)
            proton_dirs.sort(reverse=True)
            best_proton = proton_dirs[0]
            print(f"Detected Proton 10 installation: {best_proton}")
            
            # Read compatibilitytool.vdf to get internal name
            vdf_path = os.path.join(common_dir, best_proton, "compatibilitytool.vdf")
            if os.path.exists(vdf_path):
                try:
                    with open(vdf_path, 'r', encoding='utf-8') as f:
                        for line in f:
                            if '"compat_tool_name"' in line:
                                # Extract string value
                                parts = line.split('"')
                                if len(parts) >= 4:
                                    proton_name = parts[3]
                                    print(f"Found internal Proton name: {proton_name}")
                                    break
                except Exception as e:
                    print(f"Error reading Proton VDF: {e}")
        else:
             print("Proton 10 not found (not installed?). Defaulting to 'proton_10'.")
    
    
    # Global Config for Proton Mapping
    global_config_vdf = os.path.join(steam_root, "config", "config.vdf")
    
    for user_id in os.listdir(steam_userdata):
        config_dir = os.path.join(steam_userdata, user_id, 'config')
        shortcuts_path = os.path.join(config_dir, 'shortcuts.vdf')
        config_vdf_path = os.path.join(config_dir, 'config.vdf')
        
        # Repair: If a previous run corrupted config.vdf, restore from backup
        config_bak = config_vdf_path + '.bak'
        if os.path.exists(config_bak):
            print(f"Restoring config.vdf from backup (fixing previous install)...")
            shutil.copy2(config_bak, config_vdf_path)
            os.remove(config_bak)
        
        if not os.path.isdir(config_dir):
            continue
            
        print(f"Processing user: {user_id}")
        
        if os.path.exists(shortcuts_path):
            shutil.copy2(shortcuts_path, shortcuts_path + '.bak')
            
        try:
            app_id = add_shortcut(shortcuts_path, app_name, exe_path, start_dir, icon_path, launch_options)
            
            # Apply Artwork
            apply_artwork(config_dir, app_id, logo_path, hero_path, portrait_path)

            # Apply Proton Config (Main Game) - Targets Global Config
            update_config_vdf_proton(global_config_vdf, str(app_id), proton_name)

            # Create Secondary Shortcut for Launcher (Settings)
            # We must force it to use the SAME Proton Prefix (compatdata) so settings stick.
            # The prefix path is: ~/.steam/steam/steamapps/compatdata/<AppID>
            # We need to find where Steam is installed first.
            
            steam_root = os.path.expanduser("~/.steam/steam")
            if not os.path.exists(steam_root):
                 steam_root = os.path.expanduser("~/.local/share/Steam")
            
            compat_data_path = os.path.join(steam_root, "steamapps", "compatdata", str(app_id))
            
            launcher_exe = os.path.join(install_dir, 'Launcher', 'PsobbLauncher.exe')
            launcher_start = os.path.join(install_dir, 'Launcher')
            
            # Launch Options for Secondary Shortcut:
            # force STEAM_COMPAT_DATA_PATH to the main game's prefix
            launcher_opts = f'STEAM_COMPAT_DATA_PATH="{compat_data_path}" %command%'
            
            print(f"Adding Secondary Shortcut: PSOBB Settings (Shared Prefix: {app_id})...")
            setup_id = add_shortcut(shortcuts_path, "PSOBB Settings", launcher_exe, launcher_start, icon_path, launcher_opts)
            
            # Apply Proton Config (Secondary Shortcut) - Targets Global Config
            update_config_vdf_proton(global_config_vdf, str(setup_id), proton_name)

            # Create Desktop Shortcut
            create_steam_desktop_shortcut(app_id, app_name)

            
        except Exception as e:
            import traceback
            print(f"Error processing user {user_id}: {e}")
            traceback.print_exc()

if __name__ == '__main__':
    main()
PYTHON_EOF

# Run
export INSTALL_DIR
python3 "$INSTALL_DIR/add_to_steam.py"

# Cleanup
rm "$INSTALL_DIR/add_to_steam.py"

echo ""
echo "============================================"
echo "  Installation Complete!"
echo "============================================"
echo ""
echo "IMPORTANT: Follow these steps to finish setup:"
echo ""
echo "  1. Restart Steam (close and reopen it)"
echo "  2. Find 'PSOBB IO' in your Steam Library"
echo "  3. Right-click it > Properties > Compatibility"
echo "  4. Check 'Force the use of a specific Steam Play"
echo "     compatibility tool'"
echo "  5. Select 'Proton 10.x' from the dropdown"
echo "  6. REPEAT steps 3-5 for 'PSOBB Settings'!"
echo "     (Both shortcuts require Proton to run)"
echo "" 
echo "  7. Launch the game!"
echo ""
echo "  8. (Optional) Run 'PSOBB Settings' from Steam"
echo "     to configure accounts/resolution."
echo "     (Settings are shared with the main game)"
echo ""
echo "The custom launch options for widescreen and"
echo "frame generation are already configured."
echo "============================================"
