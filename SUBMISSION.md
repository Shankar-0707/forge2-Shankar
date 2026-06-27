# Submission checklist -- Forge 2 / Edition 1 (PulseDesk)

Tick each and point to the in-repo path. Everything must be committed in THIS repo.

- [ ] Repo is public, named forge2-<myname>
- [ ] README has exact run steps; `php artisan migrate --seed` works from a fresh clone
- [ ] Backend = Laravel 11 + MySQL ; Frontend = React 19 + Vite + Tailwind
- [ ] Multi-tenancy: Org A cannot see Org B data (tenant derived from auth session)
- [ ] Hermes config committed -> agents/hermes/hermes-config.yaml (secrets redacted)
- [ ] OpenClaw config committed -> agents/openclaw/openclaw.json (secrets redacted)
- [ ] agent-log.md shows the real human->Hermes->OpenClaw loop
- [ ] sprints/ has >= 2 sprint docs
- [ ] Slack proof in slack-export/ (export) or slack-export/screenshots/ (per channel)
- [ ] App / agents-running / CI screenshots in evidence/screenshots/
- [ ] .github/workflows/ci.yml present + a green run on the Actions tab
- [ ] PRs merged by ME (human); commit authors are the agents
- [ ] All model calls went through EastRouter
- [ ] Models used: <list>     Sprints run: <n>
