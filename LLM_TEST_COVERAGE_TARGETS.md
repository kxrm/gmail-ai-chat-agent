# Test Coverage Targets for the AI Chat Agent

This file captures the **minimum coverage expectations** and **edge-case checklist** for the project.  CI pipelines should read these values and fail if they are not met.

---
## Coverage tiers
| Layer / directory | Why it matters | Line coverage | Branch coverage |
|-------------------|---------------|---------------|-----------------|
| **Tier 1 — Business logic**  
`core/`, `commands/` | Deterministic, highest risk of silent logic bugs | **85 – 90 %** | **≥ 80 %** |
| **Tier 2 — Adapters / wiring**  
`services/` | Handles external APIs, can be mocked | **70 – 80 %** | **≈ 70 %** |
| **Tier 3 — Controllers / endpoints**  
`api/`, `public/` | Thin request/response wrappers | **50 – 60 %** | Best-effort |

**Overall gate:**
* CI must fail if **overall line coverage < 75 %** OR **overall branch coverage < 70 %**.

---
## Edge-case checklist (must be explicitly covered)
1. Empty / whitespace-only user input.
2. Excessive input length (> 32 kB).
3. LLM returns invalid JSON (malformed, missing keys, wrong `tool_name`).
4. Tool returns:  
   • very large payloads  
   • non-UTF-8 bytes  
   • network time-outs / 5xx.
5. OAuth token scenarios: expired, missing refresh-token, consent revoked.
6. Session loss or reset mid-conversation.
7. CSRF attempt on `api/ajax_handler.php`.
8. Two concurrent requests (e.g. multiple tabs) mutating the same session.

---
## Suggested CI commands
```bash
vendor/bin/phpunit --coverage-clover build/coverage.xml
phpcov --min-lines=75 --min-branches=70 build/coverage.xml
```

Maintain directory-specific thresholds in your coverage dashboard; only the **overall gate** blocks merges.  Aim higher where practical 🛡️ 