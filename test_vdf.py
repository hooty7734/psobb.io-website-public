
import struct
import zlib
import os

def pack_str(k, v):
    return b'\x01' + k.encode('utf-8') + b'\x00' + v.encode('utf-8') + b'\x00'

def test_entry():
    entry = b''
    # Simulated content
    entry += pack_str('AppID', '0')
    entry += pack_str('AppName', 'TestApp')
    
    # FIXED logic in install-deck.sh
    # entry += b'\x00tags\x00\x08'
    
    good_tags = b'\x00tags\x00\x08'
    
    # And line 168 appends the final \x08 to close the entry
    entry_good = entry + good_tags + b'\x08'
    
    print("Good Entry Hex:")
    print(entry_good.hex())
    
    print("\nAnalysis:")
    if entry_good.endswith(b'\x08\x08'):
        print("Ends with 2x 08. Correct.")
    else:
        print("Ends incorrectly.")

test_entry()
