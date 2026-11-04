#!/usr/bin/env python3
"""
Fabric Vendor Portal — Read-Only Model Context Protocol (MCP) Server
Standard: MCP Specification 2024-11-05 (JSON-RPC 2.0 over Stdio)
Target Clients: Antigravity, Claude Code, Claude Desktop, Cursor

Guarantees 100% READ-ONLY safe access to the Fabric Vendor Portal subsystem,
preliminary contracts, master data, and operating guidebooks. Zero mutation risk.
"""

import sys
import json
import subprocess
import os
from typing import Dict, Any, Optional

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../"))
BRIDGE_CONTAINER_PATH = "/var/www/html/.agents/mcp/fabric_portal_mcp/bridge.php"

TOOLS = [
    {
        "name": "get_module_architecture",
        "description": "Returns the complete architectural blueprint of the Fabric Vendor Portal and Preliminary Contract (PC) subsystem, including business workflows, state machines, TOTP security rules, and database table definitions.",
        "inputSchema": {
            "type": "object",
            "properties": {},
            "additionalProperties": False,
        },
    },
    {
        "name": "get_pipeline_stats",
        "description": "Returns current aggregate pipeline metrics of Preliminary Contracts broken down by status (Draft, Pending Approval, Approved, Sent to Supplier, Confirmed, Queried).",
        "inputSchema": {
            "type": "object",
            "properties": {},
            "additionalProperties": False,
        },
    },
    {
        "name": "list_fabric_vendors",
        "description": "Lists all active textile mills and fabric vendors registered in the portal with their vendor code, contact details, currency, and payment terms.",
        "inputSchema": {
            "type": "object",
            "properties": {},
            "additionalProperties": False,
        },
    },
    {
        "name": "get_master_data",
        "description": "Returns fabric procurement master data including active seasons, freight transport modes, delivery terms (Incoterms), and HS customs classification codes.",
        "inputSchema": {
            "type": "object",
            "properties": {},
            "additionalProperties": False,
        },
    },
    {
        "name": "inspect_contract",
        "description": "Retrieves comprehensive read-only details of a Preliminary Contract by contract number (e.g. 'MPG/PC/PDV/26/X/451') or internal ID, including fabric technical specs, delivery schedule splits, and audit event logs.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "identifier": {
                    "type": "string",
                    "description": "Contract number (e.g. 'MPG/PC/PDV/26/X/451') or numerical contract ID."
                }
            },
            "required": ["identifier"],
            "additionalProperties": False,
        },
    },
    {
        "name": "read_guidebook",
        "description": "Reads the official Preliminary Contract Operating & Workflow Guidebook in full Markdown. Specify 'admin' for Buyer/Administrator manual or 'vendor' for Fabric Mill/Supplier manual.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "type": {
                    "type": "string",
                    "enum": ["admin", "vendor"],
                    "description": "Type of guidebook to read: 'admin' (Buyer/Admin SOP) or 'vendor' (Supplier/Mill SOP)."
                }
            },
            "required": ["type"],
            "additionalProperties": False,
        },
    },
]

RESOURCES = [
    {
        "uri": "fabric://guidebook/admin",
        "name": "Admin Preliminary Contract Guidebook",
        "description": "Standard operating procedure for Buyers and Administrators covering creation, auto-calculations, TOTP dual-control approval, and email dispatch.",
        "mimeType": "text/markdown",
    },
    {
        "uri": "fabric://guidebook/vendor",
        "name": "Supplier & Mill Operating Guidebook",
        "description": "Standard operating procedure for Fabric Mills covering capacity reservations, technical reviews, acceptance protocols, and PO transition.",
        "mimeType": "text/markdown",
    },
    {
        "uri": "fabric://architecture/blueprint",
        "name": "Fabric Module Architecture & Blueprint",
        "description": "High-level system design and security blueprint of the Fabric Vendor Portal.",
        "mimeType": "text/markdown",
    },
]


def execute_bridge(action: str, params: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    """Execute the read-only PHP bridge inside the docker container."""
    payload = json.dumps({"action": action, "params": params or {}})
    cmd = [
        "docker", "exec", "-i", "vendor_portal_app",
        "php", BRIDGE_CONTAINER_PATH
    ]
    try:
        proc = subprocess.run(
            cmd,
            input=payload,
            capture_output=True,
            text=True,
            timeout=15,
            check=True
        )
        return json.loads(proc.stdout)
    except subprocess.CalledProcessError as e:
        return {
            "status": "error",
            "error_message": f"Bridge execution error: {e.stderr or e.stdout}"
        }
    except Exception as e:
        # Fallback for offline guidebook reading if container is unreachable
        if action == "read_guidebook":
            target = (params or {}).get("type", "admin").lower()
            doc_file = "vendor_preliminary_contract_guidebook.md" if target == "vendor" else "admin_preliminary_contract_guidebook.md"
            doc_path = os.path.join(PROJECT_ROOT, "docs", doc_file)
            if os.path.exists(doc_path):
                with open(doc_path, "r", encoding="utf-8") as f:
                    return {"status": "success", "data": {"content": f.read()}}
        return {
            "status": "error",
            "error_message": f"Bridge error: {str(e)}"
        }


def handle_request(req: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    method = req.get("method")
    req_id = req.get("id")

    if method == "initialize":
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {
                "protocolVersion": "2024-11-05",
                "serverInfo": {
                    "name": "fabric-vendor-portal-mcp",
                    "version": "1.0.0"
                },
                "capabilities": {
                    "tools": {},
                    "resources": {}
                }
            }
        }

    if method == "notifications/initialized":
        return None

    if method == "ping":
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {}
        }

    if method == "tools/list":
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {
                "tools": TOOLS
            }
        }

    if method == "tools/call":
        params = req.get("params", {})
        tool_name = params.get("name")
        tool_args = params.get("arguments", {})

        result = execute_bridge(tool_name, tool_args)
        formatted_text = json.dumps(result, indent=2, ensure_ascii=False)

        # If reading guidebook, present clean markdown directly
        if tool_name == "read_guidebook" and result.get("status") == "success":
            formatted_text = result.get("data", {}).get("content", formatted_text)

        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {
                "content": [
                    {
                        "type": "text",
                        "text": formatted_text
                    }
                ]
            }
        }

    if method == "resources/list":
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {
                "resources": RESOURCES
            }
        }

    if method == "resources/read":
        params = req.get("params", {})
        uri = params.get("uri", "")

        if uri == "fabric://guidebook/admin":
            res = execute_bridge("read_guidebook", {"type": "admin"})
            content = res.get("data", {}).get("content", "Not found")
        elif uri == "fabric://guidebook/vendor":
            res = execute_bridge("read_guidebook", {"type": "vendor"})
            content = res.get("data", {}).get("content", "Not found")
        elif uri == "fabric://architecture/blueprint":
            res = execute_bridge("get_architecture", {})
            content = json.dumps(res.get("data", {}), indent=2)
        else:
            return {
                "jsonrpc": "2.0",
                "id": req_id,
                "error": {
                    "code": -32602,
                    "message": f"Resource not found: {uri}"
                }
            }

        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {
                "contents": [
                    {
                        "uri": uri,
                        "mimeType": "text/markdown",
                        "text": content
                    }
                ]
            }
        }

    # Unknown method
    if req_id is not None:
        return {
            "jsonrpc": "2.0",
            "id": req_id,
            "error": {
                "code": -32601,
                "message": f"Method '{method}' not implemented."
            }
        }
    return None


def main():
    """Main stdio loop reading JSON-RPC 2.0 lines from stdin."""
    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            req = json.loads(line)
            resp = handle_request(req)
            if resp is not None:
                sys.stdout.write(json.dumps(resp) + "\n")
                sys.stdout.flush()
        except json.JSONDecodeError:
            err = {
                "jsonrpc": "2.0",
                "id": None,
                "error": {
                    "code": -32700,
                    "message": "Parse error: invalid JSON."
                }
            }
            sys.stdout.write(json.dumps(err) + "\n")
            sys.stdout.flush()
        except Exception as e:
            err = {
                "jsonrpc": "2.0",
                "id": None,
                "error": {
                    "code": -32603,
                    "message": f"Internal error: {str(e)}"
                }
            }
            sys.stdout.write(json.dumps(err) + "\n")
            sys.stdout.flush()


if __name__ == "__main__":
    main()
