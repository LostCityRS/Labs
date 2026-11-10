#!/usr/bin/env bash
set -euo pipefail
export PATH=/usr/bin:/bin
trap 'echo "Labs deployment failed at line $LINENO" >&2' ERR
fail() { echo "$*" >&2; exit 1; }

# The SSH key must not grant an interactive shell or accept arbitrary commands.
[[ "${SSH_ORIGINAL_COMMAND:-}" =~ ^deploy\ ([0-9a-f]{40})$ ]] || fail 'Expected: deploy <40-character commit SHA>'
sha=${BASH_REMATCH[1]}
exec 9>"$HOME/.cache/labs-deploy.lock"
flock -w 120 9
cd "$HOME/Projects/Labs"
[[ -z "$(git status --porcelain)" ]] || fail 'Server checkout has local changes; refusing to overwrite them.'
git fetch --no-tags origin main
[[ "$sha" == "$(git rev-parse FETCH_HEAD)" ]] || fail 'Requested commit is not the latest origin/main; refusing a stale deployment.'
git merge-base --is-ancestor HEAD "$sha" || fail 'Server history diverges from main; refusing deployment.'
git merge --ff-only "$sha"
echo "Deployed $(git rev-parse HEAD)"
