# DPIK Tadbir Tooling & Control Plane

- **Canonical CLI**: [`tools/tadbir.py`](tadbir.py) (Zero-dependency stdlib runner)
- **Package Implementation**: [`tools/tadbir_cli/`](tadbir_cli/)
- **Full Architecture & Research Guide**: [`tools/tadbir_cli/README.md`](tadbir_cli/README.md)

---

## Quick Reference

```bash
# 1. Cold-start status
python tools/tadbir.py status

# 2. Pre-push local quality gates
python tools/tadbir.py gate

# 3. PR triage
python tools/tadbir.py pr [PR_NUMBER]

# 4. CI check
python tools/tadbir.py ci-wait

# 5. Test triage
python tools/tadbir.py test-triage

# 6. Register + verify snip output filters (first checkout, or after editing .snip/filters/)
python tools/tadbir.py snip-setup
```

`gate` / `test-triage` parse tool output into JSON metrics directly. The
`.snip/filters/*.yaml` compress raw `pest` / `phpstan` / `pint` / `gh run` /
`php artisan migrate` output when those are run through snip's PreToolUse hook.
`gate` is the fast subset — `composer check:full` is the authoritative pre-merge gate.

See [`tools/tadbir_cli/README.md`](tadbir_cli/README.md) for full operational documentation, problem background, and research papers.
