#!/usr/bin/env python3
"""
Testcom publisher: one command sends local work to GitHub and to Timeweb FTP.

Usage:
    python3 deploy.py "commit message"            # commit + git push + FTP upload
    python3 deploy.py --dry-run                   # show what would happen, change nothing
    python3 deploy.py "msg" --no-git              # FTP only
    python3 deploy.py --no-ftp "msg"              # commit + push only
    python3 deploy.py --force "msg"               # re-upload every file
    python3 deploy.py "msg" --skip=auth.php       # leave a file out of both the commit and the upload

Rules:
  * Never deletes anything on the server.
  * Before overwriting a remote file, its current copy is saved to .deploy-backups/.
  * FTP credentials come from the environment or the untracked .deploy.env file
    (FTP_HOST, FTP_USER, FTP_PASS, FTP_REMOTE_DIR). They are never printed or committed.
"""

import fnmatch
import ftplib
import hashlib
import json
import os
import subprocess
import sys
import time
from datetime import datetime

LOCAL_DIR = os.path.dirname(os.path.abspath(__file__))
STATE_FILE = os.path.join(LOCAL_DIR, ".deploystate")
BACKUP_ROOT = os.path.join(LOCAL_DIR, ".deploy-backups")

# Not uploaded to the server (junk, local tooling, secrets).
EXCLUDE_DIRS = {".git", ".claude", "content", "dev", "__MACOSX", ".deploy-backups", "node_modules", ".idea", ".vscode"}
EXCLUDE_FILES = [
    ".DS_Store", ".gitignore", ".deploy.env", ".deploystate",
    "deploy.py", "build_weekly.py", "README.md", "index.html", "gamesblock.png", "*.previous", "*.backup", "*.liquid", "*.zip", "*.log",
    "*.v[0-9]*",
]


SKIP = set()   # relative paths given with --skip=...; left out of the commit and the upload


def log(msg=""):
    print(msg, flush=True)


def load_env():
    env = dict(os.environ)
    path = os.path.join(LOCAL_DIR, ".deploy.env")
    if os.path.isfile(path):
        with open(path, encoding="utf-8") as fh:
            for line in fh:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    env.setdefault(k.strip(), v.strip())
    return env


def should_skip(rel):
    parts = rel.split("/")
    if any(p in EXCLUDE_DIRS for p in parts[:-1]):
        return True
    return any(fnmatch.fnmatch(parts[-1], pat) for pat in EXCLUDE_FILES)


def collect_files():
    out = []
    for root, dirs, files in os.walk(LOCAL_DIR):
        dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
        for fn in files:
            rel = os.path.relpath(os.path.join(root, fn), LOCAL_DIR).replace(os.sep, "/")
            if not should_skip(rel) and rel not in SKIP:
                out.append(rel)
    # *.local.php first, so auth.php never goes live before the file it reads.
    return sorted(out, key=lambda r: (not r.endswith('.local.php'), r))


def md5(path):
    h = hashlib.md5()
    with open(path, "rb") as fh:
        for chunk in iter(lambda: fh.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def git(*args, check=True):
    return subprocess.run(["git", *args], cwd=LOCAL_DIR, check=check,
                          capture_output=True, text=True)


def publish_git(message, dry_run):
    if not os.path.isdir(os.path.join(LOCAL_DIR, ".git")):
        log("git: no repository here, skipping.")
        return True
    status = git("status", "--porcelain").stdout.strip()
    if status:
        log("git: changes to commit:")
        log("\n".join("   " + l for l in status.splitlines()))
        if dry_run:
            log("git: DRY RUN - not committing.")
        else:
            if not message:
                log("git: there are changes but no commit message was given.")
                return False
            git("add", "-A", "--", ".", *[":(exclude)" + name for name in sorted(SKIP)])
            git("commit", "-m", message)
            log("git: committed.")
    else:
        log("git: nothing new to commit.")

    remotes = git("remote").stdout.split()
    if "origin" not in remotes:
        log("git: no 'origin' remote configured yet - skipping push.")
        return True
    if dry_run:
        log("git: DRY RUN - not pushing.")
        return True
    res = git("push", "-u", "origin", "HEAD", check=False)
    if res.returncode != 0:
        log("git: push FAILED:\n" + (res.stderr or res.stdout))
        return False
    log("git: pushed to origin.")
    return True


def ensure_remote_dir(ftp, root, rel_dir, made):
    if not rel_dir or rel_dir in made:
        return
    ensure_remote_dir(ftp, root, os.path.dirname(rel_dir), made)
    try:
        ftp.mkd(root + "/" + rel_dir)
        log("  mkdir " + rel_dir + "/")
    except ftplib.error_perm as e:
        if not str(e).startswith(("550", "521")):
            raise
    made.add(rel_dir)


def remote_size(ftp, path):
    try:
        return ftp.size(path)
    except ftplib.all_errors:
        return None


def publish_ftp(env, dry_run, force):
    host = env.get("FTP_HOST")
    user = env.get("FTP_USER")
    password = env.get("FTP_PASS")
    root = env.get("FTP_REMOTE_DIR", "/public_html/quiz-iitu.mcm2601").rstrip("/")
    if not (host and user and password):
        log("ftp: FTP_HOST / FTP_USER / FTP_PASS missing (.deploy.env) - skipping.")
        return False

    state = {}
    if os.path.isfile(STATE_FILE):
        try:
            with open(STATE_FILE, encoding="utf-8") as fh:
                state = json.load(fh)
        except (OSError, ValueError):
            state = {}

    files = collect_files()
    log("ftp: %d files in scope -> ftp://%s%s" % (len(files), host, root))

    ftp = ftplib.FTP(timeout=60)
    ftp.connect(host, 21)
    ftp.login(user, password)
    ftp.set_pasv(True)

    backup_dir = os.path.join(BACKUP_ROOT, datetime.now().strftime("%Y%m%d-%H%M%S"))
    made, uploaded, skipped, failed = set(), 0, 0, 0
    t0 = time.time()
    try:
        for rel in files:
            full = os.path.join(LOCAL_DIR, rel)
            digest = md5(full)
            rpath = root + "/" + rel
            rsize = remote_size(ftp, rpath)
            same = rsize is not None and rsize == os.path.getsize(full) and \
                (state.get(rel) == digest or rel not in state)
            if same and not force:
                state[rel] = digest
                skipped += 1
                continue
            if dry_run:
                log("  WOULD UPLOAD " + rel + ("  (replaces remote)" if rsize is not None else "  (new)"))
                uploaded += 1
                continue
            try:
                if rsize is not None:
                    dest = os.path.join(backup_dir, rel)
                    os.makedirs(os.path.dirname(dest), exist_ok=True)
                    with open(dest, "wb") as fh:
                        ftp.retrbinary("RETR " + rpath, fh.write)
                ensure_remote_dir(ftp, root, os.path.dirname(rel), made)
                with open(full, "rb") as fh:
                    ftp.storbinary("STOR " + rpath, fh, 32768)
                state[rel] = digest
                uploaded += 1
                log("  uploaded %s (%.1f KB)" % (rel, os.path.getsize(full) / 1024))
            except Exception as e:  # noqa: BLE001
                failed += 1
                log("  !! FAILED %s: %s" % (rel, e))
    finally:
        try:
            ftp.quit()
        except ftplib.all_errors:
            ftp.close()

    if not dry_run:
        with open(STATE_FILE, "w", encoding="utf-8") as fh:
            json.dump(state, fh, indent=1, sort_keys=True)
    log("ftp: done in %.1fs - uploaded %d, unchanged %d, failed %d." % (time.time() - t0, uploaded, skipped, failed))
    if uploaded and not dry_run and os.path.isdir(backup_dir):
        log("ftp: previous remote copies saved in " + os.path.relpath(backup_dir, LOCAL_DIR))
    return failed == 0


def main():
    args = sys.argv[1:]
    flags = {a for a in args if a.startswith("--")}
    SKIP.update(a.split("=", 1)[1] for a in args if a.startswith("--skip=") and "=" in a)
    message = " ".join(a for a in args if not a.startswith("--")).strip()
    dry_run = "--dry-run" in flags

    log("Testcom deploy  %s%s" % (datetime.now().strftime("%Y-%m-%d %H:%M:%S"), "  [DRY RUN]" if dry_run else ""))
    ok = True
    builder = os.path.join(LOCAL_DIR, "build_weekly.py")
    if os.path.isfile(builder):
        built = subprocess.run([sys.executable, builder], cwd=LOCAL_DIR, capture_output=True, text=True)
        log("weekly: " + (built.stdout or built.stderr).strip())
        if built.returncode != 0:
            log("Finished WITH PROBLEMS (weekly-tests.js was not built, nothing was published).")
            return 1
    if "--no-git" not in flags:
        ok = publish_git(message, dry_run) and ok
    if "--no-ftp" not in flags:
        ok = publish_ftp(load_env(), dry_run, "--force" in flags) and ok
    log("Finished." if ok else "Finished WITH PROBLEMS.")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
