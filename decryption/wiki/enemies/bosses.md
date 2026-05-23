# Bosses

## Dragon Boss

### Constructor Logic (`00425c6c`)
The Dragon boss is instantiated via a specialized constructor that allocates memory for three distinct enemy objects, each representing a part of the boss entity.

*   **Signature**: `void __thiscall DragonConstructor(void * this, undefined * param_1)`
*   **VTable Pointer**: `00af3ce8`

The constructor performs the following initialization steps:
1.  **Allocation**: Allocates three enemy objects. Each object is initialized with a size of `0xB8` bytes (note: this differs from the standard `Enemy` struct size of `0x41C`, suggesting a specific initialization structure or subclass).
2.  **VTable Setup**: Sets the vtable pointer to `00af3ce8`.
3.  **Entity Configuration**: Initializes hitbox and collision data for the three parts.
4.  **Base Initialization**: Calls `FUN_007b4b48` for base initialization.
5.  **Collision Update**: Calls `unk_update_collision_box_position` to update collision box positions.
6.  **Entity Registration**: Calls `insert_into_entity_list2` to register the boss entities in the active entity list.

### Entity Configuration
The Dragon boss consists of three parts with specific hitbox and collision properties:

| Entity | Hitbox Count | Radius | Field `0xAC` | Field `0xB4` |
| :--- | :--- | :--- | :--- | :--- |
| **Entity 1** | 1 | 25.0 | 0 | 0 |
| **Entity 2** | 3 | 10.0 | -2.0 | 10.0 |
| **Entity 3** | 5 | 10.0 | -2.0 | -14.0 |

### VTable (`00af3ce8`)
| Offset | Function | Description |
| :--- | :--- | :--- |
| `0x00` | `FUN_00425b20` | Unknown (likely destructor or vtable init) |
| `0x04` | `GenericMenuHandlerNoOp` (`0061cdb0`) | |
| `0x08` | `GenericMenuHandlerNoOp` (`0061cdb0`) | |
| `0x0C` | `GenericMenuHandlerNoOp` (`0061cdb0`) | |
| `0x10` | `GenericMenuHandlerNoOp` (`0061cdb0`) | |
| `0x14` | `FUN_00448a61` | |
| `0x18` | `FUN_003ca37b` | |
| `0x1C` | `FUN_001c4c7b` | |
| `0x20` | `FUN_0060a47b` | |
| `0x24` | `FUN_0038a67b` | |
| `0x28` | `FUN_0068a47b` | |
| `0x2C` | `FUN_0058c36d` | |
| `0x30` | `FUN_00dc6740` | |

### Related Functions
*   `FUN_007b4b48`: Base initialization routine.
*   `unk_update_collision_box_position`: Updates the position of the collision boxes for the boss parts.
*   `insert_into_entity_list2`: Registers the boss entities into the active entity list.
*   `00415154`: `ConstructGameListMenuEntry` (Referenced in context of `AllocateAndInitializeWeapon`).

---

## De Rol Le (Derolle)

### Constructor (`0043abe0`)
The De Rol Le constructor initializes the entity with specific rotation, position, and behavior data.

*   **VTable Pointer**: `00af5300`
*   **Behavior Function**: Sets `field_0x04` to `PTR_DAT_009a49a8` (likely the update or behavior function pointer).

**Initialization Steps**:
1.  Calls `base_game_object_constructor`.
2.  Initializes random rotation/position offsets using `_rand()` and global constants (`008fda80`, `008fda84`, `008fda7c`, `008fda88`, `008fda58`).
3.  Copies `param_2` (3 floats) to fields `0x28-0x30` (likely position).
4.  Copies `param_3` (3 floats) to fields `0x34-0x3c` (likely direction or velocity).
5.  Sets `field_0x24` to `param_4` (pointer to parent or context).

### VTable (`00af5300`)
| Offset | Function | Description |
| :--- | :--- | :--- |
| `0x00` | `DerolleConstructor` (`0043a994`) | |
| `0x04` | `DerolleSpawnRockAttack` (`0043ac84`) | |
| `0x08` | `GenericMenuHandlerNoOp` (`0061cdb0`) | |
| `0x0C` | `GenericMenuHandlerNoOp` (`0061cdb0`) | |
| `0x10` | `FUN_00199320` | Unknown |
| `0x14` | `0x1` | Unknown flag/offset |
| `0x18` | `PTR_DAT_00af5330` | Data pointer |
| `0x3C` | `0xFFFFFFFF` | Destructor flag? |
| `0x40` | `LAB_008ce1ac` | Exception handler? |
| `0x44` | `FUN_00199320` | Repeated? |
| `0x48` | `0x1` | Repeated? |

### Update Loop (`0043b0f8`)
The main behavior loop for De Rol Le handles a state machine located at `param_1 + 0x24`.

*   **State 0 (Init)**: Sets `0x100` flag in `DAT_00a44730`.
*   **State 1**: Updates position/rotation, manages array at `0x2c-0x40`.
*   **State 2**: Similar to State 1, spawns rock objects (`FUN_0043a710`).
*   **State 3**: Updates light/diffuse values.
*   **State 4**: Transition state.

**Additional Logic**:
*   Manages particle effects (`create_particle_effect6`, `FUN_0043aa84`).
*   Manages sound effects (`play_directional_sound_effect`).
*   Updates light entry diffuse (`set_lightentry_diffuse`).

### Key Functions
*   `00426290`: `DerolleDestructor`
*   `004262e0`: `DerolleConstructor`
*   `00426624`: `DerolleCleanup`
*   `0043ac84`: `DerolleSpawnRockAttack`
*   `0043b0f8`: `DerolleUpdate` (Main behavior loop)
*   `0043be44`: `DerolleUpdateFog` (Calls `get_fogentry_data`, sets `DAT_00a44730 |= 4`)

---

## Vol Opt (Volopt)

### Constructor (`0043c508`)
*   **Signature**: `__thiscall`, sets vtable to `PTR_FUN_00af5540`.
*   **Global State**: Initializes volopt global state (monitor IDs, entity IDs) to `0xffff`.
*   **Base Init**: Calls `FUN_0052bf84`.
*   **Dead Check**: Checks `maybe_volopt_is_dead`; if true, calls `set_monster_spawn_initdata_as_dead`.
*   **Deallocation**: If `param_1` bit 0 is set, calls `deallocate_in_main_arena(this)`.

### AI State Machine (`0043c5d4`)
The `Enemy::UpdateAiState` function handles the AI logic.

*   **Dead Check**: Checks 'dead' flag; if set, sets bit 0 in `flPosition_x` (collision/visibility?) and returns.
*   **Flag 0x4000**: Checks flag `0x4000` (possibly 'isStunned' or 'invulnerable').
    *   **If NOT set**:
        *   Compares `state_maybe` and `unknown_ai_state`.
        *   If different, resets `field_0x384` to `-1`, calls `FUN_0043c680`, updates `unknown_ai_state`.
        *   Resets `field_0x384` to `0`.
        *   Calls `FUN_0043c680` again.
    *   **If set**:
        *   Decrements counter at `field_0x294`.
        *   If counter `<= 0`, clears `field_0x290`, `0x28c`, `0x294` and clears `0x4000` flag.

### AI State Dispatcher (`0043c680`)
`EnemyProcessAiState` is a dispatcher for enemy state machine (states 0-4).

*   **State 0**: Spawns minions (0x420 size, count 6), rocks (0x39c size, count 24), Volopt object, and another object (0x398 size). Sets state to 1.
*   **State 1**: Calls `FUN_0043e134`, plays sound `0x33a`.
*   **State 2**: Calls `FUN_0043d5f4`, updates particle/visual globals using `volopt_bps_1`.
*   **State 3**: Calls `FUN_0043d47c`, plays sound `0x33b`.
*   **State 4**: Calls `FUN_0043c9f0`, updates particle/visual globals using `volopt_bps_2/3`.

### Attack Phase Execution (`0043cb04`)
`Volopt::ExecuteAttackPhase` is the main attack logic.

*   **Validity Check**: Calls `FUN_0043d408` (`IsVoloptEntityValid`); returns if invalid.
*   **Targeting**: Finds nearest target within 400.0 units.
*   **Phase Counter**: Uses `field_0x3f8` as an attack phase counter (0-4).
    *   **Phase 0**: Sends packet `0x15` sub `3`. Checks entity index bits.
    *   **Phase 1**: Sends packet `0x15` sub `4`. Checks entity index bits.
    *   **Phase 2**: Sends packet `0x15` sub `5`. Checks entity index bits.
    *   **Phase 3**: Sends packet `0x15` sub `7` with random offset. Checks entity index bits.
    *   **Phase 4**: Sends packet `0x15` sub `6`. Checks entity index bits.
*   **Reset**: Resets `field_0x3f8` to `0` after Phase 4.
*   **Player Selection**: Handles player selection logic (`DAT_00a44770` array).

### Entity Validation (`0043d408`)
*   Checks if entity at `DAT_009a4e04` is valid (non-null) and active (`field_0x22 == 0`).
*   Returns `1` if valid, `0` otherwise.

---

## Global Context & Reset

### ResetGlobalContext (`0043c4d0`)
*   **Signature**: `void __stdcall ResetGlobalContext(void)`
*   **Called By**: `FUN_0043a4c0`.
*   **Logic**:
    *   Traverses pointer chain from `DAT_00a44270`.
    *   Initializes `DAT_00a44728`, `DAT_00a44730`, `DAT_00a4472c`.
    *   Clears LSB of `*DAT_00a44728` and `*puVar1`.

### Global Context Structure (Inferred from `0043c4d0`)
*   `DAT_00a44270`: Base pointer (likely GlobalContext or similar).
*   `00a44270+0x42c`: Pointer to something (maybe scene or room).
*   `...+0xa8`: Pointer to something (maybe player or container).
*   `...+0x2c`: Pointer to something (maybe state or config).
*   `...+0x30`: `uint* puVar1` (Pointer to current state flags/data).
*   `DAT_00a44728`: Points to `puVar1[0xc]` (Secondary state pointer).
*   `DAT_00a4472c`: Points to `puVar1` (Primary state pointer).
*   `DAT_00a44730`: Global flags (cleared to `0` in `ResetGlobalContext`).

---

## AdScrollBarXb (UI/Menu Object)

### Struct Layout
| Offset | Size | Type | Description |
| :--- | :--- | :--- | :--- |
| `0x00` | 4 | `void*` | Pointer to result of `PreviewObjectConstructor3` |
| `0x04` | 4 | `void*` | Pointer to `GameListMenuObject` |
| `0x08` | 4 | `uint` | Detail view active flag? |
| `0x0C` | 4 | `uint` | Selected item value (from `get_value_from_selected_list_item`) |
| `0x1dc` | 72 | `uint[]` | Array of item states/indices (size `0x48`) |
| `0x1ec` | 4 | `uint` | Update counter |
| `0x1f0` | 4 | `uint` | State machine variable (inferred from `TAdSelectCurGC_Confirm`) |
| `0x1f8` | 4 | `uint` | Flags |
| `0x1fc` | 4 | `uint` | Index into `TAdScrollBarXb_objs` array |
| `0x200` | 4 | `uint` | Input state offset |

### Functions
*   `00412018`: `AdScrollBarXb_Destroy`
*   `00411a68`: `clamp_cursor_index_decrease`
*   `00411a8c`: `clamp_cursor_index_increase`
*   `00411ab4`: `AdScrollBarXbDecrement`
