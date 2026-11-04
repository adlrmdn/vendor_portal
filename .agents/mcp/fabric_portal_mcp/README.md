# Fabric Vendor Portal — Read-Only AI Model Context Protocol (MCP) Server

**Target Environment:** PT Mega Perintis Tbk &bull; Vendor Portal  
**Protocol Standard:** Model Context Protocol (MCP) Spec 2024-11-05 (JSON-RPC 2.0 over Stdio)  
**Supported AI Clients:** Claude Code, Claude Desktop, Antigravity / Gemini, Cursor, Windsurf  
**Security Level:** **100% STRICTLY READ-ONLY** (Guaranteed zero mutation, zero data wipe, zero tables drop)

---

## 1. Safety Architecture & Read-Only Guarantees

This MCP server provides safe introspection, learning, and reasoning capabilities for AI assistants without any ability to write, mutate, or drop database records.

1. **PostgreSQL Session Guard**: Every database inspection runs with:
   ```sql
   SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY;
   ```
   PostgreSQL enforces that any attempt to perform `INSERT`, `UPDATE`, `DELETE`, `DROP`, or `ALTER` causes a hard engine-level rejection.
2. **Read-Only API Bridge**: The Python MCP server interfaces with Laravel via a dedicated, read-only bridge (`bridge.php`), selecting only necessary sanitized fields.
3. **Sensitive Data Protection**: Passwords, user remember tokens, and secret hashes are explicitly omitted from query selections.

---

## 2. Available AI Tools

| Tool Name | Parameters | Purpose |
| :--- | :--- | :--- |
| `get_module_architecture` | *None* | Returns complete subsystem architecture, state machines, TOTP dual-control rules, Incoterms, and database relations. |
| `get_pipeline_stats` | *None* | Returns aggregate Preliminary Contract counts grouped by status (`draft`, `sent_to_supplier`, `confirmed`, `supplier_queried`, etc.). |
| `list_fabric_vendors` | *None* | Lists all active textile mills/vendors with vendor codes, email contacts, currency codes, and payment terms. |
| `get_master_data` | *None* | Returns active fabric seasons, transport modes, delivery terms (Incoterms), and HS customs codes. |
| `inspect_contract` | `identifier` (e.g. `"MPG/PC/PDV/26/X/451"` or `1`) | Returns complete contract header, fabric technical specifications, milestone delivery schedules, and audit event logs. |
| `read_guidebook` | `type` (`"admin"` or `"vendor"`) | Reads the complete official Preliminary Contract operating guidebook in full Markdown. |

---

## 3. Available MCP Resources

- `fabric://guidebook/admin` — Buyer & Administrator Operating Guidebook
- `fabric://guidebook/vendor` — Fabric Mill & Supplier Operating Guidebook
- `fabric://architecture/blueprint` — Subsystem Architecture & Security Blueprint

---

## 4. Setup & Integration Instructions

### A. Claude Code (CLI)
The workspace root already contains [`.mcp.json`](file:///home/ubuntu/webservice/vendor_portal/.mcp.json). Claude Code automatically detects and loads the server when running in the project directory:

```bash
# Verify with Claude Code CLI
claude mcp list
```

Alternatively, to add it globally to Claude Code:
```bash
claude mcp add fabric-vendor-portal /usr/bin/python3 /home/ubuntu/webservice/vendor_portal/.agents/mcp/fabric_portal_mcp/server.py
```

---

### B. Antigravity / Gemini
The server is pre-configured in [`.agents/mcp_config.json`](file:///home/ubuntu/webservice/vendor_portal/.agents/mcp_config.json):
```json
{
  "mcpServers": {
    "fabric-vendor-portal": {
      "command": "/usr/bin/python3",
      "args": [
        "/home/ubuntu/webservice/vendor_portal/.agents/mcp/fabric_portal_mcp/server.py"
      ]
    }
  }
}
```

---

### C. Claude Desktop
Add the following configuration block to your Claude Desktop config file:
- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`
- **Linux:** `~/.config/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "fabric-vendor-portal": {
      "command": "/usr/bin/python3",
      "args": [
        "/home/ubuntu/webservice/vendor_portal/.agents/mcp/fabric_portal_mcp/server.py"
      ],
      "env": {
        "PORTAL_READONLY": "true"
      }
    }
  }
}
```

---

### D. Cursor / Windsurf
In **Settings &rarr; Features &rarr; MCP Servers**, click **Add New MCP Server**:
- **Name:** `fabric-vendor-portal`
- **Type:** `command`
- **Command:** `/usr/bin/python3 /home/ubuntu/webservice/vendor_portal/.agents/mcp/fabric_portal_mcp/server.py`

---

## 5. Verification Commands

Test that the MCP server responds cleanly via command line:

```bash
# 1. Initialize Handshake
/usr/bin/python3 /home/ubuntu/webservice/vendor_portal/.agents/mcp/fabric_portal_mcp/server.py <<< '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'

# 2. List Tools
/usr/bin/python3 /home/ubuntu/webservice/vendor_portal/.agents/mcp/fabric_portal_mcp/server.py <<< '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'

# 3. Call Tool (Pipeline Stats)
/usr/bin/python3 /home/ubuntu/webservice/vendor_portal/.agents/mcp/fabric_portal_mcp/server.py <<< '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"get_pipeline_stats","arguments":{}}}'
```
