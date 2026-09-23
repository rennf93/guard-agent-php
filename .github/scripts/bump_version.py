#!/usr/bin/env python3
"""
Version bump helper script for guard-agent-php.

Updates the version string across all files that reference it:
- src/Version.php (single source of truth for the User-Agent and the
  agent_version field on every batch; mirrors guard_agent/_version.py)
- CHANGELOG.md (scaffold for the new version entry)

Note: the Packagist version is derived from the git tag (vX.Y.Z); composer.json
carries no version field. src/Version.php is the reported agent version and
must match the tag.

Usage:
    python .github/scripts/bump_version.py <version>
    make bump-version VERSION=x.y.z

No external dependencies required (stdlib only).
"""

from __future__ import annotations

import re
import sys
from datetime import datetime, timezone
from pathlib import Path

# Resolve project root relative to this script's location
PROJECT_ROOT = Path(__file__).resolve().parent.parent.parent

VERSION_PATTERN = re.compile(r"^\d+\.\d+\.\d+$")


def update_version_module(version: str) -> bool:
    """Update VERSION in src/Version.php (single source of truth)."""
    path = PROJECT_ROOT / "src" / "Version.php"
    if not path.exists():
        print(f"  ERROR: Could not find {path.relative_to(PROJECT_ROOT)}")
        return False
    content = path.read_text()
    pattern = re.compile(r"^(\s*public const VERSION\s*=\s*)'[^']*';", re.MULTILINE)
    match = pattern.search(content)
    if not match:
        print("  ERROR: Could not find VERSION constant in src/Version.php")
        return False
    current = re.search(r"'([^']*)'", match.group(0))
    if current and current.group(1) == version:
        print(f"  src/Version.php: already set to {version}")
        return True
    new_content = pattern.sub(f"{match.group(1)}'{version}';", content)
    path.write_text(new_content)
    print(f"  src/Version.php: updated to {version}")
    return True


def _insert_changelog_scaffold(path: Path, version: str, label: str) -> bool:
    """Insert a version scaffold block into the changelog file."""
    content = path.read_text()
    today = datetime.now(tz=timezone.utc).strftime("%Y-%m-%d")
    header = f"v{version} ({today})"

    # Check if this version already has an entry
    if f"v{version} (" in content:
        print(f"  {label}: v{version} entry already exists")
        return True

    scaffold = (
        f"{header}\n"
        f"-------------------\n"
        f"\n"
        f"TITLE (v{version})\n"
        f"------------\n"
        f"\n"
        f"CONTENT\n"
        f"\n"
        f"___\n"
        f"\n"
    )

    # Find the first existing version entry to insert before it
    version_header_pattern = re.compile(r"^v\d+\.\d+\.\d+ \(", re.MULTILINE)
    match = version_header_pattern.search(content)
    if match:
        insert_pos = match.start()
        new_content = content[:insert_pos] + scaffold + content[insert_pos:]
    else:
        # No existing entries, append at end
        new_content = content.rstrip() + "\n\n" + scaffold

    path.write_text(new_content)
    print(f"  {label}: added v{version} scaffold")
    return True


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: bump_version.py <version>")
        print("  version must be in X.Y.Z format")
        return 1

    version = sys.argv[1]

    if not VERSION_PATTERN.match(version):
        print(f"Error: '{version}' is not a valid version. Expected format: X.Y.Z")
        return 1

    print(f"Bumping version to {version}...\n")

    updaters = [
        ("src/Version.php", update_version_module),
        ("CHANGELOG.md", lambda v, p=PROJECT_ROOT / "CHANGELOG.md": _insert_changelog_scaffold(p, v, "CHANGELOG.md")),
    ]

    all_ok = True
    for name, updater in updaters:
        try:
            if not updater(version):
                print(f"\n  FAILED: {name}")
                all_ok = False
        except Exception as e:
            print(f"\n  ERROR updating {name}: {e}")
            all_ok = False

    print()
    print("Version bump complete." if all_ok else "Version bump completed with errors.")
    return 0 if all_ok else 1


if __name__ == "__main__":
    sys.exit(main())
