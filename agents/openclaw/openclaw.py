import os, re, json, subprocess, requests
from dotenv import load_dotenv
from slack_bolt import App
from slack_bolt.adapter.socket_mode import SocketModeHandler

processed_ts = set()
load_dotenv()

app = App(token=os.environ["SLACK_BOT_TOKEN_OPENCLAW"])

WORKING_DIR = os.path.abspath("./backend")
REPO        = os.environ["GITHUB_REPO"]
GH_TOKEN    = os.environ["GITHUB_TOKEN"]

# ── EastRouter call (GLM-5.1 for coding) ─────────────────
def call_glm(system, user):
    r = requests.post(
        "https://api.eastrouter.com/v1/chat/completions",
        headers={"Authorization": f"Bearer {os.environ['EASTROUTER_API_KEY']}",
                 "Content-Type": "application/json"},
        json={"model": "z-ai/glm-5.1",
              "messages": [{"role": "system", "content": system},
                           {"role": "user",   "content": user}]}
    )
    resp = r.json()
    print(f"[OpenClaw] EastRouter model used: {resp.get('model', 'z-ai/glm-5.1')}")
    return resp["choices"][0]["message"]["content"]

# ── run shell command ─────────────────────────────────────
def run(cmd, cwd=None):
    result = subprocess.run(
        cmd, shell=True, capture_output=True, text=True,
        cwd=cwd or os.path.dirname(WORKING_DIR)
    )
    return (result.stdout + result.stderr).strip()

# ── parse FILE: blocks from LLM output ───────────────────
# Expected format:
# FILE: path/to/file.php
# ```php
# ...code...
# ```
def parse_and_write_files(llm_output):
    pattern = r'FILE:\s*(\S+)\s*```(?:\w+)?\n(.*?)```'
    matches = re.findall(pattern, llm_output, re.DOTALL)
    written = []
    for filepath, content in matches:
        filepath = filepath.strip().strip("*`").strip()
        full_path = os.path.join(WORKING_DIR, filepath)
        os.makedirs(os.path.dirname(full_path), exist_ok=True)
        with open(full_path, "w", encoding="utf-8") as f:
            f.write(content.strip() + "\n")
        written.append(filepath)
        print(f"[OpenClaw] 📝 Wrote: {filepath}")
    return written

# ── open a GitHub PR ──────────────────────────────────────
def open_pr(branch, title, body):
    r = requests.post(
        f"https://api.github.com/repos/{REPO}/pulls",
        headers={"Authorization": f"token {GH_TOKEN}",
                 "Accept": "application/vnd.github.v3+json"},
        json={"title": title, "body": body, "head": branch, "base": "main"}
    )
    data = r.json()
    return data.get("html_url", f"PR error: {data.get('message','unknown')}")

# ── resolve channel name ──────────────────────────────────
def get_channel_name(client, channel_id):
    try:
        return client.conversations_info(channel=channel_id)["channel"]["name"]
    except:
        return ""

# ── listen for Hermes task in #agent-coder ───────────────
@app.event("message")
def handle_message(event, client):
    channel_id = event.get("channel", "")
    text       = event.get("text", "")
    bot_id     = event.get("bot_id")
    subtype    = event.get("subtype")

    if subtype in ("message_changed", "message_deleted"):
        return

    channel_name = get_channel_name(client, channel_id)

    # Only act on Hermes messages in #agent-coder
    if channel_name != "agent-coder" or not bot_id:
        return
    if "assigned to OpenClaw" not in text:
        return

    print(f"[OpenClaw] 📥 Task received from Hermes")

    client.chat_postMessage(
        channel="#agent-log",
        text="🔄 *OpenClaw* — picked up task, generating code via EastRouter (GLM-5.1)..."
    )

    # ── Step 1: generate code ─────────────────────────────
    code_output = call_glm(
        system="""You are OpenClaw, an expert Laravel 11 + React 19 developer building PulseDesk.

CRITICAL RULES:
1. Always scope DB queries by: $org = auth()->user()->organization_id
2. Never trust organization_id from request input — always from auth user
3. Use Laravel Sanctum for auth
4. Write Pest tests for every API endpoint you create
5. Follow Laravel 11 conventions (no legacy patterns)

OUTPUT FORMAT — use exactly this for every file:
FILE: relative/path/from/backend/folder.php
```php
<?php
// complete file content here
```

FILE: another/file.php
```php
// complete content
```

Always output complete files, never partial snippets.""",
        user=f"Implement this for PulseDesk:\n\n{text}"
    )

    # ── Step 2: write files to disk ───────────────────────
    written = parse_and_write_files(code_output)

    if not written:
        client.chat_postMessage(
            channel="#agent-log",
            text=f"⚠️ *OpenClaw* — could not parse file output. Check format.\n```{code_output[:400]}```"
        )
        return

    # ── Step 3: git branch + commit ───────────────────────
    import random, string
    suffix = ''.join(random.choices(string.ascii_lowercase, k=6))
    branch = f"openclaw/{suffix}"

    repo_root = os.path.dirname(WORKING_DIR)
    run("git fetch origin", cwd=repo_root)
    run("git checkout main && git pull origin main", cwd=repo_root)
    run(f"git checkout -b {branch}", cwd=repo_root)
    run("git add -A", cwd=repo_root)

    task_title = re.search(r'\*What to build:\*\s*(.+)', text)
    commit_title = task_title.group(1).strip()[:72] if task_title else "feat: openclaw task"
    run(f'git commit -m "feat: {commit_title} [OpenClaw]"', cwd=repo_root)
    run(f"git push origin {branch}", cwd=repo_root)

    # ── Step 4: run tests ─────────────────────────────────
    test_out = run("php artisan test --no-ansi 2>&1", cwd=WORKING_DIR)
    passed = "PASSED" in test_out or "Tests:" in test_out

    # ── Step 5: open PR ───────────────────────────────────
    pr_body = f"""## OpenClaw PR

**Task:** {commit_title}

**Files changed:**
{chr(10).join(f'- `{f}`' for f in written)}

**Tests:** {'✅ Passing' if passed else '⚠️ Check logs'}
"""
    pr_url = open_pr(branch, f"feat: {commit_title}", pr_body)

    # ── Step 6: post CI results ───────────────────────────
    client.chat_postMessage(
        channel="#ci-cd",
        text=f"🔧 *CI — {branch}*\n```{test_out[-600:]}```\nPR: {pr_url}"
    )

    # ── Step 7: post DONE report (triggers Hermes) ────────
    status_emoji = "✅" if passed else "⚠️"
    report = f"""📝 *OpenClaw Report*

✅ *What I Did:*
Implemented: {commit_title}
Files written: {', '.join(written)}
{status_emoji} Tests: {'passed' if passed else 'some failures — check #ci-cd'}

🔄 *What's Left:*
Waiting for next task from Hermes.

❓ *Needs Your Call:*
Please review and merge PR: {pr_url}

DONE"""

    client.chat_postMessage(channel="#agent-log", text=report)
    client.chat_postMessage(
        channel="#human-review",
        text=f"🔍 *Release candidate ready for review*\nTask: {commit_title}\nPR: {pr_url}\n\nReview and merge when approved ✓"
    )
    print(f"[OpenClaw] ✅ Done. PR: {pr_url}")

if __name__ == "__main__":
    print("🟢 OpenClaw online — listening on Socket Mode...")
    SocketModeHandler(app, os.environ["SLACK_APP_TOKEN_OPENCLAW"]).start()