# Inputs Controls

*(Waiting for Wiki Agent updates...)*


# UI Control Logic: AdScrollBarXb

The `AdScrollBarXb` object is a UI component responsible for managing scrollbar interactions and cursor movement within the game's menu systems. It is frequently associated with the `TAdSelectCurGC` class for state machine management.

## Functions

### `AdScrollBarXb_Destroy`
- **Address**: `00412018`
- **Signature**: `void __fastcall AdScrollBarXb_Destroy(undefined * * param_1)`
- **Description**: Destroys the scrollbar object instance and cleans up associated resources.

### `clamp_cursor_index_decrease`
- **Address**: `00411a68`
- **Signature**: `void __fastcall clamp_cursor_index_decrease(int param_1)`
- **Description**: Decrements the cursor index value. Includes logic to clamp the value so it does not drop below the minimum valid index (e.g., 0).

### `clamp_cursor_index_increase`
- **Address**: `00411a8c`
- **Signature**: `void __fastcall clamp_cursor_index_increase(int param_1)`
- **Description**: Increments the cursor index value. Includes logic to clamp the value so it does not exceed the maximum valid index (e.g., list length - 1).

### `AdScrollBarXbDecrement`
- **Address**: `00411ab4`
- **Signature**: `void __fastcall AdScrollBarXbDecrement(int param_1)`
- **Description**: A wrapper function for decrementing the scrollbar state. Likely calls `clamp_cursor_index_decrease` to ensure bounds safety.

## Future Research
- Investigate vtable at `00af3500` to confirm class hierarchy.
- Find next `FUN_` function after `00417d00`.



# UI Control Logic: AdScrollBarXb

The `AdScrollBarXb` object is a UI component responsible for managing scrollbar interactions and cursor movement within the game's menu systems. It is frequently associated with the `TAdSelectCurGC` class for state machine management.

## Functions

### `AdScrollBarXb_Destroy`
- **Address**: `00412018`
- **Signature**: `void __fastcall AdScrollBarXb_Destroy(undefined * * param_1)`
- **Description**: Destroys the scrollbar object instance and cleans up associated resources.

### `clamp_cursor_index_decrease`
- **Address**: `00411a68`
- **Signature**: `void __fastcall clamp_cursor_index_decrease(int param_1)`
- **Description**: Decrements the cursor index value. Includes logic to clamp the value so it does not drop below the minimum valid index (e.g., 0).

### `clamp_cursor_index_increase`
- **Address**: `00411a8c`
- **Signature**: `void __fastcall clamp_cursor_index_increase(int param_1)`
- **Description**: Increments the cursor index value. Includes logic to clamp the value so it does not exceed the maximum valid index (e.g., list length - 1).

### `AdScrollBarXbDecrement`
- **Address**: `00411ab4`
- **Signature**: `void __fastcall AdScrollBarXbDecrement(int param_1)`
- **Description**: A wrapper function for decrementing the scrollbar state. Likely calls `clamp_cursor_index_decrease` to ensure bounds safety.

## Struct Layout

The `AdScrollBarXb` structure defines the memory layout for the scrollbar UI object. It contains pointers to menu objects, state flags, and an array of item states.

| Offset | Size | Description |
| :--- | :--- | :--- |
| `0x00` | ... | Base Object Header (inferred) |
| `0x4c` | 4 | Pointer to result of `PreviewObjectConstructor3` |
| `0x50` | 4 | Pointer to `GameListMenuObject` |
| `0x54` | 4 | Detail view active flag? |
| `0x58` | 4 | Selected item value (from `get_value_from_selected_list_item`) |
| `0x1dc` | 0x48 | **Array of item states/indices** (See sub-fields below) |
| `0x1ec` | 4 | **Update counter** (Offset within `0x1dc` array) |
| `0x1f0` | 4 | **State machine variable** (Offset within `0x1dc` array; inferred from `TAdSelectCurGC_Confirm`) |
| `0x1f8` | 4 | **Flags** (Offset within `0x1dc` array) |
| `0x1fc` | 4 | **Index into `TAdScrollBarXb_objs` array** (Offset within `0x1dc` array) |
| `0x200` | 4 | **Input state offset** (Offset within `0x1dc` array) |

### Notes
- The fields starting at `0x1ec` appear to be located within the `0x48`-byte block starting at `0x1dc`. This suggests the "Array of item states" is actually a composite struct or buffer containing multiple state variables.
- The struct is referenced by the global array `TAdScrollBarXb_objs`.

## Future Research
- Investigate vtable at `00af3500` to confirm class hierarchy.
- Find next `FUN_` function after `00417d00`.

