---
name: deploy-check
description: Verify that the latest push to main deployed successfully to production (banrukrot.com) — checks GitHub Actions CI status, VPS container health, and a production smoke test. Use after pushing to main, or when asked to check deploy/production status.
---

# Deploy Check

Production runs on a VPS behind Nginx Proxy Manager with a self-hosted Docker
registry; CI/CD is GitHub Actions (`.github/workflows/deploy.yml`) triggered on
push to `main`. See `plan.md` for the full architecture and history, and
`deploy-secrets.local.md` (not committed) for credentials this skill doesn't
need to know directly (SSH key is already configured for `deploy@103.107.53.112`).

Run these steps in order and report a short pass/fail summary at the end —
don't just dump raw command output.

## 1. Check the GitHub Actions run

There is no `gh` CLI installed locally — use the GitHub REST API instead:

```bash
curl -s "https://api.github.com/repos/Korarak/-loei-banrukrot/actions/runs?per_page=1" \
  | grep -E '"status"|"conclusion"|"html_url"|"created_at"' | head -5
```

- `status` should be `completed` and `conclusion` should be `success`.
- If it's still `in_progress`, CI typically takes ~3-5 minutes — wait and re-check
  rather than assuming failure.
- If `conclusion` is `failure`, stop here and report which job failed (open the
  `html_url` for details) instead of proceeding to the VPS checks.

## 2. Check container health on the VPS

```bash
ssh deploy@103.107.53.112 "docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.RunningFor}}'"
```

- Confirm `frontend`, `backend`, `mongodb`, `npm` (Nginx Proxy Manager), and
  `registry` are all `Up` and were recreated recently (`RunningFor` should be
  minutes, not days, right after a deploy).
- If a container is missing, restarting in a loop, or `Exited`, pull its logs:

```bash
ssh deploy@103.107.53.112 "docker logs --tail 50 <container-name>"
```

Optionally check resource usage isn't near the configured limits (per
`plan.md`, NPM previously OOM-killed at 256M and was raised to 512M):

```bash
ssh deploy@103.107.53.112 "docker stats --no-stream"
```

## 3. Smoke test the live site

```bash
curl -s https://banrukrot.com/api/status
```

Expect `{"status":"Server Running","database":"Connected"}`. Also worth a
quick check that the homepage itself returns 200:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://banrukrot.com
```

## 4. Report

Summarize in a few lines: CI result, container status, smoke test result. If
everything passed, say so plainly — don't pad it. If something failed, name
the specific failing piece and the next diagnostic step (e.g. "backend
container is restarting — check `docker logs backend` for a crash on boot").
