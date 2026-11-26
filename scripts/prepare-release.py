"""Build release notes and an installable ZIP from the current commit."""

import argparse
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PACKAGE_PATHS = ("Plugin.php", "Assets", "Controller", "Job", "Locale", "Model", "Schema", "Template")


def git(*args):
    return subprocess.check_output(["git", *args], cwd=ROOT, text=True)


def prepare_release(output):
    commit = git("rev-parse", "HEAD").strip()
    plugin = git("show", f"{commit}:Plugin.php")
    version = re.search(
        r"\bfunction\s+getPluginVersion\(\)\s*\{\s*return\s+'((?:0|[1-9][0-9]*)"
        r"\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*))';\s*\}",
        plugin,
    )
    if not version:
        raise ValueError("Plugin.php must return a stable X.Y.Z from getPluginVersion().")
    version = version[1]

    changelog = git("show", f"{commit}:CHANGELOG.md")
    sections = re.split(r"^##[ \t]+", changelog, flags=re.MULTILINE)[1:]
    header = re.compile(rf"\[{re.escape(version)}\](?: - [0-9]{{4}}-[0-9]{{2}}-[0-9]{{2}})?")
    notes = []
    for section in sections:
        heading, _, body = section.partition("\n")
        if header.fullmatch(heading.strip()):
            notes.append(body.strip())
    if len(notes) != 1 or not notes[0]:
        raise ValueError(f"CHANGELOG.md must contain exactly one nonempty section for [{version}].")

    output.mkdir(parents=True, exist_ok=True)
    subprocess.run(
        [
            "git", "archive", "--format=zip", "--prefix=Notifications/",
            f"--output={output / f'Notifications-{version}.zip'}",
            commit, "--", *PACKAGE_PATHS,
        ],
        cwd=ROOT,
        check=True,
    )
    (output / "release-notes.md").write_text(notes[0] + "\n", encoding="utf-8")
    return version


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("output", type=Path, help="Directory for the ZIP and release notes")
    args = parser.parse_args()
    try:
        print(prepare_release(args.output.resolve()))
    except (ValueError, subprocess.CalledProcessError) as error:
        parser.exit(1, f"{error}\n")
