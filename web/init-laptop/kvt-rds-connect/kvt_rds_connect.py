#!/usr/bin/env python3
"""KVT RDS Connect — local FreeRDP launcher for KVT RDS (GTK3)."""

from __future__ import annotations

import json
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path

import gi

gi.require_version("Gtk", "3.0")
from gi.repository import Gtk, GLib  # noqa: E402

APP_NAME = "KVT RDS Connect"
DOMAIN = "KVT"
SERVER = "KVT-RDS-CB.KVT.LOCAL"
LOAD_BALANCE = "tsv://MS Terminal Services Plugin.1.KVT_Desktops"
SIZE = "1920x1080"
DEFAULT_USER = "milanscheenloop"

CONFIG_DIR = Path.home() / ".config" / "kvt-rds-connect"
CONFIG_FILE = CONFIG_DIR / "config.json"

LSUSB_RE = re.compile(
    r"ID\s+([0-9a-fA-F]{4}):([0-9a-fA-F]{4})\s+(.*)$"
)

NONE_USB = ("", "None (no USB redirection)")


def load_config() -> dict:
    try:
        data = json.loads(CONFIG_FILE.read_text(encoding="utf-8"))
        if not isinstance(data, dict):
            return {}
        # Never keep password if somehow present
        data.pop("password", None)
        return data
    except (OSError, json.JSONDecodeError):
        return {}


def save_config(username: str, usb_id: str) -> None:
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    payload = {"username": username, "usb_id": usb_id}
    CONFIG_FILE.write_text(
        json.dumps(payload, indent=2) + "\n", encoding="utf-8"
    )


def list_usb_devices() -> list[tuple[str, str]]:
    """Return [(idVendor:idProduct, label), ...] plus None option first."""
    devices: list[tuple[str, str]] = [NONE_USB]
    try:
        out = subprocess.check_output(
            ["lsusb"], text=True, stderr=subprocess.DEVNULL
        )
    except (OSError, subprocess.CalledProcessError):
        return devices

    for line in out.splitlines():
        m = LSUSB_RE.search(line.strip())
        if not m:
            continue
        vid, pid, desc = m.group(1).lower(), m.group(2).lower(), m.group(3).strip()
        usb_id = f"{vid}:{pid}"
        label = f"{usb_id}  {desc}"
        devices.append((usb_id, label))
    return devices


def find_xfreerdp3() -> str | None:
    return shutil.which("xfreerdp3")


class App(Gtk.Window):
    def __init__(self) -> None:
        super().__init__(title=APP_NAME)
        self.set_border_width(12)
        self.set_default_size(520, 220)
        self.set_resizable(False)
        self.connect("destroy", Gtk.main_quit)

        cfg = load_config()
        self._usb_devices = list_usb_devices()

        grid = Gtk.Grid(column_spacing=10, row_spacing=10)
        self.add(grid)

        # Username
        grid.attach(Gtk.Label(label="Username:", xalign=0), 0, 0, 1, 1)
        self.user_entry = Gtk.Entry()
        self.user_entry.set_text(cfg.get("username") or DEFAULT_USER)
        self.user_entry.set_hexpand(True)
        grid.attach(self.user_entry, 1, 0, 2, 1)

        # Password (never saved)
        grid.attach(Gtk.Label(label="Password:", xalign=0), 0, 1, 1, 1)
        self.pass_entry = Gtk.Entry()
        self.pass_entry.set_visibility(False)
        self.pass_entry.set_input_purpose(Gtk.InputPurpose.PASSWORD)
        self.pass_entry.set_hexpand(True)
        self.pass_entry.connect("activate", lambda *_: self.on_connect())
        grid.attach(self.pass_entry, 1, 1, 2, 1)

        # Domain (read-only)
        grid.attach(Gtk.Label(label="Domain:", xalign=0), 0, 2, 1, 1)
        domain_entry = Gtk.Entry()
        domain_entry.set_text(DOMAIN)
        domain_entry.set_editable(False)
        domain_entry.set_sensitive(False)
        grid.attach(domain_entry, 1, 2, 2, 1)

        # USB
        grid.attach(Gtk.Label(label="USB device:", xalign=0), 0, 3, 1, 1)
        self.usb_combo = Gtk.ComboBoxText()
        self.usb_combo.set_hexpand(True)
        self._fill_usb_combo(cfg.get("usb_id", ""))
        grid.attach(self.usb_combo, 1, 3, 1, 1)

        refresh_btn = Gtk.Button(label="Refresh USB")
        refresh_btn.connect("clicked", self.on_refresh_usb)
        grid.attach(refresh_btn, 2, 3, 1, 1)

        # Status
        self.status = Gtk.Label(label="", xalign=0)
        self.status.set_line_wrap(True)
        grid.attach(self.status, 0, 4, 3, 1)

        # Buttons
        btn_box = Gtk.Box(orientation=Gtk.Orientation.HORIZONTAL, spacing=8)
        btn_box.set_halign(Gtk.Align.END)
        connect_btn = Gtk.Button(label="Connect")
        connect_btn.get_style_context().add_class("suggested-action")
        connect_btn.connect("clicked", lambda *_: self.on_connect())
        quit_btn = Gtk.Button(label="Quit")
        quit_btn.connect("clicked", lambda *_: self.destroy())
        btn_box.pack_end(connect_btn, False, False, 0)
        btn_box.pack_end(quit_btn, False, False, 0)
        grid.attach(btn_box, 0, 5, 3, 1)

        xfp = find_xfreerdp3()
        if not xfp:
            self.status.set_text(
                "xfreerdp3 not found. Install with: sudo pacman -S --needed freerdp"
            )
        else:
            self.status.set_text(f"Ready ({xfp})")

        self.show_all()
        GLib.idle_add(self.pass_entry.grab_focus)

    def _fill_usb_combo(self, preferred_id: str) -> None:
        self.usb_combo.remove_all()
        active = 0
        for i, (usb_id, label) in enumerate(self._usb_devices):
            self.usb_combo.append(usb_id, label)
            if preferred_id and usb_id == preferred_id:
                active = i
        self.usb_combo.set_active(active)

    def on_refresh_usb(self, *_args) -> None:
        current = self.usb_combo.get_active_id() or ""
        self._usb_devices = list_usb_devices()
        self._fill_usb_combo(current)
        n = max(0, len(self._usb_devices) - 1)
        self.status.set_text(f"USB list refreshed ({n} device(s))")

    def on_connect(self) -> None:
        xfp = find_xfreerdp3()
        if not xfp:
            self._error(
                "xfreerdp3 is missing.\n"
                "Install with:\n  sudo pacman -S --needed freerdp"
            )
            return

        username = self.user_entry.get_text().strip()
        password = self.pass_entry.get_text()
        usb_id = self.usb_combo.get_active_id() or ""

        if not username:
            self._error("Username is required.")
            return
        if not password:
            self._error("Password is required.")
            return

        save_config(username, usb_id)

        argv = [
            xfp,
            f"/v:{SERVER}",
            f"/u:{username}",
            f"/d:{DOMAIN}",
            "/auth-pkg-list:!kerberos",
            "/cert:ignore",
            f"/load-balance-info:{LOAD_BALANCE}",
            f"/size:{SIZE}",
            f"/p:{password}",
        ]
        if usb_id:
            # FreeRDP expects /usb:id:vvvv:pppp
            vid, pid = usb_id.split(":", 1)
            argv.append(f"/usb:id:{vid}:{pid}")

        self.status.set_text("Starting FreeRDP…")
        try:
            # Detach so the GUI can close or stay; do not wait.
            subprocess.Popen(
                argv,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                start_new_session=True,
            )
        except OSError as exc:
            self._error(f"Failed to start xfreerdp3:\n{exc}")
            return

        self.status.set_text("FreeRDP launched.")
        # Clear password from memory/UI after launch
        self.pass_entry.set_text("")

    def _error(self, message: str) -> None:
        self.status.set_text(message)
        dialog = Gtk.MessageDialog(
            transient_for=self,
            flags=0,
            message_type=Gtk.MessageType.ERROR,
            buttons=Gtk.ButtonsType.OK,
            text=message,
        )
        dialog.run()
        dialog.destroy()


def main() -> int:
    if len(sys.argv) > 1 and sys.argv[1] in ("--check", "--self-test"):
        devices = list_usb_devices()
        print(f"xfreerdp3: {find_xfreerdp3() or 'MISSING'}")
        print(f"usb_count: {max(0, len(devices) - 1)}")
        for usb_id, label in devices:
            print(f"  {label}")
        # Syntax/import already succeeded if we got here
        print("import_ok: True")
        has_mouse = any(d[0] == "046d:c092" for d in devices)
        print(f"has_046d_c092: {has_mouse}")
        return 0 if find_xfreerdp3() else 1

    App()
    Gtk.main()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
