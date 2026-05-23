# Global Structs

*(Waiting for Wiki Agent updates...)*


# UI & Game List Systems

This document details the reverse-engineered functions, structures, and state machines governing the PSOBB Game List, Character Select UI, and related preview systems.

## Function Reference

The following functions handle the construction, rendering, selection, and lifecycle of UI elements within the game list and character selection screens.

| Address | Function Name | Signature | Description |
|:---|:---|:---|:---|
| `004107fc` | `ConstructGameListObject` | `undefined * * __thiscall ConstructGameListObject(void * this, undefined * param_1)` | Initializes and constructs the primary Game List object. Handles base object setup and parameter binding. |
| `00410d8c` | `GameListObjectRender` | `void __fastcall GameListObjectRender(int param_1)` | Renders the Game List object to the screen. Processes drawing calls for list items and scrollbars. |
| `00411ad8` | `UpdateGameListItemSelection` | `void __fastcall UpdateGameListItemSelection(int param_1)` | Updates the visual and logical state of the currently selected item in the game list. Handles cursor highlighting and selection flags. |
| `00412018` | `AdScrollBarXb_Destroy` | `void __fastcall AdScrollBarXb_Destroy(undefined * * param_1)` | Destroys and cleans up the scrollbar UI component (`AdScrollBarXb`). Frees associated memory and resets pointers. |
| `0041245c` | `TAdSelectCurGC_update` | `void __fastcall TAdSelectCurGC_update(int param_1)` | The main update loop for the Character Select UI (`TAdSelectCurGC`). Processes input, state transitions, and preview object synchronization. |
| `004138c8` | `TAdSelectCurGC_Confirm` | `undefined8 __fastcall TAdSelectCurGC_Confirm(int param_1)` | Handles the "Enter" key confirmation logic within the Character Select UI. Validates selection and triggers state transitions. |
| `00413f54` | `TAdSelectCurGC_HandleMenuAction` | `void __fastcall TAdSelectCurGC_HandleMenuAction(int param_1)` | Dispatches menu actions based on input. Routes commands to appropriate handlers (e.g., confirm, cancel, preview toggle). |
| `00414b84` | `TadselectcurgcDestructor` | `void __thiscall TadselectcurgcDestructor(void * this, uint param_1)` | Destructor for the `TAdSelectCurGC` object. Cleans up preview pools, list windows, and associated UI resources. |
| `00414d60` | `InitPreviewObjectPool` | `void __cdecl InitPreviewObjectPool(undefined * param_1)` | Allocates a pool of **9 preview objects**. Initializes their vtables, bounding boxes, and prepares them for character/model preview rendering. |
| `00415154` | `ConstructGameListMenuEntry` | `undefined * * __thiscall ConstructGameListMenuEntry(void * this, undefined * param_1, undefined * param_2)` | Constructor for individual list menu entries. Handles string initialization, bounding box calculation, and attachment to the parent list window. |
| `0041560c` | `InitializeGameLanguage` | `undefined * * __thiscall InitializeGameLanguage(void * this, undefined * param_1, undefined * param_2)` | Sets up locale strings for the UI. Loads compressed PRS resources and XVM texture files required for localized text rendering. |
| `00417cb8` | `SetGlobalUIField1C` | `undefined * * __thiscall SetGlobalUIField1C(void * this, undefined * param_1)` | Stores `param_1` into the global UI data structure at `DAT_00a4369c + 0x1c`. Only writes if `param_1` is non-null. |

## `TAdSelectCurGC` Structure Layout (Inferred)

The `TAdSelectCurGC` structure manages the character selection screen state. The following offsets have been identified through static analysis and dynamic tracing.

| Offset | Field Type | Description |
|:---|:---|:---|
| `0x20` | `uint32_t` | **State Machine Variable**. Controls the current UI phase. See state table below. |
| `0x28` | `void*` | Pointer to a player/enemy-related structure. Used to set the `0x1f0` flag during preview updates. |
| `0x34` | `void*` | Pointer to the `list_window_object` responsible for rendering the selectable list. |
| `0x44` | `void*` | Pointer to the result of `PreviewObjectConstructor4` (likely a specific preview model instance). |
| `0x48` | `void*` | Pointer to the result of `PreviewObjectConstructor2` (likely another preview model instance or slot). |

### State Machine Values (`TAdSelectCurGC + 0x20`)

The state variable at offset `0x20` dictates the behavior of the update loop and input handlers:

| State ID | Name | Behavior |
|:---|:---|:---|
| `0x0C` (12) | `Confirm` | Awaits user confirmation. Disables navigation, enables selection commit. |
| `0x09` (9) | `Preview1` | Displays the first preview model. Updates bounding box and rendering flags. |
| `0x05` (5) | `Flag1` | Internal flag state. Likely used for toggling UI visibility or transition flags. |
| `0x0A` (10) | `Preview2` | Displays the second preview model. |
| `0x07` (7) | `Preview3` | Displays the third preview model. |

## Preview Object Pool & Menu Initialization

The preview system relies on a pre-allocated pool of 9 objects managed by `InitPreviewObjectPool`. Each object is initialized with a valid vtable pointer and a calculated bounding box for collision/rendering checks. These objects are linked to the `TAdSelectCurGC` instance via pointers at offsets `0x44` and `0x48`.

Menu entries are constructed dynamically using `ConstructGameListMenuEntry`, which binds string data and spatial bounding boxes to the list window. This ensures accurate hit-testing and rendering alignment.

## Global UI & Language Management

Language localization is handled by `InitializeGameLanguage`, which parses locale-specific strings and decompresses PRS archives containing XVM textures. UI state is frequently synchronized with global data structures, notably via `SetGlobalUIField1C`, which safely writes to `DAT_00a4369c + 0x1C` when provided with a non-null pointer. This global field is likely used for cross-screen UI state persistence or network sync flags.
