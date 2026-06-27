import os, json, requests
from dotenv import load_dotenv
from slack_bolt import App
from slack_bolt.adapter.socket_mode import SocketModeHandler

load_dotenv()

app = App(token=os.environ["SLACK_BOT_TOKEN_HERMES"])

# ── memory ──────────────────────────────────────────────
memory = {"sprint_goal": "", "tasks": [], "current_task_index": 0}

def save_memory():
    os.makedirs("agents/hermes", exist_ok=True)
    with open("agents/hermes/memory.json", "w") as f:
        json.dump(memory, f, indent=2)

# ── EastRouter call (DeepSeek for planning) ─────────────
def call_deepseek(system, user):
    r = requests.post(
        "https://api.eastrouter.com/v1/chat/completions",
        headers={"Authorization": f"Bearer {os.environ['EASTROUTER_API_KEY']}",
                 "Content-Type": "application/json"},
        json={"model": "deepseek/deepseek-v4-pro",
              "messages": [{"role": "system", "content": system},
                           {"role": "user",   "content": user}]}
    )
    return r.json()["choices"][0]["message"]["content"]

# ── resolve channel name from ID ─────────────────────────
def get_channel_name(client, channel_id):
    try:
        return client.conversations_info(channel=channel_id)["channel"]["name"]
    except:
        return ""

# ── assign next task ─────────────────────────────────────
def assign_next_task(client):
    idx = memory["current_task_index"]
    tasks = memory["tasks"]

    if idx >= len(tasks):
        client.chat_postMessage(
            channel="#sprint-main",
            text="🎉 *Sprint complete!* All tasks done. Post your next sprint goal when ready."
        )
        return

    task = tasks[idx]
    msg = f"""📋 *Task #{task['id']} — assigned to OpenClaw*

*What to build:* {task['title']}
*Details:* {task['description']}
*Files to touch:* {', '.join(task.get('files', ['TBD']))}

When done, post to #agent-log with your report and include the word DONE."""

    client.chat_postMessage(channel="#agent-coder", text=msg)
    print(f"[Hermes] ✅ Assigned task {idx + 1}: {task['title']}")

# ── listen to all messages ───────────────────────────────
@app.event("message")
def handle_message(event, client):
    channel_id = event.get("channel", "")
    text       = event.get("text", "")
    bot_id     = event.get("bot_id")
    subtype    = event.get("subtype")

    # ignore edits and deletes
    if subtype in ("message_changed", "message_deleted"):
        return

    channel_name = get_channel_name(client, channel_id)

    # ── YOU posted a sprint goal in #sprint-main ──────────
    if channel_name == "sprint-main" and not bot_id:
        print(f"[Hermes] 📥 Sprint goal received: {text[:80]}")
        memory["sprint_goal"] = text
        memory["current_task_index"] = 0

        client.chat_postMessage(
            channel="#sprint-main",
            text=f"📝 Got it! Planning sprint for: _{text[:120]}_\nBreaking into tasks..."
        )

        plan_json = call_deepseek(
            system="""You are Hermes, sprint planner for PulseDesk (Laravel 11 + React 19 SaaS).
Break the sprint goal into 3-5 small coding tasks. Return ONLY a JSON array, no extra text:
[
  {
    "id": 1,
    "title": "Short task title",
    "description": "Exact implementation details. Be specific about files, methods, DB columns.",
    "files": ["app/Models/Organization.php", "database/migrations/xxx.php"]
  }
]
Keep each task completable in under 30 min. Always remind: scope queries by organization_id from auth user.""",
            user=f"Sprint goal: {text}"
        )

        # strip markdown fences if model adds them
        clean = plan_json.strip().lstrip("```json").lstrip("```").rstrip("```").strip()
        try:
            tasks = json.loads(clean)
            memory["tasks"] = tasks
            save_memory()

            task_list = "\n".join([f"{i+1}. {t['title']}" for i, t in enumerate(tasks)])
            client.chat_postMessage(
                channel="#sprint-main",
                text=f"✅ *Sprint Plan*\n\n{task_list}\n\nAssigning Task 1 to OpenClaw now →"
            )
            assign_next_task(client)

        except json.JSONDecodeError:
            client.chat_postMessage(
                channel="#sprint-main",
                text=f"⚠️ Could not parse plan. Raw output:\n```{plan_json[:600]}```"
            )

    # ── OpenClaw posted DONE in #agent-log ───────────────
    elif channel_name == "agent-log" and bot_id and "DONE" in text.upper():
        print(f"[Hermes] 🔔 OpenClaw reported DONE. Moving to next task.")
        memory["current_task_index"] += 1
        save_memory()
        assign_next_task(client)

if __name__ == "__main__":
    print("🟢 Hermes online — listening on Socket Mode...")
    SocketModeHandler(app, os.environ["SLACK_APP_TOKEN_HERMES"]).start()