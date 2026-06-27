import os
import json
import re
import requests
from dotenv import load_dotenv
from slack_bolt import App
from slack_bolt.adapter.socket_mode import SocketModeHandler

load_dotenv()

app = App(token=os.environ["SLACK_BOT_TOKEN_HERMES"])

# --------------------------------------------------
# Memory
# --------------------------------------------------

memory = {
    "sprint_goal": "",
    "tasks": [],
    "current_task_index": 0
}

processed_ts = set()


def save_memory():
    os.makedirs("agents/hermes", exist_ok=True)

    with open("agents/hermes/memory.json", "w") as f:
        json.dump(memory, f, indent=2)


# --------------------------------------------------
# EastRouter
# --------------------------------------------------

def call_llm(system, user):
    r = requests.post(
        "https://api.eastrouter.com/v1/chat/completions",
        headers={
            "Authorization": f"Bearer {os.environ['EASTROUTER_API_KEY']}",
            "Content-Type": "application/json"
        },
        json={
            "model": "z-ai/glm-5.1",
            "messages": [
                {"role": "system", "content": system},
                {"role": "user", "content": user}
            ]
        },
        timeout=120
    )

    print("=" * 70)
    print("[EastRouter] STATUS :", r.status_code)
    print("[EastRouter] RESPONSE :")
    print(r.text[:2000])
    print("=" * 70)

    data = r.json()

    if "choices" not in data:
        raise Exception(data)

    return data["choices"][0]["message"]["content"]


# --------------------------------------------------
# Slack helpers
# --------------------------------------------------

def get_channel_name(client, channel_id):
    try:
        return client.conversations_info(
            channel=channel_id
        )["channel"]["name"]
    except Exception:
        return ""


# --------------------------------------------------
# Assign task
# --------------------------------------------------

def assign_next_task(client):

    idx = memory["current_task_index"]
    tasks = memory["tasks"]

    if idx >= len(tasks):

        client.chat_postMessage(
            channel="#sprint-main",
            text="🎉 Sprint Complete!"
        )
        return

    task = tasks[idx]

    msg = f"""📋 *Task #{task['id']} — assigned to OpenClaw*

*What to build:* {task['title']}

*Details:* {task['description']}

*Files to touch:* {", ".join(task.get("files", ["TBD"]))}

When done, post to #agent-log and include the word DONE.
"""

    client.chat_postMessage(
        channel="#agent-coder",
        text=msg
    )

    print(f"[Hermes] Assigned Task {idx+1}")


# --------------------------------------------------
# Events
# --------------------------------------------------

@app.event("message")
def handle_message(event, client):

    ts = event.get("ts", "")

    if ts in processed_ts:
        return

    processed_ts.add(ts)

    channel_id = event.get("channel", "")
    text = event.get("text", "")
    bot_id = event.get("bot_id")
    subtype = event.get("subtype")

    if subtype in ("message_changed", "message_deleted"):
        return

    channel_name = get_channel_name(client, channel_id)

    # ------------------------------------------
    # Sprint Goal
    # ------------------------------------------

    if channel_name == "sprint-main" and not bot_id:

        text = re.sub(r"<@[A-Z0-9]+>", "", text).strip()

        print("[Hermes] Sprint Goal Received")
        print(text)

        memory["sprint_goal"] = text
        memory["current_task_index"] = 0

        client.chat_postMessage(
            channel="#sprint-main",
            text=f"📝 Planning sprint...\n\n{text[:120]}"
        )

        plan_json = call_llm(

            system="""
You are Hermes.

Break the sprint goal into 3-5 coding tasks.

Return ONLY valid JSON.

Example:

[
 {
   "id":1,
   "title":"Organization Model",
   "description":"Create model and migration",
   "files":[
      "app/Models/Organization.php",
      "database/migrations/xxxx.php"
   ]
 }
]

DO NOT write markdown.
DO NOT explain.
ONLY JSON.
""",

            user=text

        )

        clean = re.sub(
            r"^```json\s*|^```\s*|\s*```$",
            "",
            plan_json.strip(),
            flags=re.MULTILINE
        ).strip()

        print("------------ CLEAN OUTPUT ------------")
        print(clean)
        print("--------------------------------------")

        try:

            tasks = json.loads(clean)

            memory["tasks"] = tasks

            save_memory()

            task_list = "\n".join(
                [
                    f"{i+1}. {t['title']}"
                    for i, t in enumerate(tasks)
                ]
            )

            client.chat_postMessage(
                channel="#sprint-main",
                text=f"""✅ *Sprint Plan*

{task_list}

Assigning Task 1...
"""
            )

            assign_next_task(client)

        except Exception as e:

            print("JSON ERROR")
            print(e)

            print(clean)

            client.chat_postMessage(
                channel="#sprint-main",
                text=f"""⚠️ Parse Error

{e}

```{clean[:700]}```
"""
            )

    # ------------------------------------------
    # DONE
    # ------------------------------------------

    elif (
        channel_name == "agent-log"
        and bot_id
        and "DONE" in text.upper()
    ):

        print("[Hermes] OpenClaw Finished")

        memory["current_task_index"] += 1

        save_memory()

        assign_next_task(client)


# --------------------------------------------------

if __name__ == "__main__":

    print("🟢 Hermes Online")

    SocketModeHandler(
        app,
        os.environ["SLACK_APP_TOKEN_HERMES"]
    ).start()